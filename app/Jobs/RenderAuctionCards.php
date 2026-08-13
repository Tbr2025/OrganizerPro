<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AuctionCardExport;
use App\Models\AuctionPlayer;
use App\Services\Auction\AuctionCardRenderer;
use App\Services\Poster\AuctionPosterData;
use App\Services\Poster\TemplateRenderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Render one chunk of an export's cards, then hand the rest to another run of itself.
 *
 * Each card is a headless-browser screenshot, so a 200-player auction is several minutes of
 * work — longer than the live worker's `--timeout=300` allows any single job to live. Rather
 * than raise that ceiling (which would let ANY stuck job hold a worker for as long), the batch
 * re-dispatches itself: every run does a bounded amount of work, updates the count the operator
 * is watching, and queues the remainder.
 *
 * The zip is appended to across runs. ZipArchive reads the files it was given at close(), so
 * the PNGs a run produced are deleted only after that run's close() — never before.
 *
 * With QUEUE_CONNECTION=sync this all still happens, just inline and in one unbroken stretch
 * during the request that started it: the progress bar jumps straight to done rather than
 * climbing. That is a degraded experience, not a broken one, and it is what a development box
 * without `queue:work` gets.
 */
class RenderAuctionCards implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * One attempt.
     *
     * A retry would re-render cards already in the zip and double them up, and the failures
     * worth retrying (a browser that cannot reach the page) fail identically every time.
     */
    public int $tries = 1;

    /** Comfortably inside the live worker's --timeout=300, with one chunk to cover. */
    public int $timeout = 240;

    /**
     * Cards per run.
     *
     * Sized so the slowest realistic card (~5s, cold browser and a large background) still
     * leaves the run well inside $timeout. Smaller would also mean a progress bar that moves
     * more often — but every re-dispatch costs a queue round trip, and 20 already updates the
     * bar four or five times a minute.
     */
    private const CHUNK = 20;

    public function __construct(public int $exportId)
    {
    }

    public function handle(AuctionCardRenderer $renderer): void
    {
        $export = AuctionCardExport::find($this->exportId);

        // Cancelled, or swept — nothing to do, and re-queueing would resurrect it.
        if (! $export || $export->isFinished()) {
            return;
        }

        // Under `sync` this runs in the request, where PHP's own ceiling still applies.
        set_time_limit(0);

        $export->update(['status' => AuctionCardExport::STATUS_RUNNING]);

        $ids = $export->auction_player_ids ?? [];
        $chunk = array_slice($ids, $export->settled(), self::CHUNK);

        if ($chunk === []) {
            $this->finish($export);

            return;
        }

        $players = AuctionPlayer::with('player')
            ->whereIn('id', $chunk)
            ->get()
            ->keyBy('id');

        $zipPath = $this->openable($export);
        $zip = new \ZipArchive();

        if ($zip->open($zipPath, \ZipArchive::CREATE) !== true) {
            $export->update([
                'status' => AuctionCardExport::STATUS_FAILED,
                'message' => 'Could not open the zip file to write into.',
            ]);

            return;
        }

        $written = [];
        $completed = $export->completed;
        $failed = $export->failed;
        $message = $export->message;

        foreach ($chunk as $id) {
            $auctionPlayer = $players->get($id);

            /*
             * A player removed from the pool since the export was created.
             *
             * Counted as failed rather than skipped, because the total was fixed when the
             * export was created — dropping it silently would leave a bar that never reaches
             * the end and an operator waiting on a card that is never coming.
             */
            if (! $auctionPlayer) {
                $failed++;
                $message ??= 'One or more players were removed from the auction while the export was running.';
                continue;
            }

            try {
                $png = $export->tournament_template_id
                    ? $this->renderPoster($export, $auctionPlayer)
                    : $renderer->render($export->auction, $auctionPlayer, $export->with_result);

                $zip->addFile($png, $renderer->filename($auctionPlayer, $export->with_result));
                $written[] = $png;
                $completed++;
            } catch (\Throwable $e) {
                $failed++;
                // The first failure is the one worth showing: they are nearly always the same
                // failure, and the operator needs the reason, not a list of two hundred.
                $message ??= $e->getMessage();

                Log::warning('Auction card render failed', [
                    'auction_card_export_id' => $export->id,
                    'auction_player_id' => $id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $zip->close();

        foreach ($written as $png) {
            @unlink($png);
        }

        $export->update([
            'completed' => $completed,
            'failed' => $failed,
            'message' => $message,
        ]);

        // Refreshed, so `settled()` reflects what this run just wrote.
        $export->refresh();

        if ($export->settled() >= $export->total) {
            $this->finish($export);

            return;
        }

        self::dispatch($export->id);
    }

    /**
     * One player, drawn onto an auction poster template with GD.
     *
     * A different job from the wall card entirely: no browser, no page fetch, a hundredth of
     * the time — and a canvas whose shape is whatever the designer chose rather than the wall's
     * fixed 1601x910. The PNG is copied out of the public disk into a temp file because the
     * caller deletes what it is given, and deleting out of storage/app/public would remove a
     * poster the tournament may still be serving.
     */
    private function renderPoster(AuctionCardExport $export, AuctionPlayer $auctionPlayer): string
    {
        $template = $export->tournamentTemplate;

        if (! $template) {
            throw new \RuntimeException('The poster template for this export no longer exists.');
        }

        $stored = app(TemplateRenderService::class)->renderTemplate(
            $template,
            app(AuctionPosterData::class)->forPlayer($auctionPlayer),
            false,
            // Hide anything with no value, so ONE template serves both the lot announcement
            // and the sold poster: no price, no badge and no team until they are true.
            true
        );

        $source = Storage::disk('public')->path($stored);
        $temp = tempnam(sys_get_temp_dir(), 'auction-poster-') . '.png';

        copy($source, $temp);
        Storage::disk('public')->delete($stored);

        return $temp;
    }

    /** A queue-level failure still has to close the bar the operator is watching. */
    public function failed(\Throwable $e): void
    {
        AuctionCardExport::where('id', $this->exportId)
            ->whereNotIn('status', [AuctionCardExport::STATUS_DONE])
            ->update([
                'status' => AuctionCardExport::STATUS_FAILED,
                'message' => $e->getMessage(),
            ]);
    }

    /**
     * Nothing rendered is a failure, not a finished export of zero cards.
     *
     * An empty zip downloads perfectly happily and tells the operator nothing about why it is
     * empty, which is the exact trap the old synchronous version fell into.
     */
    private function finish(AuctionCardExport $export): void
    {
        if ($export->completed < 1) {
            if ($export->path) {
                Storage::disk(AuctionCardExport::DISK)->delete($export->path);
            }

            $export->update([
                'status' => AuctionCardExport::STATUS_FAILED,
                'path' => null,
                'message' => 'None of the cards could be rendered. ' . ($export->message ?: 'See the log for why.'),
            ]);

            return;
        }

        $export->update(['status' => AuctionCardExport::STATUS_DONE]);
    }

    /**
     * The zip's path on disk, with its directory guaranteed to exist.
     *
     * ZipArchive writes with the raw filesystem, not through the Storage disk, so a missing
     * directory is a bare `open()` failure rather than anything Laravel would create for us.
     */
    private function openable(AuctionCardExport $export): string
    {
        $disk = Storage::disk(AuctionCardExport::DISK);

        if (! $disk->exists(AuctionCardExport::DIRECTORY)) {
            $disk->makeDirectory(AuctionCardExport::DIRECTORY);
        }

        return $disk->path($export->path);
    }
}
