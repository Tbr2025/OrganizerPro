# OrganizerPro

## Deployment

When asked to deploy, run:
```bash
ssh -i ~/Desktop/key/"LightsailDefaultKey-ap-south-1 (1).pem" ubuntu@13.232.249.159 "cd /var/www/laravel-app && git pull origin main && sudo chown -R www-data:www-data storage bootstrap/cache && sudo chmod -R 775 storage bootstrap/cache && php artisan optimize:clear"
```

- **Host:** 13.232.249.159
- **User:** ubuntu
- **Key:** ~/Desktop/key/LightsailDefaultKey-ap-south-1 (1).pem
- **Project Path on Server:** /var/www/laravel-app

## Tournament Types: Open vs Auction

Tournaments have a `type` field (enum: `open` | `auction`, default: `open`). Each type has a completely separate team-building and player-assignment process. **Never mix their logic.**

### Open Tournaments
- Team managers **manually select/add players** from approved registrants
- No budget, no auction, no retained players
- Player mode is always `normal`
- UI shows "Add Player" controls on team manager dashboard

### Auction Tournaments
- Players are acquired **only through the auction process**
- Team managers **cannot manually add players** — roster UI is hidden
- Supports **retained players** (`player_mode = 'retained'`) with a `retained_value` that counts against team budget
- Budget tracking: total spend = auction bids + retained player values
- Requires `Auction` record with `max_budget_per_team`, squad size settings
- Player modes: `retained`, `normal`, `not_selected`

### Key Code Locations
| What | Where |
|------|-------|
| Type constants & helpers | `Tournament::TYPE_OPEN`, `TYPE_AUCTION`, `isAuction()`, `isOpen()` |
| Type check in team manager | `TeamManagerController` — `$isAuctionTeam` flag controls UI |
| Auction module | `AuctionAdminController`, `AuctionOrganizerController` |
| Retained player logic | `ActualTeamController` — budget/retained calculations |
| Player mode migration | `2025_08_11_113152_add_player_mode_to_players.php` |
| Type migration | `2026_06_27_000003_add_type_to_tournaments_table.php` |

### Rules for Future Development
- Always check `$tournament->isAuction()` before showing auction-specific UI or logic
- Open tournaments must never reference budgets, retained values, or auction features
- Auction tournaments must never allow manual player addition by team managers
- Default to `open` behavior for backwards compatibility

## Team Concepts — Three Distinct Entities

The word "team" refers to **three different things** in this codebase. Never confuse them.

### 1. Registration Teams (Player Registration)
- Created during **player registration** — a player registers *for* a team
- Represents the team a player is associated with at signup
- Stored via the registration/player models
- Used in: registration forms, player approval, squad building

### 2. Tournament Teams (Actual Teams)
- The **actual teams** that participate in a tournament
- Created/managed by organizers or team managers
- Have budgets (auction), rosters, logos, etc.
- Used in: team management, auction, squad views

### 3. Match Teams (Teams in Matches)
- The two teams assigned to a **specific match/fixture**
- Linked from fixtures/match records
- **Poster-related features always relate to match teams** — team logos, names, and branding on posters come from the match context, not registration or tournament teams
- Used in: fixtures, scorecards, match posters, match results

### Rules
- When working on **posters**, always use match team data (not registration or tournament teams)
- Do not assume a "team" in one context is the same model/table as in another
- Clarify which "team" is meant before making changes

## Email Template System

Customizable email templates with placeholder tokens, per-tournament overrides, and a visual editor.

### Architecture
- **`EmailTemplateService`** — Central service: defines template types/placeholders (`types()`), builds placeholder data (`data()`), resolves templates, and holds seed defaults (`seedXxx()` methods)
- **`EmailTemplate` model** — Stored overrides (global or per-tournament) in `email_templates` table. Types defined as constants: `TYPE_UNDER_REVIEW`, `TYPE_APPROVED`, `TYPE_WELCOME_CARD`, `TYPE_RETAINED_WELCOME_CARD`
- **`PlayerWelcomeMail`** — Mailable that resolves templates via `EmailTemplateService::resolve()`. Accepts `$type` and `$overrides` params to handle both welcome card and retained welcome card
- **`TournamentNotificationService`** — Orchestrates sending: generates poster → logs notification → sends mail → marks as sent

### Template Resolution Order
1. Tournament-specific override (`email_templates` where `tournament_id = X`)
2. Global override (`email_templates` where `tournament_id IS NULL`)
3. Built-in seed default (`EmailTemplateService::defaults()`)

### Email Types & Their Templates
| Type | Seed Method | Layout Style | Key Placeholders |
|------|------------|-------------|-----------------|
| Under Review | `seedUnderReview()` | Yellow warning box | `{applicant_name}`, `{registration_type_label}` |
| Approved | `seedApproved()` | Green success box + View Tournament button | `{recipient_name}`, `{team_name}` |
| Welcome Card | `seedWelcome()` | Complete Profile button + poster attachment | `{player_name}`, `{complete_profile_url}` |
| Retained Welcome Card | `seedRetainedWelcome()` | Approved-style: green box with retained value, NO profile button + poster attachment | `{player_name}`, `{team_name}`, `{retained_value}` |

### Common Placeholders (all types)
`{brand_name}`, `{tournament_name}`, `{tournament_logo}`, `{tournament_start_date}`, `{tournament_location}`, `{tournament_url}`, `{primary_color}`, `{secondary_color}`, `{header_text_color}`, `{header_logos}`, `{app_url}`, `{contact_info}`

### Key Code Locations
| What | Where |
|------|-------|
| Template service | `app/Services/Email/EmailTemplateService.php` |
| Template model | `app/Models/EmailTemplate.php` |
| Welcome card mailable | `app/Mail/PlayerWelcomeMail.php` |
| Notification orchestration | `app/Services/Notification/TournamentNotificationService.php` |
| Email preview/editor UI | Admin route `/admin/emails/preview` |
| Poster template rendering | `app/Services/Poster/TemplateRenderService.php` |

### Rules for Future Development
- New email types: add constant to `EmailTemplate`, add entry in `types()`, add seed method, add placeholder defaults in `data()`
- Retained welcome card follows **Approved** layout (NOT Welcome Card layout) — no "Complete Your Profile" button
- `{retained_value}` default is empty string; actual value passed via `$overrides` from `TournamentNotificationService`
- When resetting templates, create a migration that deletes rows from `email_templates` so seed defaults take effect
- Poster attachments are generated by `TemplateRenderService` using tournament poster templates (`TournamentTemplate` model), separate from email templates
