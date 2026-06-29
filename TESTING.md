# Testing the Tournament Lifecycle (isolation · type · registration · auction pools)

This guide walks through verifying the three feature phases, both automatically and by clicking through the app.

---

## 0. One-time setup

```bash
# 1. Make the lifecycle emails readable (they'll be written to the log instead of sent)
#    Edit .env:  MAIL_MAILER=log
php artisan config:clear

# 2. Build the local schema + base data (roles, permissions, Superadmin, etc.)
php artisan migrate:fresh --seed

# 3. Add the demo test environment (2 orgs, 2 organizer logins, tournaments, an auction)
php artisan db:seed --class=DemoTestDataSeeder

# 4. Run the app (two terminals)
php artisan serve            # http://localhost:8000
npm run dev                  # or: npm run build  (regenerates the Vite manifest)
```

### Logins
| Role | Email | Password |
|------|-------|----------|
| Superadmin | `superadmin@sportzley.com` | `Super@Sportzley#2025123` |
| Organizer A (Alpha Sports) | `organizer.a@test.com` | `password` |
| Organizer B (Beta Cricket) | `organizer.b@test.com` | `password` |

### What the demo seeder creates
- **Alpha Sports** (org A): "Alpha Open Cup" (open, registration), "Alpha Auction League" (auction) with an Auction of **10 waiting players**, 3 teams, 13 approved + 2 pending players.
- **Beta Cricket** (org B): "Beta Open Cup" + a couple of players — used to prove isolation.

Reading emails: after any email action, open `storage/logs/laravel.log` (newest entries at the bottom).

---

## 1. Phase 1 — Organization data isolation

1. Log in as **Organizer A**.
   - `/admin/tournaments` → only **Alpha** tournaments (2).
   - `/admin/players` → only Alpha players.
   - `/admin/auctions` → only the Alpha auction.
2. Cross-org block: as **Superadmin**, open `/admin/tournaments` and note **Beta Open Cup**'s id (or hover its Edit link). Log back in as **Organizer A** and visit `/admin/tournaments/{thatId}/edit` → **404** (you cannot reach another org's data, even by direct URL).
3. Log in as **Organizer B** → sees only **Beta** data (1 tournament).
4. Log in as **Superadmin** → sees **everything** (3 tournaments, all players, all auctions).

✅ Pass = each organizer sees only their own org; Superadmin sees all; cross-org URLs 404.

---

## 2. Phase 2 — Tournament type, registration lifecycle, player attributes

**Tournament type (open vs auction)**
1. As **Organizer A**, `/admin/tournaments/create` → fill name/dates → set **Tournament Type = Auction** → save.
2. Edit it again → the type is persisted. (Open tournaments are for registration + teams; auction unlocks pools/retained/auction.)

**"In queue / under review" email on submit**
3. Open the public form: `http://localhost:8000/t/alpha-open-cup/register/player`.
4. Fill it in — note the new **State / Province** field next to Country — and submit.
5. Open `storage/logs/laravel.log` → you'll see the **"Application Received … your application is under review / you're in the queue"** email addressed to the applicant.

**Approval → greeting card**
6. As **Organizer A**, go to `/admin/tournaments/{alpha-open-cup id}/registrations` → find the pending applicant → **Approve**.
7. Check `storage/logs/laravel.log` again → a **welcome / greeting card** email (with the generated card) is sent. The registration/player is now **approved** (no longer "in queue").

**Retained-then-value guard**
8. As **Organizer A**, open a team and try to add a **pending** player as **Retained** → blocked: *"must complete and be approved in registration before they can be retained."*
9. Retain an **approved** player → allowed. (Value/points can only attach to a registered+approved player.)

**Nationality + State end-to-end**
10. `/admin/players/create` and any player's Edit page show **Country** and **State / Province**; both save and show on reload.

✅ Pass = type persists; applicant gets the under-review email; approval sends the card; pending players can't be retained; State saves everywhere.

---

## 3. Phase 3 — Auction pools, lots & per-team budgets

1. As **Organizer A** (or Superadmin), `/admin/auctions` → open **Alpha Auction League** → click **Pools & Lots**.
2. **Create a pool**: name "Pool A", capacity `50`, order mode **Odd then Even** → Create.
3. **Assign players**: in the "Unassigned players" box, tick several of the 10 players → choose Pool A → **Assign to pool**.
4. **Draw lots**: click **Draw lots** on Pool A → the players list shows lot numbers. With **Odd then Even**, the draw order is 1st, 3rd, 5th… then 2nd, 4th… (try **Sequential** or **Random** on another pool to see the difference).
5. **Second pool**: create "Pool B" (it gets the next sequence) → the live auction runs Pool A fully, then Pool B, each in lot order.
6. **Per-team budgets**: in the "Per-team budgets" panel, give teams different amounts → **Save budgets** (leave blank to use the uniform cap of 100,000).
7. **Budget enforcement**: in the organizer panel, selling a player to a team for more than its remaining budget is rejected.

✅ Pass = pools group players (cap respected), lot numbers follow the chosen mode, budgets save per team and are enforced on sell.

---

## 4. Automated tests (fastest confidence)

```bash
php artisan test                                          # full suite — expect 107 passed

# focused
php artisan test --filter=OrganizationIsolationTest        # Phase 1
php artisan test --filter=TournamentTypeTest               # Phase 2 (type)
php artisan test --filter=RegistrationLifecycleEmailsTest  # Phase 2 (emails)
php artisan test --filter=AuctionPoolOrderingTest          # Phase 3 (lots)
php artisan test --filter=AuctionTeamBudgetTest            # Phase 3 (budgets)
php artisan test --filter=AuctionPoolControllerTest        # Phase 3 (endpoints)
```

