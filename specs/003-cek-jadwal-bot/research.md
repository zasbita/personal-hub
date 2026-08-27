# Research: Cek Jadwal 1 Hari via Telegram Bot

**Branch**: `003-cek-jadwal-bot` | **Date**: 2026-08-27

## Q1: Bagaimana BotRouter menangani command saat ini?

- **Decision**: Reuse `BotRouter::handle(array $update)` routing style — tambah `if (in_array(text, ['/jadwal','/schedule','/next']) || str_starts_with)` sebelum catch-all `str_starts_with('/')`.
- **Rationale**: Semua command existing (`/summary`, `/follow`, `/myteams`, `/check_service`) pakai pattern yang sama di `app/Services/BotRouter.php:90-134`. Menu `MENU` di `BotRouter.php:21` dipublish oleh `RegisterWebhook.php` via `setMyCommands`.
- **Alternatives considered**: Buat command class `App\Console\Commands\CheckSchedule` — ditolak karena bot command bukan artisan; webhook & polling berbagi `BotRouter`.

## Q2: Sumber jadwal H-1 sudah ada di mana? Bagaimana filter 0–24h?

- **Decision**: Gunakan `SupabaseService::select('match_schedule', ['match_time' => 'gte.now,lte.now+24h', 'status' => 'eq.NS', 'sport_type' => 'eq.xxx'])` + `MatchHelper::isInWindow` untuk client-side fallback. Tambah helper `isNext24Hours` sebagai wrapper `isInWindow(now, now+24h)`.
- **Rationale**: `MatchScheduler.php:85` sudah pakai `select` + `MatchHelper::isOneDayAway` (20–30h window). `MatchHelper.php:40-51` already centralizes `isInWindow`. Tambah konstanta `NEXT_24H` atau method baru adalah smallest diff.
- **Alternatives considered**: Query Supabase dengan `and=(match_time.gte...,match_time.lte...)` server-side — bisa tapi tetap perlu client filter untuk API fallback; reuse helper keeps one code path.

## Q3: Fallback API per-sport bagaimana menghemat quota?

- **Decision**: Hanya hit API untuk sport yang `sport_preferences` mengandungnya DAN hasil DB untuk sport itu kosong. Reuse `FootballService::getUpcomingFixtures()` yang sudah `Cache::remember 3h`.
- **Rationale**: `FootballService.php:17-26` caches `football.upcoming` 3h, `searchTeams` 1d. `VolleyballService` & `MotoGPService` punya cache serupa. Per-sport fallback berarti 1 user dengan hanya football tidak pernah hit MotoGP API.
- **Alternatives considered**: Selalu hit semua API — boros quota (100/day). Global fallback (if any DB empty, fetch all) — masih boros untuk sport yang sudah ada.

## Q4: Format waktu & sorting?

- **Decision**: Sorting asc `match_time`/`date` in-memory, display via `DisplayTime::format(iso)` yang convert UTC → `config('app.display_timezone')` (Asia/Jakarta).
- **Rationale**: `MatchNotifier.php:96` sudah pakai `DisplayTime::format`. `DisplayTime.php` handles WIB conversion. Consistent display.
- **Alternatives considered**: Format manual `DateTime` — duplikat logic.

## Q5: MENU & WELCOME publish flow?

- **Decision**: Tambah entry `jadwal` di `BotRouter::MENU` dan baris di `WELCOME` sport section (`🏆`). `RegisterWebhook.php` (dipanggil `php artisan bot:webhook`) iterasi `BotRouter::MENU` untuk `setMyCommands`.
- **Rationale**: `BotRouter.php:21-30` MENU adalah single source of truth untuk Telegram menu. `RegisterWebhook.php:??` reads MENU. Edit one place propagates via `bot:webhook`.
- **Alternatives considered**: Hardcode menu di webhook command — divergensi.

## Q6: Error handling & unauthorized?

- **Decision**: Awal `handleJadwal` cek `from.id != ownerId` → `Unauthorized` early return (copy pattern `BotRouter.php:84`). Try/catch per sport untuk Supabase/API; on error balas "⚠️ Gagal ambil..." tapi tetap coba fallback.
- **Rationale**: Konsisten dengan existing `handleFollow`/`handleMyTeams` error messages. Webhook controller already answers 200 after `BotRouter::handle` (no throw).
- **Alternatives considered**: Let throw propagate — webhook would 500 and Telegram redelivery loop (violates constitution).

## Open Items

- None — all unknowns resolved from existing code.

