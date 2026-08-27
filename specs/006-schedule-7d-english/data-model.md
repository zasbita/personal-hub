# Data Model: Schedule 7 Days English — /schedule 1 Minggu

**Branch**: `006-schedule-7d-english` | **Date**: 2026-08-27 | **Spec**: `specs/006-schedule-7d-english/spec.md`

## Entities (no new tables)

### user_preferences (existing, Supabase)

| Column | Type | Constraints | Notes |
| ------ | ---- | ----------- | ----- |
| user_id | int | — | Telegram id |
| sport_type | text | `SportPrefsService::SPORTS` | same |
| entity_id | text | slug lower | same |
| entity_name | text | canonical | same |

### match_schedule (existing, Supabase)

| Column | Type | Constraints | Notes |
| ------ | ---- | ----------- | ----- |
| id | int PK | auto | — |
| source_id | text UK per sport | `id:uUID` | same |
| sport_type | text | `mobilelegend`, `futsal`, `football`, `volly`, `motogp` | same |
| competition | text | — | same |
| home_team | text | — | same |
| away_team | text | — | same |
| match_time | timestamptz UTC | ISO | display window now **0–168h** for `/schedule` (was 0–24h) |
| status | text | `NS`/`scheduled`/`FT` | same |
| notified | bool | — | same (H-1 lifecycle unchanged 20–30h) |

**Validation for /schedule 7d**: `match_time > now && <= now+168h` via `isNext7Days`; `status NS/scheduled` shown; cap 10 sorted asc.

### Fixture 7d (transient)

Shape `{id,date,home,away,league}` from 7-date fetch (football/volly) or many-day scrape (mlbb/wikipedia) filtered `isNext7Days`.

## State Lifecycle

- `match_schedule`: No change — H-1 insert 20–30h via `isOneDayAway`, not 7d. `/schedule` read-only display 7d window only.

## No Migrations

Reuse `supabase/` waves; display window change is client-side filter only.

