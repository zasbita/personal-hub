# Data Model: Cek Jadwal 1 Hari via Telegram Bot

**Branch**: `003-cek-jadwal-bot` | **Date**: 2026-08-27 | **Spec**: `specs/003-cek-jadwal-bot/spec.md`

## Entities (read-only for this feature)

### match_schedule (existing, Supabase)

| Column | Type | Constraints | Notes |
| ------ | ---- | ----------- | ----- |
| id | int PK | auto | — |
| source_id | text UK per sport | `apiId:uUserId` via `MatchHelper::sourceId` | deduplicate key |
| sport_type | text | `football`,`volly`,`motogp`,`moto2`,`moto3`,`baggers` | filter per pref |
| competition | text | — | `league` atau `raceName` |
| home_team | text | — | team atau `circuitName` |
| away_team | text | — | team atau `locality,country` |
| match_time | timestamptz UTC | — | `date` ISO, window `now .. now+24h` |
| status | text | `NS`/`scheduled`/`FT` | only `NS`/`scheduled` displayed |
| notified | bool | — | not filtered by `/jadwal`; all NS shown |

**Validation for /jadwal**: `match_time` parseable ISO, `status IN (NS,scheduled)`, `match_time > now && <= now+24h`. Query: `select(..., ['status'=>'eq.NS', 'match_time'=>'gte.<now>'])` then client `isInWindow`.

### sport_preferences (existing, Supabase)

| Column | Type | Constraints | Notes |
| ------ | ---- | ----------- | ----- |
| user_id | int | — | Telegram id |
| sport_type | text | `SportPrefsService::SPORTS` | — |
| entity_name | text | — | canonical name |
| notification_enabled | bool | — | only `true` considered |

**Relationships**: `sport_preferences -(user_id,entity_name)-> match_schedule(home_team/away_team match via NameMatcher)` — logical, not FK.

### Telegram Update (transient)

| Field | Type | Notes |
| ----- | ---- | ----- |
| message.from.id | int | `OWNER_ID` check |
| message.chat.id | int | reply target |
| message.text | text | `/jadwal` / `/schedule` / `/next` |

### External Fixture (transient, football)

Shape from `FootballService::fetch`: `{id, date, home, away, league, status=NS}`. Used only when DB empty per sport.

### External Race (transient, MotoGP)

Shape from `MotoGPService::getCurrentSeasonRaces`: `{classification, round, session, date, time, raceName, Circuit{circuitName, Location{locality,country}}}`. `iso = date+"T"+(time||"00:00:00")`.

## State Lifecycle

- `match_schedule`: No state change by `/jadwal` (read-only). Lifecycle tetap `NS/notified false → NS/notified true → FT` via `bot:schedule`/`bot:notify`.
- `sport_preferences`: Read-only.
- Fixture/Race external: `NS/scheduled` shown, `FT`/past skipped.

## Relationships Diagram

See `plan.md` ERD — `sport_preferences` filters `match_schedule` & API results via `NameMatcher::matches` / `MotoGPService::matchesRace`.

## No New Tables or Migrations

Reuse existing `supabase/` wave migrations. Idempotent `match_schedule` already created.

