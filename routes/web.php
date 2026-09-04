<?php

declare(strict_types=1);

use App\Http\Controllers\Backend\ActionLogController;
use App\Http\Controllers\Backend\EmailLogController;
use App\Http\Controllers\Backend\ActualTeamController;
use App\Http\Controllers\Backend\AdminNotificationController;
use App\Http\Controllers\Backend\AppreciationController;
use App\Http\Controllers\Backend\AuctionAdminController;
use App\Http\Controllers\Backend\AuctionAllotmentController;
use App\Http\Controllers\Backend\AuctionPoolController;
use App\Http\Controllers\Backend\AuctionBiddingController;
use App\Http\Controllers\Backend\FastAuctionScreenController;
use App\Http\Controllers\FastAuctionPublicController;
use App\Http\Controllers\Backend\AuctionTemplateController;
use App\Http\Controllers\Backend\AuctionController;
use App\Http\Controllers\Backend\AuctionOrganizerController;
use App\Http\Controllers\Backend\Auth\ScreenshotGeneratorLoginController;
use App\Http\Controllers\Backend\BackupController;
use App\Http\Controllers\Backend\EmailPreviewController;
use App\Http\Controllers\Backend\BallController;
use App\Http\Controllers\Backend\ClosedBidController;
use App\Http\Controllers\Backend\ClosedBidRoundController;
use App\Http\Controllers\Backend\DashboardController;
use App\Http\Controllers\Backend\ImageTemplateController;
use App\Http\Controllers\Backend\LocaleController;
use App\Http\Controllers\Backend\MatchAppreciationController;
use App\Http\Controllers\Backend\MatchReportController;
use App\Http\Controllers\Backend\MatchesController;
use App\Http\Controllers\Backend\ModulesController;
use App\Http\Controllers\Backend\OrganizationController;
use App\Http\Controllers\Backend\ZoneController;
use App\Http\Controllers\Backend\PermissionsController;
use App\Http\Controllers\Backend\OrganizerController;
use App\Http\Controllers\Backend\PlayerController;
use App\Http\Controllers\Backend\PlayerProfileController;
use App\Http\Controllers\Backend\PostsController;
use App\Http\Controllers\Backend\ProfilesController;
use App\Http\Controllers\Backend\RolesController;
use App\Http\Controllers\Backend\ScorecardController;
use App\Http\Controllers\Backend\MailTestController;
use App\Http\Controllers\Backend\SettingsController;
use App\Http\Controllers\Backend\TeamController;
use App\Http\Controllers\Backend\TeamPlayerController;
use App\Http\Controllers\Backend\TermsController;
use App\Http\Controllers\Backend\TournamentController;
use App\Http\Controllers\Backend\TranslationController;
use App\Http\Controllers\Backend\UserLoginAsController;
use App\Http\Controllers\Backend\UsersController;
use App\Http\Controllers\Backend\PlayerVerificationController;
use App\Http\Controllers\Backend\PlayerDashboardController as BackendPlayerDashboardController;
use App\Http\Controllers\Backend\TeamManagerController;
use App\Http\Controllers\PublicAuctionController;
use App\Http\Controllers\PublicTeamJoinController;
use App\Http\Controllers\PublicPlayerController;
use App\Http\Controllers\PublicTournamentRegistrationController;
use App\Http\Controllers\Backend\Tournament\TournamentSettingsController;
use App\Http\Controllers\Backend\Tournament\TournamentRegistrationController;
use App\Http\Controllers\Backend\Tournament\TournamentGroupController;
use App\Http\Controllers\Backend\Tournament\TournamentFixtureController;
use App\Http\Controllers\Backend\Tournament\TournamentTemplateController;
use App\Http\Controllers\Backend\Tournament\TournamentBannerController;
use App\Http\Controllers\Backend\Tournament\TournamentCalendarController;
use App\Http\Controllers\Backend\Tournament\TournamentPlayerHistoryController;
use App\Http\Controllers\Backend\Tournament\MatchSummaryController;
use App\Http\Controllers\Backend\Tournament\AwardTemplateController;
use App\Http\Controllers\Backend\GroundController;
use App\Http\Controllers\Backend\MatchResultController;
use App\Http\Controllers\Backend\PointTableController;
use App\Http\Controllers\Public\BlogController;
use App\Http\Controllers\Public\TournamentPublicController;
use App\Http\Controllers\Public\RegistrationController as PublicRegistrationController;
use App\Http\Controllers\Public\MatchPublicController;
use App\Http\Controllers\Public\TournamentsListController;
use App\Http\Controllers\Public\PlayerDashboardController;
use App\Http\Controllers\Backend\PlayerImageProcessController;

/*
 * `{auction}` is always a numeric id.
 *
 * Route::resource('auctions') registers `show` as GET /auctions/{auction}, and that happily
 * matches /auctions/auctioned-players — the literal is taken as an id, model binding finds no
 * auction called "auctioned-players", and the page 404s. The file already carries a comment about
 * this trap catching /auctions/create; it caught the next literal route too, which is what a
 * comment cannot prevent.
 *
 * Constraining the parameter fixes every one of them at once, including the ones nobody has added
 * yet. Nothing is lost: an auction is bound by id everywhere in this application.
 */
Route::pattern('auction', '[0-9]+');
use App\Models\Organization;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', [\App\Http\Controllers\Public\LandingPageController::class, 'index'])->name('index');
Route::get('/home', 'HomeController@index')->name('home');
Route::get('/pricing', [\App\Http\Controllers\Public\PricingController::class, 'index'])->name('public.pricing');

/**
 * Admin routes.
 */

Route::get('admin/players/sample-csv', [PlayerController::class, 'downloadSampleCsv'])->name('players.sample');

Route::get('/player-dashboard', [BackendPlayerDashboardController::class, 'index'])
    ->name('player-dashboard')
    ->middleware('auth');

Route::group(['prefix' => 'profileplayers', 'as' => 'profileplayers.', 'middleware' => ['auth']], function () {
    Route::get('/edit', [PlayerProfileController::class, 'edit'])->name('edit');
    Route::put('/edit', [PlayerProfileController::class, 'update'])->name('update');
});

// Player image AJAX processing (authenticated)
Route::post('/player-image/process', [PlayerImageProcessController::class, 'process'])
    ->middleware('auth')
    ->name('player-image.process');
Route::get('/player-image/status', [PlayerImageProcessController::class, 'status'])
    ->middleware('auth')
    ->name('player-image.status');

// Player image AJAX processing (public, rate-limited)
Route::post('/public/player-image/process', [PlayerImageProcessController::class, 'process'])
    ->middleware('throttle:10,1')
    ->name('public.player-image.process');
Route::get('/public/player-image/status', [PlayerImageProcessController::class, 'status'])
    ->middleware('throttle:30,1')
    ->name('public.player-image.status');

// --- Main Admin Route Group for general pages ---
/*
 * `auction.operator` sits on the whole group, not only on the live panel.
 *
 * It reads broader than it is: the middleware stands aside for anybody not named on an auction,
 * and again for any route with no {auction} in it — which is most of this group. What it catches is
 * the rest of the auction surface, and that surface is wide: the show page, the pools, the reports,
 * the email outbox, the player cards, the allotment screen. Guarding only the panel left an
 * auctioneer able to open every OTHER page of an auction they were never given.
 */