---

## Reset

Re-run steps 2–3 of setup any time to rebuild a clean demo environment:
```bash
php artisan migrate:fresh --seed && php artisan db:seed --class=DemoTestDataSeeder
```

---

# Auction (open & closed bid) + registration — end-to-end click-through

This section covers the live auction, team-manager bidding, the closed-bids page, and the email preview/editor.

## Where to log in

- **Production:** `https://sportzley.com/admin/login` — Superadmin `superadmin@sportzley.com` (your password).
- **Local:** `http://localhost:8000/admin/login` after the setup above.
  - Superadmin `superadmin@sportzley.com` / `Super@Sportzley#2025123`
  - Organizer A `organizer.a@test.com` / `password` · Organizer B `organizer.b@test.com` / `password`

**Bidding without team-manager passwords:** as Superadmin open **Admin → Users** and click **"Login as"** (`/admin/users/{id}/login-as`) on a team-manager user to impersonate them, place a bid, then switch back. Team Manager / Owner accounts are created automatically when a **team registration is approved**, and their passwords are emailed — locally, read them at the bottom of `storage/logs/laravel.log` (with `MAIL_MAILER=log`).

## URL cheat-sheet (replace `{id}` / `{slug}`)

| Flow | URL |
|------|-----|
| Admin login | `/admin/login` |
| Player registration (public) | `/t/{tournament-slug}/register/player` |
| Team registration (public) | `/t/{tournament-slug}/register/team` |
| Registrations to approve | `/admin/tournaments/{id}/registrations` |
| Auctions list | `/admin/auctions` |
| Create auction (4-step wizard) | `/admin/auctions/create` |
| Auction pools & per-team budgets | `/admin/auctions/{id}/pools` |
| **Organizer live panel** | `/admin/organizer/auction/{id}/panel` |
| Organizer offline panel | `/admin/organizer/auction/{id}/offline-panel` |
| **Team-manager bidding** | `/admin/team/auction/{id}/live` |
| Closed bids (awarded) page | `/admin/auctions-closed-bids` |
| Public big-screen display | `/auction/{id}/live` · `/auction/{id}/sold` · `/auction/{id}/results` |
| Email preview / editor | `/admin/emails/preview` |

> Tip: get a real `{id}`/`{slug}` from `/admin/auctions` and `/admin/tournaments`. The demo seeder creates **Alpha Auction League** (auction, 10 waiting players) and **alpha-open-cup** (registration).

## A. Player registration (public)
1. Open `/t/{slug}/register/player`.
2. Set **Visa = Work Visa** → Employer Name/Address/Position appear and are **required**; switch to another visa → they hide (and aren't required).
3. Tick **Available on Saturday** / **Available on Sunday** independently.
4. Submit → the applicant gets an **"Application Received / under review"** email (log).
5. As the organizer, approve at `/admin/tournaments/{id}/registrations` → **"Registration Approved"** + **welcome-card** emails are sent; the player becomes approved.

✅ Pass = conditional employer fields work, both day checkboxes save, both emails fire on submit/approve.

## B. Team registration (public)
1. Open `/t/{slug}/register/team` (a **different** form from the player one).
2. Submit team + captain (+ optional vice-captain).
3. Approve in registrations → **Team Manager** and **Owner** logins are created and credentials emailed (log). Use those — or **Login as** them — for bidding.

## C. Open-bid auction (live ascending)
1. `/admin/auctions/create` → step 1 set **Bid type = Open**; step 4 build pools (only the selected org's approved players appear; drag to set Custom order).
2. Open `/admin/organizer/auction/{id}/panel` → **Start** → a player comes up in pool→lot order.
3. Place raises with the team buttons, or impersonate team managers at `/admin/team/auction/{id}/live`.
4. **SELL** → the highest bid wins **only if within that team's remaining budget** (an over-budget sale is refused with a message); **PASS** → unsold.
5. Open `/auction/{id}/live` on a second screen for the audience display.

## D. Closed-bid auction (sealed bids → organizer picks the winner)
1. Create with **Bid type = Closed** (or toggle **CLOSED** in the panel).
2. Put a player on auction.
3. As each team (Login as → `/admin/team/auction/{id}/live`) submit a **sealed** bid — teams can't see each other's bids.
4. In the organizer panel click the **"B"** (Bids) side-panel button → all sealed bids are listed **highest first** → click **"Sold To This Team"** on the winner → confirm. Over-budget awards are refused.
5. Review awarded players at `/admin/auctions-closed-bids`; the **team filter** now returns that team's players.

✅ Pass = sealed bids are private, the organizer awards manually, budget caps hold, the closed-bids team filter works.

## E. Email preview / editor (Superadmin)
`/admin/emails/preview` → choose a tournament (or **Global default**) → the three emails render in iframes. **Edit** the HTML, **Preview draft**, **Save** (a per-tournament override beats the global default), **Reset to default**. Set the global **Brand name** at the top.

## F. Automated tests
```bash
php artisan test                                 # full suite — expect 140 passed

php artisan test --filter=ClosedBidFlowTest       # sealed-bid award + budget + closed-bids filter
php artisan test --filter=BudgetEnforcementTest   # open-bid sell respects budget
php artisan test --filter=AuctionOrgIsolationTest # no cross-org players in an auction
php artisan test --filter=AdminPagesRenderTest    # key admin pages render (no 500s)
```
