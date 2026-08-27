# Quickstart: Cek Jadwal 1 Hari via Telegram Bot

**Branch**: `003-cek-jadwal-bot`

## Manual Test via Telegram

1. Follow tim: `/follow football Arsenal`
2. `php artisan bot:schedule` atau tunggu scheduler hourly — isi `match_schedule` untuk H-1
3. Kirim `/jadwal` → harus lihat jadwal 0–24h dari DB (WIB)
4. Kosongkan `match_schedule` (Supabase delete) → kirim `/jadwal` → harus tetap lihat jadwal dari API fallback (jika ada fixture 24h)
5. Kirim `/schedule` dan `/next` → sama dengan `/jadwal`
6. `/help` harus tampilkan `/jadwal` di daftar

## Tests

```bash
composer test --filter=BotRouterJadwalTest
php artisan test --filter=MatchHelperWindow
./vendor/bin/pint
```

## Webhook Menu

```bash
php artisan bot:webhook   # publish MENU termasuk jadwal
```

## Time Window

- DB path: `match_time between now and now+24h UTC`, status `NS`/`scheduled`
- API fallback per-sport jika kosong: `isInWindow(date, now, now+24h)` + `NameMatcher::matches`
- Display: `DisplayTime::format(iso)` → WIB (`Asia/Jakarta`)
- Cap: max 10, overflow "… dan N lainnya"

