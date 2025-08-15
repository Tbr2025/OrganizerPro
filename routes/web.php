<?php

declare(strict_types=1);

use App\Http\Controllers\Backend\ActionLogController;
use App\Http\Controllers\Backend\ActualTeamController;
use App\Http\Controllers\Backend\AdminNotificationController;
use App\Http\Controllers\Backend\AppreciationController;
use App\Http\Controllers\Backend\AuctionAdminController;
use App\Http\Controllers\Backend\AuctionBiddingController;
use App\Http\Controllers\Backend\AuctionController;
use App\Http\Controllers\Backend\AuctionLiveController;
use App\Http\Controllers\Backend\AuctionOrganizerController;
use App\Http\Controllers\Backend\Auth\ScreenshotGeneratorLoginController;
use App\Http\Controllers\Backend\BallController;
use App\Http\Controllers\Backend\ClosedBidController;
use App\Http\Controllers\Backend\DashboardController;
use App\Http\Controllers\Backend\ImageTemplateController;
use App\Http\Controllers\Backend\LocaleController;
use App\Http\Controllers\Backend\MatchAppreciationController;
use App\Http\Controllers\Backend\MatchesController;
use App\Http\Controllers\Backend\ModulesController;
use App\Http\Controllers\Backend\OrganizationController;
use App\Http\Controllers\Backend\PermissionsController;
use App\Http\Controllers\Backend\PlayerController;
use App\Http\Controllers\Backend\PlayerProfileController;
use App\Http\Controllers\Backend\PostsController;
use App\Http\Controllers\Backend\ProfilesController;
use App\Http\Controllers\Backend\RolesController;
use App\Http\Controllers\Backend\ScorecardController;
use App\Http\Controllers\Backend\SettingsController;
use App\Http\Controllers\Backend\TeamController;
use App\Http\Controllers\Backend\TeamPlayerController;
use App\Http\Controllers\Backend\TermsController;
use App\Http\Controllers\Backend\TournamentController;
use App\Http\Controllers\Backend\TranslationController;
use App\Http\Controllers\Backend\UserLoginAsController;
use App\Http\Controllers\Backend\UsersController;
use App\Http\Controllers\Backend\PlayerVerificationController;
use App\Http\Controllers\PublicAuctionController;
use App\Http\Controllers\PublicPlayerController;
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

Route::get('/', 'HomeController@redirectAdmin')->name('index');
Route::get('/home', 'HomeController@index')->name('home');

/**
 * Admin routes.
 */





Route::get('admin/players/sample-csv', [PlayerController::class, 'downloadSampleCsv'])->name('players.sample');



Route::group(['prefix' => 'profileplayers', 'as' => 'profileplayers.', 'middleware' => ['auth']], function () {
    Route::get('/edit', [PlayerProfileController::class, 'edit'])->name('edit');
    Route::put('/edit', [PlayerProfileController::class, 'update'])->name('update');
});




// --- Main Admin Route Group for general pages ---
Route::group(['prefix' => 'admin', 'as' => 'admin.', 'middleware' => ['auth']], function () {

    // Auction Administration (CRUD for auctions)
    Route::resource('auctions', AuctionAdminController::class);



    Route::post('/auction/{auction}/player/{player}/final-price', [ClosedBidController::class, 'updateFinalPrice'])
        ->name('auction.player.final-price');
    // Closed bids
    Route::get('/auctions-closed-bids', [ClosedBidController::class, 'index'])
        ->name('auctions.closed-bids');

    Route::get('/auctions-closed-bids/fetch', [ClosedBidController::class, 'fetchClosedBids'])
        ->name('auctions.closed-bids.fetch');

    // Route::post('/auctions-closed-bids/{id}/update-final-price', [ClosedBidController::class, 'updateFinalPrice']);


    Route::delete('/auctions/{auction}/clear-pool', [AuctionAdminController::class, 'clearPool'])->name('auctions.clear-pool');
    Route::delete('/auctions/remove-player/{auctionPlayer}', [AuctionAdminController::class, 'removePlayer'])->name('auctions.remove-player');
    Route::post('/auctions/assign-player', [AuctionAdminController::class, 'assignPlayer'])->name('auctions.assign-player');
    // routes/web.php
    Route::get('/auctions/{auction}/latest-players', [AuctionAdminController::class, 'fetchPlayers'])
        ->name('admin.auctions.latest-players');

    Route::post('/auctions/add-bid', [AuctionAdminController::class, 'addBid'])
        ->name('auctions.players.addBid');
    Route::post('/auctions/decrease-bid', [AuctionAdminController::class, 'decreaseBid'])
        ->name('auctions.players.decreaseBid');

    Route::post('/auctions/close-bid', [AuctionAdminController::class, 'closeBid']);
});


