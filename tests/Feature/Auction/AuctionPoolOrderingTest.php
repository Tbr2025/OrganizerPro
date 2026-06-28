<?php

namespace Tests\Feature\Auction;

use App\Models\Auction;
use App\Models\AuctionPlayer;
use App\Models\AuctionPool;
use App\Models\Player;
use App\Services\Auction\AuctionPoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuctionPoolOrderingTest extends TestCase
{
    use RefreshDatabase;

    private function auction(): Auction
    {
        return Auction::create(['name' => 'Test Auction', 'status' => 'scheduled', 'max_budget_per_team' => 1000]);
    }

    /** Create $count players in a pool, returns ordered array of AuctionPlayer ids (by insertion). */
    private function seedPool(AuctionPool $pool, int $count): array
    {
        $ids = [];
        for ($i = 1; $i <= $count; $i++) {
            $player = Player::create(['name' => "P{$i}", 'email' => "p{$i}-{$pool->id}@x.test", 'status' => 'approved']);
            $ap = AuctionPlayer::create([
                'auction_id' => $pool->auction_id,
                'auction_pool_id' => $pool->id,
                'player_id' => $player->id,
                'base_price' => 10,
                'starting_price' => 10,
                'status' => 'waiting',
            ]);
            $ids[] = $ap->id;
        }

        return $ids;
    }

    #[Test]
    public function sequential_mode_numbers_lots_in_insertion_order(): void
    {
        $auction = $this->auction();
        $pool = AuctionPool::create(['auction_id' => $auction->id, 'name' => 'A', 'order_mode' => 'sequential', 'sequence' => 1]);
        $ids = $this->seedPool($pool, 4);

        app(AuctionPoolService::class)->generateLotNumbers($pool);

        foreach ($ids as $i => $id) {
            $this->assertSame($i + 1, AuctionPlayer::find($id)->lot_number);
        }
    }

    #[Test]
    public function odd_even_mode_draws_odd_positions_first(): void
    {
        $auction = $this->auction();
        $pool = AuctionPool::create(['auction_id' => $auction->id, 'name' => 'A', 'order_mode' => 'odd_even', 'sequence' => 1]);
        // ids in insertion order: [1st,2nd,3rd,4th,5th]
        [$a, $b, $c, $d, $e] = $this->seedPool($pool, 5);

        app(AuctionPoolService::class)->generateLotNumbers($pool);

        // odds first (1st,3rd,5th) then evens (2nd,4th)
        $this->assertSame(1, AuctionPlayer::find($a)->lot_number);
        $this->assertSame(2, AuctionPlayer::find($c)->lot_number);
        $this->assertSame(3, AuctionPlayer::find($e)->lot_number);
        $this->assertSame(4, AuctionPlayer::find($b)->lot_number);
        $this->assertSame(5, AuctionPlayer::find($d)->lot_number);
    }

    #[Test]
    public function random_mode_assigns_a_full_permutation(): void
    {
        $auction = $this->auction();
        $pool = AuctionPool::create(['auction_id' => $auction->id, 'name' => 'A', 'order_mode' => 'random', 'sequence' => 1]);
        $this->seedPool($pool, 6);

        app(AuctionPoolService::class)->generateLotNumbers($pool);

        $lots = AuctionPlayer::where('auction_pool_id', $pool->id)->pluck('lot_number')->sort()->values()->all();
        $this->assertSame([1, 2, 3, 4, 5, 6], $lots);
    }

    #[Test]
    public function next_player_respects_pool_sequence_then_lot(): void
    {
        $auction = $this->auction();
        $pool1 = AuctionPool::create(['auction_id' => $auction->id, 'name' => 'First', 'order_mode' => 'sequential', 'sequence' => 1]);
        $pool2 = AuctionPool::create(['auction_id' => $auction->id, 'name' => 'Second', 'order_mode' => 'sequential', 'sequence' => 2]);
        $p1 = $this->seedPool($pool1, 2);
        $p2 = $this->seedPool($pool2, 2);

        $service = app(AuctionPoolService::class);
        $service->generateLotNumbers($pool1);
        $service->generateLotNumbers($pool2);

        // First call → pool1 lot1
        $this->assertSame($p1[0], $service->nextPlayer($auction)->id);

        // Sell it → next is pool1 lot2
        AuctionPlayer::find($p1[0])->update(['status' => 'sold']);
        $this->assertSame($p1[1], $service->nextPlayer($auction)->id);

        // Exhaust pool1 → move to pool2 lot1
        AuctionPlayer::find($p1[1])->update(['status' => 'sold']);
        $this->assertSame($p2[0], $service->nextPlayer($auction)->id);
    }
}
