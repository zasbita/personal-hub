# Quickstart: Timnas Futsal Indonesia — Jadwal 24 Jam

**Branch**: `005-timnas-futsal`

## Manual Telegram

1. Follow: `/follow futsal Indonesia` atau `/follow futsal timnas` → `✅ Memantau`
2. `/myteams` → `Indonesia (futsal) 🔔`
3. `php artisan bot:schedule` — simpan H-1 20–30h futsal jika ada fixture 25h
4. `/jadwal` → `⚽ Indonesia vs Cambodia — CFA — ⏱️ WIB` 0–24h; jika DB kosong, fallback Wikipedia futsal
5. Tunggu H-1 jam: `php artisan bot:notify` → `⚽ 1 jam lagi!`
6. Alias garuda: `/follow futsal garuda` sama

## Tests

```bash
php artisan test --filter=BotRouterFutsalTest
php artisan test --filter=FutsalService
composer test   # 121+ expected green
./vendor/bin/pint
```

## Config

- `FUTSAL_API_URL` optional default `https://en.wikipedia.org` via `config/services.php` `futsal.url`

## Verify Isolation

- `/jadwal` tanpa pref futsal → tidak hit Wikipedia (per-sport)
- `match_schedule` futsal tidak campur football/mlbb
