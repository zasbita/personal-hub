# Data Model: Timnas Futsal Indonesia — Jadwal 24 Jam

**Branch**: `005-timnas-futsal` | **Date**: 2026-08-27 | **Spec**: `specs/005-timnas-futsal/spec.md`

## Entities (no new tables)

### user_preferences (existing, Supabase)

| Column | Type | Constraints | Notes |
| ------ | ---- | ----------- | ----- |
| user_id | int | — | Telegram id |
| sport_type | text | `futsal` canonical | alias `timnas`/`garuda` normalized → `Indonesia` entity |
| entity_id | text | `indonesia` slug | constant |
| entity_name | text | `Indonesia` canonical | constant |
| notification_enabled | bool | — | only `true` considered |

### match_schedule (existing, Supabase)

| Column | Type | Constraints | Notes |
| ------ | ---- | ----------- | ----- |
| id | int PK | auto | — |
| source_id | text UK per sport | `futsal-{date}:uUID` | e.g. `futsal-20250905T0530:u99` |
| sport_type | text | `futsal` | per-sport isolation |
| competition | text | — | `CFA International`, `ASEAN Futsal`, `AFC Asian Cup` |
| home_team | text | `Indonesia` | home or away |
| away_team | text | `Cambodia` | lawan |
| match_time | timestamptz UTC | ISO `date` UTC | window `isNext24Hours` 0–24h / `isOneDayAway` 20–30h |
| status | text | `NS`/`scheduled`/`FT` | `NS` displayed |
| notified | bool | — | guard notifier |

**Validation for futsal**: `match_time` parseable UTC, `status NS/scheduled` shown, `match_time > now && <= now+24h`.

### Timnas Fixture (transient, Wikipedia)

Shape via `FutsalService::getUpcomingMatches`: `{id, date (ISO UTC), home, away, league, status}`. Parsed from `Indonesia v Cambodia + "5 September 2025 13:30 UTC+8"`.

## State Lifecycle

- `match_schedule` futsal: `NS/false` (H-1) → `NS/true` (notified 1h) → `FT` (optional). No change by `/jadwal` read-only except scheduler/notifier.
- `user_preferences` futsal: single row `Indonesia` enabled true → false on unfollow.

## Relationships

- `user_preferences futsal Indonesia` —(contains Indonesia)→ `match_schedule futsal` logical — no FK, trivial NameMatcher.

## No Migrations

Reuse `supabase/` waves; no DDL.

## Cache Keys

- `futsal.upcoming` 3h (fixture list next)
- `futsal.teams` 1d (static `['Indonesia']`)
