# Sportzley.com - Complete User Guide

**Website:** https://sportzley.com/
**Admin Panel:** https://sportzley.com/admin
**Last Updated:** March 2026

---

## Table of Contents

1. [Recent Updates Summary](#recent-updates-summary)
2. [User Roles & Access Levels](#user-roles--access-levels)
3. [Admin Panel Menu Guide](#admin-panel-menu-guide)
4. [Public URLs & Sharing Links](#public-urls--sharing-links)
5. [Feature Guides](#feature-guides)

---

## Recent Updates Summary

### New Features (March 2026)

| Feature | Description |
|---------|-------------|
| **Canva-Style Template Editor** | Drag-and-drop poster/certificate designer using Fabric.js |
| **Generate Poster Page** | Auto-generate posters with real tournament data |
| **IPL-Style Dashboard** | Professional tournament management dashboard |
| **Live Match Ticker** | 1920x1080 broadcast-ready live score ticker |
| **Player Registration System** | Online registration for players and teams |
| **Toss Management** | Record toss winner and decision (bat/bowl) |
| **Player Verification** | Email verification with password confirmation |
| **Background Removal** | Auto-remove player photo backgrounds |
| **Calendar Scheduling** | Visual match scheduling with calendar view |
| **Zones Management** | Geographic organization of tournaments |

---

## User Roles & Access Levels

### Role Hierarchy

```
Superadmin (Full Access)
    |
    +-- Admin (Most features, no critical settings)
    |
    +-- Organizer (Tournament management)
    |
    +-- Team Manager (Team & player management)
    |
    +-- Coach / Captain (View team & players)
    |
    +-- Scorer (Match scoring)
    |
    +-- Player (Own profile only)
    |
    +-- Viewer / Subscriber / Contact (Basic access)
```

---

### 1. Superadmin

**Login URL:** https://sportzley.com/login

**Full Access To:**
- All features and settings
- User management
- Role & permission management
- System monitoring (Laravel Pulse, Action Logs)
- Modules management
- Backup & restore
- Translations
- Organizations management

**Menu Access:**
| Menu Item | URL |
|-----------|-----|
| Dashboard | https://sportzley.com/admin |
| Roles & Permissions | https://sportzley.com/admin/roles |
| Users | https://sportzley.com/admin/users |
| Players | https://sportzley.com/admin/players |
| Teams | https://sportzley.com/admin/actual-teams |
| Matches | https://sportzley.com/admin/matches |
| Tournaments | https://sportzley.com/admin/tournaments |
| Auctions | https://sportzley.com/admin/auctions |
| Organizations | https://sportzley.com/admin/organizations |
| Zones | https://sportzley.com/admin/zones |
| Grounds | https://sportzley.com/admin/grounds |
| Appreciations | https://sportzley.com/admin/appreciations |
| Modules | https://sportzley.com/admin/modules |
| Monitoring > Action Logs | https://sportzley.com/admin/action-log |
| Monitoring > Laravel Pulse | https://sportzley.com/pulse |
| Settings > General | https://sportzley.com/admin/settings |
| Settings > Templates | https://sportzley.com/admin/image-templates |
| Settings > Translations | https://sportzley.com/admin/translations |
| Settings > Backup/Restore | https://sportzley.com/admin/backups |

---

### 2. Admin

**Similar to Superadmin but CANNOT access:**
- Role management
- User deletion
- Module management
- Organization management
- System monitoring
- Critical settings

**Menu Access:** Same as Superadmin except restricted items above.

---

### 3. Organizer

**Purpose:** Manage tournaments, matches, teams, and players

**Can Access:**
| Menu Item | URL | Description |
|-----------|-----|-------------|
| Dashboard | https://sportzley.com/admin | Overview |
| Tournaments | https://sportzley.com/admin/tournaments | Create & manage tournaments |
| Tournament Dashboard | https://sportzley.com/admin/tournaments/{id}/dashboard | IPL-style management |
| Teams | https://sportzley.com/admin/actual-teams | Manage teams |
| Players | https://sportzley.com/admin/players | View/create players |
| Matches | https://sportzley.com/admin/matches | Schedule & manage matches |
| Grounds | https://sportzley.com/admin/grounds | Manage venues |
| Zones | https://sportzley.com/admin/zones | View zones |
| Appreciations | https://sportzley.com/admin/appreciations | Player appreciations |
| Templates | https://sportzley.com/admin/image-templates | Design posters |

**Tournament Management Features:**
- Settings & configuration
- Team registrations
- Group management
- Fixture generation
- Point table management
- Statistics
- Awards

---

### 4. Team Manager

**Purpose:** Manage their assigned team and participate in auctions

**Simplified Menu:**
| Menu Item | URL | Description |
|-----------|-----|-------------|
| Team Dashboard | https://sportzley.com/team-manager/dashboard | Team overview |
| My Players | https://sportzley.com/admin/actual-teams | View team players |
| Add Player | https://sportzley.com/team-manager/players/create | Add new player |
| My Auctions | https://sportzley.com/team-manager/auctions | View assigned auctions |
| Matches | https://sportzley.com/admin/matches | View matches |

**Key Features:**
- Add players to their team
- View team roster
- Participate in live auctions
- View match schedules

---

### 5. Coach

**Purpose:** View team and player information

**Can Access:**
| Menu Item | URL |
|-----------|-----|
| Dashboard | https://sportzley.com/admin |
| Teams (View) | https://sportzley.com/admin/actual-teams |
| Players (View) | https://sportzley.com/admin/players |

---

### 6. Captain

**Purpose:** View own team and players

**Can Access:**
| Menu Item | URL |
|-----------|-----|
| Dashboard | https://sportzley.com/admin |
| Teams (View) | https://sportzley.com/admin/actual-teams |
| Players (View) | https://sportzley.com/admin/players |

---

### 7. Scorer

**Purpose:** Record match scores

**Can Access:**
| Menu Item | URL | Description |
|-----------|-----|-------------|
| Dashboard | https://sportzley.com/admin | Overview |
| Matches | https://sportzley.com/admin/matches | View & edit match scores |

---

### 8. Player

**Purpose:** View and manage own profile

**Can Access:**
| Menu Item | URL |
|-----------|-----|
| Dashboard | https://sportzley.com/admin |
| My Profile | https://sportzley.com/player/{id}/dashboard |

---

### 9. Viewer / Subscriber / Contact

**Purpose:** Basic read-only access

**Can Access:**
| Menu Item | URL |
|-----------|-----|
| Dashboard | https://sportzley.com/admin |
| Profile | Profile view/edit |

---

## Admin Panel Menu Guide

### Main Menu Structure

```
MAIN
├── Dashboard
├── Roles & Permissions (Superadmin/Admin)
│   ├── Roles
│   ├── New Role
│   └── Permissions
├── Users (Superadmin/Admin)
│   ├── Users List
│   └── New User
├── Players
│   ├── All Players
│   └── New Player
├── Organizations (Superadmin only)
│   ├── All Organizations
│   └── New Organization
├── Zones
│   ├── All Zones
│   └── New Zone
├── Teams
│   ├── All Teams
│   └── New Team
├── Matches
│   ├── All Matches
│   ├── New Match
│   └── Live Ticker (1920x1080)
├── Appreciations
│   └── All Appreciations
├── Tournaments
│   ├── All Tournaments
│   └── New Tournament
├── Grounds
│   ├── All Grounds
│   └── New Ground
├── Auctions
│   ├── All Auctions
│   ├── New Auction
│   ├── Closed Bids
│   └── LED Templates
└── Modules (Superadmin only)

MORE
├── Settings
│   ├── General Settings
│   ├── Templates
│   ├── Translations
│   └── Backup / Restore
├── Monitoring (Superadmin only)
│   ├── Action Logs
│   └── Laravel Pulse
└── Logout
```

---

## Public URLs & Sharing Links

### Tournament URLs

| Purpose | URL Format | Example |
|---------|------------|---------|
| Tournament Page | `https://sportzley.com/t/{slug}` | https://sportzley.com/t/ipl-2026 |
| Fixtures | `https://sportzley.com/t/{slug}/fixtures` | https://sportzley.com/t/ipl-2026/fixtures |
| Point Table | `https://sportzley.com/t/{slug}/point-table` | https://sportzley.com/t/ipl-2026/point-table |
| Statistics | `https://sportzley.com/t/{slug}/statistics` | https://sportzley.com/t/ipl-2026/statistics |
| Teams | `https://sportzley.com/t/{slug}/teams` | https://sportzley.com/t/ipl-2026/teams |

### Registration URLs

| Purpose | URL Format | Example |
|---------|------------|---------|
| Player Registration | `https://sportzley.com/t/{slug}/register/player` | https://sportzley.com/t/ipl-2026/register/player |
| Team Registration | `https://sportzley.com/t/{slug}/register/team` | https://sportzley.com/t/ipl-2026/register/team |
| General Player Registration | `https://sportzley.com/player/register` | Direct player signup |

### Match URLs

| Purpose | URL Format | Example |
|---------|------------|---------|
| Match Details | `https://sportzley.com/m/{match-id}` | https://sportzley.com/m/123 |
| Match Scorecard | `https://sportzley.com/m/{match-id}/scorecard` | https://sportzley.com/m/123/scorecard |
| Match Summary | `https://sportzley.com/m/{match-id}/summary` | https://sportzley.com/m/123/summary |
| Match Poster | `https://sportzley.com/m/{match-id}/poster` | https://sportzley.com/m/123/poster |

### Live Ticker URLs (For Broadcasting/Streaming)

| Purpose | URL Format | Example |
|---------|------------|---------|
| Public Live Ticker | `https://sportzley.com/live/{match-id}` | https://sportzley.com/live/123 |
| Alternative Ticker | `https://sportzley.com/m/{match-id}/ticker` | https://sportzley.com/m/123/ticker |

**Usage:** Add this URL as a browser source in OBS/Streamlabs for 1920x1080 live score display.

### Auction URLs

| Purpose | URL Format | Example |
|---------|------------|---------|
| Live Auction Display | `https://sportzley.com/auction/{id}/live` | For LED screens |
| Auction Results | `https://sportzley.com/auction/{id}/results` | Final results |
| Sold Players Display | `https://sportzley.com/auction/{id}/sold` | Sold list |

### Player URLs

| Purpose | URL Format |
|---------|------------|
| Player Dashboard | `https://sportzley.com/player/{id}/dashboard` |

---

## Feature Guides

### 1. Creating a Tournament

1. **Login** at https://sportzley.com/admin
2. Go to **Tournaments** > **New Tournament**
3. Fill in tournament details:
   - Name, description, dates
   - Set status to "registration" to enable registrations
   - Configure settings
4. After creation, access **Tournament Dashboard**

### 2. Tournament Dashboard Features

Access: https://sportzley.com/admin/tournaments/{id}/dashboard

| Tab | Function |
|-----|----------|
| **Overview** | Tournament stats and quick actions |
| **Settings** | Configure tournament options |
| **Registrations** | View/approve player & team registrations |
| **Groups** | Create and manage groups |
| **Fixtures** | Generate group stage & knockout fixtures |
| **Point Table** | Standings and qualifications |
| **Statistics** | Player and team statistics |
| **Awards** | Configure tournament awards |
| **Calendar** | Visual match scheduling |
| **Templates** | Generate posters |

### 3. Sharing Registration Links

From Tournament Dashboard, copy these links:

**For Players:**
```
https://sportzley.com/t/{tournament-slug}/register/player
```

**For Teams:**
```
https://sportzley.com/t/{tournament-slug}/register/team
```

### 4. Using the Template Editor

1. Go to **Settings** > **Templates**
2. Create new template or edit existing
3. Use drag-and-drop editor to:
   - Add text, images, shapes
   - Position elements
   - Set colors and fonts
4. Save and use in poster generation

### 5. Generating Posters

1. Go to **Tournament** > **Dashboard** > **Templates** > **Generate**
2. Select template
3. Choose data (player, match, etc.)
4. Generate and download poster

### 6. Setting Up Live Ticker for Streaming

1. Create/edit a match
2. Go to match details and click **Live Ticker**
3. Copy the public URL: `https://sportzley.com/live/{match-id}`
4. In OBS/Streamlabs:
   - Add Browser Source
   - Set URL to the live ticker link
   - Set resolution to 1920x1080
5. Update scores from admin panel - ticker updates in real-time

### 7. Running an Auction

1. Go to **Auctions** > **New Auction**
2. Configure:
   - Linked tournament
   - Participating teams
   - Player pool
   - Budget settings
3. Start auction from admin panel
4. Share public display URL for LED screens
5. Team managers can bid via their dashboard

### 8. Managing Registrations

1. Go to **Tournament** > **Registrations**
2. Review pending registrations
3. **Approve** or **Reject** individually
4. Use **Bulk Approve** for multiple registrations
5. Approved players/teams appear in the system

---

## Quick Reference Card

### Admin URLs

| Action | URL |
|--------|-----|
| Login | https://sportzley.com/login |
| Dashboard | https://sportzley.com/admin |
| Tournaments | https://sportzley.com/admin/tournaments |
| Matches | https://sportzley.com/admin/matches |
| Players | https://sportzley.com/admin/players |
| Teams | https://sportzley.com/admin/actual-teams |
| Auctions | https://sportzley.com/admin/auctions |

### Public URLs (Share with audience)

| Action | URL Format |
|--------|------------|
| Tournament | https://sportzley.com/t/{slug} |
| Player Registration | https://sportzley.com/t/{slug}/register/player |
| Team Registration | https://sportzley.com/t/{slug}/register/team |
| Live Ticker | https://sportzley.com/live/{match-id} |
| Match Details | https://sportzley.com/m/{match-id} |

---

## Support

For technical support or feature requests, please contact your development team.

---

*Document generated for Sportzley.com - Cricket Tournament Management Platform*
