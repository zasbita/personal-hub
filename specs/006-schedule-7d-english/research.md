# Research: Schedule 7 Days English — 7 Hari 168h

**Branch**: `006-schedule-7d-english` | **Date**: 2026-08-27

## Q1: 24h → 7d window dimana saja?

- **Decision**: Ubah `MatchHelper::NEXT_7D_HOURS=168` + `isNext7Days(iso, now)` wrapper `isInWindow(now, now+168h)`. Pakai di `BotRouter::handleJadwal` DB filter `isNext7Days(match_time)` dan fallback `isNext7Days(fixture date)` untuk semua sport (football/volly/mlbb/futsal). Keep `isNext24Hours` tidak hapus (legacy, H-1 scheduler pakai `isOneDayAway`).
- **Rationale**: Single source of truth untuk window display — tune 168h here, not per caller (ponytail). DB `match_time` UTC, filter UTC, display WIB tetap. H-1 insert window (20–30h) tetap terpisah — tidak diubah (scheduler lifecycle H-1 tetap).
- **Alternatives considered**: Hardcode `+7 days` per service — ditolak (duplikat). Ubah `isNext24Hours` constant 24→168 — ditolak (breaking legacy tests & scheduler confusion).

## Q2: Fetch 7 dates vs 2 dates (quota)?

- **Decision**: `FootballService::getUpcomingFixtures` loop `for i=0..6 addDays` (7 HTTP calls, cached 3h `Cache::remember('football.upcoming', 3h)`), `VolleyballService` sama 7 dates. MLBB `id-mpl.com` scrape & Futsal Wikipedia scrape already return many days (weeks) — cuma filter `isNext7Days`, tidak perlu extra fetch.
- **Rationale**: `/schedule` fallback harus cover 7 hari benar — fetch 2 hari akan miss 3–7 hari. 7 req per fallback fallback * cache 3h = max ~56 req/day worst (8 schedule hits *7) masih under 100 free plan. Caching mitigates.
- **Alternatives considered**: Keep 2 days fetch + just widen DB window — ditolak (fallback incomplete). Fetch 7 days without cache — ditolak (quota risk).

## Q3: Command English vs alias?

- **Decision**: Primary `schedule` di `MENU` (`schedule => "Check schedule next 7 days — /schedule"`), handler `if (in ['/jadwal','/schedule','/next'])` keep all. `WELCOME` line `/schedule — check schedule next 7 days` English. Menu publish via `bot:webhook` akan English.
- **Rationale**: Backward compat — user lama ketik `/jadwal` tetap work, tapi docs/menu English. Single handler, no new command.
- **Alternatives considered**: Hapus `/jadwal` — ditolak (breaking).

## Q4: Header/messages English?

- **Decision**: `handleJadwal` strings → English: header `📅 Schedule next 7 days`, empty `📭 No schedule in the next 7 days.`, overflow `… and N more`, error `⚠️ Failed to fetch schedule: ...` Keep `Unauthorized` already English.
- **Rationale**: Request explicit bahasa Inggris untuk schedule feature.
- **Alternatives considered**: Bilingual — ditolak (user minta English).

## Q5: Tests perlu ubah?

- **Decision**: Existing `BotRouterJadwalTest`, `BotRouterMlbbTest`, `BotRouterFutsalTest` yang assert `Jadwal 24 jam` atau `Tidak ada jadwal...` perlu update ke English + 7 days window (e.g., fixture +2d now should pass). New `isNext7Days` unit test.
- **Rationale**: Window widened changes test expectations.

## Open Items

- None — all unknowns resolved from existing code + 7d window decision.
