# Scorecard Tables Feature — Implementation Notes

## Status: COMPLETED

---

## What Was Built

Match summary posters now support per-player scorecard tables showing **top 3 batsmen** and **top bowlers per innings** for both teams, sourced from `scorecard_data` JSON in `MatchResult`.

---

## Files Modified

### Models
- **`app/Models/TournamentTemplate.php`** — Added 4 placeholders to `match_summary` type: `batting_table_a`, `batting_table_b`, `bowling_table_a`, `bowling_table_b`

### Controllers (3 code paths for poster generation)
- **`app/Http/Controllers/Backend/Tournament/TournamentTemplateController.php`** — Scorecard extraction in `generatePreview()` for template preview page
- **`app/Http/Controllers/Backend/Tournament/MatchSummaryController.php`** — Scorecard extraction in `buildMatchData()` for summary page poster generation (`/admin/matches/{id}/summary`)
- **`app/Services/Poster/MatchSummaryPosterService.php`** — Scorecard extraction in `generateFromTemplate()` for auto-generated posters

### Rendering
- **`app/Services/Poster/TemplateRenderService.php`**
  - `renderElement()` — Dispatches `scorecardTable` type BEFORE value string cast (critical — array data causes ErrorException if cast to string)
  - `renderScorecardTable()` — Full GD rendering: team header bar, column headers, data rows with alternating backgrounds
  - `transparentBg` config option — Skips all background rectangles and dividers when enabled
  - Shadow default changed from `true` to `false` — Only renders shadow when explicitly set on element
  - Helper methods: `getScorecardBattingColumns()`, `getScorecardBowlingColumns()`, `darkenColorHex()`, `getSampleBattingData()`, `getSampleBowlingData()`

### Editor (Fabric.js)
- **`resources/views/backend/pages/tournaments/templates/editor.blade.php`**
  - Sidebar: "Scorecard Tables" section with 4 items (Team A/B Batting/Bowling), only for `match_summary` type
  - `addScorecardTable()` — Creates fabric.js Group preview (dashed border + title + monospace sample data)
  - Properties panel: Header/Row BG colors, text/accent color, font size, row height, max rows (2-5), transparent BG toggle
  - Style presets: Dark, Light, IPL
  - Save/load: `scorecardConfig` serialized in `layout_json`

### Views
- **`resources/views/backend/pages/tournaments/templates/preview.blade.php`** — `!is_array()` check to skip array placeholders in text fields
- **`resources/views/backend/pages/tournaments/templates/generate.blade.php`** — Info note about scorecard auto-population
- **`resources/views/backend/pages/matches/summary-editor.blade.php`** — Hidden innings selector for match summary poster

### Match Result
- **`app/Http/Controllers/Backend/MatchResultController.php`** — Clears cached summary poster on result save; fixed `winner_team_id` undefined key

---

## Scorecard Config (stored on element in layout_json)

```json
{
  "scorecardType": "batting",   // or "bowling"
  "team": "a",                  // or "b"
  "maxRows": 3,
  "transparentBg": false,       // true = no background rectangles, only text
  "headerBg": "#1e40af",
  "headerText": "#ffffff",
  "rowBg": "#1e293b",
  "altRowBg": "#334155",
  "textColor": "#ffffff",
  "accentColor": "#FFD700",
  "fontSize": 14,
  "rowHeight": 40
}
```

---

## Data Flow

```
scorecard_data (MatchResult JSON)
  → innings[0] = 1st batting team, innings[1] = 2nd batting team
  → Sort batsmen by runs desc, bowlers by wickets desc
  → Take top 3 each
  → Map to batting_table_a/b, bowling_table_a/b (respects team_a_batting_first swap)
  → Passed to TemplateRenderService::renderTemplate()
  → renderElement() dispatches scorecardTable BEFORE string cast
  → renderScorecardTable() draws via GD
```

---

## Key Bugs Fixed

1. **Array to string conversion** — `renderElement()` was casting `$value = (string)($data[$placeholder])` BEFORE dispatching `scorecardTable` type. Array data threw ErrorException, silently caught by per-element try-catch. Fix: moved dispatch before value resolution.

2. **Wrong controller** — Summary page uses `MatchSummaryController::buildMatchData()`, not `TournamentTemplateController::generatePreview()`. Scorecard extraction was missing from `buildMatchData()`.

3. **Cached poster** — Old poster without scorecard data was cached in `match_summaries.summary_poster`. Added cache clear on result save.

4. **Shadow default** — All text elements had shadow by default (`?? true`). Changed to `?? false`.

---

## Table Layouts

### Batting
```
Name          R    B    4s   6s
Player One   45   30    5    2
Player Two   38   25    4    1
Player Three 32   22    3    2
```
Column proportions: name=4%, R=60%, B=72%, 4s=84%, 6s=94%

### Bowling
```
Name          O    R    W   Econ
Bowler One   4.0  28   3   7.00
Bowler Two   4.0  32   2   8.00
Bowler Three 3.0  22   1   7.33
```
Column proportions: name=4%, O=55%, R=66%, W=77%, Econ=90%
