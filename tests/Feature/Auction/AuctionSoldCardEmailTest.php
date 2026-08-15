<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\AuctionPendingEmail;
use App\Models\TournamentTemplate;
use App\Services\Auction\AuctionPosterMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * One email per sale, carrying the card the organizer chose.
 *
 * A panel sale used to raise two emails and a drawn or allotted player only one — the same event
 * producing different mail depending on which button reached it. And the card attached was
 * whichever auction poster happened to be the tournament's default, with no way to say otherwise.
 */
class AuctionSoldCardEmailTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAuctionScenario;

    private function template($tournament, string $name, bool $default = false): TournamentTemplate
    {
        return TournamentTemplate::create([
            'tournament_id' => $tournament->id,
            'type' => TournamentTemplate::TYPE_AUCTION_POSTER,
            'name' => $name,
            'is_default' => $default,
            'is_active' => true,
            'layout_json' => [],
            'canvas_width' => 1200,
            'canvas_height' => 1600,
        ]);
    }

    #[Test]
    public function the_auctions_chosen_card_wins_over_the_tournament_default(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);

        $default = $this->template($tournament, 'Landscape', true);
        $chosen = $this->template($tournament, 'Portrait for social');

        $mailer = app(AuctionPosterMailer::class);

        // Unset: the tournament's default, exactly as before this setting existed.
        $this->assertSame($default->id, $mailer->templateFor($auction)?->id);

        $auction->update(['sold_poster_template_id' => $chosen->id]);
        $this->assertSame($chosen->id, $mailer->templateFor($auction->fresh())?->id);

        /*
         * A stale id falls back rather than failing.
         *
         * A template can be deleted long after an auction was configured, and a setting that has
         * gone stale must not be the reason a sold player is never emailed.
         */
        $chosen->delete();
        $this->assertSame($default->id, $mailer->templateFor($auction->fresh())?->id);
    }

    #[Test]
    public function a_sale_raises_exactly_one_email_whichever_route_made_it(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 100000,
            'status' => 'running',
        ]);
        $operator = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'Strikers', $tournament);

        $player = $this->makePlayer($org, ['user_id' => $this->makePlainUser($org)->id]);
        $ap = $this->makeAuctionPlayer($auction, ['player' => $player, 'status' => 'on_auction']);
        $this->makeBid($ap, $team, 500, $operator);

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.player.sell', $auction), ['auction_player_id' => $ap->id])
            ->assertOk();

        $types = AuctionPendingEmail::where('auction_id', $auction->id)->pluck('type')->all();

        $this->assertSame([AuctionPendingEmail::TYPE_WELCOME_CARD], $types, 'one email, not two');
    }
}
