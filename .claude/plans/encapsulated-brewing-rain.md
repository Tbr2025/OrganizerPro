# Merge Welcome Card into Registration Approved Email

## Context
Currently, when a player registration is approved, **two separate emails** are sent:
1. Registration Approved email (no attachment)
2. Welcome Card email (poster attached)

The user wants **one combined email** on approval — the approved email should include the welcome card poster embedded in the body and attached as PNG. "Resend Welcome Card" should also send this combined email.

## Changes

### 1. Add `{welcome_card_image}` placeholder to Approved template
**File:** `app/Services/Email/EmailTemplateService.php`

**a) `types()` (~line 39):** Add `{welcome_card_image}` to `TYPE_APPROVED` placeholders

**b) `data()` (~line 210):** Add `'{welcome_card_image}' => ''` default (actual image injected as override)

**c) `seedApproved()` (~line 305):** Insert `{welcome_card_image}` in the template between the tournament details box and the success message. Also update the success message content:
- Remove "Stay tuned for further updates" generic text
- Add: "Your profile is all set! To view your profile, log in using the credentials shared in our previous email."
- Add "View Your Profile" button linking to `{complete_profile_url}`

Updated template structure:
```
[Colored Header with logos + "Registration Approved!"]
[Greeting + approved message]
[Tournament Details table]
[Welcome Card Image — {welcome_card_image}]  ← NEW
[Green info box: "Your profile is all set! Log in with credentials from previous email"]  ← UPDATED
[View Your Profile button]  ← NEW
[Contact info]
[Footer]
```

### 2. Enhance `RegistrationApprovedMail` to accept poster
**File:** `app/Mail/RegistrationApprovedMail.php`

- Add optional `?string $posterPath = null` constructor parameter
- In `resolved()`: if posterPath exists and is a valid file, convert to base64 and pass as `{welcome_card_image}` override
- In `attachments()`: if posterPath exists, attach it as `welcome-card.png`

### 3. Generate poster in approval flow and send ONE email
**File:** `app/Services/Tournament/RegistrationService.php`

**a) `approvePlayerRegistration()` (~line 336-340):**
- Remove `$this->sendGreetingCard($registration)` call
- Pass poster generation into `sendApprovalEmail()`:
  ```php
  $posterPath = $this->generateWelcomeCardPoster($registration);
  $this->sendApprovalEmail($registration, $posterPath);
  if ($posterPath) {
      $registration->markWelcomeCardSent();
  }
  ```

**b) `sendApprovalEmail()` (~line 684):**
- Accept optional `?string $posterPath = null` parameter
- Pass it to `RegistrationApprovedMail`:
  ```php
  $this->safeMail($email, new RegistrationApprovedMail($tournament, $registration, $posterPath));
  ```

**c) Add new `generateWelcomeCardPoster()` method:**
- Extract poster generation logic from `TournamentNotificationService::sendWelcomeCard()` (lines 70-99)
- Returns poster file path or null if no template exists
- Handles player data assembly (with `playing_team_name_ref` fallback)

### 4. Update "Resend Welcome Card" to use combined email
**File:** `app/Http/Controllers/Backend/Tournament/TournamentRegistrationController.php`

**`resendWelcome()` (~line 439):**
- Instead of calling `TournamentNotificationService::sendWelcomeCard()`, generate poster and send `RegistrationApprovedMail` with it:
  ```php
  $posterPath = app(RegistrationService::class)->generateWelcomeCardPoster($registration);
  Mail::to($email)->send(new RegistrationApprovedMail($tournament, $registration, $posterPath));
  $registration->markWelcomeCardSent();
  ```

### 5. Update "Resend Confirmation" to include poster too
**File:** `app/Http/Controllers/Backend/Tournament/TournamentRegistrationController.php`

**`resendConfirmation()` (~line 459):**
- When status is 'approved' and registration is a player, include poster:
  ```php
  if ($registration->status === 'approved') {
      $posterPath = $registration->isPlayerRegistration()
          ? app(RegistrationService::class)->generateWelcomeCardPoster($registration)
          : null;
      Mail::to($email)->send(new RegistrationApprovedMail($tournament, $registration, $posterPath));
  }
  ```

## Files Modified
1. `app/Services/Email/EmailTemplateService.php` — `types()`, `data()`, `seedApproved()`
2. `app/Mail/RegistrationApprovedMail.php` — constructor, `resolved()`, `attachments()`
3. `app/Services/Tournament/RegistrationService.php` — `approvePlayerRegistration()`, `sendApprovalEmail()`, new `generateWelcomeCardPoster()`
4. `app/Http/Controllers/Backend/Tournament/TournamentRegistrationController.php` — `resendWelcome()`, `resendConfirmation()`

## Not Changed
- `PlayerWelcomeMail` — kept for backward compatibility but no longer used in approval flow
- `TournamentNotificationService::sendWelcomeCard()` — kept for any other callers but approval flow no longer uses it
- Team registration approval — unchanged, still sends `RegistrationApprovedMail` without poster

## Verification
1. Approve a player registration → should receive ONE email with approved content + welcome card poster embedded + attached
2. "Resend Welcome Card" on a registration → should send the same combined email
3. "Resend Confirmation" on approved player → should include poster
4. Approve a team registration → should still work (no poster, just approval email)
5. If no welcome_card template exists → approval email still sends, just without the poster image