// =====================================================================
// LIVE AUCTION ROUTES (Kept separate from the main admin group)
// =====================================================================

// --- Organizer Control Panel Routes ---
// URL Prefix: /admin/organizer/auction/{auction}
// Name Prefix: admin.auction.organizer.
Route::middleware(['auth'])
    ->prefix('admin/organizer/auction/{auction}')
    ->name('admin.auction.organizer.')
    ->group(function () {

        // **FIX**: Added route to SHOW the panel page
        Route::get('/panel', [AuctionOrganizerController::class, 'showPanel'])->name('panel');


        Route::prefix('api')->name('api.')->group(function () {
            Route::post('/start', [AuctionOrganizerController::class, 'startAuction'])->name('start');
            Route::post('/end', [AuctionOrganizerController::class, 'endAuction'])->name('end');
            Route::post('/toggle-pause', [AuctionOrganizerController::class, 'togglePause'])->name('toggle-pause');
            Route::post('/player-on-bid', [AuctionOrganizerController::class, 'putPlayerOnBid'])->name('player.onbid');
            Route::post('/sell-player', [AuctionOrganizerController::class, 'sellPlayer'])->name('player.sell');
            Route::post('/pass-player', [AuctionOrganizerController::class, 'passPlayer'])->name('player.pass');
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

        // API route for placing a bid
        Route::post('/api/place-bid', [AuctionBiddingController::class, 'placeBid'])->name('api.place-bid');
    });


// --- Public Display Route ---
Route::get('/auction/{auction}/live', [PublicAuctionController::class, 'showPublicDisplay'])
    ->name('public.auction.live');
Route::get('/auction/{auction}/sold', [PublicAuctionController::class, 'showPublicDisplaySold'])
    ->name('public.auction.sold');
// API endpoint for AJAX polling
Route::get('/auction/{auction}/active-player', [PublicAuctionController::class, 'activePlayer']);
Route::get('/auction/{auction}/sold-player', [PublicAuctionController::class, 'soldPlayer']);


Route::group(['prefix' => 'admin', 'as' => 'admin.', 'middleware' => ['auth']], function () {






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
        $returnVar = NULL;
        $output = NULL;
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

    Route::resource('actual-teams', ActualTeamController::class);
    Route::post('actual-teams/{actualTeam}/remove-member', [ActualTeamController::class, 'removeMember'])
        ->name('actual-teams.remove-member');

    // Auctions
    // Route::prefix('auctions')->as('auctions.')->group(function () {
    //     Route::get('/', [AuctionController::class, 'index'])->name('index');
    //     Route::get('/create', [AuctionController::class, 'create'])->name('create');
    //     Route::post('/', [AuctionController::class, 'store'])->name('store');
    //     Route::get('/{auction}', [AuctionController::class, 'show'])->name('show');
    //     Route::get('/{auction}/edit', [AuctionController::class, 'edit'])->name('edit');
    //     Route::put('/{auction}', [AuctionController::class, 'update'])->name('update');
    //     Route::delete('/{auction}', [AuctionController::class, 'destroy'])->name('destroy');

    //     // Live bidding
    // Route::get('/{auction}/live', [AuctionLiveController::class, 'index'])->name('live');
    // Route::post('/{auction}/bid', [AuctionLiveController::class, 'placeBid'])->name('bid');
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
    Route::resource('teams', TeamController::class);
    Route::resource('tournaments', TournamentController::class);
    Route::post('/players/{player}/intimate', [PlayerController::class, 'intimate'])->name('players.intimate');
    Route::post('/players/save-image', [PlayerController::class, 'saveImage'])->name('players.saveImage');
    Route::get('/players/{player}/image-editor', [PlayerController::class, 'editor'])
        ->name('players.image-editor');
    Route::post('/players/remove-background', [PlayerController::class, 'removeBackground'])->name('players.removeBackground');
    Route::post('/teams/{team}/players', [TeamPlayerController::class, 'store'])->name('teams.addPlayer');
    Route::delete('/teams/{team}/players/{player}', [TeamPlayerController::class, 'destroy'])->name('teams.removePlayer');

    Route::resource('players', PlayerController::class);
    Route::post('/players/export', [PlayerController::class, 'export'])->name('players.export');

    Route::post('players/import', [PlayerController::class, 'importCsv'])->name('players.import');

    Route::get('players/sample-csv', [PlayerController::class, 'downloadSampleCsv'])->name('players.sample');




    Route::resource('matches', MatchesController::class);
    Route::get('/matches/{match}/overs', [MatchesController::class, 'editOvers'])->name('overs.edit');
    Route::post('/matches/{match}/overs', [MatchesController::class, 'updateOvers'])->name('overs.update');
    Route::get('/matches/{match}/balls/create', [BallController::class, 'create'])->name('balls.create');
    Route::post('/matches/{match}/balls', [BallController::class, 'store'])->name('balls.store');


    Route::post('/matches/{match}/balls/ajax-store', [BallController::class, 'ajaxStore'])->name('balls.ajaxStore');
    Route::get('/matches/{match}/balls/summary', [BallController::class, 'summary'])->name('balls.summary');


    Route::get('/matches/{match}/scorecard', [ScorecardController::class, 'show'])->name('matches.scorecard');

    Route::resource('appreciations', MatchAppreciationController::class);
    Route::get('/matches/{match}/appreciations/create', [MatchAppreciationController::class, 'create'])
        ->name('matches.appreciations.create');

    Route::post('/appreciations/save/{tournament}/{match}/{player}', [AppreciationController::class, 'store'])->name('appreciations.save');

    Route::get('matches/{match}/appreciations/create', [MatchAppreciationController::class, 'create'])->name('admin.matches.appreciations.create');
    Route::post('matches/{match}/appreciations', [MatchAppreciationController::class, 'store'])->name('admin.matches.appreciations.store');
    Route::delete('match-appreciations/{appreciation}', [MatchAppreciationController::class, 'destroy'])->name('admin.matches.appreciations.destroy');

    Route::post('/players/{player}/approve', [PlayerVerificationController::class, 'approve'])->name('players.approve');
    // Route to reject a player
    Route::post('/players/{player}/reject', [PlayerVerificationController::class, 'reject'])->name('players.reject');




    Route::prefix('admin/templates')->name('admin.templates.')->group(function () {});


    Route::resource('image-templates', ImageTemplateController::class);
    Route::get('/image-templates/create', [ImageTemplateController::class, 'create'])->name('image-templates.create');

    Route::get('/image-templates/edit', [ImageTemplateController::class, 'edit'])->name('image-templates.edit');
    Route::post('/image-templates/save', [ImageTemplateController::class, 'store'])->name('image-templates.save');
    Route::get('/image-templates/generate/{player}', [ImageTemplateController::class, 'generateImage'])->name('image-templates.generate');

    Route::delete('/image-templates/{image_template}', [ImageTemplateController::class, 'destroy'])->name('image-templates.destroy');


    Route::get('/background/remove', function () {
        return view('background.remove'); // blade file
    })->name('background.form');



    Route::post('/image-templates/remove', [ImageTemplateController::class, 'removeTemplate'])
        ->name('image-templates.remove');


    Route::get('image-templates/remove-bg', [ImageTemplateController::class, 'removebg'])->name('image-templates.remove-bg');
    // Optional route to generate output image from a saved template
    Route::post('image-templates/{image_template}/generate', [ImageTemplateController::class, 'generate'])
        ->name('image-templates.generate');


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

    // Translation Routes
    Route::get('/translations', [TranslationController::class, 'index'])->name('translations.index');
    Route::post('/translations', [TranslationController::class, 'update'])->name('translations.update');
    Route::post('/translations/create', [TranslationController::class, 'create'])->name('translations.create');

    // Login as & Switch back
    Route::resource('users', UsersController::class);
    Route::delete('users/delete/bulk-delete', [UsersController::class, 'bulkDelete'])->name('users.bulk-delete');
    Route::get('users/{id}/login-as', [UserLoginAsController::class, 'loginAs'])->name('users.login-as');
    Route::post('users/switch-back', [UserLoginAsController::class, 'switchBack'])->name('users.switch-back');

    // Action Log Routes.
    Route::get('/action-log', [ActionLogController::class, 'index'])->name('actionlog.index');

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


// Route::get('/test-mail', function () {
//     \Illuminate\Support\Facades\Mail::raw('Test mail from Laravel on EC2', function ($message) {
//         $message->to('navasfazil@gmail.com')
//             ->subject('Test Email');
//     });

//     return 'Mail Sent!';
// });
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