Route::group(['prefix' => 'admin', 'as' => 'admin.', 'middleware' => ['auth', 'redirect.team-manager', 'organizer.access', 'auction.operator']], function () {

    /*
     * Auction Administration (CRUD for auctions).
     *
     * Split by what each verb actually does. The whole resource used to carry NO permission
     * gate at all — `organizer.access` only scopes a pure Organizer to their own tournaments,
     * it does not ask whether the user should be here — so any authenticated non-Team-Manager
     * could open the configuration wizard and rewrite a live auction's rules. That was
     * survivable while only Admins had a way in; an Auctioneer who can reach the panel makes
     * it a real hole.
     *
     * Reading the list and a single auction is `auction.view` (a Team Manager holds it, and is
     * separately redirected). Creating, editing and deleting are `auction.edit` — deliberately
     * NOT granted to the Auctioneer, whose job is to call the lots, not to change the rules
     * halfway through.
     */
    /*
     * The write verbs are registered FIRST on purpose. `show` is GET /auctions/{auction},
     * which happily matches /auctions/create — so with the read resource declared first,
     * the create form resolved to show() looking for an auction called "create" and 404'd.
     * A single Route::resource() orders these correctly for you; two of them do not.
     */
    Route::resource('auctions', AuctionAdminController::class)
        ->except(['index', 'show'])
        ->middleware('permission:auction.edit');

    Route::resource('auctions', AuctionAdminController::class)
        ->only(['index', 'show'])
        ->middleware('permission:auction.view');

    // Auction pools, lots & per-team budgets are managed in the auction Edit wizard.
    // Pool control actions on the auction Show page (reorder, re-draw lots, merge retained).
    Route::post('/auctions/{auction}/pools/reorder', [AuctionAdminController::class, 'reorderPools'])->name('auctions.pools.reorder');
    Route::post('/auctions/{auction}/pools/{pool}/redraw', [AuctionAdminController::class, 'redrawPool'])->name('auctions.pools.redraw');
    Route::post('/auctions/{auction}/pools/{pool}/merge-retained', [AuctionAdminController::class, 'mergeRetained'])->name('auctions.pools.merge-retained');

    // Dedicated Pool management (separate from the create/edit wizard).
    Route::get('/auctions/{auction}/pools', [AuctionPoolController::class, 'index'])->name('auctions.pools.index');
    Route::post('/auctions/{auction}/pools/assign', [AuctionPoolController::class, 'assign'])->name('auctions.pools.assign');
    Route::post('/auctions/{auction}/pools/unassign', [AuctionPoolController::class, 'unassign'])->name('auctions.pools.unassign');
    Route::post('/auctions/{auction}/pools/bulk-unassign', [AuctionPoolController::class, 'bulkUnassign'])->name('auctions.pools.bulk-unassign');
    Route::post('/auctions/{auction}/pools/auto-assign', [AuctionPoolController::class, 'autoAssign'])->name('auctions.pools.auto-assign');
    // Undo the last auto-assign run — it sweeps every unassigned player at once, so a
    // mistaken run needs one way back rather than manual unpicking.
    Route::post('/auctions/{auction}/pools/auto-assign/revert', [AuctionPoolController::class, 'revertAutoAssign'])->name('auctions.pools.auto-assign.revert');
    Route::post('/auctions/{auction}/pools', [AuctionPoolController::class, 'store'])->name('auctions.pools.store');
    Route::put('/auctions/{auction}/pools/{pool}', [AuctionPoolController::class, 'update'])->name('auctions.pools.update');
    // Declared before the {pool} route: with DELETE /pools/{pool} first, a request to
    // /pools/bulk would bind "bulk" as a pool id and 404 instead of reaching this.
    Route::delete('/auctions/{auction}/pools/bulk', [AuctionPoolController::class, 'bulkDestroy'])->name('auctions.pools.bulk-destroy');
    Route::delete('/auctions/{auction}/pools/{pool}', [AuctionPoolController::class, 'destroy'])->name('auctions.pools.destroy');
    Route::get('/auctions/{auction}/report', [AuctionAdminController::class, 'report'])->name('auctions.report');
    /*
     * A player's wall card as a PNG, and the whole pool as a zip. `?result=1` keeps the SOLD
     * stamp and price on the card; without it the card is the player before the hammer fell.
     */
    Route::get('/auctions/{auction}/cards', [AuctionAdminController::class, 'downloadPlayerCards'])->name('auctions.cards');

    /*
     * Registered BEFORE `cards/{auctionPlayer}`, or the wildcard matches the literal segment
     * "export" and route-model binding 404s looking for an auction player with that id.
     */
    Route::post('/auctions/{auction}/cards/export', [AuctionAdminController::class, 'startCardExport'])->name('auctions.cards.export');
    Route::get('/auctions/{auction}/cards/export/{token}', [AuctionAdminController::class, 'cardExportProgress'])->name('auctions.cards.export.progress');
    Route::get('/auctions/{auction}/cards/export/{token}/download', [AuctionAdminController::class, 'cardExportDownload'])->name('auctions.cards.export.download');
    Route::post('/auctions/{auction}/cards/export/{token}/cancel', [AuctionAdminController::class, 'cancelCardExport'])->name('auctions.cards.export.cancel');
    Route::delete('/auctions/{auction}/cards/export/{token}', [AuctionAdminController::class, 'deleteCardExport'])->name('auctions.cards.export.destroy');
    // Every archive this auction has produced — see AuctionAdminController::cardExports().
    Route::get('/auctions/{auction}/card-exports', [AuctionAdminController::class, 'cardExports'])->name('auctions.card-exports');
    // One player's poster, rendered on the spot — no job, no zip. See playerPoster().
    Route::get('/auctions/{auction}/poster/{auctionPlayer}', [AuctionAdminController::class, 'playerPoster'])->name('auctions.player-poster');

    Route::get('/auctions/{auction}/cards/{auctionPlayer}', [AuctionAdminController::class, 'downloadPlayerCard'])->name('auctions.cards.player');
    // Broadcast screens picker — the ticker and LED wall had no home in the menu.
    Route::get('/auctions-broadcast', [AuctionAdminController::class, 'liveTickerIndex'])->name('auctions.broadcast');
    // Release held player emails by hand — the safety net when no queue worker is running.
    Route::post('/auctions/{auction}/emails/flush', [AuctionAdminController::class, 'flushEmails'])->name('auctions.emails.flush');
    // Read back what was sent, held, or suppressed — test mode is only useful if visible.
    Route::get('/auctions/{auction}/emails', [AuctionAdminController::class, 'emailOutbox'])->name('auctions.emails.index');
    Route::post('/auctions/{auction}/emails/retry', [AuctionAdminController::class, 'retryEmails'])->name('auctions.emails.retry');

    // Housekeeping and inspection for the outbox. Clearing only ever removes rows that
    // have already resolved; preview renders without sending.
    Route::post('/auctions/{auction}/emails/clear', [AuctionAdminController::class, 'clearEmailLog'])->name('auctions.emails.clear');
    Route::get('/auctions/{auction}/emails/{email}/preview', [AuctionAdminController::class, 'previewEmail'])->name('auctions.emails.preview');

    // Final allotment: place unsold players with teams that are short of a squad.
    Route::get('/auctions/{auction}/allotment', [AuctionAllotmentController::class, 'index'])->name('auctions.allotment');
    Route::post('/auctions/{auction}/allotment/allot', [AuctionAllotmentController::class, 'allot'])->name('auctions.allotment.allot');
    Route::get('/auctions/{auction}/allotment/preview', [AuctionAllotmentController::class, 'preview'])->name('auctions.allotment.preview');
    Route::post('/auctions/{auction}/allotment/auto', [AuctionAllotmentController::class, 'autoDistribute'])->name('auctions.allotment.auto');
    Route::delete('/auctions/{auction}/branding-image', [AuctionAdminController::class, 'removeBrandingImage'])->name('auctions.branding.remove');

    Route::post('/auctions/{auction}/players/{player}', [AuctionAdminController::class, 'addPlayerToPool'])->name('auctions.players.add');

    // Route for removing a player from the pool via AJAX
    Route::delete('/auctions/{auction}/players/{player}', [AuctionAdminController::class, 'removePlayerFromPool'])->name('auctions.players.remove');

    Route::post('/auction/{auction}/player/{player}/final-price', [ClosedBidController::class, 'updateFinalPrice'])
        ->name('auction.player.final-price');
    // Closed bids. These routes carried no permission gate at all, and `organizer.access`
    // only constrains users holding the Organizer role — and only on route-bound models,
    // of which these have none. Spatie reads `|` as OR: a bare auction.closed-bids check
    // would lock out roles that legitimately hold only auction.view.
    Route::get('/auctions-closed-bids', [ClosedBidController::class, 'index'])
        ->middleware('permission:auction.closed-bids|auction.view')
        ->name('auctions.closed-bids');

    Route::get('/auctions-closed-bids/fetch', [ClosedBidController::class, 'fetchClosedBids'])
        ->middleware('permission:auction.closed-bids|auction.view')
        ->name('auctions.closed-bids.fetch');

    // Route::post('/auctions-closed-bids/{id}/update-final-price', [ClosedBidController::class, 'updateFinalPrice']);

    Route::delete('/auctions/{auction}/clear-pool', [AuctionAdminController::class, 'clearPool'])->name('auctions.clear-pool');
    Route::delete('/auctions/remove-player/{auctionPlayer}', [AuctionAdminController::class, 'removePlayer'])->name('auctions.remove-player');
    Route::post('/auctions/assign-player', [AuctionAdminController::class, 'assignPlayer'])->name('auctions.assign-player');
    // routes/web.php
    Route::get('/auctions/{auction}/latest-players', [AuctionAdminController::class, 'fetchPlayers'])
        ->name('admin.auctions.latest-players');

    // routes/web.php
    Route::post('/auction/{auction}/player/{player}/toggle-status', [AuctionAdminController::class, 'toggleStatus'])
        ->name('auction.player.toggle-status');

    /*
     * Entering and unwinding bids: `auction.control`, the same permission the organizer
     * panel's own POSTs require.
     *
     * These three carried no gate. The panel's team chips post straight to add-bid rather than
     * through its sendCommand() helper, so an observer's screen was one fetch away from
     * raising a price — the client-side guard on the button would have been the only thing
     * stopping it, and a guard you can skip with the console is not a guard.
     */
    Route::post('/auctions/add-bid', [AuctionAdminController::class, 'addBid'])
        ->middleware(['permission:auction.control|auction.edit', 'auction.operator:control'])
        ->name('auctions.players.addBid');
    // Lift the leading team off a bid without changing the price — a raise recorded against
    // the wrong team. See AuctionAdminController::clearBidTeam().
    /*
     * Sponsor artwork for an auction's public screens. Under the auction rather than the
     * tournament: sponsorship is sold per event, and one tournament can run an auction for one
     * sponsor and a later one for another.
     */
    /*
     * Who runs an auction. Per auction rather than per role, because a permission cannot say
     * WHICH auction — see EnsureAuctionOperator.
     */
    Route::get('/auctions/{auction}/operators', [\App\Http\Controllers\Backend\AuctionOperatorController::class, 'index'])
        ->name('auctions.operators.index');
    Route::post('/auctions/{auction}/operators', [\App\Http\Controllers\Backend\AuctionOperatorController::class, 'store'])
        ->name('auctions.operators.store');
    Route::delete('/auctions/{auction}/operators/{operator}', [\App\Http\Controllers\Backend\AuctionOperatorController::class, 'destroy'])
        ->name('auctions.operators.destroy');

    /*
     * What the wall shows while bidding is private — logo and wording.
     *
     * `auction.edit` like the ads screen beside it: both are event setup rather than anything
     * done during a lot, and the operator abilities deliberately do not reach configuration.
     */
    Route::get('/auctions/{auction}/sealed-bid-settings', [\App\Http\Controllers\Backend\AuctionSealedScreenController::class, 'index'])
        ->name('auctions.sealed-screen.index');
    Route::post('/auctions/{auction}/sealed-bid-settings', [\App\Http\Controllers\Backend\AuctionSealedScreenController::class, 'update'])
        ->name('auctions.sealed-screen.update');
    Route::delete('/auctions/{auction}/sealed-bid-settings/logo', [\App\Http\Controllers\Backend\AuctionSealedScreenController::class, 'removeLogo'])
        ->name('auctions.sealed-screen.logo.destroy');

    /*
     * The path this was first built at, kept as a redirect.
     *
     * It shipped at /sealed-screen and the address that was actually asked for is
     * /sealed-bid-settings. A bookmark or a link already sent to somebody should not 404 over a
     * name I chose and did not mention.
     */
    Route::get('/auctions/{auction}/sealed-screen', fn (\App\Models\Auction $auction) => redirect()
        ->route('admin.auctions.sealed-screen.index', $auction));

    Route::get('/auctions/{auction}/ads', [\App\Http\Controllers\Backend\AuctionAdController::class, 'index'])
        ->name('auctions.ads.index');
    Route::post('/auctions/{auction}/ads', [\App\Http\Controllers\Backend\AuctionAdController::class, 'store'])
        ->name('auctions.ads.store');
    Route::put('/auctions/{auction}/ads/{ad}', [\App\Http\Controllers\Backend\AuctionAdController::class, 'update'])
        ->name('auctions.ads.update');
    Route::delete('/auctions/{auction}/ads/{ad}', [\App\Http\Controllers\Backend\AuctionAdController::class, 'destroy'])
        ->name('auctions.ads.destroy');

    // Every auctioned player across auctions, with sold/unsold/team/upcoming filters — the
    // questions a pool-scoped list cannot answer. See AuctionAdminController::auctionedPlayers().
    Route::get('/auctions/auctioned-players', [AuctionAdminController::class, 'auctionedPlayers'])
        ->name('auctions.auctioned-players');
    Route::post('/auctions/clear-bid-team', [AuctionAdminController::class, 'clearBidTeam'])
        ->name('auctions.players.clearBidTeam');
    Route::post('/auctions/decrease-bid', [AuctionAdminController::class, 'decreaseBid'])
        ->middleware(['permission:auction.control|auction.edit', 'auction.operator:control'])
        ->name('auctions.players.decreaseBid');

    Route::post('/auctions/close-bid', [AuctionAdminController::class, 'closeBid'])
        ->middleware(['permission:auction.control|auction.edit', 'auction.operator:control']);

    // Auction Templates (LED wall display configuration).
    // These carried no permission gate at all, and `organizer.access` waves through
    // anyone who is not a pure Organizer — so any logged-in account could edit and
    // delete every organization's templates.
    // `create` is registered before `{auctionTemplate}`, or the wildcard swallows it
    // and route-model binding 404s on the literal string "create".
    Route::middleware('permission:auction.edit')->group(function () {
        Route::get('auction-templates/create', [AuctionTemplateController::class, 'create'])->name('auction-templates.create');
        Route::post('auction-templates', [AuctionTemplateController::class, 'store'])->name('auction-templates.store');
        Route::get('auction-templates/{auctionTemplate}/edit', [AuctionTemplateController::class, 'edit'])->name('auction-templates.edit');
        Route::match(['put', 'patch'], 'auction-templates/{auctionTemplate}', [AuctionTemplateController::class, 'update'])->name('auction-templates.update');
        Route::delete('auction-templates/{auctionTemplate}', [AuctionTemplateController::class, 'destroy'])->name('auction-templates.destroy');
        Route::post('auction-templates/{auctionTemplate}/set-default', [AuctionTemplateController::class, 'setDefault'])->name('auction-templates.set-default');
    });

    /*
     * Rehearsing the auction on a venue's connection. Gated on auction.view because it is a
     * testing aid for whoever runs the auction, and it can only ever throttle their own
     * browser — see SimulateBandwidth.
     */
    Route::middleware('permission:auction.view')->group(function () {
        Route::get('network-test', [\App\Http\Controllers\Backend\NetworkTestController::class, 'index'])->name('network-test.index');
        Route::post('network-test', [\App\Http\Controllers\Backend\NetworkTestController::class, 'update'])->name('network-test.update');
    });

    Route::middleware('permission:auction.view')->group(function () {
        Route::get('auction-templates', [AuctionTemplateController::class, 'index'])->name('auction-templates.index');
        Route::get('auction-templates/{auctionTemplate}/preview', [AuctionTemplateController::class, 'preview'])
            ->name('auction-templates.preview');
    });
});

// =====================================================================
// LIVE AUCTION ROUTES (Kept separate from the main admin group)
// =====================================================================

