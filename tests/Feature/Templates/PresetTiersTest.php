<?php

declare(strict_types=1);

namespace Tests\Feature\Templates;

use App\Models\Credits\CreditFeature;
use App\Models\Organization;
use App\Models\Tournament;
use App\Models\TournamentTemplate;
use App\Models\User;
use App\Services\Credits\CreditService;
use App\Services\PermissionService;
use App\Services\Poster\TemplateLibrary;
use App\Services\Poster\TemplateRenderService;
use App\Services\RolesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Free and Premium presets, who they are offered to, auction-only types, the
 * background-only rule for copies of free presets, and bringing copies up to a
 * preset's new version.
 */
class PresetTiersTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Organization $other;
    private User $super;
    private User $admin;
    private CreditService $credits;
    private TemplateLibrary $library;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        app(PermissionService::class)->createPermissions();
        app(RolesService::class)->createPredefinedRoles();
        $this->credits = app(CreditService::class);
        $this->library = app(TemplateLibrary::class);

        $this->org = Organization::create(['name' => 'Club', 'billing_mode' => 'credits']);
        $this->other = Organization::create(['name' => 'Rival', 'billing_mode' => 'credits']);
        $this->super = User::factory()->create(['organization_id' => null])->assignRole('Superadmin');
        $this->admin = User::factory()->create(['organization_id' => $this->org->id])->assignRole('Admin');
    }

    private function preset(string $type, string $name, array $extra = []): TournamentTemplate
    {
        Storage::disk('public')->put($bg = 'tournament_templates/library/bg_' . uniqid() . '.png', 'png');

        return TournamentTemplate::create($extra + [
            'tournament_id' => null, 'organization_id' => null, 'type' => $type, 'name' => $name, 'background_image' => $bg,
            'layout_json' => [['type' => 'text', 'text' => $name, 'x' => 50, 'y' => 50]],
            'canvas_width' => 1080, 'canvas_height' => 1350, 'is_default' => false, 'is_active' => true,
        ]);
    }

    private function tournament(string $type = 'open', ?Organization $org = null): Tournament
    {
        return Tournament::create([
            'name' => 'Cup ' . uniqid(), 'slug' => 'cup-' . uniqid(), 'start_date' => now()->addWeek(), 'end_date' => now()->addWeeks(2),
            'organization_id' => ($org ?? $this->org)->id, 'status' => 'active', 'type' => $type,
        ]);
    }

    private function price(string $key, int $cost): void
    {
        CreditFeature::where('key', $key)->update(['cost' => $cost, 'is_active' => true]);
    }

    #[Test]
    public function a_premium_preset_is_charged_once_per_tournament_and_refused_when_short(): void
    {
        $premium = $this->preset('match_poster', 'Gold', ['tier' => 'premium', 'price' => 40]);
        $free = $this->preset('flyer', 'Plain');
        $form = fn (string $name) => [
            'organization_id' => $this->org->id, 'name' => $name, 'type' => 'open',
            'start_date' => now()->addDay()->toDateString(), 'end_date' => now()->addDays(9)->toDateString(),
            'presets' => [$premium->id, $free->id],
        ];

        // Not enough for the premium preset: nothing is created.
        $this->actingAs($this->admin)->post(route('admin.tournaments.store'), $form('Short Cup'))->assertSessionHas('error');
        $this->assertFalse(Tournament::where('name', 'Short Cup')->exists());

        $this->credits->grant($this->org, 50, 'promo', 'test');
        $this->post(route('admin.tournaments.store'), $form('Paid Cup'))->assertSessionHas('success');
        $t = Tournament::where('name', 'Paid Cup')->firstOrFail();
        $this->assertSame(2, $t->templates()->count());
        $this->assertSame(10, $this->credits->balance($this->org->fresh()));

        // Adding it again to the same tournament costs nothing more.
        $this->post(route('admin.template-library.copy-to-tournament', $t), ['presets' => [$premium->id]])->assertSessionHas('success');
        $this->assertSame(10, $this->credits->balance($this->org->fresh()));

        // The premium copy is fully editable; the free one is background-only.
        $this->assertFalse($t->templates()->where('name', 'Gold')->first()->bg_only);
        $this->assertTrue($t->templates()->where('name', 'Plain')->first()->bg_only);
    }

    #[Test]
    public function presets_are_offered_only_to_selected_organizations_and_auction_types_only_to_auctions(): void
    {
        $everyone = $this->preset('flyer', 'For all');
        $onlyRival = $this->preset('flyer', 'Rival only', ['offered_to' => [$this->other->id]]);
        $auctionCard = $this->preset('auction_poster', 'Card');

        $open = $this->tournament('open');
        $this->assertEqualsCanonicalizing(['For all'], $this->library->pickerFor($this->admin, $open)->pluck('name')->all());

        $auction = $this->tournament('auction');
        $this->assertEqualsCanonicalizing(['For all', 'Card'], $this->library->pickerFor($this->admin, $auction)->pluck('name')->all());

        // A posted id never pulls in what is not offered.
        $this->library->copyInto($open, [$everyone->id, $onlyRival->id, $auctionCard->id]);
        $this->assertSame(['For all'], $open->templates()->pluck('name')->all());

        // Super Admin sets who it is offered to on the presets page.
        $this->actingAs($this->super)->post(route('admin.template-library.settings', $everyone), [
            'tier' => 'premium', 'price' => 25, 'offer' => 'selected', 'offered_to' => [$this->other->id],
        ])->assertSessionHas('success');
        $everyone->refresh();
        $this->assertSame([$this->other->id], $everyone->offered_to);
        $this->assertTrue($everyone->isPremium());
        $this->actingAs($this->admin)->get(route('admin.template-library.index'))->assertForbidden();
    }

    #[Test]
    public function a_free_design_changes_only_its_background_and_that_costs_credits(): void
    {
        $t = $this->tournament();
        $preset = $this->preset('match_poster', 'Free one');
        $copy = TournamentTemplate::findOrFail($this->library->copyInto($t, [$preset->id])[$preset->id]);
        $this->assertTrue($copy->bg_only);
        $this->price(CreditFeature::TEMPLATE_BACKGROUND, 7);

        $this->actingAs($this->admin)->get(route('admin.tournaments.templates.edit', [$t, $copy]))
            ->assertRedirect(route('admin.tournaments.templates.index', $t));
        $this->putJson(route('admin.tournaments.templates.update', [$t, $copy]), ['name' => 'Hacked', 'layout_json' => '[]'])->assertForbidden();
        $this->assertSame('Free one', $copy->fresh()->name);
        $this->get(route('admin.tournaments.templates.index', $t))->assertOk()->assertSee('Free design')->assertSee('Change background');

        // Short: nothing changes. Paid: the background is replaced and the charge recorded.
        $before = $copy->background_image;
        $upload = fn () => UploadedFile::fake()->image('bg.jpg', 1200, 1500);
        $this->post(route('admin.tournaments.templates.update-background', [$t, $copy]), ['background_image' => $upload()])->assertSessionHas('error');
        $this->assertSame($before, $copy->fresh()->background_image);

        $this->credits->grant($this->org, 10, 'promo', 'test');
        $this->post(route('admin.tournaments.templates.update-background', [$t, $copy]), ['background_image' => $upload()])->assertSessionHas('success');
        $this->assertNotSame($before, $copy->fresh()->background_image);
        $this->assertTrue($copy->fresh()->bg_changed);
        $this->assertSame(3, $this->credits->balance($this->org->fresh()));

        // Super Admin may still edit the whole design.
        $this->actingAs($this->super)->get(route('admin.tournaments.templates.edit', [$t, $copy]))->assertOk();
    }

    #[Test]
    public function preset_changes_reach_the_copies_nobody_edited(): void
    {
        $free = $this->preset('match_poster', 'Free');
        $premium = $this->preset('flyer', 'Rich', ['tier' => 'premium', 'price' => 1]);
        $t = $this->tournament();
        $this->credits->grant($this->org, 5, 'promo', 'test');
        $map = $this->library->copyInto($t, [$free->id, $premium->id], $this->admin);
        $freeCopy = TournamentTemplate::find($map[$free->id]);
        $richCopy = TournamentTemplate::find($map[$premium->id]);

        // The free copy paid for its own background; the premium copy was edited.
        $freeCopy->update(['background_image' => 'tournament_templates/mine.jpg', 'bg_changed' => true]);
        $this->actingAs($this->admin)->putJson(route('admin.tournaments.templates.update', [$t, $richCopy]), [
            'name' => 'Rich (ours)', 'layout_json' => json_encode([['type' => 'text', 'text' => 'Ours']]),
        ])->assertOk();
        $this->assertTrue($richCopy->fresh()->customized);

        // Super Admin changes both presets.
        foreach ([$free, $premium] as $p) {
            $this->actingAs($this->super)->putJson(route('admin.template-library.update', $p), [
                'name' => $p->name, 'type' => $p->type, 'layout_json' => json_encode([['type' => 'text', 'text' => 'Version 2']]),
            ])->assertOk();
        }
        $this->assertSame(2, $free->fresh()->design_version);
        $this->assertTrue($freeCopy->fresh()->hasPresetUpdate());

        $this->post(route('admin.template-library.sync', $free))->assertSessionHas('success');
        $this->post(route('admin.template-library.sync', $premium))->assertSessionHas('success');

        $freeCopy->refresh();
        $this->assertSame('Version 2', $freeCopy->layout_json[0]['text']);
        $this->assertSame('tournament_templates/mine.jpg', $freeCopy->background_image, 'A background it paid for is kept');
        $this->assertFalse($freeCopy->hasPresetUpdate());

        $richCopy->refresh();
        $this->assertSame('Ours', $richCopy->layout_json[0]['text'], 'An edited copy is never overwritten silently');
        $this->assertTrue($richCopy->hasPresetUpdate());
        $this->actingAs($this->admin)->get(route('admin.tournaments.templates.index', $t))->assertSee('Update available');

        // The tournament takes the new version when it chooses to.
        $this->post(route('admin.tournaments.templates.use-preset-version', [$t, $richCopy]))->assertSessionHas('success');
        $this->assertSame('Version 2', $richCopy->fresh()->layout_json[0]['text']);
        $this->assertFalse($richCopy->fresh()->hasPresetUpdate());
    }

    #[Test]
    public function a_template_deleted_from_the_generate_page_answers_in_place(): void
    {
        $t = $this->tournament();
        $own = TournamentTemplate::create(['tournament_id' => $t->id, 'type' => 'flyer', 'name' => 'Mine', 'layout_json' => []]);

        $this->actingAs($this->admin)->deleteJson(route('admin.tournaments.templates.destroy', [$t, $own]))
            ->assertOk()->assertJson(['success' => true]);
        $this->assertNull(TournamentTemplate::find($own->id));
    }

    #[Test]
    public function the_live_preview_is_drawn_in_memory(): void
    {
        $preset = $this->preset('flyer', 'Light');
        Storage::disk('public')->delete($preset->background_image);
        $renderer = app(TemplateRenderService::class);
        $files = count(Storage::disk('public')->allFiles());

        $uri = $renderer->renderLivePreview($preset, $renderer->getSampleData('flyer', []));

        $this->assertStringStartsWith('data:image/jpeg;base64,', $uri);
        $this->assertSame($files, count(Storage::disk('public')->allFiles()), 'Nothing is written for a live preview');
    }

    #[Test]
    public function a_premium_preset_can_be_free_or_cheaper_for_one_organization(): void
    {
        $premium = $this->preset('match_poster', 'Gold', ['tier' => 'premium', 'price' => 40]);

        // Super Admin adds a discount on premium presets for one organization.
        $this->actingAs($this->super)->post(route('admin.credits.discounts.store'), [
            'name' => 'Club half price', 'type' => 'percent', 'value' => 50,
            'applies_to' => 'feature:premium_preset', 'organization_ids' => [$this->org->id],
        ])->assertSessionHas('success');

        // The picker shows each organization its own price.
        $mine = $this->tournament();
        $theirs = $this->tournament('open', $this->other);
        $this->assertSame(20, $this->library->pickerFor($this->admin, $mine)->firstWhere('id', $premium->id)->effective_price);
        $this->assertSame(40, $this->library->pickerFor($this->admin, $theirs)->firstWhere('id', $premium->id)->effective_price);

        // Charged at the discounted price, and the saving recorded.
        $this->credits->grant($this->org, 25, 'promo', 'test');
        $this->library->copyInto($mine, [$premium->id], $this->admin);
        $this->assertSame(5, $this->credits->balance($this->org->fresh()));
        $this->assertSame(1, \App\Models\Credits\DiscountRedemption::where('organization_id', $this->org->id)->count());

        // The other organization pays the full price.
        $this->credits->grant($this->other, 40, 'promo', 'test');
        $rivalAdmin = User::factory()->create(['organization_id' => $this->other->id])->assignRole('Admin');
        $this->library->copyInto($theirs, [$premium->id], $rivalAdmin);
        $this->assertSame(0, $this->credits->balance($this->other->fresh()));

        // 100% makes it free for that organization.
        \App\Models\Credits\Discount::query()->update(['value' => 100]);
        $again = $this->tournament();
        $this->assertSame(0, $this->library->premiumCost($this->org->id, 'open', [$premium->id], $again, $this->admin));
        $this->library->copyInto($again, [$premium->id], $this->admin);
        $this->assertSame(5, $this->credits->balance($this->org->fresh()));
        $this->assertSame(1, $again->templates()->count());
    }
}
