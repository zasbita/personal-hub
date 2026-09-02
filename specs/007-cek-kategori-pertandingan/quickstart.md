# Quickstart: Cek Kategori Pertandingan

**Branch**: `007-cek-kategori-pertandingan`

## Validate

```bash
composer test --filter=BotCategoryTest
composer test --filter=MatchCategoryApiTest
./vendor/bin/pint --test

# manual bot
php artisan bot:listen  # try /schedule football, /schedule xyz, /categories, /schedule (all)

# manual API
curl -H "Cookie: sb_access_token=..." http://localhost:8000/api/matches?sport_type=football
curl http://localhost:8000/api/matches?sport_type=invalid # expect 400
curl http://localhost:8000/api/matches # all

# dashboard
npm run dev # open /sports, click tabs Sepak Bola/Volly/MotoGP/MLBB/Futsal, check ?sport=football deep-link, badge counts
```

## What to test

- `/schedule football` single kategori only, header `Schedule football — next 7 days`, cap10, aggregated motogp group.
- `/schedule xyz` → `⚠️ Kategori tidak dikenal. Pilihan: football, volly, motogp, mlbb, futsal`
- `/categories` → `📊 Kategori: football (N), ...` hint
- `SportsPage` tabs: `Semua` default, click `Futsal` filters client-side, URL `?sport=futsal`, count badge, empty per kategori `Tidak ada pertandingan mendatang`.

## Files to touch

- `app/Services/BotRouter.php` (handleJadwal + handleCategories + MENU)
- `app/Http/Controllers/Api/MatchController.php` (sport_type filter)
- `resources/js/views/SportsPage.vue` (tabs, computed filter, URL sync)
- `resources/js/api/client.js` (optional sport_type param)

No `.env` change, no migration.
