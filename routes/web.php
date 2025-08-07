<?php

declare(strict_types=1);

use App\Http\Controllers\Backend\ActionLogController;
use App\Http\Controllers\Backend\AppreciationController;
use App\Http\Controllers\Backend\Auth\ScreenshotGeneratorLoginController;
use App\Http\Controllers\Backend\BallController;
use App\Http\Controllers\Backend\DashboardController;
use App\Http\Controllers\Backend\ImageTemplateController;
use App\Http\Controllers\Backend\LocaleController;
use App\Http\Controllers\Backend\MatchAppreciationController;
use App\Http\Controllers\Backend\MatchesController;
use App\Http\Controllers\Backend\ModulesController;
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
use App\Http\Controllers\PublicPlayerController;
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




Route::group(['prefix' => 'admin', 'as' => 'admin.', 'middleware' => ['auth']], function () {
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



    Route::get('/background/remove', function () {
        return view('background.remove'); // blade file
    })->name('background.form');

    Route::post('/background/remove', [ImageTemplateController::class, 'remove'])->name('background.remove');


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


Route::get('/test-mail', function () {
    \Illuminate\Support\Facades\Mail::raw('Test mail from Laravel on EC2', function ($message) {
        $message->to('navasfazil@gmail.com')
            ->subject('Test Email');
    });

    return 'Mail Sent!';
});
