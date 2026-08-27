# Quickstart: Schedule 7 Days English — /schedule 1 Minggu

**Branch**: `006-schedule-7d-english`

## Manual Telegram

1. Follow tetap: `/follow volly Indonesia W`, `/follow mobilelegend ONIC`, `/follow futsal Indonesia`
2. `php artisan bot:schedule` — H-1 20–30h (unchanged)
3. `/schedule` → `📅 Schedule next 7 days` — shows 0–168h DB + fallback 7d per-sport; `/jadwal` alias same; `/next` same
4. `/help` → line `/schedule — check schedule next 7 days`
5. Empty: `📭 No schedule in the next 7 days.`

## Tests

```bash
php artisan test --filter=BotRouterSchedule
php artisan test --filter=MatchHelper
composer test
./vendor/bin/pint
```

## Config

- No new env — reuse `FOOTBALL_API_KEY`, `MPL_API_URL`, `FUTSAL_API_URL`

## Verify 7d

- DB row +2d and +6d both show in `/schedule` sorted; +8d not shown
- Fallback 7d: API fixture +3d shows; +8d not