// --- Organizer Control Panel Routes ---
// URL Prefix: /admin/organizer/auction/{auction}
// Name Prefix: admin.auction.organizer.
//
// Reaching the panel is `auction.edit` OR `auction.observe` — never `auction.view`, which a
// Team Manager holds and which must never open the control panel. Spatie reads `|` as OR.
//
// CHANGING the auction is a second, separate permission: every POST below sits in a nested
// group requiring `auction.control`. That split is what makes an Auctioneer possible — the
// person calling the lots in the room needs the whole board in front of them and must not be
// able to sell, pass, skip or undo anything. Enforced on the route, not by hiding buttons:
// a hidden button is not a permission.
//
// `organizer.access` additionally scopes a pure Organizer to their own tournaments, so one
// org cannot start, sell in, or end another org's auction.
/*
 * `auction.operator` narrows an auctioneer to the auction they were actually given.
 *
 * A permission says WHAT somebody may do; it cannot say WHICH auction, so without this a person
 * trusted to call one evening's lots could open any auction in the organization. It runs after
 * the permission check and only ever narrows — reaching a route already proves the permission,
 * and this asks the second question. Admins, organizers and superadmins pass through: they hand
 * these rows out, and narrowing them by an absent row locks the people who set an event up out
 * of it. See EnsureAuctionOperator.
 */
Route::middleware(['auth', 'permission:auction.edit|auction.observe', 'organizer.access', 'auction.operator'])
    ->prefix('admin/organizer/auction/{auction}')
    ->name('admin.auction.organizer.')
    ->group(function () {

        // **FIX**: Added route to SHOW the panel page
        Route::get('/panel', [AuctionOrganizerController::class, 'showPanel'])->name('panel');
        /*
         * Put the sold board up on the wall and the ticker, or take it down.
         *
         * This carried no guard at all — not a permission, not an ability — so anybody who could
         * reach the panel could change what a hall full of people was looking at. `screens` is its
         * own ability for exactly this reason: running the projector is a job, and it is not the
         * same job as calling the lots.
         */
        Route::post('/api/sold-board', [AuctionOrganizerController::class, 'toggleSoldBoard'])
            ->middleware(['permission:auction.control|auction.edit', 'auction.operator:screens'])
            ->name('api.sold-board');
        /*
         * The offline panel is a control surface by definition — its entire purpose is
         * entering bids by hand — so unlike the main panel it is not observable. An
         * auctioneer holding only `auction.observe` is kept out at the door rather than shown
         * a page of bid entry that would refuse every keystroke.
         */
        Route::get('/offline-panel', [AuctionOrganizerController::class, 'showOfflinePanel'])
            ->middleware(['permission:auction.control|auction.edit', 'auction.operator:control'])
            ->name('offline-panel');

        /*
         * Fast Auction's panel. Observe-or-edit, like the classic panel beside it — deliberately
         * NOT auction.control, because an auctioneer holding only `auction.observe` must be able
         * to watch. The write buttons are hidden for them by the ability check in the boot blob,
         * and the endpoints they post to carry their own guards regardless.
         */
        Route::get('/fast-panel', [FastAuctionScreenController::class, 'organizerPanel'])
            ->name('fast-panel');

        Route::prefix('api')->name('api.')->group(function () {
            Route::get('/poll-state', [AuctionOrganizerController::class, 'pollState'])->name('poll-state');
            // The same state, trimmed for the wire. pollState() is untouched.
            Route::get('/fast-state', [FastAuctionScreenController::class, 'fastState'])->name('fast-state');
            Route::post('/start', [AuctionOrganizerController::class, 'startAuction'])->name('start')->middleware(['permission:auction.control|auction.edit', 'auction.operator:control']);
            Route::post('/end', [AuctionOrganizerController::class, 'endAuction'])->name('end')->middleware(['permission:auction.control|auction.edit', 'auction.operator:sell']);
            Route::post('/restart', [AuctionOrganizerController::class, 'restartAuction'])->name('restart')->middleware(['permission:auction.control|auction.edit', 'auction.operator:sell']);
            Route::post('/toggle-pause', [AuctionOrganizerController::class, 'togglePause'])->name('toggle-pause')->middleware(['permission:auction.control|auction.edit', 'auction.operator:control']);
            Route::post('/player-on-bid', [AuctionOrganizerController::class, 'putPlayerOnBid'])->name('player.onbid')->middleware(['permission:auction.control|auction.edit', 'auction.operator:control']);
            // On demand, not on the 2s poll: a roster per team would multiply its cost.
            Route::get('/team/{team}/squad', [AuctionOrganizerController::class, 'teamSquad'])->name('team.squad');
            Route::post('/sell-player', [AuctionOrganizerController::class, 'sellPlayer'])->name('player.sell')->middleware(['permission:auction.control|auction.edit', 'auction.operator:sell']);
            Route::post('/pass-player', [AuctionOrganizerController::class, 'passPlayer'])->name('player.pass')->middleware(['permission:auction.control|auction.edit', 'auction.operator:sell']);
            Route::post('/sell-to-team', [AuctionOrganizerController::class, 'sellToTeam'])->name('player.sell-to-team')->middleware(['permission:auction.control|auction.edit', 'auction.operator:sell']);
            Route::post('/close-bidding', [AuctionOrganizerController::class, 'closeBidding'])->name('player.close-bidding')->middleware(['permission:auction.control|auction.edit', 'auction.operator:control']);
            Route::get('/sealed-bids', [AuctionOrganizerController::class, 'fetchSealedBids'])->name('sealed-bids');

            // Rescue hatch: the whole auction as a spreadsheet, for when something goes
            // wrong in the hall and the state has to come out now. Read-only.
            Route::get('/export', [AuctionOrganizerController::class, 'exportSnapshot'])->name('export');

            // Sealed (closed) rounds. The group already carries auth + auction.edit +
            // organizer.access, and {auction} is model-bound so a pure Organizer is
            // confined to their own tournaments.
            Route::prefix('closed-bid')->name('closed-bid.')->group(function () {
                Route::get('/state', [ClosedBidRoundController::class, 'state'])->name('state');
                // The organizer's yes to crossing the sealed threshold. There is no
                // matching "no" route: no is selling to the leading team.
                Route::post('/confirm-threshold', [ClosedBidRoundController::class, 'confirmThreshold'])->name('confirm-threshold')->middleware(['permission:auction.control|auction.edit', 'auction.operator:control']);
                Route::post('/open-entry', [ClosedBidRoundController::class, 'openEntry'])->name('open-entry')->middleware(['permission:auction.control|auction.edit', 'auction.operator:control']);
                // Back out of the invite list, before the round starts.
                Route::post('/reopen-selection', [ClosedBidRoundController::class, 'reopenSelection'])->name('reopen-selection')->middleware(['permission:auction.control|auction.edit', 'auction.operator:control']);
                // Time up holds the round; this is how the room is given longer.
                Route::post('/extend-timer', [ClosedBidRoundController::class, 'extendTimer'])->name('extend-timer')->middleware(['permission:auction.control|auction.edit', 'auction.operator:control']);
                Route::post('/start', [ClosedBidRoundController::class, 'start'])->name('start')->middleware(['permission:auction.control|auction.edit', 'auction.operator:control']);
                Route::post('/lock', [ClosedBidRoundController::class, 'lock'])->name('lock')->middleware(['permission:auction.control|auction.edit', 'auction.operator:control']);
                Route::post('/award', [ClosedBidRoundController::class, 'award'])->name('award')->middleware(['permission:auction.control|auction.edit', 'auction.operator:control']);
                Route::post('/start-rebid', [ClosedBidRoundController::class, 'startRebid'])->name('start-rebid')->middleware(['permission:auction.control|auction.edit', 'auction.operator:control']);
                Route::post('/lot', [ClosedBidRoundController::class, 'drawLot'])->name('lot')->middleware(['permission:auction.control|auction.edit', 'auction.operator:control']);
                Route::post('/resolve-manual', [ClosedBidRoundController::class, 'resolveManual'])->name('resolve-manual')->middleware(['permission:auction.control|auction.edit', 'auction.operator:control']);
                Route::post('/no-entries-decision', [ClosedBidRoundController::class, 'noEntriesDecision'])->name('no-entries-decision')->middleware(['permission:auction.control|auction.edit', 'auction.operator:control']);

                // {entry} is NOT covered by EnsureOrganizerCanAccess, so each of these
                // verifies the entry belongs to this auction before doing anything.
                Route::post('/entries/{entry}/adjust', [ClosedBidRoundController::class, 'adjustEntry'])->name('entries.adjust')->middleware(['permission:auction.control|auction.edit', 'auction.operator:control']);
                Route::post('/entries/{entry}/withdraw', [ClosedBidRoundController::class, 'withdrawEntry'])->name('entries.withdraw')->middleware(['permission:auction.control|auction.edit', 'auction.operator:control']);
                Route::post('/entries/{entry}/reinstate', [ClosedBidRoundController::class, 'reinstateEntry'])->name('entries.reinstate')->middleware(['permission:auction.control|auction.edit', 'auction.operator:control']);
            });
            Route::post('/switch-mode', [AuctionOrganizerController::class, 'switchMode'])->name('switch-mode')->middleware(['permission:auction.control|auction.edit', 'auction.operator:control']);
            Route::post('/switch-bid-type', [AuctionOrganizerController::class, 'switchBidType'])->name('switch-bid-type')->middleware(['permission:auction.control|auction.edit', 'auction.operator:control']);
            Route::get('/all-players', [AuctionOrganizerController::class, 'allPlayers'])->name('all-players');
            Route::post('/re-bid-player', [AuctionOrganizerController::class, 'rebidPlayer'])->name('player.re-bid')->middleware(['permission:auction.control|auction.edit', 'auction.operator:control']);
            Route::post('/re-auction-player', [AuctionOrganizerController::class, 'reAuctionPlayer'])->name('player.re-auction')->middleware(['permission:auction.control|auction.edit', 'auction.operator:control']);
            Route::post('/skip-player', [AuctionOrganizerController::class, 'skipPlayer'])->name('player.skip')->middleware(['permission:auction.control|auction.edit', 'auction.operator:sell']);
            Route::post('/start-reauction-round', [AuctionOrganizerController::class, 'startReAuctionRound'])->name('start-reauction-round')->middleware(['permission:auction.control|auction.edit', 'auction.operator:control']);
            Route::post('/update-base-price', [AuctionOrganizerController::class, 'updateBasePrice'])->name('player.update-base-price')->middleware(['permission:auction.control|auction.edit', 'auction.operator:control']);
            Route::post('/update-auction-base-price', [AuctionOrganizerController::class, 'updateAuctionBasePrice'])->name('auction.update-base-price')->middleware(['permission:auction.control|auction.edit', 'auction.operator:control']);
            // Undo: reverse the last bid / sale / pass / skip.
            Route::post('/undo', [AuctionOrganizerController::class, 'undoLastAction'])->name('undo')->middleware(['permission:auction.control|auction.edit', 'auction.operator:sell']);
            Route::get('/action-log', [AuctionOrganizerController::class, 'actionLog'])->name('action-log');

            // Pool-locked auctioning: run one pool at a time.
            Route::post('/pools/{pool}/activate', [AuctionOrganizerController::class, 'activatePool'])->name('pool.activate')->middleware(['permission:auction.control|auction.edit', 'auction.operator:pools']);
            Route::post('/pools/{pool}/complete', [AuctionOrganizerController::class, 'completePool'])->name('pool.complete')->middleware(['permission:auction.control|auction.edit', 'auction.operator:pools']);
            // Run a finished pool again, keeping its sales — see AuctionPoolService::reopenPool().
            Route::post('/pools/{pool}/reopen', [AuctionOrganizerController::class, 'reopenPool'])->name('pool.reopen')->middleware(['permission:auction.control|auction.edit', 'auction.operator:pools']);
            // Re-run one pool without wiping the pools around it.
            Route::post('/pools/{pool}/restart', [AuctionOrganizerController::class, 'restartPool'])->name('pool.restart')->middleware(['permission:auction.control|auction.edit', 'auction.operator:pools']);
            Route::post('/pools/{pool}/toggle-enabled', [AuctionOrganizerController::class, 'togglePoolEnabled'])->name('pool.toggle-enabled')->middleware(['permission:auction.control|auction.edit', 'auction.operator:pools']);

            // Bid timer.
            Route::post('/toggle-timer', [AuctionOrganizerController::class, 'toggleTimer'])->name('toggle-timer')->middleware(['permission:auction.control|auction.edit', 'auction.operator:control']);
            Route::post('/timer-expired', [AuctionOrganizerController::class, 'timerExpired'])->name('timer-expired')->middleware(['permission:auction.control|auction.edit', 'auction.operator:control']);
        });

        // API routes for the panel to call
        // Route::post('/start', [AuctionOrganizerController::class, 'startAuction'])->name('api.start');
        // Route::post('/end', [AuctionOrganizerController::class, 'endAuction'])->name('api.end');
        // Route::post('/player-on-bid', [AuctionOrganizerController::class, 'putPlayerOnBid'])->name('api.player.onbid');
        // Route::post('/sell-player', [AuctionOrganizerController::class, 'sellPlayer'])->name('api.player.sell');
        // Route::post('/pass-player', [AuctionOrganizerController::class, 'passPlayer'])->name('api.player.pass');
    });

