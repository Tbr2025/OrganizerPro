<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\Auction;
use App\Models\AuctionTemplate;
use App\Models\Organization;
use App\Services\Auction\TemplateTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * A template can now be authored as raw HTML instead of dragged elements.
 *
 * The screen is public and same-origin with the admin app, so most of what is asserted
 * here is about what CANNOT happen: no script from the authored markup may run, and no
 * value substituted into it may break out of its attribute.
 */
class AuctionHtmlTemplateTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function htmlTemplate(?Auction $auction, ?Organization $org, array $attrs = []): AuctionTemplate
    {
        return AuctionTemplate::create(array_merge([
            'name' => 'Neon Wall',
            'type' => 'live_display',
            'render_mode' => AuctionTemplate::RENDER_HTML,
            'html_body' => '<div class="lower-third s-{status}"><h1>{player_name}</h1></div>',
            'html_css' => '.lower-third { color: red; }',
            'auction_id' => $auction?->id,
            'organization_id' => $org?->id,
            'is_active' => true,
        ], $attrs));
    }

    #[Test]
    public function an_html_template_renders_instead_of_the_positioned_page(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $this->htmlTemplate($auction, $org);

        $this->get(route('public.auction.live', $auction))
            ->assertOk()
            ->assertSee('lower-third', false)
            ->assertSee('.lower-third { color: red; }', false)
            // The positioned page's canvas must not be anywhere near it.
            ->assertDontSee('card-container', false);
    }

    #[Test]
    public function a_positioned_template_still_renders_the_original_page(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $this->htmlTemplate($auction, $org, [
            'render_mode' => AuctionTemplate::RENDER_POSITIONED,
            'html_body' => null,
        ]);

        // The no-regression test: HTML mode is additive, not a replacement.
        $this->get(route('public.auction.live', $auction))
            ->assertOk()
            ->assertSee('card-container', false);
    }

    #[Test]
    public function the_html_screen_carries_a_nonce_csp_that_blocks_authored_scripts(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $this->htmlTemplate($auction, $org);

        $response = $this->get(route('public.auction.live', $auction))->assertOk();
        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp, 'the authored-markup page must carry a CSP');
        $this->assertStringContainsString("script-src 'self' 'nonce-", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("base-uri 'none'", $csp);
        $this->assertStringContainsString("form-action 'none'", $csp);

        // A nonce is worthless beside unsafe-inline: the browser would run anything.
        $scriptDirective = collect(explode(';', $csp))
            ->first(fn ($part) => str_contains($part, 'script-src'));
        $this->assertStringNotContainsString('unsafe-inline', (string) $scriptDirective);
    }

    #[Test]
    public function the_positioned_led_wall_is_not_given_the_csp(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);

        // No HTML template at all — the ordinary drag-positioned wall.
        $response = $this->get(route('public.auction.live', $auction))->assertOk();

        // The policy was briefly attached to this whole route, which blocked the CDN
        // Tailwind build, confetti, Pusher and Echo that this page depends on, plus all
        // of its inline scripts — so it rendered as unstyled blankness.
        $this->assertNull(
            $response->headers->get('Content-Security-Policy'),
            'the CSP belongs on admin-authored markup only, never on our own page'
        );
        $response->assertSee('cdn.tailwindcss.com', false);
    }

    #[Test]
    public function markup_that_could_execute_is_rejected_on_save(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $template = $this->htmlTemplate($auction, $org);
        $operator = $this->makeAuctionOperator($org);

        foreach ([
            '<script>alert(1)</script>',
            '<div onload="alert(1)"></div>',
            '<iframe src="//evil"></iframe>',
            '<a href="javascript:alert(1)">x</a>',
            '<form action="//evil"><input name="p"></form>',
        ] as $payload) {
            $this->actingAs($operator)
                ->put(route('admin.auction-templates.update', $template), [
                    'name' => 'Neon Wall',
                    'type' => 'live_display',
                    'render_mode' => 'html',
                    'canvas_width' => 1920,
                    'canvas_height' => 1080,
                    'html_body' => $payload,
                ])
                ->assertSessionHasErrors('html_body');
        }

        // Rejected, so the stored markup is untouched.
        $this->assertSame(
            '<div class="lower-third s-{status}"><h1>{player_name}</h1></div>',
            $template->fresh()->html_body
        );
    }

    #[Test]
    public function clean_markup_saves_and_keeps_one_step_of_undo(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $template = $this->htmlTemplate($auction, $org);
        $original = $template->html_body;

        $this->actingAs($this->makeAuctionOperator($org))
            ->put(route('admin.auction-templates.update', $template), [
                'name' => 'Neon Wall',
                'type' => 'live_display',
                'render_mode' => 'html',
                'canvas_width' => 1920,
                'canvas_height' => 1080,
                'html_body' => '<section>{current_bid}</section>',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $template->refresh();

        $this->assertSame('<section>{current_bid}</section>', $template->html_body);
        // Breaking the wall mid-auction should not be a one-way door.
        $this->assertSame($original, $template->html_body_previous);
    }

    #[Test]
    public function an_auctions_explicit_template_choice_wins(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);

        // A template bound to the auction, and a global default — both would otherwise
        // be picked ahead of an explicit choice.
        $this->htmlTemplate($auction, $org, ['name' => 'Bound', 'html_body' => '<p>bound</p>']);
        $this->htmlTemplate(null, null, ['name' => 'Default', 'is_default' => true, 'html_body' => '<p>default</p>']);
        $chosen = $this->htmlTemplate(null, $org, ['name' => 'Chosen', 'html_body' => '<p>chosen</p>']);

        $auction->update(['auction_template_id' => $chosen->id]);

        $this->assertSame($chosen->id, AuctionTemplate::resolveFor($auction->fresh())?->id);

        $this->get(route('public.auction.live', $auction))
            ->assertOk()
            ->assertSee('chosen', false)
            ->assertDontSee('bound', false);
    }

    #[Test]
    public function values_are_escaped_for_attribute_position_not_just_text(): void
    {
        // A player name arrives through PUBLIC tournament registration, so this payload
        // reaches the screen no matter how trustworthy the template's author is.
        $payload = '" onerror="fetch(\'//evil\')" x="';

        $escaped = TemplateTokenService::escape($payload);

        $this->assertStringNotContainsString('"', $escaped);
        $this->assertStringContainsString('&quot;', $escaped);
        $this->assertSame('&lt;img&gt;', TemplateTokenService::escape('<img>'));
        $this->assertSame('&#039;', TemplateTokenService::escape("'"));
    }

    #[Test]
    public function every_documented_token_is_a_real_one(): void
    {
        $names = TemplateTokenService::tokenNames();

        $this->assertContains('player_name', $names);
        $this->assertContains('current_bid', $names);
        $this->assertContains('status', $names);
        // The cheat-sheet is generated from this list, so duplicates would render twice.
        $this->assertSame(count($names), count(array_unique($names)));
    }

    #[Test]
    public function the_deny_list_passes_ordinary_markup(): void
    {
        $this->assertNull(TemplateTokenService::findUnsafeMarkup(
            '<div class="a"><img src="{player_image}" alt=""><h1>{player_name}</h1></div>'
        ));
        $this->assertNull(TemplateTokenService::findUnsafeMarkup(null));
        // "onload" as part of a word must not trip the inline-handler pattern.
        $this->assertNull(TemplateTokenService::findUnsafeMarkup('<div class="season">x</div>'));
    }
}
