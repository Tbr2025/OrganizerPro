<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Auction;
use App\Services\Auction\AuctionMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Send everything held in an auction's outbox, once the auction has finished.
 *
 * Queued so ending an auction returns immediately: a 200-player auction means 200 emails,
 * several of them rendering a poster first, and the organizer should not be staring at a
 * spinner while that happens. With QUEUE_CONNECTION=sync this still runs inline — set a
 * real driver and run `php artisan queue:work` to get it off the request.
 */
class FlushAuctionEmails implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Emails are slow and occasionally flaky; give the batch room to retry. */
    public int $tries = 3;

    /**
     * Comfortably inside the live worker's `--timeout=300`.
     *
     * The batch is chunked rather than run in one pass, so this does not need to cover
     * every email — it only needs to cover one chunk.
     */
    public int $timeout = 240;

    /** Kept small enough that a chunk always finishes well inside the timeout. */
    private const CHUNK = 25;

    public function __construct(public Auction $auction)
    {
    }

    public function handle(AuctionMailService $mail): void
    {
        $result = $mail->flush($this->auction, self::CHUNK);

        Log::info(sprintf(
            'Auction %d email flush: %d sent, %d failed, %d skipped, %d remaining.',
            $this->auction->id,
            $result['sent'],
            $result['failed'],
            $result['skipped'],
            $result['remaining']
        ));

        // More to do — hand the rest to a fresh job rather than risk the worker's
        // timeout. Each row is marked sent as it goes, so nothing is ever sent twice.
        if ($result['remaining'] > 0) {
            self::dispatch($this->auction)->delay(now()->addSeconds(5));
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error("Auction {$this->auction->id} email flush failed: " . $e->getMessage());
    }
}
