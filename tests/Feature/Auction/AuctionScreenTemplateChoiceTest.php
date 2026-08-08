<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\Auction;
use App\Models\AuctionTemplate;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Choosing which template each broadcast screen renders with.
 *
 * The wall and the ticker are two screens running side by side, so each carries its own
 * choice. Two things were wrong: `resolveFor()` read the WALL's column whatever type it was
 * asked for, so a ticker's explicit pick could never be honoured; and the Create wizard
 * offered no picker at all while `store()` happily validated one — so a new auction always
 * fell back to defaults however many templates existed.
 */
class AuctionScreenTemplateChoiceTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function template(Organization $org, string $type, array $attrs = []): AuctionTemplate
    {
        return AuctionTemplate::create(array_merge([
            'name' => ucfirst($type) . ' ' . uniqid(),
            'type' => $type,
            'organization_id' => $org->id,
            'canvas_width' => 1601,
            'canvas_height' => 910,
            'is_active' => true,
            'render_mode' => AuctionTemplate::RENDER_HTML,
            'html_body' => '<div class="strip">{player_name}</div>',
        ], $attrs));
    }

    #[Test]
    public function a_ticker_template_is_resolved_from_its_own_column(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $wall = $this->template($org, AuctionTemplate::TYPE_LIVE_DISPLAY, ['name' => 'Wall']);
        $ticker = $this->template($org, AuctionTemplate::TYPE_TICKER, ['name' => 'Strip']);

        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'auction_template_id' => $wall->id,
            'ticker_template_id' => $ticker->id,
        ]);

        // resolveFor() used to test the WALL's chosen id against type = 'ticker', always
        // miss, and silently fall through to the default.
        $this->assertSame(
            $ticker->id,
            AuctionTemplate::resolveFor($auction, AuctionTemplate::TYPE_TICKER)?->id
        );
        $this->assertSame(
            $wall->id,
            AuctionTemplate::resolveFor($auction, AuctionTemplate::TYPE_LIVE_DISPLAY)?->id
        );
    }

    #[Test]
    public function the_ticker_url_renders_the_chosen_template_with_a_csp(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $ticker = $this->template($org, AuctionTemplate::TYPE_TICKER, [
            'html_body' => '<div class="lower-third">{player_name}</div>',
            'html_css' => '.lower-third { color: lime; }',
        ]);

        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'ticker_template_id' => $ticker->id,
        ]);

        $response = $this->get(route('public.auction.ticker', $auction))->assertOk();

        $response->assertSee('lower-third', false)
            ->assertSee('.lower-third { color: lime; }', false);

        // Same protection the authored wall gets: nothing in admin-authored markup may run.
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("script-src 'self' 'nonce-", $csp);
    }

    #[Test]
    public function the_ticker_keeps_its_built_in_look_when_nothing_is_chosen(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);

        // Every existing auction is in this state, so the default path must be untouched.
        $response = $this->get(route('public.auction.ticker', $auction))->assertOk();

        $this->assertNull(
            $response->headers->get('Content-Security-Policy'),
            'our own ticker page must not carry the authored-markup CSP'
        );
    }

    #[Test]
    public function a_wall_template_cannot_be_used_as_the_ticker(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $wall = $this->template($org, AuctionTemplate::TYPE_LIVE_DISPLAY);

        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'ticker_template_id' => $wall->id,
        ]);

        // The type check still guards the explicit pick, so a mismatched id falls back
        // rather than rendering a 1601x910 card as a lower third.
        $this->assertNull(AuctionTemplate::resolveFor($auction, AuctionTemplate::TYPE_TICKER));
    }

    #[Test]
    public function both_wizards_offer_both_pickers(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $this->template($org, AuctionTemplate::TYPE_LIVE_DISPLAY, ['name' => 'Neon Wall']);
        $this->template($org, AuctionTemplate::TYPE_TICKER, ['name' => 'Neon Strip']);

        $operator = $this->makeAuctionOperator($org);

        foreach ([
            route('admin.auctions.edit', $auction),
            route('admin.auctions.create'),
        ] as $url) {
            $this->actingAs($operator)->get($url)->assertOk()
                ->assertSee('auction_template_id', false)
                ->assertSee('ticker_template_id', false)
                ->assertSee('Neon Wall')
                ->assertSee('Neon Strip')
                // Create was missing the Branding step entirely, even though store()
                // validated and saved every field on it.
                ->assertSee('Branding');
        }
    }

    #[Test]
    public function creating_an_auction_stores_both_template_choices(): void
    {
        $org = $this->makeOrganization();
        // store() refuses outright when the organization has auctions switched off.
        $org->update(['auction_enabled' => true]);
        $tournament = $this->makeTournament($org);
        $wall = $this->template($org, AuctionTemplate::TYPE_LIVE_DISPLAY);
        $ticker = $this->template($org, AuctionTemplate::TYPE_TICKER);

        // store() gates on auction.create, which auction.edit does not imply.
        $creator = $this->makeAuctionOperator($org, ['auction.create', 'auction.edit', 'auction.view']);

        $this->actingAs($creator)
            ->post(route('admin.auctions.store'), [
                'name' => 'Wizard Auction',
                'organization_id' => $org->id,
                'tournament_id' => $tournament->id,
                'status' => 'scheduled',
                'bid_type' => 'open',
                'base_price' => 100,
                'max_budget_per_team' => 1_000_000,
                'bid_timer_seconds' => 30,
                'bid_rules' => [['from' => 0, 'to' => 1_000_000, 'increment' => 100]],
                'start_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'end_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
                'auction_template_id' => $wall->id,
                'ticker_template_id' => $ticker->id,
            ])
            ->assertSessionHasNoErrors()
            // A 403 also has no session errors, so assert we were not simply refused.
            ->assertRedirect();

        $created = Auction::where('name', 'Wizard Auction')->firstOrFail();

        $this->assertSame($wall->id, $created->auction_template_id);
        $this->assertSame($ticker->id, $created->ticker_template_id, 'Create never sent this before');
    }

    #[Test]
    public function create_offers_the_squad_reserve_settings(): void
    {
        $org = $this->makeOrganization();

        /*
         * store() has always validated and saved both of these, but Create never offered
         * them — so every auction made through the wizard silently took the defaults (a
         * squad of 11, reserving the base price per place) and the reserve could only be
         * configured by going back in through Edit.
         */
        $this->actingAs($this->makeAuctionOperator($org))
            ->get(route('admin.auctions.create'))
            ->assertOk()
            ->assertSee('name="min_squad_size"', false)
            ->assertSee('name="min_price_per_player"', false)
            ->assertSee('Squad Reserve Rule');
    }

    #[Test]
    public function the_reserve_settings_are_stored_from_create(): void
    {
        $org = $this->makeOrganization();
        $org->update(['auction_enabled' => true]);
        $tournament = $this->makeTournament($org);

        $this->actingAs($this->makeAuctionOperator($org, ['auction.create', 'auction.edit', 'auction.view']))
            ->post(route('admin.auctions.store'), [
                'name' => 'Reserve Auction',
                'organization_id' => $org->id,
                'tournament_id' => $tournament->id,
                'status' => 'scheduled',
                'bid_type' => 'open',
                'base_price' => 100,
                'max_budget_per_team' => 50_000_000,
                'bid_timer_seconds' => 30,
                'bid_rules' => [['from' => 0, 'to' => 50_000_000, 'increment' => 100_000]],
                'start_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'end_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
                'min_squad_size' => 10,
                'min_price_per_player' => 1_000_000,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $created = \App\Models\Auction::where('name', 'Reserve Auction')->firstOrFail();

        $this->assertSame(10, (int) $created->min_squad_size);
        $this->assertSame(1_000_000.0, (float) $created->min_price_per_player);

        // And the rule the organizer just configured is what the floor enforces: a team with
        // ten places to fill must hold back nine of them.
        $team = $this->makeTeam($org, 'Alpha', $tournament);
        $pools = app(\App\Services\Auction\AuctionPoolService::class);

        $this->assertSame(9_000_000.0, $pools->reserveFor($created, $team->id));
        $this->assertSame(41_000_000.0, $pools->maxAllowedBid($created, $team->id));
    }

    #[Test]
    public function each_screen_can_ask_for_its_own_template(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);

        // One auction, two displays of different shapes.
        $projector = $this->template($org, AuctionTemplate::TYPE_LIVE_DISPLAY, [
            'name' => 'Projector 16x9',
            'html_body' => '<div class="wide">{player_name}</div>',
        ]);
        $portrait = $this->template($org, AuctionTemplate::TYPE_LIVE_DISPLAY, [
            'name' => 'Portrait LED',
            'html_body' => '<div class="tall">{player_name}</div>',
        ]);

        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'auction_template_id' => $projector->id,
        ]);

        // Without an override, the auction's stored pick.
        $this->get(route('public.auction.live', $auction))->assertOk()->assertSee('wide', false);

        // The portrait wall opens the same auction with its own layout.
        $this->get(route('public.auction.live', $auction) . '?template=' . $portrait->id)
            ->assertOk()
            ->assertSee('tall', false)
            ->assertDontSee('class="wide"', false);
    }

    #[Test]
    public function the_override_cannot_reach_another_organizations_template(): void
    {
        $mine = $this->makeOrganization('Mine');
        $theirs = $this->makeOrganization('Theirs');
        $tournament = $this->makeTournament($mine);

        $ours = $this->template($mine, AuctionTemplate::TYPE_LIVE_DISPLAY, [
            'name' => 'Ours',
            'html_body' => '<div class="ours">x</div>',
        ]);
        $foreign = $this->template($theirs, AuctionTemplate::TYPE_LIVE_DISPLAY, [
            'name' => 'Theirs',
            'html_body' => '<div class="stolen">x</div>',
        ]);

        $auction = $this->makeAuction($mine, [
            'tournament_id' => $tournament->id,
            'auction_template_id' => $ours->id,
        ]);

        /*
         * These pages are public and unauthenticated, so the id in the URL cannot be
         * trusted — it must belong to this auction, its organization, or the global set.
         * Anything else falls back rather than rendering someone else's artwork.
         */
        $this->get(route('public.auction.live', $auction) . '?template=' . $foreign->id)
            ->assertOk()
            ->assertDontSee('stolen', false)
            ->assertSee('ours', false);
    }

    #[Test]
    public function the_override_will_not_put_a_ticker_template_on_the_wall(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $ticker = $this->template($org, AuctionTemplate::TYPE_TICKER, [
            'html_body' => '<div class="strip">x</div>',
        ]);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);

        // Type still guards the override, so a lower-third cannot be rendered as the wall.
        $this->get(route('public.auction.live', $auction) . '?template=' . $ticker->id)
            ->assertOk()
            ->assertDontSee('class="strip"', false);
    }
}