// --- Team Manager Bidding Routes ---
// URL Prefix: /team/auction/{auction}
// Name Prefix: team.auction.bidding.
Route::middleware(['auth'])
    ->prefix('admin/team/auction/{auction}')
    ->name('team.auction.bidding.')
    ->group(function () {

        // **FIX**: Corrected route to SHOW the bidding page
        Route::get('/live', [AuctionBiddingController::class, 'showBiddingPage'])->name('show');

        /*
         * Fast Auction — the lean bidding screen, on its own URL beside /live.
         *
         * Inside THIS group deliberately. It inherits bare `auth` like everything else here, with
         * the team scoping done in the method bodies, and — the part that is easy to miss — the
         * `redirect.team-manager` middleware on the /admin groups already allowlists
         * `admin/team/auction/*`, so a team manager reaches this without touching that allowlist.
         * Mounted anywhere else, they would be bounced to their dashboard.
         *
         * /live is untouched and stays the fallback.
         */
        Route::get('/fast', [FastAuctionScreenController::class, 'teamBidding'])->name('fast');
        Route::get('/api/fast-snapshot', [FastAuctionScreenController::class, 'teamSnapshot'])->name('fast-snapshot');

        // API route for placing a bid
        Route::post('/api/place-bid', [AuctionBiddingController::class, 'placeBid'])->name('api.place-bid');
        // Authenticated purse poll — the public active-player feed carries no team data.
        Route::get('/api/purse', [AuctionBiddingController::class, 'pursePoll'])->name('api.purse');

        /*
         * One request per tick instead of four.
         *
         * The bidding screen used to fetch active-player, sold-players, purse and closed-bid
         * state separately every couple of seconds. Two of those are served from a shared cache
         * and cost almost nothing to answer, but each still paid a full framework boot — and at
         * forty screens that was 83% of all traffic and a machine at 0% idle. See tick().
         *
         * The four originals stay: the wall and the ticker use them, and they are the fallback
         * for a screen whose Javascript predates this route.
         */
        Route::get('/api/tick', [AuctionBiddingController::class, 'tick'])->name('api.tick');

        // Sealed rounds. These routes carry only `auth`, like place-bid beside them, so
        // AuctionBiddingController does the role/team/preview work in the method bodies.
        Route::prefix('api/closed-bid')->name('api.closed-bid.')->group(function () {
            Route::get('/state', [AuctionBiddingController::class, 'closedBidState'])->name('state');
            Route::post('/accept', [AuctionBiddingController::class, 'acceptClosedBid'])->name('accept');
            Route::post('/decline', [AuctionBiddingController::class, 'declineClosedBid'])->name('decline');
            Route::post('/submit', [AuctionBiddingController::class, 'submitClosedBid'])->name('submit');
            Route::post('/withdraw', [AuctionBiddingController::class, 'withdrawClosedBid'])->name('withdraw');
            Route::post('/reinstate', [AuctionBiddingController::class, 'reinstateClosedBid'])->name('reinstate');
        });
    });

// --- Team Manager Dashboard Routes ---
// URL Prefix: /admin/team-manager
// Name Prefix: team-manager.
Route::middleware(['auth'])
    ->prefix('admin/team-manager')
    ->name('team-manager.')
    ->group(function () {
        Route::get('/dashboard', [TeamManagerController::class, 'dashboard'])->name('dashboard');
        Route::get('/team', [TeamManagerController::class, 'viewTeam'])->name('team');
        Route::get('/auctions', [TeamManagerController::class, 'auctions'])->name('auctions');
        // "I agree" on the budget warning. Stored rather than held in the session, so it
        // survives the manager closing their laptop — see acknowledgeBudgetAlert().
        Route::post('/budget-alert/ack', [TeamManagerController::class, 'acknowledgeBudgetAlert'])
            ->name('budget-alert.ack');

        // Player management
        Route::get('/players/create', [TeamManagerController::class, 'createPlayer'])->name('players.create');
        Route::post('/players', [TeamManagerController::class, 'storePlayer'])->name('players.store');
        Route::post('/players/add', [TeamManagerController::class, 'addPlayerToRoster'])->name('players.add');
        Route::delete('/players/{player}', [TeamManagerController::class, 'removePlayerFromRoster'])->name('players.remove');
        Route::post('/players/{player}/verify', [TeamManagerController::class, 'verifyPlayer'])->name('players.verify');
        Route::post('/players/{player}/reject', [TeamManagerController::class, 'rejectPlayer'])->name('players.reject');
        Route::post('/players/{player}/resend-welcome', [TeamManagerController::class, 'resendWelcomeEmail'])->name('players.resend-welcome');

        // Player pool, squad, other teams, wishlist
        Route::get('/players/{player}', [TeamManagerController::class, 'showPlayer'])->name('players.show');
        Route::get('/players', [TeamManagerController::class, 'players'])->name('players');
        Route::get('/squad', [TeamManagerController::class, 'squad'])->name('squad');

        /*
         * Naming the XI for a match. Lives here, not under /admin/matches, because it is the one
         * thing about a match a manager may change — and only for their own team.
         */
        Route::get('/matches/{match}/lineup', [TeamManagerController::class, 'editLineup'])->name('matches.lineup');
        Route::post('/matches/{match}/lineup', [TeamManagerController::class, 'saveLineup'])->name('matches.lineup.save');
        Route::get('/other-teams', [TeamManagerController::class, 'otherTeams'])->name('other-teams');
        Route::get('/other-teams/{otherTeam}', [TeamManagerController::class, 'otherTeamPlayers'])->name('other-teams.players');
        Route::get('/wishlist', [TeamManagerController::class, 'wishlist'])->name('wishlist');
        Route::post('/wishlist/toggle', [TeamManagerController::class, 'toggleWishlist'])->name('wishlist.toggle');

        // Register as Player (manager self-registration)
        Route::get('/register-as-player', [TeamManagerController::class, 'registerAsPlayer'])->name('register-as-player');

        // Captain management
        Route::post('/assign-captain', [TeamManagerController::class, 'assignCaptain'])->name('assign-captain');
    });

// --- Public Tournaments List ---
Route::get('/tournaments', [TournamentsListController::class, 'index'])
    ->name('public.tournaments.index');

// --- Public Display Route ---
// The CSP for admin-authored markup is applied by the controller on the HTML-template
// branch only. It must NOT sit on the route: the ordinary LED wall rendered here is our
// own Blade and loads Tailwind, confetti, Pusher and Echo from CDNs, which the policy
// blocks.
Route::get('/auction/{auction}/live', [PublicAuctionController::class, 'showPublicDisplay'])
    ->name('public.auction.live');
Route::get('/auction/{auction}/sold', [PublicAuctionController::class, 'showPublicDisplaySold'])
    ->name('public.auction.sold');
/*
 * Fast Auction's wall, beside the classic one at /live, which is untouched.
 *
 * The classic wall had already escaped the admin bundle — it is standalone with no @vite — so the
 * gain here is not bundle size. It is that this ships PRECOMPILED CSS instead of pulling
 * cdn.tailwindcss.com, which compiles stylesheets in the browser on every load, on the venue PC
 * driving the projector.
 */
Route::get('/auction/{auction}/fast-wall', [FastAuctionPublicController::class, 'wall'])
    ->name('public.auction.fast-wall');
Route::get('/auction/{auction}/results', [PublicAuctionController::class, 'showResults'])
    ->name('public.auction.results');
// Transparent 1920x1080 overlay for a streaming mixer (OBS browser source).
Route::get('/auction/{auction}/ticker', [PublicAuctionController::class, 'liveTicker'])
    ->name('public.auction.ticker');
/*
 * ── The three broadcast feeds: no session ──
 *
 * These are polled by every screen in the hall — on a busy auction they were 54% of all requests
 * — and being in routes/web.php they inherited the `web` group, so each one STARTED A SESSION:
 * a session file read, an exclusive lock, and a rewrite, per request. Two consequences, both
 * measured on live:
 *
 *   - The lock serialises a single browser's own polls. The team screen fires three of these at
 *     once and they queued behind each other, each holding a php-fpm worker while it waited.
 *   - Every response carried `Set-Cookie: sportzley_session=…`, which makes the responses
 *     uncacheable at the CDN. Cloudflare will not cache a response with Set-Cookie, and forcing
 *     it would hand one visitor's session cookie to every other visitor.
 *
 * Nothing here reads the session: these are public, unauthenticated, and identical for every
 * viewer — which is exactly why cachedFeed() already shares one payload between them all. A team
 * manager's login is unaffected; it lives on the page load and is refreshed by the authenticated
 * purse poll, which is untouched.
 *
 * VerifyCsrfToken is excluded too, and must be: it short-circuits for GET, but still calls
 * `$request->session()->token()` on the way out to refresh the XSRF cookie, which throws once
 * StartSession is gone.
 */
Route::middleware([])->withoutMiddleware([
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
])->group(function () {
    Route::get('/auction/{auction}/ticker-feed', [PublicAuctionController::class, 'tickerFeed'])
        ->name('public.auction.ticker-feed');
    // API endpoint for AJAX polling
    Route::get('/auction/{auction}/active-player', [PublicAuctionController::class, 'activePlayer']);
    Route::get('/auction/{auction}/sold-players', [PublicAuctionController::class, 'soldPlayers']);
    // Same treatment, and for the same three reasons: public, identical for every viewer, and it
    // must not emit Set-Cookie or Cloudflare will refuse to cache it.
    Route::get('/auction/{auction}/fast-wall-snapshot', [FastAuctionPublicController::class, 'snapshot'])
        ->name('public.auction.fast-wall-snapshot');
});

Route::get('/auction/{auction}/sold-player', [PublicAuctionController::class, 'soldPlayer']);

// --- Public Tournament Registration Routes ---
Route::get('/tournament/{tournament}/register', [PublicTournamentRegistrationController::class, 'showForm'])
    ->name('public.tournament.register');
Route::post('/tournament/{tournament}/register/player', [PublicTournamentRegistrationController::class, 'registerPlayer'])
    ->name('public.tournament.register.player');
Route::post('/tournament/{tournament}/register/team', [PublicTournamentRegistrationController::class, 'registerTeam'])
    ->name('public.tournament.register.team');
Route::get('/tournament/{tournament}/register/success', [PublicTournamentRegistrationController::class, 'success'])
    ->name('public.tournament.registration.success');

// Redirect old /tournament/ URLs to new /t/ URLs for player & team registration
Route::get('/tournament/{tournament}/register/player', fn ($tournament) => redirect()->route('public.tournament.registration.player', $tournament, 301));
Route::get('/tournament/{tournament}/register/team', fn ($tournament) => redirect()->route('public.tournament.registration.team', $tournament, 301));

