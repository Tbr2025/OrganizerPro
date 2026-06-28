<?php

namespace Tests\Feature\Admin;

use App\Mail\RegistrationApprovedMail;
use App\Models\EmailTemplate;
use App\Models\Organization;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Services\Email\EmailTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailTemplateServiceTest extends TestCase
{
    use RefreshDatabase;

    private function tournament(): Tournament
    {
        $org = Organization::create(['name' => 'Org']);

        return Tournament::create([
            'name' => 'Cup', 'slug' => 'cup-x', 'start_date' => '2026-01-01', 'organization_id' => $org->id,
        ]);
    }

    #[Test]
    public function resolution_order_is_tournament_then_global_then_default(): void
    {
        $svc = app(EmailTemplateService::class);
        $tournament = $this->tournament();
        $type = EmailTemplate::TYPE_APPROVED;

        // Default: built-in seed.
        $this->assertSame('default', $svc->rawTemplate($type, $tournament)['source']);

        // Global override wins over default.
        EmailTemplate::create(['tournament_id' => null, 'type' => $type, 'subject' => 'G', 'body_html' => 'GLOBAL']);
        $this->assertSame('global', $svc->rawTemplate($type, $tournament)['source']);
        $this->assertSame('GLOBAL', $svc->rawTemplate($type, $tournament)['body_html']);

        // Tournament override wins over global.
        EmailTemplate::create(['tournament_id' => $tournament->id, 'type' => $type, 'subject' => 'T', 'body_html' => 'TOURNEY']);
        $this->assertSame('tournament', $svc->rawTemplate($type, $tournament)['source']);
        $this->assertSame('TOURNEY', $svc->rawTemplate($type, $tournament)['body_html']);
    }

    #[Test]
    public function placeholders_are_filled_and_names_escaped(): void
    {
        $svc = app(EmailTemplateService::class);
        $tournament = $this->tournament();

        EmailTemplate::create([
            'tournament_id' => $tournament->id,
            'type' => EmailTemplate::TYPE_APPROVED,
            'subject' => 'Hi {recipient_name}',
            'body_html' => '<p>{recipient_name} — {tournament_name}</p>',
        ]);

        $player = new Player(['name' => '<b>Evil</b>', 'email' => 'e@x.test']);
        $reg = new TournamentRegistration(['type' => 'player']);
        $reg->setRelation('player', $player);

        $out = $svc->resolve(EmailTemplate::TYPE_APPROVED, $tournament, $reg, null);

        $this->assertStringContainsString('Cup', $out['html']);
        $this->assertStringContainsString('&lt;b&gt;Evil&lt;/b&gt;', $out['html']); // escaped
        $this->assertStringNotContainsString('<b>Evil</b>', $out['html']);
    }

    #[Test]
    public function contrast_color_picks_white_on_dark_and_dark_on_light(): void
    {
        $svc = app(EmailTemplateService::class);

        $this->assertSame('#ffffff', $svc->contrastColor('#1a1a4b')); // dark navy → white
        $this->assertSame('#111827', $svc->contrastColor('#ffffff')); // white → dark
    }

    #[Test]
    public function mailable_uses_a_saved_override(): void
    {
        $tournament = $this->tournament();

        EmailTemplate::create([
            'tournament_id' => $tournament->id,
            'type' => EmailTemplate::TYPE_APPROVED,
            'subject' => 'Custom Subject {tournament_name}',
            'body_html' => '<p>CUSTOM BODY for {tournament_name}</p>',
        ]);

        $player = Player::create(['organization_id' => $tournament->organization_id, 'name' => 'Pat', 'email' => 'p@x.test', 'status' => 'approved']);
        $reg = TournamentRegistration::create([
            'tournament_id' => $tournament->id, 'organization_id' => $tournament->organization_id,
            'type' => 'player', 'player_id' => $player->id, 'status' => 'approved',
        ]);
        $reg->setRelation('tournament', $tournament);

        $mail = new RegistrationApprovedMail($tournament, $reg);
        $rendered = $mail->render();

        $this->assertStringContainsString('CUSTOM BODY for Cup', $rendered);
        $this->assertSame('Custom Subject Cup', $mail->envelope()->subject);
    }

    #[Test]
    public function reset_removes_override_and_falls_back(): void
    {
        $svc = app(EmailTemplateService::class);
        $tournament = $this->tournament();
        $type = EmailTemplate::TYPE_UNDER_REVIEW;

        EmailTemplate::create(['tournament_id' => $tournament->id, 'type' => $type, 'subject' => 'X', 'body_html' => 'OVERRIDE']);
        $this->assertSame('tournament', $svc->rawTemplate($type, $tournament)['source']);

        EmailTemplate::where('tournament_id', $tournament->id)->where('type', $type)->delete();
        $this->assertSame('default', $svc->rawTemplate($type, $tournament)['source']);
    }
}
