<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\Auction;
use App\Models\AuctionPlayer;
use App\Models\AuctionPool;
use App\Services\Auction\AuctionPoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Retention prices had no default anywhere, and a blank field was written straight
 * through as 0 — inside an updateOrCreate, so re-assigning an already-priced retained
 * player wiped their price and their team got them for nothing.
 */
class AuctionRetentionDefaultsTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function pools(): AuctionPoolService
    {
        return app(AuctionPoolService::class);
    }

    #[Test]
    public function the_default_retained_value_is_five_million_when_unset(): void
    {
        $auction = $this->makeAuction($this->makeOrganization());

        $this->assertNull($auction->default_retained_value);
        $this->assertSame(5_000_000.0, $auction->defaultRetainedValue());
    }

    #[Test]
    public function an_explicit_zero_default_retained_value_means_free(): void
    {
        $auction = $this->makeAuction($this->makeOrganization(), ['default_retained_value' => 0]);

        // 0 is a real setting — retentions cost nothing here — and must not be read as
        // "unset". This is why the column is nullable rather than defaulted.
        $this->assertSame(0.0, $auction->defaultRetainedValue());
    }

    #[Test]
    public function the_expected_retained_count_defaults_to_four_and_honours_zero(): void
    {
        $org = $this->makeOrganization();

        $this->assertSame(4, $this->makeAuction($org)->expectedRetainedPerTeam());
        $this->assertSame(0, $this->makeAuction($org, ['expected_retained_per_team' => 0])->expectedRetainedPerTeam());
        $this->assertSame(6, $this->makeAuction($org, ['expected_retained_per_team' => 6])->expectedRetainedPerTeam());
    }

    #[Test]
    public function max_squad_size_is_null_rather_than_falling_back_to_the_minimum(): void
    {
        $auction = $this->makeAuction($this->makeOrganization(), ['min_squad_size' => 11]);

        // Null so the display can say "MAX: —" instead of inventing a ceiling.
        $this->assertNull($auction->maxSquadSize());
        $this->assertSame(11, $auction->minSquadSize());
    }

    #[Test]
    public function the_retained_price_resolver_walks_its_fallbacks_in_order(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, ['default_retained_value' => 5_000_000]);
        $player = $this->makePlayer($org, ['retained_value' => 3_000_000]);
        $pools = $this->pools();

        $existing = new AuctionPlayer(['retained_price' => 8_000_000]);

        // 1. An explicit submission wins over everything.
        $this->assertSame(1_000_000.0, $pools->resolveRetainedPrice($auction, $player, 1_000_000, $existing));

        // ...including a deliberate zero.
        $this->assertSame(0.0, $pools->resolveRetainedPrice($auction, $player, 0, $existing));

        // 2. Blank falls back to a price already on record before any default.
        $this->assertSame(8_000_000.0, $pools->resolveRetainedPrice($auction, $player, null, $existing));

        // 3. Then the player's own retention value.
        $this->assertSame(3_000_000.0, $pools->resolveRetainedPrice($auction, $player, null, null));

        // 4. Then the auction default.
        $bare = $this->makePlayer($org);
        $this->assertSame(5_000_000.0, $pools->resolveRetainedPrice($auction, $bare, null, null));
    }

    /** Assign players to a pool through the real endpoint. */
    private function assign(Auction $auction, AuctionPool $pool, array $playerIds, array $retainedPrices = [])
    {
        $payload = ['pool_id' => $pool->id, 'player_ids' => $playerIds];

        if ($retainedPrices !== []) {
            $payload['retained_prices'] = $retainedPrices;
        }

        return $this->post(route('admin.auctions.pools.assign', $auction), $payload);
    }

    /** A retained player with an approved registration, ready to be pooled. */
    private function retainedPlayer(Auction $auction, $team, array $attrs = [])
    {
        $player = $this->makePlayer($auction->organization, array_merge([
            'player_mode' => 'retained',
            'actual_team_id' => $team->id,
        ], $attrs));

        \App\Models\TournamentRegistration::create([
            'tournament_id' => $auction->tournament_id,
            'player_id' => $player->id,
            'organization_id' => $auction->organization_id,
            'status' => 'approved',
            'registration_type' => 'player',
        ]);

        return $player;
    }

    #[Test]
    public function a_blank_retained_price_no_longer_stores_zero(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 100_000_000,
            'default_retained_value' => 5_000_000,
        ]);
        $team = $this->makeTeam($org, 'Strikers', $tournament);
        $pool = $this->makePool($auction);
        $player = $this->retainedPlayer($auction, $team);

        $this->actingAs($this->makeAuctionOperator($org));
        $this->assign($auction, $pool, [$player->id])->assertRedirect();

        $row = AuctionPlayer::where('auction_id', $auction->id)->where('player_id', $player->id)->first();

        // Was 0 — the team was charged nothing for a retained player.
        $this->assertSame(5_000_000.0, (float) $row->retained_price);
        $this->assertSame(5_000_000.0, $this->pools()->retainedSpent($auction, $team->id));
    }

    #[Test]
    public function re_assigning_with_a_blank_price_keeps_the_price_already_set(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 100_000_000,
        ]);
        $team = $this->makeTeam($org, 'Strikers', $tournament);
        $pool = $this->makePool($auction);
        $player = $this->retainedPlayer($auction, $team);

        $this->actingAs($this->makeAuctionOperator($org));

        $this->assign($auction, $pool, [$player->id], [$player->id => 8_000_000])->assertRedirect();
        // Re-assign with the field left empty — this is the destructive path.
        $this->assign($auction, $pool, [$player->id])->assertRedirect();

        $row = AuctionPlayer::where('auction_id', $auction->id)->where('player_id', $player->id)->first();

        $this->assertSame(8_000_000.0, (float) $row->retained_price, 'a blank field must not overwrite a set price');
    }

    #[Test]
    public function a_players_own_retained_value_is_used_before_the_auction_default(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 100_000_000,
            'default_retained_value' => 5_000_000,
        ]);
        $team = $this->makeTeam($org, 'Strikers', $tournament);
        $pool = $this->makePool($auction);
        $player = $this->retainedPlayer($auction, $team, ['retained_value' => 3_000_000]);

        $this->actingAs($this->makeAuctionOperator($org));
        $this->assign($auction, $pool, [$player->id])->assertRedirect();

        $row = AuctionPlayer::where('auction_id', $auction->id)->where('player_id', $player->id)->first();

        $this->assertSame(3_000_000.0, (float) $row->retained_price);
    }

    #[Test]
    public function an_explicit_zero_retained_price_is_still_stored_as_zero(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 100_000_000,
            'default_retained_value' => 5_000_000,
        ]);
        $team = $this->makeTeam($org, 'Strikers', $tournament);
        $pool = $this->makePool($auction);
        $player = $this->retainedPlayer($auction, $team);

        $this->actingAs($this->makeAuctionOperator($org));
        $this->assign($auction, $pool, [$player->id], [$player->id => 0])->assertRedirect();

        $row = AuctionPlayer::where('auction_id', $auction->id)->where('player_id', $player->id)->first();

        // A free retention is a legitimate choice and must survive the fallbacks.
        $this->assertSame(0.0, (float) $row->retained_price);
    }
}
