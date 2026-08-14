<?php

namespace App\Mail;

use App\Models\ActualTeam;
use App\Models\Auction;
use App\Models\Player;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PlayerSoldMail extends Mailable
{
    use Queueable, SerializesModels;

    public Player $player;
    public ActualTeam $team;
    public Auction $auction;
    public float $finalPrice;

    /**
     * The sold poster, already rendered, as an absolute path.
     *
     * Passed in rather than rendered here: a Mailable is constructed on the queue as well as in
     * a request, and a poster render is GD work measured in seconds — doing it inside the
     * mailable would make every retry redraw the artwork, and a preview redraw it again.
     * AuctionPosterMailer renders it once and hands the path over.
     */
    public ?string $posterPath;

    public function __construct(
        Player $player,
        ActualTeam $team,
        Auction $auction,
        float $finalPrice,
        ?string $posterPath = null
    ) {
        $this->player = $player;
        $this->team = $team;
        $this->auction = $auction;
        $this->finalPrice = $finalPrice;
        $this->posterPath = $posterPath;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You've been selected! — " . $this->auction->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.player-sold',
            with: [
                /*
                 * The poster is embedded in the body as well as attached, under this cid.
                 *
                 * An attachment alone is a file a player has to go and open; a picture in the
                 * message is the thing they screenshot and put in a group chat, which is the
                 * entire point of a sold poster. Both, because an inline image is what some
                 * clients strip and an attachment is what the rest hide behind a paperclip.
                 */
                'posterCid' => $this->posterPath && is_file($this->posterPath) ? 'sold-poster' : null,
                'posterPath' => $this->posterPath,
            ],
        );
    }

    public function attachments(): array
    {
        if (! $this->posterPath || ! is_file($this->posterPath)) {
            return [];
        }

        return [
            Attachment::fromPath($this->posterPath)
                ->as(basename($this->posterPath))
                ->withMime('image/png'),
        ];
    }
}