// --- Public Team Join (Invite Link) Routes ---
Route::get('/join/{invite_code}', [PublicTeamJoinController::class, 'showForm'])
    ->name('public.team.join');
Route::post('/join/{invite_code}', [PublicTeamJoinController::class, 'store'])
    ->name('public.team.join.store');
Route::get('/join/{invite_code}/success', [PublicTeamJoinController::class, 'success'])
    ->name('public.team.join.success');

Route::group(['prefix' => 'admin', 'as' => 'admin.', 'middleware' => ['auth', 'redirect.team-manager', 'organizer.access']], function () {

    Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
    Route::post('/backups/create', [BackupController::class, 'create'])->name('backups.create');
    Route::get('/backups/download', [BackupController::class, 'download'])->name('backups.download');
    Route::get('/backups/export', [BackupController::class, 'export'])->name('backups.export');
    Route::post('/backups/import', [BackupController::class, 'import'])->name('backups.import');
    Route::post('/backups/restore', [BackupController::class, 'restore'])->name('backups.restore');
    Route::delete('/backups/delete', [BackupController::class, 'delete'])->name('backups.delete');

    // Superadmin-only: preview + edit transactional registration/approval emails.
    Route::get('/emails/preview', [EmailPreviewController::class, 'index'])->name('emails.preview');
    Route::get('/emails/preview/{type}/render', [EmailPreviewController::class, 'render'])->name('emails.preview.render');
    Route::post('/emails/preview/{type}/draft', [EmailPreviewController::class, 'previewDraft'])->name('emails.preview.draft');
    Route::post('/emails/templates', [EmailPreviewController::class, 'saveTemplate'])->name('emails.templates.save');
    Route::delete('/emails/templates', [EmailPreviewController::class, 'resetTemplate'])->name('emails.templates.reset');
    Route::post('/emails/brand', [EmailPreviewController::class, 'saveBrand'])->name('emails.brand.save');

    Route::get('/download-log', function () {
        $path = storage_path('logs/laravel.log');

        if (! file_exists($path)) {
            abort(404, "Log file not found!");
        }

        return response()->download($path, 'laravel-log.txt', [
            'Content-Type' => 'text/plain',
        ]);
    });

    Route::get('/backup-db', function () {
        // Database connection info
        $dbHost = env('DB_HOST', '127.0.0.1');
        $dbPort = env('DB_PORT', '3306');
        $dbName = env('DB_DATABASE');
        $dbUser = env('DB_USERNAME');
        $dbPass = env('DB_PASSWORD');

        // Filename with timestamp
        $fileName = 'backup-' . date('Ymd-His') . '.sql';

        // Full path in storage/app/public/
        $filePath = storage_path('app/public/' . $fileName);

        // Create the mysqldump command
        // Adjust --single-transaction for InnoDB, avoid locking tables
        $command = "mysqldump --user={$dbUser} --password=\"{$dbPass}\" --host={$dbHost} --port={$dbPort} --single-transaction {$dbName} > {$filePath}";

        // Execute the command
        $returnVar = null;
        $output = null;
        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            return response()->json([
                'success' => false,
                'message' => 'Backup failed',
                'output' => $output,
            ], 500);
        }

        // Return the URL to the backup file
        $url = asset('storage/' . $fileName);

        return response()->json([
            'success' => true,
            'message' => 'Backup created successfully',
            'file' => $fileName,
            'url' => $url,
        ]);
    });

    Route::resource('organizations', OrganizationController::class);

    // Zones
    Route::resource('zones', ZoneController::class);
    Route::get('/zones/by-organization', [ZoneController::class, 'getByOrganization'])->name('zones.by-organization');

    Route::delete('/auctions/{team}/clear', [AuctionController::class, 'clearTeamData'])
        ->name('auctions.clear');

    Route::resource('actual-teams', ActualTeamController::class);
    // Route to add a member to a team
    Route::post('actual-teams/{actualTeam}/members', [ActualTeamController::class, 'addMember'])->name('actual-teams.add-member');

    Route::delete('actual-teams/{actualTeam}/members/{user}', [ActualTeamController::class, 'removeMember'])
        ->name('actual-teams.delete-member');

    // Optional: Route to update a member's role
    Route::put('actual-teams/{actualTeam}/members/{user}/role', [ActualTeamController::class, 'updateMemberRole'])->name('actual-teams.update-member-role');

    // Team Manager Management
    Route::post('actual-teams/{actualTeam}/team-manager', [ActualTeamController::class, 'createTeamManager'])->name('actual-teams.create-team-manager');
    Route::get('actual-teams/{actualTeam}/team-managers', [ActualTeamController::class, 'getTeamManagers'])->name('actual-teams.get-team-managers');
    Route::post('actual-teams/{actualTeam}/team-manager/{user}/reset-password', [ActualTeamController::class, 'resetTeamManagerPassword'])->name('actual-teams.reset-team-manager-password');
    Route::post('actual-teams/{actualTeam}/team-manager/{user}/resend-credentials', [ActualTeamController::class, 'resendTeamManagerCredentials'])->name('actual-teams.resend-team-manager-credentials');
    Route::get('actual-teams/{actualTeam}/search-org-users', [ActualTeamController::class, 'searchOrgUsers'])->name('actual-teams.search-org-users');
    Route::post('actual-teams/{actualTeam}/assign-team-manager', [ActualTeamController::class, 'assignTeamManager'])->name('actual-teams.assign-team-manager');

    // Player management on teams
    Route::post('actual-teams/{actualTeam}/players', [ActualTeamController::class, 'addPlayer'])->name('actual-teams.add-player');
    Route::put('actual-teams/{actualTeam}/players/{player}', [ActualTeamController::class, 'updatePlayer'])->name('actual-teams.update-player');
    Route::delete('actual-teams/{actualTeam}/players/{player}', [ActualTeamController::class, 'removePlayer'])->name('actual-teams.remove-player');
    Route::post('actual-teams/{actualTeam}/players/{player}/toggle-approve', [ActualTeamController::class, 'toggleApprove'])->name('actual-teams.toggle-approve');

    // Auctions
    // Route::prefix('auctions')->as('auctions.')->group(function () {
    //     Route::get('/', [AuctionController::class, 'index'])->name('index');
    //     Route::get('/create', [AuctionController::class, 'create'])->name('create');
    //     Route::post('/', [AuctionController::class, 'store'])->name('store');
    //     Route::get('/{auction}', [AuctionController::class, 'show'])->name('show');
    //     Route::get('/{auction}/edit', [AuctionController::class, 'edit'])->name('edit');
    //     Route::put('/{auction}', [AuctionController::class, 'update'])->name('update');
    //     Route::delete('/{auction}', [AuctionController::class, 'destroy'])->name('destroy');

    //     // Live bidding — see admin/organizer/auction/{auction}/panel instead.
    // });

    // // Auction Settings
    // Route::prefix('auction-settings')->as('auction-settings.')->group(function () {
    //     Route::get('/', [AuctionSettingController::class, 'index'])->name('index');
    //     Route::post('/', [AuctionSettingController::class, 'update'])->name('update');
    // });

    Route::get('/organizations/{organization}/locations', function (Organization $organization) {
        return $organization->locations()->select('id', 'name')->get();
    })->name('organizations.locations');

    Route::get('/notifications/unread', [AdminNotificationController::class, 'unread'])
        ->name('notifications.unread');
    Route::get('/notifications/read', [AdminNotificationController::class, 'read'])
        ->name('notifications.red');
    Route::get('/notifications', [AdminNotificationController::class, 'index'])->name('notifications.index');

    Route::post('/notifications/read/{id}', [AdminNotificationController::class, 'markAsRead'])
        ->name('notifications.read');
    Route::post('notifications/unread/{id}', [AdminNotificationController::class, 'markAsUnread']);

    Route::post('/notifications/read-all', [AdminNotificationController::class, 'markAllAsRead'])->name('notifications.read.all');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // NOTE: Legacy Team routes commented out - use ActualTeam (/admin/actual-teams) instead
    // The old Team model is not connected to matches, auctions, or tournaments
    // Route::resource('teams', TeamController::class);
    // Route::post('/teams/{team}/players', [TeamPlayerController::class, 'store'])->name('teams.addPlayer');
    // Route::delete('/teams/{team}/players/{player}', [TeamPlayerController::class, 'destroy'])->name('teams.removePlayer');

    Route::resource('tournaments', TournamentController::class);
    Route::get('/tournaments/{tournament}/dashboard', [TournamentController::class, 'dashboard'])->name('tournaments.dashboard');
    Route::post('/tournaments/{tournament}/global-budget', [TournamentController::class, 'globalBudgetUpdate'])->name('tournaments.global-budget');
    Route::post('/players/{player}/intimate', [PlayerController::class, 'intimate'])->name('players.intimate');
    Route::post('/players/save-image', [PlayerController::class, 'saveImage'])->name('players.saveImage');
    Route::get('/players/{player}/image-editor', [PlayerController::class, 'editor'])
        ->name('players.image-editor');
    Route::post('/players/remove-background', [PlayerController::class, 'removeBackground'])->name('players.removeBackground');

    /*
     * Quick photo replacement, outside the player edit form.
     * Registered before Route::resource('players') so "{player}/photo" is not
     * swallowed by the resource's own patterns.
     */
    Route::post('/players/{player}/photo', [PlayerImageProcessController::class, 'replacePlayerPhoto'])
        ->name('players.replace-photo');
    Route::post('/actual-teams/{actualTeam}/captain-photo', [PlayerImageProcessController::class, 'replaceCaptainPhoto'])
        ->name('actual-teams.replace-captain-photo');

    /*
     * Registered BEFORE the resource, because `Route::resource` gives it
     * `GET /players/{player}` — a pattern that matches the literal segment
     * "export-xlsx" and then 404s on binding a player with that id. That is the
     * whole reason the live export answered 404 while the route was plainly
     * present in this file.
     *
     * GET so it can be a plain link carrying the list's current filters.
     */
    Route::get('/players/export-xlsx', [PlayerController::class, 'exportXlsx'])->name('players.export-xlsx');

    Route::resource('players', PlayerController::class);
    Route::post('/players/{player}/retain', [PlayerController::class, 'retain'])->name('players.retain');
    Route::post('/players/{player}/unretain', [PlayerController::class, 'unretain'])->name('players.unretain');
    // Organizer management: create/pick organizer users and assign tournaments/teams/matches
    Route::resource('organizers', OrganizerController::class)->except(['show'])->parameters(['organizers' => 'organizer']);
    Route::post('/players/export', [PlayerController::class, 'export'])->name('players.export');

    Route::post('players/import', [PlayerController::class, 'importCsv'])->name('players.import');

    Route::get('players/sample-csv', [PlayerController::class, 'downloadSampleCsv'])->name('players.sample');

    Route::get('profile-tracking', [\App\Http\Controllers\Backend\ProfileTrackingController::class, 'index'])->name('profile-tracking.index');
    Route::get('pending-approvals', [\App\Http\Controllers\Backend\PendingApprovalsController::class, 'index'])->name('pending-approvals.index');
    Route::get('requested-changes', [\App\Http\Controllers\Backend\RequestedChangesController::class, 'index'])->name('requested-changes.index');

    // Live Ticker Index (must be before resource route)
    Route::get('/matches/live-ticker', [MatchesController::class, 'liveTickerIndex'])->name('matches.live-ticker-index');

    Route::resource('matches', MatchesController::class);
    Route::post('/matches/bulk-delete', [MatchesController::class, 'bulkDelete'])->name('matches.bulkDelete');
    Route::post('/matches/reorder', [MatchesController::class, 'reorder'])->name('matches.reorder');
    Route::get('/matches/{match}/state', [MatchesController::class, 'getState'])->name('matches.state');
    Route::post('/matches/{match}/switch-innings', [MatchesController::class, 'switchInnings'])->name('matches.switchInnings');
    Route::post('/matches/{match}/toss', [MatchesController::class, 'saveToss'])->name('matches.saveToss');
    Route::post('/matches/{match}/go-live', [MatchesController::class, 'goLive'])->name('matches.goLive');
    Route::post('/matches/{match}/cancel', [MatchesController::class, 'cancelMatch'])->name('matches.cancel');
    Route::get('/matches/{match}/download-posters', [MatchesController::class, 'downloadAllPosters'])->name('matches.download-posters');

    // CricHeroes match report PDF -> AI-drafted blog post. Superadmin only; the gate is in
    // MatchReportController, because these routes spend money at OpenAI.
    Route::post('/matches/{match}/report/upload', [MatchReportController::class, 'upload'])->name('matches.report.upload');
    Route::post('/matches/{match}/report/generate', [MatchReportController::class, 'generate'])->name('matches.report.generate');
    Route::delete('/matches/{match}/report', [MatchReportController::class, 'destroy'])->name('matches.report.destroy');
    Route::get('/ai/models', [MatchReportController::class, 'models'])->name('ai.models');
    Route::get('/matches/{match}/generate-poster', [MatchesController::class, 'generatePoster'])->name('matches.generate-poster');
    Route::get('/matches/{match}/overs', [MatchesController::class, 'editOvers'])->name('overs.edit');
    Route::post('/matches/{match}/overs', [MatchesController::class, 'updateOvers'])->name('overs.update');
    Route::get('/matches/{match}/balls/create', [BallController::class, 'create'])->name('balls.create');
    Route::post('/matches/{match}/balls', [BallController::class, 'store'])->name('balls.store');
    Route::delete('/matches/{match}/balls/{ball}', [BallController::class, 'destroy'])->name('balls.destroy');

    // Option A: Add /admin prefix to match your JS
    Route::post('/matches/{match}/balls/ajax-store', [BallController::class, 'ajaxStore'])
        ->name('balls.ajaxStore');
    Route::get('/matches/{match}/balls/summary', [BallController::class, 'summary'])->name('balls.summary');
    Route::get('/matches/{match}/balls/last', [BallController::class, 'lastBall'])->name('balls.last');

    Route::get('/matches/{match}/scorecard', [ScorecardController::class, 'show'])->name('matches.scorecard');

    // Live Match Ticker (1920x1080 Broadcast Display)
    Route::get('/matches/{match}/live-ticker', [MatchesController::class, 'liveTicker'])->name('matches.live-ticker');

    Route::resource('appreciations', MatchAppreciationController::class)->only(['index']);

    Route::post('/appreciations/save/{tournament}/{match}/{player}', [AppreciationController::class, 'store'])->name('appreciations.save');

    Route::get('matches/{match}/appreciations/create', [MatchAppreciationController::class, 'create'])->name('matches.appreciations.create');
    Route::post('matches/{match}/appreciations', [MatchAppreciationController::class, 'store'])->name('matches.appreciations.store');
    Route::delete('match-appreciations/{appreciation}', [MatchAppreciationController::class, 'destroy'])->name('matches.appreciations.destroy');

    Route::post('/players/{player}/approve', [PlayerVerificationController::class, 'approve'])->name('players.approve');
    // Route to reject a player
    Route::post('/players/{player}/reject', [PlayerVerificationController::class, 'reject'])->name('players.reject');

    Route::prefix('admin/templates')->name('admin.templates.')->group(function () {});

    Route::resource('image-templates', ImageTemplateController::class)->except(['create', 'edit', 'destroy']);
    Route::get('/image-templates/create', [ImageTemplateController::class, 'create'])->name('image-templates.create');
    Route::get('/image-templates/edit', [ImageTemplateController::class, 'edit'])->name('image-templates.edit');
    Route::post('/image-templates/save', [ImageTemplateController::class, 'store'])->name('image-templates.save');
    Route::get('/image-templates/generate/{player}', [ImageTemplateController::class, 'generateImage'])->name('image-templates.generate-player');
    Route::delete('/image-templates/{image_template}', [ImageTemplateController::class, 'destroy'])->name('image-templates.destroy');

    Route::get('/background/remove', function () {
        return view('background.remove'); // blade file
    })->name('background.form');

    Route::post('/image-templates/remove', [ImageTemplateController::class, 'removeTemplate'])
        ->name('image-templates.remove');

    Route::get('image-templates/remove-bg', [ImageTemplateController::class, 'removebg'])->name('image-templates.remove-bg');
    // Optional route to generate output image from a saved template
    Route::post('image-templates/{image_template}/generate', [ImageTemplateController::class, 'generate'])
        ->name('image-templates.generate-template');

    Route::resource('roles', RolesController::class);
    Route::delete('roles/delete/bulk-delete', [RolesController::class, 'bulkDelete'])->name('roles.bulk-delete');

    // Permissions Routes.
    Route::get('/permissions', [PermissionsController::class, 'index'])->name('permissions.index');
    Route::get('/permissions/{id}', [PermissionsController::class, 'show'])->name('permissions.show');

    // Modules Routes.
    Route::get('/modules', [ModulesController::class, 'index'])->name('modules.index');
    Route::post('/modules/toggle-status/{module}', [ModulesController::class, 'toggleStatus'])->name('modules.toggle-status');
    Route::post('/modules/upload', [ModulesController::class, 'store'])->name('modules.store');
    Route::delete('/modules/{module}', [ModulesController::class, 'destroy'])->name('modules.delete');

    // Settings Routes.
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings', [SettingsController::class, 'store'])->name('settings.store');

    // Mail Test (Superadmin only)
    Route::post('/settings/test-email', [MailTestController::class, 'send'])
        ->name('settings.test-email')
        ->middleware(['role:Superadmin']);

    // Translation Routes
    Route::get('/translations', [TranslationController::class, 'index'])->name('translations.index');
    Route::post('/translations', [TranslationController::class, 'update'])->name('translations.update');
    Route::post('/translations/create', [TranslationController::class, 'create'])->name('translations.create');

    // Login as & Switch back
    // NOTE: switch-back must be declared BEFORE the users resource, otherwise the
    // resource's GET users/{user} (show) route swallows "users/switch-back".
    // GET (like login-as) so exiting impersonation never fails CSRF/419, and so
    // it works for any impersonated role. Restoring your own session is harmless.
    Route::get('users/switch-back', [UserLoginAsController::class, 'switchBack'])->name('users.switch-back');
    Route::resource('users', UsersController::class);
    Route::delete('users/delete/bulk-delete', [UsersController::class, 'bulkDelete'])->name('users.bulk-delete');
    Route::get('users/{id}/login-as', [UserLoginAsController::class, 'loginAs'])->name('users.login-as');

    // Font Manager (Superadmin only) — manage Google/custom fonts for templates.
    Route::middleware(['role:Superadmin'])->group(function () {
        Route::get('fonts', [\App\Http\Controllers\Backend\FontController::class, 'index'])->name('fonts.index');
        Route::post('fonts/google', [\App\Http\Controllers\Backend\FontController::class, 'storeGoogle'])->name('fonts.google');
        Route::post('fonts/custom', [\App\Http\Controllers\Backend\FontController::class, 'storeCustom'])->name('fonts.custom');
        Route::delete('fonts/{font}', [\App\Http\Controllers\Backend\FontController::class, 'destroy'])->name('fonts.destroy');
    });

    // Action Log Routes.
    Route::get('/action-log', [ActionLogController::class, 'index'])->name('actionlog.index');
    Route::delete('/action-log', [ActionLogController::class, 'clear'])->name('actionlog.clear');

    // Email Log Routes.
    Route::get('/email-logs', [EmailLogController::class, 'index'])->name('email-logs.index');
    Route::post('/email-logs/batch-retry', [EmailLogController::class, 'batchRetry'])->name('email-logs.batch-retry');
    Route::get('/email-logs/{emailLog}', [EmailLogController::class, 'show'])->name('email-logs.show');
    Route::post('/email-logs/{emailLog}/retry', [EmailLogController::class, 'retry'])->name('email-logs.retry');
    Route::delete('/email-logs', [EmailLogController::class, 'clear'])->name('email-logs.clear');

    // Content Management Routes

    // Posts/Pages Routes - Dynamic post types
    Route::get('/posts/{postType?}', [PostsController::class, 'index'])->name('posts.index');
    Route::get('/posts/{postType}/create', [PostsController::class, 'create'])->name('posts.create');
    Route::post('/posts/{postType}', [PostsController::class, 'store'])->name('posts.store');
    Route::get('/posts/{postType}/{id}', [PostsController::class, 'show'])->name('posts.show');
    Route::get('/posts/{postType}/{id}/edit', [PostsController::class, 'edit'])->name('posts.edit');
    Route::put('/posts/{postType}/{id}', [PostsController::class, 'update'])->name('posts.update');
    Route::delete('/posts/{postType}/{id}', [PostsController::class, 'destroy'])->name('posts.destroy');
    Route::delete('/posts/{postType}/delete/bulk-delete', [PostsController::class, 'bulkDelete'])->name('posts.bulk-delete');

    // Terms Routes (Categories, Tags, etc.)
    Route::get('/terms/{taxonomy}', [TermsController::class, 'index'])->name('terms.index');
    Route::get('/terms/{taxonomy}/{term}/edit', [TermsController::class, 'edit'])->name('terms.edit');
    Route::post('/terms/{taxonomy}', [TermsController::class, 'store'])->name('terms.store');
    Route::put('/terms/{taxonomy}/{id}', [TermsController::class, 'update'])->name('terms.update');
    Route::delete('/terms/{taxonomy}/{id}', [TermsController::class, 'destroy'])->name('terms.destroy');
    Route::delete('/terms/{taxonomy}/delete/bulk-delete', [TermsController::class, 'bulkDelete'])->name('terms.bulk-delete');

    // Editor Upload Route
    Route::post('/editor/upload', [App\Http\Controllers\Backend\EditorController::class, 'upload'])->name('editor.upload');
});

