# Research: Cek Kategori Pertandingan

**Feature**: 007-cek-kategori-pertandingan  
**Date**: 2026-09-02

## Decision: Bot single kategori arg parsing

- **Decision**: Single optional kategori arg: `str_starts_with` for `/schedule`, then `explode(' ', $text)` second token normalized via `SportPrefsService::normalizeSport`. Invalid → error list. No multi-kategori.
- **Rationale**: Ponytail shortest diff — reuse existing `handleJadwal` branch already matches `str_starts_with` vs exact. Single token parser one line, test simple.
- **Alternatives considered**: Multi space/comma — menambah split loop + ambiguous error handling, tidak diminta clarified answer A.

## Decision: MotoGP group aggregation

- **Decision**: `motogp` filter = aggregation semua `motogp,moto2,moto3,baggers`. Helper `isMotoGroup(sport)` → `in_array(..., ['motogp','moto2','moto3','baggers'])`. API `sport_type=motogp` juga aggregate.
- **Rationale**: Clarified A — owner mau cek "MotoGP kategori" sebagai satu grup, bukan per class. Tetap allow exact `/schedule moto2` if they want (handled via exact branch when normalize equals one of the four).
- **Alternatives**: Exact only — lebih presisi tapi tidak sesuai clarified desire; Both — adds complexity, deferred.

## Decision: Dashboard client-side filter

- **Decision**: Fetch once `GET /api/matches` (no param) → `matches` ref all → computed `filteredMatches = sportFilter.value==='all' ? matches : matches.filter(m=>normalizeSport(m.sport_type)===filter)`. Tabs do not refetch. Deep-link `?sport=` applied after fetch via `onMounted` reading `URLSearchParams` + `watch` pushState.
- **Rationale**: Clarified A — instant tab switch, no loading per click, minimal backend change. Single fetch ≤50 rows trivial.
- **Alternatives**: Server-side per-tab refetch — more network, needs loading state; Hybrid — added param handling for nothing.

## Decision: API sport_type optional filter

- **Decision**: `MatchController::index(Request $r)` reads `$r->query('sport_type')`. If present, normalize, validate vs `SPORTS` allowlist (plus alias). If invalid → 400 `invalid sport_type`. If `motogp` → whereIn 4 classes. Else `eq.sport_type`. No param → existing behavior.
- **Rationale**: Backward compat, testable via supabase `select` eq. Reuse `SportPrefsService::normalizeSport`.
- **Alternatives**: Separate endpoint `/api/matches/{sport}` — breaking, not needed.

## Decision: /categories bot command

- **Decision**: Add `handleCategories(int $uid,...)` counting `user_preferences` grouped by `sport_type` (normalized for mlbb). Return formatted list + hint. Add `MENU['categories']` and WELCOME line.
- **Rationale**: Dashboard tabs already give visual count from matches; bot count from prefs gives follow-specific insight. No new table.
- **Alternatives**: New `GET /api/categories` endpoint — optional, but bot-only already satisfies US3; could add later if frontend needs prefs count distinct from matches count.

## Decision: Existing design system reuse

- **Decision**: Tabs as pill buttons `px-3 py-1.5 rounded-full border` inactive `border-outline-variant/30 text-on-surface-variant`, active `bg-primary-container text-on-primary-container`. Badge `ml-1 text-xs`. No new library.
- **Rationale**: Matches `SportsPage.vue` existing `bg-surface-container border-outline-variant/20`.
- **Alternatives**: Import Headless UI tabs — unnecessary dependency.

## Alternatives overall rejected

- No migration, no new service class — reuse `BotRouter`, `MatchController`, `SupabaseService`.
