<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A batch of player cards being rendered, and how far it has got.
 *
 * @see \App\Jobs\RenderAuctionCards for the work itself.
 */
class AuctionCardExport extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';

    /** Where the zips live. Private: a card carries a player's photo and their price. */
    public const DISK = 'local';
    public const DIRECTORY = 'auction-card-exports';

    protected $fillable = [
        'auction_id', 'user_id', 'token', 'with_result', 'tournament_template_id', 'auction_player_ids',
        'total', 'completed', 'failed', 'status', 'message', 'path', 'filename',
    ];

    protected $casts = [
        'with_result' => 'boolean',
        'auction_player_ids' => 'array',
        'total' => 'integer',
        'completed' => 'integer',
        'failed' => 'integer',
    ];

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    /** The poster design, when the export is rendering one rather than the LED wall's card. */
    public function tournamentTemplate(): BelongsTo
    {
        return $this->belongsTo(TournamentTemplate::class);
    }

    /** Cards accounted for either way — a card that failed is still one the operator is not waiting on. */
    public function settled(): int
    {
        return $this->completed + $this->failed;
    }

    /**
     * Percent complete, as an integer.
     *
     * Never returns 100 before the status says so: a bar that reads 100% while the zip is
     * still being closed invites a click on a download that is not there yet.
     */
    public function percent(): int
    {
        if ($this->status === self::STATUS_DONE) {
            return 100;
        }

        if ($this->total < 1) {
            return 0;
        }

        return (int) min(99, floor(($this->settled() / $this->total) * 100));
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_DONE, self::STATUS_FAILED], true);
    }

    /** What the polling endpoint hands back. Deliberately small — it is fetched every second. */
    public function toProgressPayload(): array
    {
        return [
            'token' => $this->token,
            'status' => $this->status,
            'total' => $this->total,
            'completed' => $this->completed,
            'failed' => $this->failed,
            'percent' => $this->percent(),
            'message' => $this->message,
            'finished' => $this->isFinished(),
            'download_url' => $this->status === self::STATUS_DONE
                ? route('admin.auctions.cards.export.download', [$this->auction_id, $this->token])
                : null,
        ];
    }

    public function absolutePath(): ?string
    {
        return $this->path ? Storage::disk(self::DISK)->path($this->path) : null;
    }

    /**
     * Forget an export and the zip it produced.
     *
     * A zip of 200 cards is tens of megabytes, so leaving them behind fills a disk on a box
     * that also has to serve the auction it is filling up during.
     */
    public function discard(): void
    {
        if ($this->path && Storage::disk(self::DISK)->exists($this->path)) {
            Storage::disk(self::DISK)->delete($this->path);
        }

        $this->delete();
    }
}