/**
 * Profile routes.
 */
Route::group(['prefix' => 'profile', 'as' => 'profile.', 'middleware' => ['auth']], function () {
    Route::get('/edit', [ProfilesController::class, 'edit'])->name('edit');
    Route::put('/update', [ProfilesController::class, 'update'])->name('update');
});

Route::get('/locale/{lang}', [LocaleController::class, 'switch'])->name('locale.switch');
Route::get('/screenshot-login/{email}', [ScreenshotGeneratorLoginController::class, 'login'])->middleware('web')->name('screenshot.login');

Route::get('/player/register', [PublicPlayerController::class, 'showForm'])->name('player.register.form');
Route::post('/player/register', [PublicPlayerController::class, 'store'])->name('player.register.store');
Route::post('/background/remove', [ImageTemplateController::class, 'remove'])->name('background.remove');

Route::view('/policies/terms-and-conditions', 'policies.terms')->name('policies.terms');
Route::view('/policies/player-availability', 'policies.availability')->name('policies.availability');
Route::view('/policies/auction-commitment', 'policies.auction')->name('policies.auction');

// Show email verification notice
/**
 * Email Verification Routes
 */

// Show email verification notice
Route::get('/email/verify', function () {
    return view('auth.verify-email');
})->middleware('auth')->name('verification.notice');

// ✅ Required by Laravel — DO NOT COMMENT THIS OUT
Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill(); // Marks email as verified
    return redirect('/home'); // Or wherever you want
})->middleware(['auth', 'signed'])->name('verification.verify');

// Resend verification email
Route::post('/email/verification-notification', function (Request $request) {
    $request->user()->sendEmailVerificationNotification();
    return back()->with('message', 'Verification link sent!');
})->middleware(['auth', 'throttle:6,1'])->name('verification.send');

Route::get('/email/public-verify/{id}/{hash}', function (Request $request, $id, $hash) {
    $user = User::findOrFail($id);

    if (! hash_equals((string) $hash, sha1($user->email))) {
        abort(403, 'Invalid or tampered verification link.');
    }

    if (! $user->hasVerifiedEmail()) {
        $user->markEmailAsVerified();
    }

    return view('public.player-verified-success'); // ✅ create this Blade file
})->middleware('signed')->name('public.verification.verify');

