<?php

namespace Tests\Feature\Auction;

use App\Models\TournamentRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Which players the pool builder offers.
 *
 * Only players with an APPROVED registration for the auction's tournament, and never
 * retained players — those are pre-kept by their team, not drawn for bidding, and are
 * managed on the Pools screen instead. Listing them in the wizard invited them into the
 * biddable pool by mistake.
 */
class AuctionAvailablePlayersTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    /** The `availablePlayers` payload the edit wizard serialises into its Alpine state. */
    private function availableNames(\App\Models\Auction $auction, \App\Models\User $actor): array
    {
        $response = $this->actingAs($actor)->get(route('admin.auctions.edit', $auction))->assertOk();

        return collect($response->viewData('availablePlayers'))->pluck('name')->all();
    }

    private function approve(\App\Models\Player $player, \App\Models\Tournament $tournament, string $status = 'approved'): void
    {
        TournamentRegistration::create([
            'tournament_id' => $tournament->id,
            'player_id' => $player->id,
            'organization_id' => $tournament->organization_id,
            'registration_type' => 'player',
            'status' => $status,
        ]);
    }

    #[Test]
    public function only_approved_players_for_this_tournament_are_offered(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $otherTournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $operator = $this->makeAuctionOperator($org);

        $approved = $this->makePlayer($org, ['name' => 'Approved Here']);
        $this->approve($approved, $tournament);

        $pending = $this->makePlayer($org, ['name' => 'Pending Here']);
        $this->approve($pending, $tournament, 'pending');

        $elsewhere = $this->makePlayer($org, ['name' => 'Approved Elsewhere']);
        $this->approve($elsewhere, $otherTournament);

        $unregistered = $this->makePlayer($org, ['name' => 'Never Registered']);

        $names = $this->availableNames($auction, $operator);

        $this->assertContains('Approved Here', $names);
        $this->assertNotContains('Pending Here', $names);
        $this->assertNotContains('Approved Elsewhere', $names);
        $this->assertNotContains('Never Registered', $names);
    }

    #[Test]
    public function retained_players_are_never_offered(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $operator = $this->makeAuctionOperator($org);

        $biddable = $this->makePlayer($org, ['name' => 'Biddable Bob', 'player_mode' => 'normal']);
        $this->approve($biddable, $tournament);

        // Approved for this tournament, but pre-kept by their team.
        $retained = $this->makePlayer($org, ['name' => 'Retained Raj', 'player_mode' => 'retained']);
        $this->approve($retained, $tournament);

        $names = $this->availableNames($auction, $operator);

        $this->assertContains('Biddable Bob', $names);
        $this->assertNotContains('Retained Raj', $names);
    }

    #[Test]
    public function players_already_in_the_auction_are_not_offered_again(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $operator = $this->makeAuctionOperator($org);
        $pool = $this->makePool($auction);

        $pooled = $this->makePlayer($org, ['name' => 'Already Pooled']);
        $this->approve($pooled, $tournament);
        $this->makeAuctionPlayer($auction, ['player' => $pooled, 'auction_pool_id' => $pool->id]);

        $free = $this->makePlayer($org, ['name' => 'Still Free']);
        $this->approve($free, $tournament);

        $names = $this->availableNames($auction, $operator);

        $this->assertNotContains('Already Pooled', $names);
        $this->assertContains('Still Free', $names);
    }

    #[Test]
    public function another_organizations_players_are_not_offered(): void
    {
        $orgA = $this->makeOrganization('Org A');
        $orgB = $this->makeOrganization('Org B');
        $tournament = $this->makeTournament($orgA);
        $auction = $this->makeAuction($orgA, ['tournament_id' => $tournament->id]);
        $operator = $this->makeAuctionOperator($orgA);

        $mine = $this->makePlayer($orgA, ['name' => 'Mine']);
        $this->approve($mine, $tournament);

        // Registered for the same tournament but belonging to another organization.
        $theirs = $this->makePlayer($orgB, ['name' => 'Theirs']);
        TournamentRegistration::create([
            'tournament_id' => $tournament->id,
            'player_id' => $theirs->id,
            'organization_id' => $orgB->id,
            'registration_type' => 'player',
            'status' => 'approved',
        ]);

        $names = $this->availableNames($auction, $operator);

        $this->assertContains('Mine', $names);
        $this->assertNotContains('Theirs', $names);
    }

    #[Test]
    public function the_create_wizard_carries_approved_tournaments_per_player(): void
    {
        $org = $this->makeOrganization();
        $tournamentA = $this->makeTournament($org);
        $tournamentB = $this->makeTournament($org);
        $operator = $this->makeAuctionOperator($org, ['auction.view', 'auction.create', 'auction.edit']);

        $player = $this->makePlayer($org, ['name' => 'Multi Tournament']);
        $this->approve($player, $tournamentA);
        $this->approve($player, $tournamentB, 'pending');

        $retained = $this->makePlayer($org, ['name' => 'Retained Raj', 'player_mode' => 'retained']);
        $this->approve($retained, $tournamentA);

        $available = collect(
            $this->actingAs($operator)->get(route('admin.auctions.create'))->assertOk()->viewData('availablePlayers')
        );

        // Retained never listed.
        $this->assertNotContains('Retained Raj', $available->pluck('name')->all());

        // The tournament is picked inside the form, so each player carries the
        // tournaments they are approved for and the wizard filters client-side.
        $row = $available->firstWhere('name', 'Multi Tournament');
        $this->assertNotNull($row);
        $this->assertContains($tournamentA->id, $row['tournament_ids']);
        $this->assertNotContains($tournamentB->id, $row['tournament_ids'], 'A pending registration must not count.');
    }
}
