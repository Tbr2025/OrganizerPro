<?php

namespace App\Mail;

use App\Models\Player;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PlayerVerificationStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public $player;
    public $verifiedFields;
    public $unverifiedFields;

    public function __construct(Player $player, array $verifiedFields, array $unverifiedFields)
    {
        $this->player = $player;
        $this->verifiedFields = $verifiedFields;
        $this->unverifiedFields = $unverifiedFields;
    }

    public function build()
    {
        return $this->subject('Player Verification Status')
            ->view('emails.player_verification_status');
    }
}