Route::get('/test-mail', function () {
    \Illuminate\Support\Facades\Mail::raw('Test email from Sportzley via Mailgun! Time: ' . now(), function ($message) {
        $message->to('booklyman2025@gmail.com')
            ->subject('Sportzley Test Email - Mailgun');
    });

    return 'Mail Sent Successfully via Mailgun!';
});
// Route::get('/test-shell', function () {
//     // --- Configuration ---
//     $pythonPath = '/var/www/OrganizerPro/rembg-env/bin/python';
//     $scriptPath = '/var/www/OrganizerPro/resources/scripts/remove_bg.py';
//     $inputImage = '/var/www/OrganizerPro/storage/app/public/player_images/player.jpeg';
//     $outputImage = '/var/www/OrganizerPro/storage/app/public/player_images/processed-EKB0GR0w.png';

//     // Define the writable cache directory
//     $cachePath = '/var/www/OrganizerPro/storage/app/rembg_cache';

//     // --- Verification ---
//     if (!is_dir($cachePath) || !is_writable($cachePath)) {
//         return "ERROR: Cache path does not exist or is not writable by the web server: " . htmlspecialchars($cachePath);
//     }

//     // --- Command Construction ---
//     // Prepend the U2NET_HOME environment variable to the command
//     $command = 'U2NET_HOME=' . escapeshellarg($cachePath) . ' ' .
//         escapeshellcmd($pythonPath) . ' ' .
//         escapeshellarg($scriptPath) . ' ' .
//         escapeshellarg($inputImage) . ' ' .
//         escapeshellarg($outputImage) . ' 2>&1';

//     // --- Diagnostics ---
//     $currentUser = shell_exec('whoami');
//     echo "<h1>Running Command...</h1>";
//     echo "<strong>As User:</strong> " . htmlspecialchars(trim($currentUser)) . "<br>";
//     echo "<strong>Full Command:</strong><pre>" . htmlspecialchars($command) . "</pre>";
//     echo "<strong>Output:</strong><br>";

//     // --- Execution ---
//     // Increase the time limit for the first run, as it needs to download the model
//     set_time_limit(300); // 5 minutes
//     $output = shell_exec($command);

//     // --- Result ---
//     echo "<pre>";
//     if ($output !== null) {
//         echo htmlspecialchars($output);
//     } else {
//         echo "No output was returned. Check web server logs.";
//     }
//     echo "</pre>";

//     // --- Final Check ---
//     if (file_exists($outputImage)) {
//         echo "<strong>Success!</strong> The output file was created.";
//         // You can optionally check the cache directory too
//         if (count(scandir($cachePath)) > 2) { // >2 because of '.' and '..'
//             echo "<br>Model appears to be cached successfully in " . htmlspecialchars($cachePath);
//         }
//     } else {
//         echo "<strong>Failure:</strong> The output file was NOT created.";
//     }
// });

/*
|--------------------------------------------------------------------------
| Tournament Organization Routes
|--------------------------------------------------------------------------
*/

// Public blog. /blog/{slug} is where a generated match report is published.
Route::get('/blog', [BlogController::class, 'index'])->name('public.blog.index');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('public.blog.show');

// Public Tournament Routes (No Auth Required)
Route::prefix('t/{tournament:slug}')->name('public.tournament.')->group(function () {
    Route::get('/', [TournamentPublicController::class, 'show'])->name('show');
    Route::get('/fixtures', [TournamentPublicController::class, 'fixtures'])->name('fixtures');
    Route::get('/point-table', [TournamentPublicController::class, 'pointTable'])->name('point-table');
    Route::get('/statistics', [TournamentPublicController::class, 'statistics'])->name('statistics');
    Route::get('/teams', [TournamentPublicController::class, 'teams'])->name('teams');

    // Registration (slug-based routes)
    Route::get('/register/player', [PublicRegistrationController::class, 'playerForm'])->name('registration.player');
    Route::post('/register/player', [PublicRegistrationController::class, 'storePlayer'])->name('registration.player.store')->middleware('throttle:5,1');
    Route::get('/register/player/success', [PublicRegistrationController::class, 'success'])->defaults('type', 'player')->name('registration.player.success');
    Route::get('/register/team', [PublicRegistrationController::class, 'teamForm'])->name('registration.team');
    Route::post('/register/team', [PublicRegistrationController::class, 'storeTeam'])->name('registration.team.store')->middleware('throttle:5,1');
    Route::get('/register/team/success', [PublicRegistrationController::class, 'success'])->defaults('type', 'team')->name('registration.team.success');
});

// Webhooks (CSRF excluded via VerifyCsrfToken middleware)
Route::post('/webhooks/ses', [\App\Http\Controllers\Webhook\SesWebhookController::class, 'handle'])->name('webhooks.ses');

// Public Match Routes (No Auth Required)
Route::prefix('m/{match:slug}')->name('public.match.')->group(function () {
    Route::get('/', [MatchPublicController::class, 'show'])->name('show');
    Route::get('/poster', [MatchPublicController::class, 'poster'])->name('poster');
    Route::get('/summary', [MatchPublicController::class, 'summary'])->name('summary');
    Route::get('/scorecard', [MatchPublicController::class, 'scorecard'])->name('scorecard');
    Route::get('/ticker', [MatchPublicController::class, 'liveTicker'])->name('ticker');
});

// Public Live Ticker by Match ID (for easy sharing)
Route::get('/live/{match}', [MatchPublicController::class, 'liveTicker'])->name('public.live-ticker');

// Public Player Dashboard
Route::get('/player/{player}/dashboard', [PlayerDashboardController::class, 'show'])->name('public.player.dashboard');

