<?php

declare(strict_types=1);

namespace App\Services\Auction;

use App\Models\Auction;
use App\Models\AuctionPlayer;
use App\Models\TournamentTemplate;
use App\Services\Poster\AuctionPosterData;
use App\Services\Poster\TemplateRenderService;
use Illuminate\Support\Facades\Storage;

/**
 * The sold poster, rendered once and reused by everything that needs it.
 *
 * The same artwork is already produced three ways — the single download on the pools screen, the
 * bulk card export, and now the email. Each of those had its own copy of "find the template, fall
 * back to the LED card, render it", and the moment they disagree a player receives a poster that
 * does not match the one the organizer downloaded and put on the group chat.
 *
 * The fallback matters as much as the render: a tournament with no auction poster designed still
 * gets the LED wall card, which every auction has. An email that arrives with no poster because
 * nobody drew one is a worse outcome than an email carrying the plain card.
 */
class AuctionPosterMailer
{
    public function __construct(
        private TemplateRenderService $templates,
        private AuctionPosterData $data,
        private AuctionCardRenderer $cards,
    ) {}

    /**
     * Render this player's poster and return an absolute path, or null when it cannot be made.
     *
     * Never throws. A poster is the ornament on a sold email, not its point — if rendering fails
     * (a corrupt upload, a template referencing a deleted image, GD out of memory on a large
     * canvas) the player must still be told they were sold, and the organizer must still see the
     * row in the outbox rather than an auction that stopped sending mail.
     */
    public function render(Auction $auction, AuctionPlayer $auctionPlayer): ?string
    {
        try {
            $auctionPlayer->loadMissing(['player', 'soldToTeam', 'pool', 'sourcePool']);

            $template = TournamentTemplate::where('tournament_id', $auction->tournament_id)
                ->whereIn('type', [
                    TournamentTemplate::TYPE_AUCTION_POSTER,
                    TournamentTemplate::TYPE_AUCTION_POSTER_PORTRAIT,
                ])
                ->orderByDesc('is_default')
                ->first();

            if (! $template) {
                // No designed poster: the LED wall card, exactly as the download falls back.
                return $this->cards->render($auction, $auctionPlayer, true);
            }

            $stored = $this->templates->renderTemplate(
                $template,
                $this->data->forPlayer($auctionPlayer),
                false,
                // Hide anything with no value, so one design serves both the lot announcement and
                // the sold poster.
                true
            );

            return Storage::disk('public')->path($stored);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /** What the file is called when it lands in somebody's inbox. */
    public function filename(AuctionPlayer $auctionPlayer): string
    {
        return $this->cards->filename($auctionPlayer, true);
    }
}
