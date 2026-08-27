# Research: Jadwal Mobile Legends (MPL ID) 24 Jam

**Branch**: `004-mlbb-jadwal` | **Date**: 2026-08-27

## Q1: Sumber jadwal MLBB mana yang paling lazy?

- **Decision**: Provisional接口 `MobileLegendService` dengan `getUpcomingMatches(): array[{id,date,home,away,league,status}]` + `searchTeams(query): string[]`, backed by Liquipedia MPL ID API atau unofficial MPL schedule endpoint; di belakang `Cache::remember('mlbb.upcoming', 3h)` seperti `FootballService`.
- **Rationale**: Semua service existing (`FootballService`, `VolleyballService`) pakai shape `{id,date,home,away,league}` dan cache 3h — reuse 1:1 membuat BotRouter/Scheduler/Notifier tidak perlu cabang shape baru. Liquipedia punya `MPL_ID_S14` page JSON; MPL unofficial `mpl-api` juga return array mirip. Bentuk service abstrak dari provider konkret, jadi ganti endpoint tidak ganti consumer.
- **Alternatives considered**: Scrape Liquipedia HTML per-request — ditolak (rapuh, no cache). Pandascore esports API — ditolak (butuh paid key, quota ketat). Manual Supabase insert tanpa API — bisa tapi kehilangan fallback otomatis; service tetap ada tapi `getUpcomingMatches` return `select match_schedule where status NS` jika provider down (graceful).

## Q2: Alias mlbb/ml → mobilelegend bagaimana tanpa migrasi?

- **Decision**: `SportPrefsService::SPORTS = [...,'mobilelegend']` saja; router normalisasi `strtolower(p[1])` map `mlbb|ml → mobilelegend` sebelum `in_array` dan sebelum `addPreference`. Persisted `sport_type` selalu `mobilelegend`.
- **Rationale**: Menjaga `sport_type` unik menghinda fragmentation `match_schedule` per alias; query `where sport_type=mobilelegend` sederhana. Alias hanya di entry point (BotRouter) — `SportPrefsService` tetap single source.
- **Alternatives considered**: Tambah 3 entries `mobilelegend,mlbb,ml` ke `SPORTS` — ditolak (duplikat sport_type, query 3x). Migrasi rename existing — tidak ada row MLBB yet, tidak perlu.

## Q3: Kenapa MLBB emoji 🎮?

- **Decision**: `🎮` untuk MLBB distinct dari `⚽`/`🏐`/`🏍️`. Dipakai di `formatJadwalRow` dan `notifyTeamSport`-like branch MLBB.
- **Rationale**: Telegram list mixed sport perlu visual cue; ponytail: satu emoji cukup, tidak perlu ikon per team.
- **Alternatives considered**: `🕹️` — similar, tapi `🎮` lebih umum untuk MLBB/esports.

## Q4: Timeout/cache quota?

- **Decision**: HTTP timeout 15s (`Http::timeout(15)`), cache `mlbb.upcoming` 3h, `mlbb.teams.<q>` 1d. Config key `services.mpl.api_key` optional — jika kosong dan endpoint tidak butuh key, tetap jalan; jika butuh dan kosong, service throw caught → fallback empty.
- **Rationale**: Sama dengan `FootballService`/`SupabaseService::TIMEOUT` 15s; konsisten dengan constitution provider quotas via cache.
- **Alternatives considered**: No cache — boros quota dan latency `/jadwal` naik.

## Q5: Apakah perlu result reporting untuk MLBB?

- **Decision**: Tidak di scope awal — `MatchNotifier::reportResults` ponytail skips non football/volly; MLBB `status FT` tidak di-report via Telegram sampai `MobileLegendService::getResult` ada.
- **Rationale**: MPL result endpoint belum pasti shape-nya; menambah spekulatif HTTP tanpa verifikasi melanggar Simplicity Over Speculation. `match_schedule` tetap update `notified` dan bisa dilihat di dashboard/ICS.
- **Alternatives considered**: Fake result via same fixture status — ditolak (provider MLBB tidak guarantee `status` field).

## Open Items

- Lock konkret MPL endpoint di implement phase research spike 30min: cek Liquipedia API `https://liquipedia.net/mobilelegends/api.php?action=...` vs `https://mpl-api.example/schedule`. Interface service tidak berubah apa pun endpoint-nya.
