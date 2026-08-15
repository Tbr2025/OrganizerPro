<?php

declare(strict_types=1);

namespace App\Services\Auction;

use App\Mail\PlayerSoldMail;
use App\Mail\PlayerUnsoldMail;
use App\Models\ActualTeam;
use App\Models\Auction;
use App\Models\AuctionPendingEmail;
use App\Models\AuctionPlayer;
use App\Models\Player;
use App\Models\TournamentRegistration;
use App\Notifications\GeneralNotification;
use App\Services\Notification\TournamentNotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * The one place that decides whether an auction email is sent now, held until the
 * auction finishes, or suppressed.
 *
 * Auction mail used to go out with `Mail::to()->send()` inline on the sell path, so every
 * sale blocked the room on SMTP and any rehearsal emailed real players. Three per-auction
 * settings govern it now:
 *
 *   notifications_enabled — master switch; off means nothing is raised at all
 *   email_test_mode       — messages are recorded as skipped and never sent
 *   email_dispatch        — `immediate`, or `deferred` until the auction completes
 */
class AuctionMailService
{
    public function __construct(
        private readonly TournamentNotificationService $notifications,
    ) {
    }

    /** Is any player mail wanted for this auction at all? */
    public function notificationsEnabled(Auction $auction): bool
    {
        return (bool) ($auction->notifications_enabled ?? true);
    }

    /** Test run: record what would have gone out, send nothing. */
    public function isTestMode(Auction $auction): bool
    {
        return (bool) ($auction->email_test_mode ?? false);
    }

    /** Held until the auction completes, rather than sent as it happens. */
    public function isDeferred(Auction $auction): bool
    {
        return ($auction->email_dispatch ?? 'deferred') === 'deferred';
    }

    /**
     * Raise an auction email.
     *
     * Returns the outbox row when one was recorded, or null when notifications are off
     * entirely (nothing is worth recording in that case).
     */
    public function raise(
        Auction $auction,
        string $type,
        ?AuctionPlayer $auctionPlayer,
        ?ActualTeam $team = null,
        array $payload = []
    ): ?AuctionPendingEmail {
        if (! $this->notificationsEnabled($auction)) {
            return null;
        }

        $row = AuctionPendingEmail::create([
            'auction_id' => $auction->id,
            'auction_player_id' => $auctionPlayer?->id,
            'player_id' => $auctionPlayer?->player_id,
            'actual_team_id' => $team?->id,
            'type' => $type,
            'payload' => $payload,
            'status' => AuctionPendingEmail::STATUS_PENDING,
        ]);

        // Test mode: keep the record so the organizer can see what a real run would have
        // sent, but never actually send it.
        if ($this->isTestMode($auction)) {
            $row->update([
                'status' => AuctionPendingEmail::STATUS_SKIPPED,
                'error' => 'Test mode — not sent.',
            ]);

            return $row;
        }

        // Deferred is the point of the outbox: leave it pending for the flush.
        if ($this->isDeferred($auction)) {
            return $row;
        }

        $this->send($row);

        return $row->fresh();
    }

    /**
     * Send pending mail for this auction, up to `$limit` messages.
     *
     * Called when the auction completes, and on demand from the auction page.
     *
     * Chunked deliberately. The live worker runs `queue:work --timeout=300`, and a few
     * hundred welcome cards — each rendering a poster — would not finish inside that. Each
     * row is marked sent the moment it goes out, so a job killed mid-batch never re-sends
     * anything: the next pass simply picks up what is still pending.
     *
     * @return array{sent: int, failed: int, skipped: int, remaining: int}
     */
    public function flush(Auction $auction, int $limit = 50): array
    {
        $result = ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'remaining' => 0];

        $pending = AuctionPendingEmail::where('auction_id', $auction->id)
            ->pending()
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($pending as $row) {
            if ($this->isTestMode($auction) || ! $this->notificationsEnabled($auction)) {
                $row->update([
                    'status' => AuctionPendingEmail::STATUS_SKIPPED,
                    'error' => $this->isTestMode($auction)
                        ? 'Test mode — not sent.'
                        : 'Notifications disabled for this auction.',
                ]);
                $result['skipped']++;
                continue;
            }

            $this->send($row);
            $result[$row->fresh()->status === AuctionPendingEmail::STATUS_SENT ? 'sent' : 'failed']++;
        }

        $result['remaining'] = AuctionPendingEmail::where('auction_id', $auction->id)->pending()->count();

        $auction->update(['emails_flushed_at' => now()]);

