# Research: Timnas Futsal Indonesia — Jadwal 24 Jam

**Branch**: `005-timnas-futsal` | **Date**: 2026-08-27

## Q1: Sumber jadwal Timnas Futsal mana yang paling lazy?

- **Decision**: Wikipedia `en.wikipedia.org/wiki/Indonesia_national_futsal_team` section `Results and fixtures` (scrape `Indonesia v Cambodia 5 Sep 2025 13:30 UTC+8`) + fallback Wikipedia `Indonesia_national_futsal_team_results`. Behind `Cache::remember('futsal.upcoming', 3h)`.
- **Rationale**: Semua timnas fixtures sudah ada di Wikipedia dengan date + time + UTC offset eksplisit per match (`13:30 UTC+8` Hebei, `18:30 UTC+7` Jakarta) — tanpa key, gratis, cache 3h seperti `football.upcoming`. AFC `the-afc.com` punya jadwal tapi butuh JS render + API tidak publik. PSSI tidak list futsal (FFI handle terpisah).
- **Alternatives considered**: Scrape `the-afc.com` AFC Asian Cup — ditolak (JS heavy). PSSI `pssi.org/national-team` — tidak ada futsal. `pflindonesia.com` — untuk liga klub, bukan timnas. Manual Supabase insert tanpa scrape — bisa tapi kehilangan fallback otomatis; service tetap `[]` graceful jika scrape gagal.

## Q2: Kenapa single team Indonesia saja, tidak dinamis?

- **Decision**: Validasi statis `timnas|garuda|indonesia → Indonesia` + `searchTeams() => ['Indonesia']` static, `NameMatcher` trivial `stripos(home,'Indonesia')`.
- **Rationale**: Timnas cuma 1 entitas yang user follow — tidak seperti liga dengan 12 tim. Dinamis `searchTeams` via Wikipedia list tim lain tidak diperlukan; ponytail ceiling: `futsal Thailand` di luar scope single-owner ditolak dengan guidance.
- **Alternatives considered**: Dinamis search lawan (Thailand, Vietnam…) dari scrape — ditolak (tidak perlu follow lawan; user cuma follow Indonesia).

## Q3: Timezone Wikipedia UTC+7/UTC+8 → UTC?

- **Decision**: Parse `13:30 UTC+8` atau `18:30 UTC+7 (GBK)` dengan regex `(\d{1,2}:\d{2})\s*UTC\+(\d+)` lalu `new DateTimeImmutable("2025-09-05 13:30", new DateTimeZone("Asia/Shanghai"))` untuk UTC+8 atau `Asia/Jakarta` untuk UTC+7, convert `setTimezone(UTC)` sebelum `isNext24Hours` check, display `Asia/Jakarta` via `DisplayTime`.
- **Rationale**: Wikipedia tulis offset eksplisit per match — lebih reliable dari asumsi WIB default `Asia/Jakarta`. `id-mpl.com` scrape juga WIB→UTC.
- **Alternatives considered**: Asumsikan semua WIB — ditolak (Hebei China UTC+8 mis-parse 1 jam).

## Q4: Cache dan quota Wikipedia?

- **Decision**: HTTP timeout 15s, cache `futsal.upcoming` 3h, `futsal.teams` 1d (static). Respect `api-terms` rate limit via cache; hourly `bot:schedule` hit cache, bukan Wikipedia tiap run.
- **Rationale**: Sama `MobileLegendService`/`FootballService`. Wikipedia tidak butuh key, tapi rate limit via cache tetap.
- **Alternatives considered**: No cache — boros dan risk block.

## Q5: Perlu result reporting timnas?

- **Decision**: Tidak di scope awal — `reportResults` timnas skip seperti MLBB (ponytail: no `FutsalService::getResult` sampai score endpoint tahu shape).
- **Rationale**: Score timnas sudah ada di Wikipedia tapi butuh parse `5–1` dari header; spekulatif tanpa verifikasi melanggar Simplicity.

## Open Items

- Lock konkret Wikipedia scrape selector di implement phase: `section Results and fixtures` → `Indonesia v` + date header `5 September 2025` + time `13:30 UTC+8`. Interface `FutsalService` stabil apa pun parser details.
