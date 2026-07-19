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
