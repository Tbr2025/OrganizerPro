<?php

namespace Tests\Feature\Tournament;

use App\Models\Organization;
use App\Models\PlayerType;
use App\Models\Tournament;
use App\Models\User;
use App\Services\Tournament\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression: approving a registration must NOT fail even when the greeting-card
 * generation throws (e.g. a TypeError from a null poster field). Previously
 * sendGreetingCard caught \Exception only — TypeError extends \Error and escaped,
 * breaking the whole approval.
 */
class ApprovalNeverBreaksTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function approving_a_player_succeeds_even_when_card_generation_runs(): void
    {
        Mail::fake();

        $org = Organization::create(['name' => 'Org']);
        $tournament = Tournament::create([
            'name' => 'Cup', 'slug' => 'cup-x', 'start_date' => '2026-01-01', 'organization_id' => $org->id,
        ]);

        // A player type whose `name` is null (column is `type`) — the exact field
        // that produced the null passed to addText().
        $type = new PlayerType();
        $type->type = 'All Rounder';
        $type->save();

        $service = app(RegistrationService::class);
        $registration = $service->registerPlayer($tournament, [
            'name' => 'Sahil',
            'first_name' => 'Sahil',
            'last_name' => 'K',
            'email' => 'sahil@x.test',
            'player_type_id' => $type->id,
        ]);

        $approver = User::factory()->create();

        // Must not throw, and the registration must end up approved.
        $service->approvePlayerRegistration($registration, $approver->id);

        $this->assertSame('approved', $registration->fresh()->status);

        // Player marked approved + their user gets the Player role.
        $player = $registration->fresh()->player;
        $this->assertSame('approved', $player->status);
        $this->assertTrue($player->user->hasRole('player'));
    }
}
