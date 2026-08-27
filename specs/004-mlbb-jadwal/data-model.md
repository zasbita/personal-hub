# Data Model: Jadwal Mobile Legends (MPL ID) 24 Jam

**Branch**: `004-mlbb-jadwal` | **Date**: 2026-08-27 | **Spec**: `specs/004-mlbb-jadwal/spec.md`

## Entities (no new tables)

### user_preferences (existing, Supabase)

| Column | Type | Constraints | Notes |
| ------ | ---- | ----------- | ----- |
| user_id | int | — | Telegram id |
| sport_type | text | `SportPrefsService::SPORTS` includes `mobilelegend` (canonical) | alias `mlbb`/`ml` normalized before insert |
| entity_id | text | slug lower | e.g. `onic` |
| entity_name | text | — | canonical e.g. `ONIC` |
| notification_enabled | bool | — | only `true` considered for jadwal/schedule/notify |

### match_schedule (existing, Supabase)

| Column | Type | Constraints | Notes |
| ------ | ---- | ----------- | ----- |
| id | int PK | auto | — |
| source_id | text UK per sport | `mlbbId:uUserId` via `MatchHelper::sourceId` | dedup key, e.g. `mlbb-123:u99` |
| sport_type | text | `mobilelegend` | per-sport isolation |
| competition | text | — | `MPL Indonesia S15` atau tournament |
| home_team | text | — | `ONIC` |
| away_team | text | — | `EVOS` |
| match_time | timestamptz UTC | ISO `date` | window `isNext24Hours` (0–24h) atau `isOneDayAway` 20–30h (scheduler) |
| status | text | `NS`/`scheduled`/`FT` | `NS`/`scheduled` displayed |
| notified | bool | — | guard notifier |

**Validation for MLBB**: `match_time` parseable ISO UTC, `status NS/scheduled` shown, `match_time > now && <= now+24h` for `/jadwal`.

### MLBB Fixture (transient, provider)

Shape via `MobileLegendService::getUpcomingMatches`: `{id: string, date: string ISO UTC, home: string, away: string, league: string, status: string}`. Same as `FootballService::fetch` — reused by BotRouter/Scheduler. `searchTeams` returns `string[]` team names deduped.

## State Lifecycle

- `match_schedule` MLBB: No state change by `/jadwal` (read-only) except via scheduler/notifier: `NS/false` (H-1) → `NS/true` (notified 1h) → `FT` (optional, not reported initially).
- `user_preferences` MLBB: `notification_enabled true` → `false` (unfollow/update), toggled via `/unfollow` or `DELETE`.

## Relationships

- `user_preferences mobilelegend` —(NameMatcher)→ `match_schedule mobilelegend` logical via `NameMatcher::matches(home, entity_name) || matches(away, entity_name)` — no FK.
- `MLBB Fixture` —(NameMatcher)→ `user_preferences` same.

## No Migrations

Reuse `supabase/` waves; no DDL for MLBB.

## Cache Keys

- `mlbb.upcoming` 3h (fixture list today+tomorrow or next N)
- `mlbb.teams.<lower query>` 1d (search)

