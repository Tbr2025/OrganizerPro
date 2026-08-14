<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Mail\PlayerSoldMail;
use App\Models\ActualTeam;
use App\Models\Auction;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The sold email carries the poster.
 *
 * A player being told they were sold is the message; the poster is what makes it worth keeping
 * and what actually gets posted to a group chat. It is embedded in the body AND attached, because
 * inline images are what some clients strip and attachments are what the rest hide behind a
 * paperclip.
 */
class AuctionSoldPosterEmailTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $org = \App\Models\Organization::create(['name' => 'Org']);
        $tournament = \App\Models\Tournament::create([
            'name' => 'T', 'slug' => 't-poster', 'start_date' => '2026-01-01', 'organization_id' => $org->id,
        ]);
        $auction = Auction::create([
            'name' => 'A', 'status' => 'running', 'max_budget_per_team' => 100_000_000,
            'base_price' => 1_000_000, 'organization_id' => $org->id, 'tournament_id' => $tournament->id,
            'bid_type' => 'open', 'bid_timer_seconds' => 30,
            'bid_rules' => [['from' => 0, 'to' => 200_000_000, 'increment' => 100_000]],
        ]);
        $team = ActualTeam::create([
            'name' => 'Alpha', 'organization_id' => $org->id, 'tournament_id' => $tournament->id,
        ]);
        $player = Player::create([
            'organization_id' => $org->id, 'name' => 'Sold Player',
            'email' => 'sold@x.test', 'status' => 'approved',
        ]);

        return compact('auction', 'team', 'player');
    }

    #[Test]
    public function the_poster_is_attached_and_embedded_when_one_was_rendered(): void
    {
        ['auction' => $auction, 'team' => $team, 'player' => $player] = $this->fixture();

        // Stand in for a rendered poster: this test is about what the mailable does with the
        // file, not about GD.
        $path = storage_path('app/test-sold-poster.png');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='
        ));

        $mail = new PlayerSoldMail($player, $team, $auction, 2_600_000, $path);

        $this->assertCount(1, $mail->attachments(), 'the poster must travel as an attachment');

        $html = $mail->render();
        $this->assertStringContainsString('cid:', $html, 'and be embedded in the body');

        @unlink($path);
    }

    #[Test]
    public function the_email_still_goes_out_when_there_is_no_poster(): void
    {
        ['auction' => $auction, 'team' => $team, 'player' => $player] = $this->fixture();

        // No template drawn, or a render that failed: being told you were sold is the message.
        $mail = new PlayerSoldMail($player, $team, $auction, 2_600_000, null);

        $this->assertSame([], $mail->attachments());

        $html = $mail->render();
        $this->assertStringContainsString('Alpha', $html);
        $this->assertStringNotContainsString('cid:', $html);
    }

    #[Test]
    public function a_path_that_no_longer_exists_is_not_attached(): void
    {
        ['auction' => $auction, 'team' => $team, 'player' => $player] = $this->fixture();

        // The render is written to a temp location and can be swept between queueing and sending.
        // Attaching a missing file throws inside the mailer, which would fail the whole send.
        $mail = new PlayerSoldMail($player, $team, $auction, 2_600_000, '/tmp/definitely-not-here.png');

        $this->assertSame([], $mail->attachments());
        $this->assertStringNotContainsString('cid:', $mail->render());
    }
}
