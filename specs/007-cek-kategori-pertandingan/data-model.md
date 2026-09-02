# Data Model: Cek Kategori Pertandingan

**Feature**: 007-cek-kategori-pertandingan  
**Date**: 2026-09-02

No schema change — reuse existing Supabase tables; this doc describes filtered views.

## Entities

### user_preferences (existing)

- `id` int PK
- `user_id` int (OWNER_ID)
- `sport_type` string enum `SportPrefsService::SPORTS` (8 values)
- `entity_id` string lowercased
- `entity_name` string display
- `notification_enabled` bool
- `created_at` datetime

**Validation**: `sport_type` in SPORTS after `normalizeSport`; unique `(user_id,sport_type,entity_id)` upsert via `addPreference`.
**Relationships**: 1 user → N prefs, grouped by sport_type for `/categories` count and for `/schedule [kategori]` filter.
**Indexes**: Supabase already has `user_id`, `sport_type` filterable via PostgREST eq.

### match_schedule (existing)

- `id` int/string PK
- `source_id` string `"{matchId}:u{userId}"`
- `sport_type` string (same enum)
- `competition` string nullable
- `home_team` string
- `away_team` string nullable
- `match_time` timestamptz (UTC ISO)
- `status` string `ns|scheduled|...`
- `notified` bool

**Validation**: `sport_type` + `match_time` required for schedule display; `isNext7Days(match_time)` window; cap 10 sorted asc.
**Relationships**: belongs to sport_type, logically linked to user via `source_id` suffix `:u{uid}` filtering in `BotRouter`.
**No migration**: filter via `?sport_type=eq.football` or in-memory `isNext7Days` + `sport_type===category`.

### Fixture (transient, not stored)

From `FootballService::getUpcomingFixtures()` etc. Shape `{home,away,league,date}` + implicit sport. Filtered via `NameMatcher::matches(team, entity_name)` + `MatchHelper::isNext7Days(date)`.

## Query patterns

- Bot `/schedule [kategori]`: `select user_preferences where user_id=eq.{uid} and sport_type=eq.{kategori?}` → if none → empty message. Then `select match_schedule where source_id like %:u{uid}` in-memory filter `sport_type` + `isNext7Days`. Fallback per sport only if DB empty for that sport.
- API `GET /api/matches?sport_type=football`: `select match_schedule where match_time>=now and sport_type=eq.football order match_time.asc limit 10`; for `motogp` group use `or` or multiple eq? Simplest: fetch then in-memory filter group `in_array`.
- Dashboard client filter: `filtered = all.filter(m => normalize(m.sport_type)===selected)`; badge counts `all.reduce((acc,m)=>acc[m.sport_type]++, {})`

## State lifecycle

No new states. `match_schedule.status` remains `ns`/`scheduled` for 7-day display. MotoGP group mapping is presentation-level aggregation, not DB enum change.

## Open questions

None — schema mirrors spec, no conformance deviation.