        return $result;
    }

    /** How much is still waiting, by status. */
    public function outboxCounts(Auction $auction): array
    {
        return AuctionPendingEmail::where('auction_id', $auction->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();
    }

    /**
     * Deliver one outbox row.
     *
     * A single failure is recorded against its own row and never aborts the rest of the
     * flush — one bad address must not strand every other player's email.
     */
    private function send(AuctionPendingEmail $row): void
    {
        try {
            match ($row->type) {
                AuctionPendingEmail::TYPE_WELCOME_CARD => $this->sendWelcomeCard($row),
                AuctionPendingEmail::TYPE_SOLD => $this->sendSold($row),
                AuctionPendingEmail::TYPE_UNSOLD => $this->sendUnsold($row),
                default => throw new \RuntimeException("Unknown auction email type [{$row->type}]."),
            };

            $row->update([
                'status' => AuctionPendingEmail::STATUS_SENT,
                'sent_at' => now(),
                'error' => null,
            ]);
        } catch (\Throwable $e) {
            Log::error("Auction email [{$row->type}] failed for row {$row->id}: " . $e->getMessage());
            $row->update([
                'status' => AuctionPendingEmail::STATUS_FAILED,
                'error' => mb_substr($e->getMessage(), 0, 250),
            ]);
        }
    }

    /**
     * The "welcome to the team" card.
     *
     * Deferring this is what keeps the auction fast: the card is a rendered poster, so
     * generating it mid-auction cost real time on every single sale.
     */
    /**
     * The one email a sold player gets, carrying their sold card.
     *
     * This delegated to TournamentNotificationService::sendRetainedWelcomeCard(), which is built
     * for RETENTION and behaves accordingly: it requires a `retained_welcome_card` design and,
     * with none, logs "No retained_welcome_card template found — skipping" and sends nothing. Live
     * has been doing exactly that. When it did send, the attachment was the welcome design rather
     * than the card showing what the player went for.
     *
     * An auction sale attaches the SOLD card — the same artwork the pools screen downloads and the
     * wall shows — from the template the organizer chose in the auction's settings. That card is
     * always renderable: AuctionPosterMailer falls back to the LED wall card when no poster has
     * been designed, so a sale can no longer go out silently unemailed for want of a template
     * nobody knew they had to draw.
     *
     * The welcome WORDING is unchanged: the same EmailTemplate type, the same per-tournament
     * overrides. Only the attachment and the reason it cannot fail have changed.
     */
    private function sendWelcomeCard(AuctionPendingEmail $row): void
    {
        $auction = $row->auction;
        $registration = TournamentRegistration::where('player_id', $row->player_id)
            ->where('tournament_id', $auction?->tournament_id)
            ->first();

        if (! $registration) {
            throw new \RuntimeException('No tournament registration found for this player.');
        }

        $player = $registration->player;
        $email = $player?->email;

        if (! $player || ! $email) {
            throw new \RuntimeException('Player has no email address to send the card to.');
        }

        $auctionPlayer = $row->auctionPlayer;
        $poster = $auctionPlayer && $auction
            ? app(AuctionPosterMailer::class)->render($auction, $auctionPlayer)
            : null;

        /*
         * A card that could not be drawn does not stop the email.
         *
         * `$poster` is null when a render fails — a corrupt upload, GD out of memory, no template
         * and no LED card either. Being told you were sold is the message; the card is what makes
         * it worth keeping. PlayerWelcomeMail already skips a missing attachment, so an empty path
         * sends the same email without it.
         *
         * This is the behaviour the old path did not have: it required a `retained_welcome_card`
         * design and, with none, logged a line and sent nothing at all.
         */
        $team = $row->team ?? $auctionPlayer?->soldToTeam;

        Mail::to($email)->send(new \App\Mail\PlayerWelcomeMail(
            $player,
            $poster ?? '',
            $registration->tournament,
            \App\Models\EmailTemplate::TYPE_WELCOME_CARD,
            array_filter([
                '{team_name}' => $team?->name,
                '{sold_price}' => $auctionPlayer && $auctionPlayer->final_price !== null
                    ? $auction->formatAmount($auctionPlayer->final_price)
                    : null,
            ])
        ));
    }

    /**
     * The email as HTML, without sending it.
     *
     * Built from the same mailables send() uses, so a preview cannot drift from what
     * actually goes out. Strictly read-only: it never touches the row's status, never
     * notifies anybody, and never dispatches mail.
     *
     * The welcome card is the exception, and deliberately so — it is produced by
     * TournamentNotificationService, which generates a poster and sends in one step. There
     * is no way to render it without the side effects, so it says so instead of pretending.
     */
    public function renderPreview(AuctionPendingEmail $row): string
    {
        $auction = $row->auction;
        $player = Player::with('user')->find($row->player_id);

        if (! $player) {
            throw new \RuntimeException('The player on this email no longer exists.');
        }

        $mailable = match ($row->type) {
            /*
             * The preview carries the poster too — that is the point of test mode.
             *
             * "Record emails but never send them" is only useful if the organizer can see what a
             * real run WOULD have sent, and for a sold email the poster is most of it. Rendering
             * it here costs a few seconds on one preview and answers the question the test-mode
             * checkbox exists to ask.
             */
            AuctionPendingEmail::TYPE_SOLD => new PlayerSoldMail(
                $player,
                $row->team,
                $auction,
                $row->payload['amount'] ?? null,
                $row->auctionPlayer
                    ? app(AuctionPosterMailer::class)->render($auction, $row->auctionPlayer)
                    : null
            ),
            AuctionPendingEmail::TYPE_UNSOLD => new PlayerUnsoldMail($player, $auction),
            /*
             * The welcome card previews like everything else now.
             *
             * It used to refuse, and the message said why: it was generated and sent in one step
             * by TournamentNotificationService, so there was no way to build it without also
             * sending it. That is no longer how it works — the card is rendered here and handed to
             * PlayerWelcomeMail — and since every sale now raises this type and nothing else, a
             * refusal meant the outbox could preview none of its rows.
             *
             * Rendering the poster for a preview costs a few seconds and is the point: test mode
             * exists to show what a real run would have sent, attachment included.
             */
            AuctionPendingEmail::TYPE_WELCOME_CARD => new \App\Mail\PlayerWelcomeMail(
                $player,
                ($row->auctionPlayer && $auction
                    ? app(AuctionPosterMailer::class)->render($auction, $row->auctionPlayer)
                    : null) ?? '',
                $auction?->tournament,
                \App\Models\EmailTemplate::TYPE_WELCOME_CARD,
                array_filter([
                    '{team_name}' => ($row->team ?? $row->auctionPlayer?->soldToTeam)?->name,
                    '{sold_price}' => $row->auctionPlayer && $row->auctionPlayer->final_price !== null && $auction
                        ? $auction->formatAmount($row->auctionPlayer->final_price)
                        : null,
                ])
            ),
            default => throw new \RuntimeException("Unknown auction email type [{$row->type}]."),
        };

        $html = $mailable->render();

        /*
         * Show the ATTACHMENT, not just the words.
         *
         * `render()` returns the body, and a mail body says nothing about what is clipped to it —
         * so the one thing test mode exists to check, the card the player receives, was the one
         * thing the preview could not show. An organizer approving artwork was approving a
         * sentence about it.
         *
         * Prepended as a strip rather than merged into the body: this is a note ABOUT the email,
         * and dressing it up as part of the email would misrepresent what actually arrives.
         */
        foreach ($mailable->attachments() as $attachment) {
            $path = $this->attachmentPath($attachment);

            if (! $path || ! is_file($path)) {
                continue;
            }

            $html = '<div style="padding:14px 18px;background:#0f172a;color:#e2e8f0;'
                . 'font:600 12px/1.4 system-ui,sans-serif;letter-spacing:.08em;text-transform:uppercase;">'
                . 'Attached to this email'
                . '</div>'
                . '<div style="padding:18px;background:#1e293b;text-align:center;">'
                . '<img src="data:image/png;base64,' . base64_encode((string) file_get_contents($path)) . '"'
                . ' alt="" style="max-width:520px;width:100%;height:auto;border-radius:8px;">'
                . '</div>'
                . $html;

            break;
        }

        return $html;
    }

    /**
     * The file behind a Mailable attachment.
     *
     * Laravel's Attachment keeps its path in a protected property, so it is read through the
     * resolver the framework itself uses rather than by reaching inside the object.
     */
    private function attachmentPath(\Illuminate\Mail\Mailables\Attachment $attachment): ?string
    {
        $resolved = null;

        $attachment->attachWith(
            function ($path) use (&$resolved) {
                $resolved = $path;

                return null;
            },
            fn () => null
        );

        return is_string($resolved) ? $resolved : null;
    }

    private function sendSold(AuctionPendingEmail $row): void
    {
        $player = Player::with('user')->find($row->player_id);
        $team = $row->team;
        $auction = $row->auction;
        $price = $row->payload['amount'] ?? null;

        if (! $player?->user) {
            throw new \RuntimeException('Player has no user account to notify.');
        }

        $player->user->notify(new GeneralNotification(
            sprintf("You've been sold to %s!", $team->name ?? 'a team'),
            route('admin.auctions.show', $auction),
            'success'
        ));

        if ($player->user->email) {
            /*
             * The sold poster travels with the mail.
             *
             * Rendered here rather than inside the Mailable: a Mailable is constructed on retries
             * and for previews as well as for the send, and a poster is GD work measured in
             * seconds — drawing it in the constructor would redraw it every one of those times.
             *
             * A failed render returns null and the email goes anyway. Being told you were sold is
             * the message; the poster is what makes it worth keeping.
             */
            $poster = $row->auctionPlayer
                ? app(AuctionPosterMailer::class)->render($auction, $row->auctionPlayer)
                : null;

            Mail::to($player->user->email)->send(
                new PlayerSoldMail($player, $team, $auction, $price, $poster)
            );
        }

        // The buying team's managers hear about it too.
        foreach ($team?->users ?? [] as $manager) {
            $manager->notify(new GeneralNotification(
                sprintf('%s has been added to %s.', $player->name, $team->name),
                route('admin.auctions.show', $auction),
                'info'
            ));
        }
    }

    private function sendUnsold(AuctionPendingEmail $row): void
    {
        $player = Player::with('user')->find($row->player_id);
        $auction = $row->auction;

        if (! $player?->user) {
            throw new \RuntimeException('Player has no user account to notify.');
        }

        $player->user->notify(new GeneralNotification(
            sprintf('You were not selected in the auction: %s.', $auction->name),
            route('admin.auctions.show', $auction),
            'warning'
        ));

        if ($player->user->email) {
            Mail::to($player->user->email)->send(new PlayerUnsoldMail($player, $auction));
        }
    }
}
