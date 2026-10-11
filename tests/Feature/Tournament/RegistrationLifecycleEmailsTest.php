<?php

namespace Tests\Feature\Tournament;

use App\Mail\ApplicationUnderReviewMail;
use App\Models\Organization;
use App\Models\Tournament;
use App\Models\User;
use App\Services\Notification\TournamentNotificationService;
use App\Services\Tournament\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RegistrationLifecycleEmailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Approval gives the player the Player role, so the roles must exist.
        app(\App\Services\PermissionService::class)->createPermissions();
        app(\App\Services\RolesService::class)->createPredefinedRoles();
    }

    private function tournament(): Tournament
    {
        $org = Organization::create(['name' => 'Org']);

        return Tournament::create([
            'name' => 'Cup', 'slug' => 'cup-x', 'start_date' => '2026-01-01',
            'organization_id' => $org->id,
        ]);
    }

    #[Test]
    public function submitting_a_player_registration_emails_the_applicant_under_review(): void
    {
        Mail::fake();
        $tournament = $this->tournament();

        app(RegistrationService::class)->registerPlayer($tournament, [
            'name' => 'Asif', 'email' => 'asif@x.test',
        ]);

        Mail::assertSent(ApplicationUnderReviewMail::class, fn ($m) => $m->hasTo('asif@x.test'));
    }

    #[Test]
    public function approving_a_player_triggers_the_greeting_card(): void
    {
        Mail::fake();
        $tournament = $this->tournament();
        $service = app(RegistrationService::class);

        $registration = $service->registerPlayer($tournament, [
            'name' => 'Asif', 'email' => 'asif@x.test',
        ]);

        // Approval renders the welcome/greeting card and attaches it to the
        // approval email (rendering is exercised elsewhere; this is the wiring).
        $this->mock(TournamentNotificationService::class)
            ->shouldReceive('generateWelcomeCard')->once()->andReturn(null);

        $approver = User::factory()->create();
        $service->approvePlayerRegistration($registration, $approver->id);

        $this->assertSame('approved', $registration->fresh()->status);
    }
}
