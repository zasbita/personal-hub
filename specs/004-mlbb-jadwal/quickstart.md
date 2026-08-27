# Quickstart: Jadwal Mobile Legends (MPL ID) 24 Jam

**Branch**: `004-mlbb-jadwal`

## Manual Telegram

1. Follow: `/follow mobilelegend ONIC` atau `/follow mlbb EVOS` → `✅ Memantau`
2. `/myteams` → lihat `ONIC (mobilelegend) 🔔`
3. `php artisan bot:schedule` — simpan H-1 20–30h (MLBB included)
4. `/jadwal` → lihat `🎮 ONIC vs EVOS — MPL ID — ⏱️ WIB` 0–24h; jika DB kosong, fallback API MLBB
5. Tunggu H-1 jam: `php artisan bot:notify` → `🎮 1 jam lagi!`
6. Alias: `/follow ml ONIC`, `/schedule`, `/next` sama

## Tests

```bash
php artisan test --filter=BotRouterMlbbTest
php artisan test --filter=MobileLegendService
composer test   # 112+ expected green
./vendor/bin/pint
```

## Config

- `config/services.php` optional `mpl.api_key` (tidak wajib untuk Liquipedia)
- `MOBILELEGEND_API_URL` jika provider tidak default — via `.env`

## Verify Isolation

- `/jadwal` tanpa pref MLBB → tidak hit MLBB API (per-sport)
- `match_schedule` MLBB tidak campur football/volly (filter `sport_type`)
