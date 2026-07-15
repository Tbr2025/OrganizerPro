# Plan: Remove "Create Player" & Redirect "Register as Player" to Public Form

## Context
Two changes for the Team Manager role:
1. **Remove "Add Player" / "Create Player"** — Team Managers should NOT be able to create players directly. Remove the route, controller methods, menu item, and all dashboard links.
2. **Change "Register as Player"** — Instead of an internal form, redirect to the public tournament registration form (`/t/{slug}/register/player`) with name & email prefilled. The normal admin-approval flow applies. The `RegistrationService` already handles "same email" by updating existing player records (no duplicate error).

## Files to Modify

### Part 1: Remove "Create Player"

| File | Change |
|------|--------|
| `app/Services/MenuService/AdminMenuService.php` (lines 137-143) | Remove the "Add Player" menu item array |
| `routes/web.php` (lines 268-269) | Remove `players/create` GET and `players` POST routes |
| `app/Http/Controllers/Backend/TeamManagerController.php` (lines 138-291) | Remove `createPlayer()` and `storePlayer()` methods |
| `resources/views/backend/pages/team-manager/dashboard.blade.php` (lines 63-68, 237-239, 376-378) | Remove 3x "Create New Player" buttons/links |
| `resources/views/backend/pages/team-manager/create-player.blade.php` | Delete entire file |

### Part 2: Change "Register as Player" → Public Registration

| File | Change |
|------|--------|
| `app/Http/Controllers/Backend/TeamManagerController.php` | Replace `registerAsPlayer()` — redirect to public form with prefilled query params; if multiple tournaments, show simple tournament picker |
| `app/Http/Controllers/Backend/TeamManagerController.php` | Remove `storeRegisterAsPlayer()` (public form handles submission) |
| `routes/web.php` (line 278) | Remove `POST /register-as-player` route |
| `resources/views/backend/pages/team-manager/register-as-player.blade.php` | Rewrite as a simple tournament-selector page (only shown when manager has multiple tournaments) |
| `resources/views/public/registration/fields/player-field.blade.php` (lines 27, 34) | For `first_name`, `last_name`, `email` fields: use `old($key, request()->query($key))` to support query-param prefilling |

## Detailed Steps

### Step 1: Remove "Add Player" menu item
In `AdminMenuService.php`, delete lines 137-143 (the `Add Player` child menu item).

### Step 2: Remove routes
In `routes/web.php`, delete:
```php
Route::get('/players/create', ...)->name('players.create');   // line 268
Route::post('/players', ...)->name('players.store');           // line 269
Route::post('/register-as-player', ...)->name('register-as-player.store'); // line 278
```

### Step 3: Remove controller methods
In `TeamManagerController.php`, delete:
- `createPlayer()` (lines 135-174)
- `storePlayer()` (lines 176-291)
- `storeRegisterAsPlayer()` (lines 678-834)

### Step 4: Rewrite `registerAsPlayer()` method
New logic:
```php
public function registerAsPlayer()
{
    $user = Auth::user();
    if ($user->player) {
        return redirect()->route('team-manager.dashboard')
            ->with('info', 'You are already registered as a player.');
    }

    $teams = $user->actualTeams()->with(['tournament', 'tournaments'])->get();
    if ($teams->isEmpty()) {
        return redirect()->route('team-manager.dashboard')
            ->with('error', 'You are not assigned to any team.');
    }

    // Collect all effective tournaments
    $tournaments = $teams->flatMap(fn($t) => $t->effectiveTournaments)->unique('id')->values();

    if ($tournaments->isEmpty()) {
        return redirect()->route('team-manager.dashboard')
            ->with('error', 'No tournaments found for your team.');
    }

    // Build prefill query params
    $nameParts = explode(' ', $user->name, 2);
    $params = [
        'first_name' => $nameParts[0] ?? '',
        'last_name'  => $nameParts[1] ?? '',
        'email'      => $user->email,
    ];

    // If only one tournament, redirect directly
    if ($tournaments->count() === 1) {
        return redirect(route('public.tournament.registration.player', $tournaments->first()) . '?' . http_build_query($params));
    }

    // Multiple tournaments: show selection page
    return view('backend.pages.team-manager.register-as-player', [
        'tournaments' => $tournaments,
        'prefillParams' => $params,
        'breadcrumbs' => ['title' => __('Register as Player')],
    ]);
}
```

### Step 5: Rewrite `register-as-player.blade.php`
Replace the full player form with a simple tournament selection page:
- Show a card for each tournament with name and date
- Each card links to `route('public.tournament.registration.player', $tournament) . '?' . http_build_query($prefillParams)`

### Step 6: Prefill public form fields
In `resources/views/public/registration/fields/player-field.blade.php`:
- `first_name` / `last_name`: change `value="{{ old($key) }}"` → `value="{{ old($key, request()->query($key)) }}"`
- `email`: change `value="{{ old('email') }}"` → `value="{{ old('email', request()->query('email')) }}"`

### Step 7: Remove dashboard "Create New Player" buttons
In `dashboard.blade.php`, remove:
1. Lines 63-68: Quick Actions "Create New Player" link
2. Lines 237-239: "Create New Player" link next to "Add Existing Player"
3. Lines 376-378: Empty state "Create New Player" link

### Step 8: Delete `create-player.blade.php`
Delete `resources/views/backend/pages/team-manager/create-player.blade.php`.

## Verification
1. Log in as Team Manager
2. Confirm "Add Player" is gone from sidebar menu
3. Confirm "Create New Player" buttons removed from dashboard
4. Click "Register as Player" → should redirect to public registration form (or tournament picker if multiple)
5. Public form should have first_name, last_name, email prefilled from manager's account
6. Submit registration → creates TournamentRegistration with status 'pending'
7. Admin approves → manager gets Player role and full player record
8. After registration, "Register as Player" button should disappear from dashboard
