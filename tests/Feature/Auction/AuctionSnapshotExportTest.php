<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Services\Export\AuctionSnapshotExport;
use App\Services\Export\XlsxWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;
use ZipArchive;

/**
 * The rescue export.
 *
 * This exists for the moment an auction goes wrong in a hall full of people, so the bar
 * is higher than "it returns a file": it has to open, it has to be right, and it must not
 * fall over on exactly the broken data somebody is exporting in order to investigate.
 */
class AuctionSnapshotExportTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    /** Read one sheet's XML back out of the workbook. */
    private function sheetXml(string $path, int $index = 1): string
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true, 'the workbook must open as a zip');
        $xml = $zip->getFromName("xl/worksheets/sheet{$index}.xml");
        $zip->close();

        $this->assertIsString($xml, "sheet{$index} must exist");

        return $xml;
    }

    private function scenario(): array
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'status' => 'running',
            'max_budget_per_team' => 100_000_000,
        ]);

        return [$org, $tournament, $auction];
    }

    #[Test]
    public function the_workbook_opens_and_carries_every_sheet(): void
    {
        [$org, $tournament, $auction] = $this->scenario();
        $this->makeTeam($org, 'Alpha', $tournament);

        $path = tempnam(sys_get_temp_dir(), 'x');
        app(AuctionSnapshotExport::class)->build($auction)->save($path);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true);

        // The parts Excel refuses to open a file without. A missing rels entry produces
        // "we found a problem with some content", not a useful error.
        foreach ([
            '[Content_Types].xml',
            '_rels/.rels',
            'xl/workbook.xml',
            'xl/_rels/workbook.xml.rels',
            'xl/styles.xml',
            'xl/worksheets/sheet1.xml',
            'xl/worksheets/sheet2.xml',
            'xl/worksheets/sheet3.xml',
            'xl/worksheets/sheet4.xml',
        ] as $part) {
            $this->assertNotFalse($zip->locateName($part), "{$part} is missing");
        }

        $workbook = $zip->getFromName('xl/workbook.xml');
        $zip->close();

        $this->assertStringContainsString('name="Squads"', $workbook);
        $this->assertStringContainsString('name="Players"', $workbook);
        $this->assertStringContainsString('name="Teams"', $workbook);
        $this->assertStringContainsString('name="Summary"', $workbook);

        // Every part must be well-formed XML, or Excel reports a corrupt file with no
        // indication of which one.
        $this->assertNotFalse(simplexml_load_string($this->sheetXml($path, 2)));

        unlink($path);
    }

    #[Test]
    public function a_sale_is_recorded_with_its_team_and_price(): void
    {
        [$org, $tournament, $auction] = $this->scenario();
        $team = $this->makeTeam($org, 'Alpha Strikers', $tournament);
        $player = $this->makePlayer($org, ['name' => 'Virat Kohli']);

        $this->makeAuctionPlayer($auction, [
            'player_id' => $player->id,
            'status' => 'sold',
            'sold_to_team_id' => $team->id,
            'final_price' => 9_500_000,
        ]);

        $path = tempnam(sys_get_temp_dir(), 'x');
        app(AuctionSnapshotExport::class)->build($auction)->save($path);
        $xml = $this->sheetXml($path, 2);

        $this->assertStringContainsString('Virat Kohli', $xml);
        $this->assertStringContainsString('Alpha Strikers', $xml);

        // As a NUMBER, not text — the point of the file is that the organizer can sum
        // the column and check the totals rather than take them on trust.
        $this->assertStringContainsString('<v>9500000</v>', $xml);

        unlink($path);
    }

    #[Test]
    public function players_still_waiting_are_exported_too(): void
    {
        [$org, $tournament, $auction] = $this->scenario();
        $waiting = $this->makePlayer($org, ['name' => 'Still Waiting']);
        $this->makeAuctionPlayer($auction, ['player_id' => $waiting->id, 'status' => 'waiting']);

        $path = tempnam(sys_get_temp_dir(), 'x');
        app(AuctionSnapshotExport::class)->build($auction)->save($path);

        // "Who is left" is usually the first question when a run has to be finished by
        // hand, so an export of sold players only would be useless for the case this
        // tool exists to serve.
        $this->assertStringContainsString('Still Waiting', $this->sheetXml($path, 2));

        unlink($path);
    }

    #[Test]
    public function a_half_finished_player_does_not_break_the_export(): void
    {
        [$org, $tournament, $auction] = $this->scenario();
        $player = $this->makePlayer($org, ['name' => 'No Pool No Team']);

        // No pool, no lot number, no sale, no final price. This is what a broken run
        // looks like — and it is exactly what somebody would be exporting to inspect, so
        // it must not be the thing that stops the export.
        $this->makeAuctionPlayer($auction, [
            'player_id' => $player->id,
            'status' => 'waiting',
            'auction_pool_id' => null,
            'lot_number' => null,
            'sold_to_team_id' => null,
            'final_price' => null,
        ]);

        $path = tempnam(sys_get_temp_dir(), 'x');
        app(AuctionSnapshotExport::class)->build($auction)->save($path);

        $this->assertStringContainsString('No Pool No Team', $this->sheetXml($path, 2));
        $this->assertNotFalse(simplexml_load_string($this->sheetXml($path, 2)));

        unlink($path);
    }

    #[Test]
    public function team_spend_and_remaining_are_exported(): void
    {
        [$org, $tournament, $auction] = $this->scenario();
        $team = $this->makeTeam($org, 'Alpha', $tournament);
        $player = $this->makePlayer($org, ['name' => 'Bought Player']);

        $this->makeAuctionPlayer($auction, [
            'player_id' => $player->id,
            'status' => 'sold',
            'sold_to_team_id' => $team->id,
            'final_price' => 25_000_000,
        ]);

        $path = tempnam(sys_get_temp_dir(), 'x');
        app(AuctionSnapshotExport::class)->build($auction)->save($path);
        $teams = $this->sheetXml($path, 3);

        // Budget, spend and what is left — read from the same service the panel uses, so
        // a figure disputed in the hall and a figure in this file cannot disagree.
        $this->assertStringContainsString('<v>100000000</v>', $teams, 'budget');
        $this->assertStringContainsString('<v>25000000</v>', $teams, 'spend');
        $this->assertStringContainsString('<v>75000000</v>', $teams, 'remaining');

        unlink($path);
    }

    #[Test]
    public function a_name_with_xml_characters_survives(): void
    {
        [$org, $tournament, $auction] = $this->scenario();
        $player = $this->makePlayer($org, ['name' => 'Ram & "Bob" <the> Great']);
        $this->makeAuctionPlayer($auction, ['player_id' => $player->id, 'status' => 'waiting']);

        $path = tempnam(sys_get_temp_dir(), 'x');
        app(AuctionSnapshotExport::class)->build($auction)->save($path);

        /*
         * Unescaped, an ampersand in a player's name makes the whole workbook unopenable
         * — and it would be discovered at the worst possible moment.
         *
         * Asserted by reading the value back rather than by matching the encoded bytes:
         * `"` may be written literally or as &quot; and both are correct XML, so pinning
         * the encoding would be testing htmlspecialchars' flags instead of the thing that
         * matters, which is that the name arrives intact.
         */
        $sheet = simplexml_load_string($this->sheetXml($path, 2));
        $this->assertNotFalse($sheet, 'a name with XML characters must not corrupt the sheet');

        $values = [];
        foreach ($sheet->sheetData->row as $row) {
            foreach ($row->c as $cell) {
                $values[] = (string) $cell->is->t;
            }
        }

        $this->assertContains('Ram & "Bob" <the> Great', $values);

        unlink($path);
    }

    #[Test]
    public function the_endpoint_downloads_a_spreadsheet(): void
    {
        [$org, $tournament, $auction] = $this->scenario();
        $this->makeTeam($org, 'Alpha', $tournament);

        $response = $this->actingAs($this->makeAuctionOperator($org))
            ->get(route('admin.auction.organizer.api.export', $auction));

        $response->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->assertStringContainsString('.xlsx', $response->headers->get('content-disposition'));
    }

    #[Test]
    public function exporting_changes_nothing(): void
    {
        [$org, $tournament, $auction] = $this->scenario();
        $team = $this->makeTeam($org, 'Alpha', $tournament);
        $player = $this->makeAuctionPlayer($auction, ['status' => 'on_auction', 'current_price' => 500]);

        $before = [$auction->fresh()->toArray(), $player->fresh()->toArray()];

        $this->actingAs($this->makeAuctionOperator($org))
            ->get(route('admin.auction.organizer.api.export', $auction))
            ->assertOk();

        // The whole value of a rescue tool is that it is always safe to press. Pressing
        // it while the room is already in trouble must not be able to make things worse.
        $this->assertSame($before, [$auction->fresh()->toArray(), $player->fresh()->toArray()]);
    }

    #[Test]
    public function the_squad_board_lays_teams_out_across_the_page(): void
    {
        [$org, $tournament, $auction] = $this->scenario();
        $alpha = $this->makeTeam($org, 'Chennai Warriors', $tournament);
        $bravo = $this->makeTeam($org, 'Thrissur Thunders', $tournament);

        $bought = $this->makePlayer($org, ['name' => 'Aqeel Ahmad']);
        $this->makeAuctionPlayer($auction, [
            'player_id' => $bought->id,
            'status' => 'sold',
            'sold_to_team_id' => $alpha->id,
            'final_price' => 5_000_000,
        ]);

        $path = tempnam(sys_get_temp_dir(), 'x');
        app(AuctionSnapshotExport::class)->build($auction)->save($path);
        $board = $this->sheetXml($path, 1);

        /*
         * The shape the organizers already keep their finance workbook in: teams across in
         * column pairs, squads down, totals underneath. The other sheets are one row per
         * record, which is right for filtering and summing; this one is right for reading
         * at the table.
         */
        $this->assertStringContainsString('Chennai Warriors', $board);
        $this->assertStringContainsString('Thrissur Thunders', $board);
        $this->assertStringContainsString('PLAYERS', $board);
        $this->assertStringContainsString('POINTS', $board);
        $this->assertStringContainsString('Aqeel Ahmad', $board);
        $this->assertStringContainsString('SPENT', $board);
        $this->assertStringContainsString('BALANCE', $board);

        // Each team name spans its own pair of columns, as in the sheet being matched.
        $this->assertStringContainsString('<mergeCell ref="E1:F1"/>', $board);
        $this->assertStringContainsString('<mergeCell ref="G1:H1"/>', $board);

        // And it stays valid XML with merges present — mergeCells must follow sheetData or
        // Excel rejects the whole file.
        $this->assertNotFalse(simplexml_load_string($board));

        unlink($path);
    }

    #[Test]
    public function a_retained_player_appears_in_the_squad_board(): void
    {
        [$org, $tournament, $auction] = $this->scenario();
        $team = $this->makeTeam($org, 'Alpha', $tournament);
        $kept = $this->makePlayer($org, ['name' => 'Kept Player']);

        $this->makeAuctionPlayer($auction, [
            'player_id' => $kept->id,
            'status' => 'waiting',
            'is_retained' => true,
            'team_id' => $team->id,
            'retained_price' => 3_000_000,
        ]);

        $path = tempnam(sys_get_temp_dir(), 'x');
        app(AuctionSnapshotExport::class)->build($auction)->save($path);

        // A retained player is part of a squad and part of what a team has spent, so a
        // board that left them out would not reconcile against its own BALANCE row.
        $this->assertStringContainsString('Kept Player (retained)', $this->sheetXml($path, 1));

        unlink($path);
    }

    #[Test]
    public function a_column_index_past_z_is_addressed_correctly(): void
    {
        // The Teams sheet has thirteen columns today and will grow. Getting this wrong
        // produces a file that opens but silently drops everything past column Z.
        $path = tempnam(sys_get_temp_dir(), 'x');

        (new XlsxWriter())
            ->addSheet('Wide', [range(1, 30), range(1, 30)])
            ->save($path);

        $xml = $this->sheetXml($path, 1);

        $this->assertStringContainsString('r="Z1"', $xml);
        $this->assertStringContainsString('r="AA1"', $xml);
        $this->assertStringContainsString('r="AD1"', $xml);

        unlink($path);
    }
}
