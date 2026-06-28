<?php

namespace Tests\Feature\Admin;

use App\Models\EmailTemplate;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailPreviewTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        $org = Organization::create(['name' => 'Org']);
        Tournament::create([
            'name' => 'Cup', 'slug' => 'cup-x', 'start_date' => '2026-01-01', 'organization_id' => $org->id,
        ]);

        $role = Role::create(['name' => 'Superadmin']);
        $user = User::factory()->create(['organization_id' => $org->id]);
        $user->assignRole($role);

        return $user;
    }

    #[Test]
    public function superadmin_can_open_the_email_preview_page(): void
    {
        $this->actingAs($this->superadmin())
            ->get(route('admin.emails.preview'))
            ->assertOk()
            ->assertSee('Email Preview');
    }

    #[Test]
    public function superadmin_can_render_each_email_type(): void
    {
        $admin = $this->superadmin();

        foreach (['under_review', 'approved', 'welcome_card'] as $type) {
            $this->actingAs($admin)
                ->get(route('admin.emails.preview.render', $type))
                ->assertOk();
        }
    }

    #[Test]
    public function an_unknown_email_type_is_404(): void
    {
        $this->actingAs($this->superadmin())
            ->get(route('admin.emails.preview.render', 'bogus'))
            ->assertNotFound();
    }

    #[Test]
    public function superadmin_can_save_and_reset_a_template_override(): void
    {
        $admin = $this->superadmin();
        $tournament = Tournament::where('slug', 'cup-x')->first();

        // Save a tournament-specific override.
        $this->actingAs($admin)->postJson(route('admin.emails.templates.save'), [
            'type' => EmailTemplate::TYPE_APPROVED,
            'tournament_id' => $tournament->id,
            'subject' => 'Edited subject',
            'body_html' => '<p>edited</p>',
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('email_templates', [
            'tournament_id' => $tournament->id,
            'type' => EmailTemplate::TYPE_APPROVED,
            'subject' => 'Edited subject',
        ]);

        // Reset removes it.
        $this->actingAs($admin)->deleteJson(route('admin.emails.templates.reset'), [
            'type' => EmailTemplate::TYPE_APPROVED,
            'tournament_id' => $tournament->id,
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseMissing('email_templates', [
            'tournament_id' => $tournament->id,
            'type' => EmailTemplate::TYPE_APPROVED,
        ]);
    }

    #[Test]
    public function draft_preview_renders_unsaved_html_without_persisting(): void
    {
        $admin = $this->superadmin();
        $tournament = Tournament::where('slug', 'cup-x')->first();

        $this->actingAs($admin)->post(
            route('admin.emails.preview.draft', 'approved') . '?tournament_id=' . $tournament->id,
            ['subject' => 'S', 'body_html' => '<p>DRAFT {tournament_name}</p>']
        )->assertOk()->assertSee('DRAFT Cup', false);

        $this->assertDatabaseCount('email_templates', 0);
    }

    #[Test]
    public function non_superadmin_is_forbidden(): void
    {
        $org = Organization::create(['name' => 'Org2']);
        $role = Role::create(['name' => 'Editor']);
        $user = User::factory()->create(['organization_id' => $org->id]);
        $user->assignRole($role);

        $this->actingAs($user)
            ->get(route('admin.emails.preview'))
            ->assertForbidden();
    }
}
