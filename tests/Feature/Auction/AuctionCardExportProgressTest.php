<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Jobs\RenderAuctionCards;
use App\Models\AuctionCardExport;
use App\Services\Auction\AuctionCardRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Exporting cards used to be one long silent request.
 *
 * Each card is a headless-browser screenshot — seconds, not milliseconds — so a pool's worth
 * was minutes of a blank tab with no way to tell a slow export from a dead one, and a whole
 * auction's worth never arrived at all: the gateway cut the connection first. Progress cannot
 * be reported down the same connection that is doing the work, so the work moved off it.
 */
class AuctionCardExportProgressTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    /** Chrome is not available in the suite, and this test is about the bookkeeping. */
    private function fakeRenderer(callable $render): void
    {
        $this->app->bind(AuctionCardRenderer::class, function () use ($render) {
            return new class($render) extends AuctionCardRenderer
            {
                public function __construct(private $render)
                {
                }

                public function render($auction, $auctionPlayer, $withResult): string
                {
                    return ($this->render)($auctionPlayer);
                }
            };
        });
    }

    /** A real PNG-ish file on disk, since ZipArchive reads what it is given. */
    private function stubPng(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'stub-card-') . '.png';
        file_put_contents($path, 'not really a png, but a real file');

        return $path;
    }

    #[Test]
    public function starting_an_export_returns_immediately_with_a_token_to_watch(): void
    {
        Queue::fake();

        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $a = $this->makeAuctionPlayer($auction);
        $b = $this->makeAuctionPlayer($auction);

        $payload = $this->actingAs($this->makeAuctionOperator($org))
            ->postJson(route('admin.auctions.cards.export', $auction), [
                'players' => [$a->player_id, $b->player_id],
            ])
            ->assertOk()
            ->json();

        $this->assertSame(2, $payload['total']);
        $this->assertSame(0, $payload['completed']);
        $this->assertFalse($payload['finished']);
        $this->assertNotEmpty($payload['token']);
        // Nothing to download until there is something in the zip.
        $this->assertNull($payload['download_url']);

        Queue::assertPushed(RenderAuctionCards::class);
    }

    #[Test]
    public function the_export_counts_up_as_cards_render_and_ends_with_a_download(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $this->makeAuctionPlayer($auction);
        $this->makeAuctionPlayer($auction);

        $this->fakeRenderer(fn () => $this->stubPng());

        $operator = $this->makeAuctionOperator($org);

        // QUEUE_CONNECTION is sync in the suite, so the render happens inside this request —
        // which is exactly the degraded path a box without a worker takes.
        $token = $this->actingAs($operator)
            ->postJson(route('admin.auctions.cards.export', $auction))
            ->assertOk()
            ->json('token');

        $progress = $this->actingAs($operator)
            ->getJson(route('admin.auctions.cards.export.progress', [$auction, $token]))
            ->assertOk()
            ->json();

        $this->assertSame('done', $progress['status']);
        $this->assertSame(2, $progress['completed']);
        $this->assertSame(0, $progress['failed']);
        $this->assertSame(100, $progress['percent']);
        $this->assertTrue($progress['finished']);
        $this->assertNotNull($progress['download_url']);

        // The zip says what is in it: an operator running a sold export and an unsold one back
        // to back should not end up with two files of the same name in one folder.
        $this->actingAs($operator)
            ->get($progress['download_url'])
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=auction-' . $auction->id . '-all-cards.zip');
    }

    #[Test]
    public function one_unrenderable_player_does_not_lose_the_others(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $good = $this->makeAuctionPlayer($auction);
        $bad = $this->makeAuctionPlayer($auction);

        $this->fakeRenderer(function ($auctionPlayer) use ($bad) {
            if ($auctionPlayer->id === $bad->id) {
                throw new \RuntimeException('The card page could not be loaded in time.');
            }

            return $this->stubPng();
        });

        $operator = $this->makeAuctionOperator($org);

        $token = $this->actingAs($operator)
            ->postJson(route('admin.auctions.cards.export', $auction))
            ->assertOk()
            ->json('token');

        $progress = $this->actingAs($operator)
            ->getJson(route('admin.auctions.cards.export.progress', [$auction, $token]))
            ->assertOk()
            ->json();

        $this->assertSame('done', $progress['status'], 'One failure must not fail the batch.');
        $this->assertSame(1, $progress['completed']);
        $this->assertSame(1, $progress['failed']);
        $this->assertNotNull($progress['download_url']);
        // The reason travels with the result rather than only into a log.
        $this->assertStringContainsString('could not be loaded', $progress['message']);
    }

    #[Test]
    public function an_export_where_nothing_rendered_fails_instead_of_handing_over_an_empty_zip(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $this->makeAuctionPlayer($auction);

        $this->fakeRenderer(function () {
            throw new \RuntimeException('Chrome could not reach the page.');
        });

        $operator = $this->makeAuctionOperator($org);

        $token = $this->actingAs($operator)
            ->postJson(route('admin.auctions.cards.export', $auction))
            ->assertOk()
            ->json('token');

        $progress = $this->actingAs($operator)
            ->getJson(route('admin.auctions.cards.export.progress', [$auction, $token]))
            ->assertOk()
            ->json();

        $this->assertSame('failed', $progress['status']);
        $this->assertNull($progress['download_url']);
        $this->assertStringContainsString('Chrome could not reach the page', $progress['message']);

        // And the download refuses rather than sending a zip with nothing in it.
        $this->actingAs($operator)
            ->get(route('admin.auctions.cards.export.download', [$auction, $token]))
            ->assertStatus(409);
    }

    #[Test]
    public function an_export_belonging_to_another_auction_is_not_reachable_through_this_one(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $mine = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $theirs = $this->makeAuction($org, ['tournament_id' => $tournament->id]);

        $export = AuctionCardExport::create([
            'auction_id' => $theirs->id,
            'token' => (string) \Illuminate\Support\Str::uuid(),
            'with_result' => false,
            'auction_player_ids' => [],
            'total' => 1,
            'status' => AuctionCardExport::STATUS_DONE,
        ]);

        // The token alone would find the row; scoping to the auction is what makes the
        // permission check a check on the auction the export actually belongs to.
        $this->actingAs($this->makeAuctionOperator($org))
            ->getJson(route('admin.auctions.cards.export.progress', [$mine, $export->token]))
            ->assertNotFound();
    }

    #[Test]
    public function an_export_can_render_an_auction_poster_instead_of_the_wall_card(): void
    {
        Queue::fake();

        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $this->makeAuctionPlayer($auction);

        $poster = \App\Models\TournamentTemplate::create([
            'tournament_id' => $tournament->id,
            'type' => \App\Models\TournamentTemplate::TYPE_AUCTION_POSTER,
            'name' => 'Lot Announcement',
            'canvas_width' => 1920,
            'canvas_height' => 1080,
        ]);

        $token = $this->actingAs($this->makeAuctionOperator($org))
            ->postJson(route('admin.auctions.cards.export', $auction), ['template_id' => $poster->id])
            ->assertOk()
            ->json('token');

        $this->assertSame(
            $poster->id,
            AuctionCardExport::where('token', $token)->value('tournament_template_id')
        );
    }

    #[Test]
    public function a_template_that_is_not_an_auction_poster_is_refused_rather_than_rendered(): void
    {
        Queue::fake();

        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $this->makeAuctionPlayer($auction);

        // A match poster knows nothing about a player. Rendering two hundred of them is a
        // slow way to produce two hundred pictures of the wrong thing.
        $wrongType = \App\Models\TournamentTemplate::create([
            'tournament_id' => $tournament->id,
            'type' => \App\Models\TournamentTemplate::TYPE_MATCH_POSTER,
            'name' => 'Fixture Card',
            'canvas_width' => 1080,
            'canvas_height' => 1080,
        ]);

        $this->actingAs($this->makeAuctionOperator($org))
            ->postJson(route('admin.auctions.cards.export', $auction), ['template_id' => $wrongType->id])
            ->assertStatus(422);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function an_export_can_be_narrowed_to_the_sold_or_the_unsold(): void
    {
        Queue::fake();

        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $team = $this->makeTeam($org, 'Buyers', $tournament);

        $sold = $this->makeAuctionPlayer($auction, ['status' => 'sold', 'sold_to_team_id' => $team->id, 'final_price' => 500]);
        $unsold = $this->makeAuctionPlayer($auction, ['status' => 'unsold']);
        // Passed over and never called back — the auction finished without selling them.
        $skipped = $this->makeAuctionPlayer($auction, ['status' => 'skipped']);
        $waiting = $this->makeAuctionPlayer($auction, ['status' => 'waiting']);

        $operator = $this->makeAuctionOperator($org);

        $soldIds = $this->exportIds($operator, $auction, ['status' => 'sold']);
        $this->assertSame([$sold->id], $soldIds);

        $unsoldIds = $this->exportIds($operator, $auction, ['status' => 'unsold']);
        sort($unsoldIds);
        $this->assertSame([$unsold->id, $skipped->id], $unsoldIds, 'A skipped player is an unsold one.');

        // No status given is still everybody, including the player not yet called.
        $allIds = $this->exportIds($operator, $auction, []);
        $this->assertCount(4, $allIds);
        $this->assertContains($waiting->id, $allIds);
    }

    #[Test]
    public function a_status_filter_that_matches_nobody_says_which_nothing_it_is(): void
    {
        Queue::fake();

        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $this->makeAuctionPlayer($auction, ['status' => 'waiting']);

        // "This auction has no players" on an auction that plainly has one reads as a broken
        // export rather than as an empty filter.
        $this->actingAs($this->makeAuctionOperator($org))
            ->postJson(route('admin.auctions.cards.export', $auction), ['status' => 'sold'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'No players have been sold yet, so there are no sold posters to render.');

        Queue::assertNothingPushed();
    }

    #[Test]
    public function a_ticked_subset_and_a_status_narrow_each_other(): void
    {
        Queue::fake();

        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $team = $this->makeTeam($org, 'Buyers', $tournament);

        $soldAndTicked = $this->makeAuctionPlayer($auction, ['status' => 'sold', 'sold_to_team_id' => $team->id]);
        $soldNotTicked = $this->makeAuctionPlayer($auction, ['status' => 'sold', 'sold_to_team_id' => $team->id]);
        $tickedButUnsold = $this->makeAuctionPlayer($auction, ['status' => 'unsold']);

        $ids = $this->exportIds($this->makeAuctionOperator($org), $auction, [
            'players' => [$soldAndTicked->player_id, $tickedButUnsold->player_id],
            'status' => 'sold',
        ]);

        $this->assertSame([$soldAndTicked->id], $ids);
        $this->assertNotContains($soldNotTicked->id, $ids);
    }

    #[Test]
    public function a_file_is_named_for_the_players_own_outcome_not_the_requests(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $team = $this->makeTeam($org, 'Buyers', $tournament);

        $renderer = app(AuctionCardRenderer::class);

        $sold = $this->makeAuctionPlayer($auction, ['status' => 'sold', 'sold_to_team_id' => $team->id, 'lot_number' => 7]);
        $unsold = $this->makeAuctionPlayer($auction, ['status' => 'unsold', 'lot_number' => 8]);
        $waiting = $this->makeAuctionPlayer($auction, ['status' => 'waiting', 'lot_number' => 9]);

        /*
         * withResult is TRUE for all three — it is a property of the request ("draw the result
         * overlay"), and taking the suffix from it named every file in a mixed export -sold.
         * In a zip of two hundred the filename is the only thing telling them apart.
         */
        /*
         * player-team-pool-id.png. A zip of three hundred is sorted by name in a file manager, so
         * the parts somebody searches for have to be IN the name — the old `042-lungi-ngidi-sold`
         * led with the lot number, which nobody looks things up by, and named no team at all.
         */
        $this->assertStringContainsString('buyers', $renderer->filename($sold->fresh(), true));
        $this->assertStringEndsWith('-' . $sold->player_id . '.png', $renderer->filename($sold->fresh(), true));

        // No team to name, so the outcome takes that slot.
        $this->assertStringContainsString('-unsold-', $renderer->filename($unsold->fresh(), true));

        // Not yet called: neither sold nor unsold.
        $name = $renderer->filename($waiting->fresh(), true);
        $this->assertStringContainsString('-unassigned-', $name);
        $this->assertStringEndsWith('-' . $waiting->player_id . '.png', $name);
    }

    /** @return list<int> the auction_player ids an export resolved to */
    private function exportIds($operator, $auction, array $payload): array
    {
        $token = $this->actingAs($operator)
            ->postJson(route('admin.auctions.cards.export', $auction), $payload)
            ->assertOk()
            ->json('token');

        return AuctionCardExport::where('token', $token)->value('auction_player_ids');
    }

    #[Test]
    public function a_pool_export_finds_its_unsold_players_even_though_they_have_been_moved_out(): void
    {
        Queue::fake();

        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $team = $this->makeTeam($org, 'Buyers', $tournament);

        $poolOne = $this->makePool($auction, ['name' => 'Pool 1']);
        $poolTwo = $this->makePool($auction, ['name' => 'Pool 2']);

        $soldFromOne = $this->makeAuctionPlayer($auction, [
            'auction_pool_id' => $poolOne->id, 'status' => 'sold', 'sold_to_team_id' => $team->id,
        ]);
        $unsoldFromOne = $this->makeAuctionPlayer($auction, ['auction_pool_id' => $poolOne->id, 'status' => 'unsold']);
        $soldFromTwo = $this->makeAuctionPlayer($auction, [
            'auction_pool_id' => $poolTwo->id, 'status' => 'sold', 'sold_to_team_id' => $team->id,
        ]);

        // As the auction does it: unsold players leave their pool for the shared pile.
        app(\App\Services\Auction\AuctionPoolService::class)->moveToUnsoldPool($unsoldFromOne);

        $operator = $this->makeAuctionOperator($org);

        $this->assertSame(
            [$soldFromOne->id],
            $this->exportIds($operator, $auction, ['pool_id' => $poolOne->id, 'status' => 'sold'])
        );

        /*
         * The one that would have been missed. An unsold player no longer sits in the pool
         * they came from, so matching on auction_pool_id alone returns every sold player from
         * Pool 1 and none of its unsold ones — which is exactly the run this exists for.
         */
        $this->assertSame(
            [$unsoldFromOne->id],
            $this->exportIds($operator, $auction, ['pool_id' => $poolOne->id, 'status' => 'unsold'])
        );

        $bothFromOne = $this->exportIds($operator, $auction, ['pool_id' => $poolOne->id]);
        sort($bothFromOne);
        $this->assertSame([$soldFromOne->id, $unsoldFromOne->id], $bothFromOne);
        $this->assertNotContains($soldFromTwo->id, $bothFromOne);
    }

    #[Test]
    public function a_pool_from_another_auction_is_refused(): void
    {
        Queue::fake();

        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $mine = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $theirs = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $this->makeAuctionPlayer($mine);

        $theirPool = $this->makePool($theirs, ['name' => 'Not Mine']);

        $this->actingAs($this->makeAuctionOperator($org))
            ->postJson(route('admin.auctions.cards.export', $mine), ['pool_id' => $theirPool->id])
            ->assertStatus(422)
            ->assertJsonPath('message', 'That pool is not part of this auction.');

        Queue::assertNothingPushed();
    }

    #[Test]
    public function an_empty_pool_run_names_the_pool_rather_than_the_auction(): void
    {
        Queue::fake();

        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $pool = $this->makePool($auction, ['name' => 'Marquee']);
        $this->makeAuctionPlayer($auction, ['auction_pool_id' => $pool->id, 'status' => 'waiting']);

        // "No players have been sold yet" on a five-pool auction does not tell an operator
        // which of their five runs came back empty.
        $this->actingAs($this->makeAuctionOperator($org))
            ->postJson(route('admin.auctions.cards.export', $auction), ['pool_id' => $pool->id, 'status' => 'sold'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'No players from Marquee have been sold yet.');
    }

    #[Test]
    public function selecting_players_that_are_not_in_this_auction_says_so_rather_than_exporting_everything(): void
    {
        Queue::fake();

        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $this->makeAuctionPlayer($auction);

        $this->actingAs($this->makeAuctionOperator($org))
            ->postJson(route('admin.auctions.cards.export', $auction), ['players' => [999999]])
            ->assertStatus(422)
            ->assertJsonPath('message', 'None of the selected players are in this auction.');

        Queue::assertNothingPushed();
    }
}