// Admin Tournament Management Routes
Route::group(['prefix' => 'admin', 'as' => 'admin.', 'middleware' => ['auth', 'redirect.team-manager', 'organizer.access']], function () {
    // Grounds
    Route::resource('grounds', GroundController::class);

    // Tournament Settings
    Route::prefix('tournaments/{tournament}')->name('tournaments.')->group(function () {
        // Settings
        Route::get('/settings', [TournamentSettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [TournamentSettingsController::class, 'update'])->name('settings.update');
        // Custom registration fields (admin-defined add-on fields)
        Route::post('/settings/custom-fields', [\App\Http\Controllers\Backend\Tournament\TournamentCustomFieldController::class, 'store'])->name('settings.custom-fields.store');
        Route::put('/settings/custom-fields/{customField}', [\App\Http\Controllers\Backend\Tournament\TournamentCustomFieldController::class, 'update'])->name('settings.custom-fields.update');
        Route::delete('/settings/custom-fields/{customField}', [\App\Http\Controllers\Backend\Tournament\TournamentCustomFieldController::class, 'destroy'])->name('settings.custom-fields.destroy');
        Route::post('/settings/custom-fields/{customField}/toggle', [\App\Http\Controllers\Backend\Tournament\TournamentCustomFieldController::class, 'toggle'])->name('settings.custom-fields.toggle');
        Route::post('/settings/generate-flyer', [TournamentSettingsController::class, 'generateFlyer'])->name('settings.generate-flyer');
        Route::put('/settings/status', [TournamentSettingsController::class, 'updateStatus'])->name('settings.status');

        // Registrations
        Route::get('/registrations', [TournamentRegistrationController::class, 'index'])->name('registrations.index');
        Route::get('/registrations/{registration}', [TournamentRegistrationController::class, 'show'])->name('registrations.show');
        Route::post('/registrations/{registration}/approve', [TournamentRegistrationController::class, 'approve'])->name('registrations.approve');
        Route::post('/registrations/{registration}/reject', [TournamentRegistrationController::class, 'reject'])->name('registrations.reject');
        Route::post('/registrations/{registration}/queue', [TournamentRegistrationController::class, 'queue'])->name('registrations.queue');
        Route::post('/registrations/{registration}/cancel', [TournamentRegistrationController::class, 'cancel'])->name('registrations.cancel');
        Route::post('/registrations/{registration}/unapprove', [TournamentRegistrationController::class, 'unapprove'])->name('registrations.unapprove');
        Route::post('/registrations/bulk-approve', [TournamentRegistrationController::class, 'bulkApprove'])->name('registrations.bulk-approve');
        Route::delete('/registrations/{registration}/force-delete', [TournamentRegistrationController::class, 'forceDelete'])->name('registrations.force-delete');
        // Per-field verification + correction (intimation) email
        Route::post('/registrations/{registration}/verification', [TournamentRegistrationController::class, 'updateVerification'])->name('registrations.verification');
        // Inline field update (AJAX)
        Route::patch('/registrations/{registration}/field', [TournamentRegistrationController::class, 'updateField'])->name('registrations.update-field');
        // Signed consent PDF download
        Route::get('/registrations/{registration}/consent-pdf', [TournamentRegistrationController::class, 'downloadConsent'])->name('registrations.consent-pdf');
        // Resend welcome card / confirmation emails
        Route::post('/registrations/{registration}/resend-welcome', [TournamentRegistrationController::class, 'resendWelcome'])->name('registrations.resend-welcome');
        Route::post('/registrations/{registration}/resend-confirmation', [TournamentRegistrationController::class, 'resendConfirmation'])->name('registrations.resend-confirmation');
        // Reset + email a temporary login password so the applicant can correct their details
        Route::post('/registrations/{registration}/send-temp-password', [TournamentRegistrationController::class, 'sendTempPassword'])->name('registrations.send-temp-password');
        // Approve / reject player-requested profile changes
        Route::post('/registrations/{registration}/pending-changes/approve', [TournamentRegistrationController::class, 'approvePendingChanges'])->name('registrations.pending-changes.approve');
        Route::post('/registrations/{registration}/pending-changes/reject', [TournamentRegistrationController::class, 'rejectPendingChanges'])->name('registrations.pending-changes.reject');
        // Welcome card preview & download on registration page
        Route::get('/registrations/{registration}/welcome-card/preview', [TournamentRegistrationController::class, 'previewWelcomeCard'])->name('registrations.welcome-card.preview');
        Route::get('/registrations/{registration}/welcome-card/download', [TournamentRegistrationController::class, 'downloadWelcomeCard'])->name('registrations.welcome-card.download');
        Route::get('/registrations/{registration}/retained-welcome-card/preview', [TournamentRegistrationController::class, 'previewRetainedWelcomeCard'])->name('registrations.retained-welcome-card.preview');
        Route::get('/registrations/{registration}/retained-welcome-card/download', [TournamentRegistrationController::class, 'downloadRetainedWelcomeCard'])->name('registrations.retained-welcome-card.download');

        // Groups
        Route::get('/groups', [TournamentGroupController::class, 'index'])->name('groups.index');
        Route::post('/groups', [TournamentGroupController::class, 'store'])->name('groups.store');
        Route::put('/groups/{group}', [TournamentGroupController::class, 'update'])->name('groups.update');
        Route::delete('/groups/{group}', [TournamentGroupController::class, 'destroy'])->name('groups.destroy');
        Route::post('/groups/{group}/add-team', [TournamentGroupController::class, 'addTeam'])->name('groups.add-team');
        Route::delete('/groups/{group}/remove-team/{team}', [TournamentGroupController::class, 'removeTeam'])->name('groups.remove-team');
        Route::post('/groups/auto-create', [TournamentGroupController::class, 'autoCreate'])->name('groups.auto-create');
        Route::post('/groups/{group}/reorder-teams', [TournamentGroupController::class, 'reorderTeams'])->name('groups.reorder-teams');

        // Fixtures
        Route::get('/fixtures', [TournamentFixtureController::class, 'index'])->name('fixtures.index');
        Route::post('/fixtures', [TournamentFixtureController::class, 'store'])->name('fixtures.store');
        Route::post('/fixtures/generate-group-stage', [TournamentFixtureController::class, 'generateGroupStage'])->name('fixtures.generate-group');
        Route::post('/fixtures/generate-knockouts', [TournamentFixtureController::class, 'generateKnockouts'])->name('fixtures.generate-knockouts');
        Route::post('/fixtures/generate-ipl-playoffs', [TournamentFixtureController::class, 'generateIplPlayoffs'])->name('fixtures.generate-ipl-playoffs');
        Route::delete('/fixtures/group-stage', [TournamentFixtureController::class, 'deleteGroupStage'])->name('fixtures.delete-group');
        /*
         * Bulk actions. Literal segments, so they must sit ABOVE the `/fixtures/{match}` routes
         * below — declared after them, `bulk-delete` binds as a match id and 404s.
         */
        Route::delete('/fixtures/bulk-delete', [TournamentFixtureController::class, 'bulkDestroy'])->name('fixtures.bulk-delete');
        Route::post('/fixtures/bulk-publish', [TournamentFixtureController::class, 'bulkPublish'])->name('fixtures.bulk-publish');
        Route::post('/fixtures/bulk-generate-posters', [TournamentFixtureController::class, 'bulkGeneratePosters'])->name('fixtures.bulk-posters');
        Route::put('/fixtures/{match}', [TournamentFixtureController::class, 'update'])->name('fixtures.update');
        Route::delete('/fixtures/{match}', [TournamentFixtureController::class, 'destroy'])->name('fixtures.destroy');
        Route::post('/fixtures/{match}/reschedule', [TournamentFixtureController::class, 'reschedule'])->name('fixtures.reschedule');
        Route::post('/fixtures/{match}/cancel', [TournamentFixtureController::class, 'cancel'])->name('fixtures.cancel');
        Route::post('/fixtures/{match}/generate-poster', [TournamentFixtureController::class, 'generatePoster'])->name('fixtures.generate-poster');
        Route::get('/fixtures/export-csv', [TournamentFixtureController::class, 'exportCsv'])->name('fixtures.export-csv');

        // Manage Teams (retain/budget)
        Route::get('/manage-teams', [TournamentGroupController::class, 'manageTeams'])->name('manage-teams');

        // Point Table
        Route::get('/point-table', [PointTableController::class, 'index'])->name('point-table.index');
        Route::post('/point-table/recalculate', [PointTableController::class, 'recalculate'])->name('point-table.recalculate');
        Route::post('/point-table/generate-poster', [PointTableController::class, 'generatePoster'])->name('point-table.generate-poster');
        Route::post('/point-table/initialize', [PointTableController::class, 'initialize'])->name('point-table.initialize');
        Route::post('/point-table/qualified', [PointTableController::class, 'updateQualified'])->name('point-table.qualified');

        /*
         * Player History — how every player in the competition was acquired, out of which pool,
         * for how much and when. The PDF route is declared FIRST: a literal segment registered
         * after a {param} sibling binds as an id and 404s (see the note above the auction pool
         * routes), and this group will grow one.
         */
        Route::get('/player-history/pdf', [TournamentPlayerHistoryController::class, 'pdf'])->name('player-history.pdf');
        Route::get('/player-history', [TournamentPlayerHistoryController::class, 'index'])->name('player-history.index');
        // One player's own trail. Numeric-constrained as well as declared last, so `/pdf` above
        // can never be read as a player id.
        Route::get('/player-history/{player}/pdf', [TournamentPlayerHistoryController::class, 'showPdf'])
            ->whereNumber('player')->name('player-history.show-pdf');
        Route::get('/player-history/{player}', [TournamentPlayerHistoryController::class, 'show'])
            ->whereNumber('player')->name('player-history.show');

        // Template Editor (Superadmin only for CRUD)
        Route::middleware(['role:Superadmin'])->group(function () {
            Route::get('/templates/create', [TournamentTemplateController::class, 'create'])->name('templates.create');
            Route::post('/templates', [TournamentTemplateController::class, 'store'])->name('templates.store');
            Route::post('/templates/apply-preset', [TournamentTemplateController::class, 'applyPreset'])->name('templates.apply-preset');
            Route::put('/templates/{template}', [TournamentTemplateController::class, 'update'])->name('templates.update');
            Route::delete('/templates/{template}', [TournamentTemplateController::class, 'destroy'])->name('templates.destroy');
            Route::post('/templates/{template}/set-default', [TournamentTemplateController::class, 'setDefault'])->name('templates.set-default');
            Route::match(['get', 'post'], '/templates/{template}/preview', [TournamentTemplateController::class, 'preview'])->name('templates.preview');
            Route::post('/templates/{template}/duplicate', [TournamentTemplateController::class, 'duplicate'])->name('templates.duplicate');
            // Graceful GET fallbacks: these actions are POST-only, but prefetchers,
            // bookmarks, or a directly-opened action URL send GET and would 405.
            // Redirect them back to the template list instead of an error page.
            Route::get('/templates/{template}/duplicate', fn (\App\Models\Tournament $tournament) => redirect()->route('admin.tournaments.templates.index', $tournament));
            Route::get('/templates/{template}/set-default', fn (\App\Models\Tournament $tournament) => redirect()->route('admin.tournaments.templates.index', $tournament));
            Route::post('/templates/upload-overlay', [TournamentTemplateController::class, 'uploadOverlay'])->name('templates.upload-overlay');
            Route::post('/templates/delete-overlay', [TournamentTemplateController::class, 'deleteOverlay'])->name('templates.delete-overlay');
            Route::patch('/templates/{template}/update-size', [TournamentTemplateController::class, 'updateSize'])->name('templates.update-size');
            Route::post('/templates/{template}/update-background', [TournamentTemplateController::class, 'updateBackground'])->name('templates.update-background');
        });

        // Template listing, viewing, generate & download (Superadmin & Admin)
        // index/edit have controller middleware allowing only AJAX for Admin
        Route::middleware(['role:Superadmin|Admin'])->group(function () {
            Route::get('/templates', [TournamentTemplateController::class, 'index'])->name('templates.index');
            Route::get('/templates/{template}/edit', [TournamentTemplateController::class, 'edit'])->name('templates.edit');
            Route::get('/templates/generate', [TournamentTemplateController::class, 'generate'])->name('templates.generate');
            Route::post('/templates/generate-preview', [TournamentTemplateController::class, 'generatePreview'])->name('templates.generate-preview');
            Route::post('/templates/{template}/render-preview', [TournamentTemplateController::class, 'renderPreview'])->name('templates.render-preview');
            Route::get('/templates/{template}/download', [TournamentTemplateController::class, 'download'])->name('templates.download');
            Route::get('/matches/{match}/awards', [TournamentTemplateController::class, 'getMatchAwards'])->name('matches.get-awards');
            Route::post('/templates/generate-fixtures-poster', [TournamentTemplateController::class, 'generateFixturesPoster'])->name('templates.generate-fixtures-poster');
            Route::post('/templates/toggle-auto-welcome', [TournamentTemplateController::class, 'toggleAutoWelcome'])->name('templates.toggle-auto-welcome');
            Route::delete('/generated-posters/{poster}', [TournamentTemplateController::class, 'deleteGeneratedPoster'])->name('generated-posters.destroy');
            Route::post('/generated-posters/{poster}/set-match-poster', [TournamentTemplateController::class, 'setMatchPoster'])->name('generated-posters.set-match-poster');
        });

        // Banners / Ads
        Route::get('/banners', [TournamentBannerController::class, 'index'])->name('banners.index');
        Route::post('/banners', [TournamentBannerController::class, 'store'])->name('banners.store');
        Route::put('/banners/{banner}', [TournamentBannerController::class, 'update'])->name('banners.update');
        Route::delete('/banners/{banner}', [TournamentBannerController::class, 'destroy'])->name('banners.destroy');
        Route::post('/banners/reorder', [TournamentBannerController::class, 'reorder'])->name('banners.reorder');
        Route::post('/banners/{banner}/toggle', [TournamentBannerController::class, 'toggleActive'])->name('banners.toggle');

        // Tournament Calendar (Calendar-based fixture scheduling)
        Route::get('/calendar', [TournamentCalendarController::class, 'index'])->name('calendar.index');
        Route::post('/calendar/generate-slots', [TournamentCalendarController::class, 'generateSlots'])->name('calendar.generate-slots');
        Route::post('/calendar/auto-fill', [TournamentCalendarController::class, 'autoFill'])->name('calendar.auto-fill');
        Route::post('/calendar/clear-slots', [TournamentCalendarController::class, 'clearSlots'])->name('calendar.clear-slots');
        Route::get('/calendar/json', [TournamentCalendarController::class, 'getCalendarJson'])->name('calendar.json');
        Route::get('/calendar/unscheduled', [TournamentCalendarController::class, 'getUnscheduledJson'])->name('calendar.unscheduled');
    });

    // Match Summary Editor
    Route::prefix('matches/{match}')->name('matches.')->group(function () {
        Route::get('/summary', [MatchSummaryController::class, 'edit'])->name('summary.edit');
        Route::put('/summary', [MatchSummaryController::class, 'update'])->name('summary.update');
        Route::post('/summary/highlight', [MatchSummaryController::class, 'addHighlight'])->name('summary.add-highlight');
        Route::delete('/summary/highlight', [MatchSummaryController::class, 'removeHighlight'])->name('summary.remove-highlight');
        Route::post('/summary/award', [MatchSummaryController::class, 'assignAward'])->name('summary.assign-award');
        Route::delete('/summary/award/{award}', [MatchSummaryController::class, 'removeAward'])->name('summary.remove-award');
        Route::post('/summary/generate-poster', [MatchSummaryController::class, 'generatePoster'])->name('summary.generate-poster');
        Route::post('/summary/generate-match-poster', [MatchSummaryController::class, 'generateMatchPoster'])->name('summary.generate-match-poster');
        Route::post('/summary/generate-award-poster', [MatchSummaryController::class, 'generateAwardPoster'])->name('summary.generate-award-poster');
        Route::post('/summary/send', [MatchSummaryController::class, 'send'])->name('summary.send');
        Route::get('/summary/download-poster', [MatchSummaryController::class, 'downloadPoster'])->name('summary.download-poster');
        Route::get('/summary/preview-poster', [MatchSummaryController::class, 'previewPoster'])->name('summary.preview-poster');
        Route::post('/summary/create-default-awards', [MatchSummaryController::class, 'createDefaultAwards'])->name('summary.create-default-awards');
        Route::post('/summary/auto-assign-awards', [MatchSummaryController::class, 'autoAssignAwardsFromCricHeroes'])->name('summary.auto-assign-awards');
        Route::post('/summary/award/{award}/update-image', [MatchSummaryController::class, 'updateAwardPlayerImage'])->name('summary.update-award-image');
        Route::post('/summary/recalculate-statistics', [MatchSummaryController::class, 'recalculateStatistics'])->name('summary.recalculate-statistics');
    });

    // Tournament Awards Management
    Route::prefix('tournaments/{tournament}/awards')->name('tournaments.awards.')->group(function () {
        Route::get('/', [AwardTemplateController::class, 'index'])->name('index');
        Route::post('/', [AwardTemplateController::class, 'store'])->name('store');
        Route::delete('/{award}', [AwardTemplateController::class, 'destroy'])->name('destroy');
    });

    // Match Result
    Route::get('/matches/{match}/result', [MatchResultController::class, 'edit'])->name('matches.result.edit');
    Route::put('/matches/{match}/result', [MatchResultController::class, 'update'])->name('matches.result.update');
    Route::post('/matches/{match}/result/quick', [MatchResultController::class, 'quickUpdate'])->name('matches.result.quick');
    Route::post('/matches/{match}/result/cricheroes', [MatchResultController::class, 'fetchCricHeroesData'])->name('matches.result.cricheroes');
    Route::post('/matches/{match}/sync-cricheroes', [MatchResultController::class, 'syncCricHeroesScore'])->name('matches.sync-cricheroes');
    Route::post('/matches/{match}/result/scorecard-pdf', [MatchResultController::class, 'importScorecardPdf'])->name('matches.result.scorecard-pdf');
    Route::delete('/matches/{match}/result/scorecard-data', [MatchResultController::class, 'clearScorecardData'])->name('matches.result.clear-scorecard');
});
