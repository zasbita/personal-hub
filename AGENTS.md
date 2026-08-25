# AGENTS.md

## Project

Personal Hub — Laravel 13 + Vue 3 SPA monolith. Telegram bot + admin dashboard in one app.
Data lives in Supabase (Postgres) + Google Sheets. No local database for app data.

## Stack

- **Backend:** Laravel 13, PHP 8.3, Supabase PHP, Firebase JWT
- **Frontend:** Vue 3.5, Vue Router 4, Pinia 4, Tailwind CSS 4, Vite 8
- **Bot:** Telegram long-polling via artisan commands

## Commands

```bash
# Dev (runs Laravel + Vite together)
composer dev

# Build frontend only
npm run build

# Run tests
composer test          # clears config cache then runs phpunit
php artisan test       # direct

# Lint (Pint)
./vendor/bin/pint

# Bot commands
php artisan bot:listen    # Telegram long-polling (dev only; production uses the webhook)
php artisan bot:webhook   # Point Telegram at POST /api/telegram/webhook + publish the / menu
php artisan bot:notify    # Match notifier + final scores (scheduled every 15min)
php artisan bot:digest    # Weekly expense/budget/service digest (scheduled Mon 07:00 WIB)

# Full setup from scratch
composer setup
```

## Architecture

```
app/Services/           # Business logic (ExpenseParser, SheetsService, SupabaseService, etc.)
app/Http/Controllers/Api/  # JSON API routes (dashboard → Supabase/Sheets)
app/Console/Commands/   # TelegramBot, MatchNotifier
resources/js/           # Vue SPA (views/, components/, stores/, api/)
routes/api.php          # Dashboard API
routes/web.php          # SPA catch-all (serves app.blade.php)
config/services.php     # Supabase, Google Sheets, Telegram, Football API keys
```

## Key quirks

- **No local DB for app data.** All reads/writes go to Supabase HTTP API or Google Sheets API. SQLite in phpunit.xml is only for framework internals (sessions, cache).
- **SPA catch-all route.** `routes/web.php` serves `resources/views/app.blade.php` for all non-API paths. Vue Router handles client-side routing.
- **Tailwind 4.** Uses `@theme` directive in `resources/css/app.css` for design tokens (Emerald Nocturne dark palette). No `tailwind.config.js`.
- **API auth is the `SupabaseAuth` middleware** (`app/Http/Middleware/SupabaseAuth.php`). Every dashboard route sits behind it; it validates the httpOnly `sb_access_token` cookie against Supabase `/auth/v1/user` and caches accepted tokens for 5 minutes. Only `/auth/login` (rate limited) and `/auth/logout` are open. The Vue router guard reading `localStorage` is UX only — never the security boundary.
- **API cookies are not encrypted.** `routes/api.php` has no `EncryptCookies` middleware, so the session cookie is read and written verbatim. Tests must use `withCredentials()->withUnencryptedCookie(...)` to match.
- **Null coalescing in string interpolation** — PHP 8.3 supports it but be careful with nested array access in double-quoted strings (use temp variables).
- **Vite 8 + laravel-vite-plugin 3.** Vue plugin must be added manually in `vite.config.js`.

## Environment

Required in `.env`:
```
SUPABASE_URL=           # Supabase project URL
SUPABASE_KEY=           # Supabase service role key
GOOGLE_SHEET_ID=        # Google Sheets expense sheet
GOOGLE_SERVICE_ACCOUNT_EMAIL=
GOOGLE_PRIVATE_KEY=     # With \n escapes
BOT_TOKEN=              # Telegram bot token
OWNER_ID=811031481      # Telegram user ID whitelist
FOOTBALL_API_KEY=       # api-football.com
TELEGRAM_WEBHOOK_SECRET= # Any long random string; must match what bot:webhook registered
MONTHLY_BUDGET=0        # Optional. Rupiah; 0 hides the budget line
```

## Testing

- PHPUnit 12 with in-memory SQLite
- Tests in `tests/Unit` and `tests/Feature`
- Run single test: `php artisan test --filter=TestName`
- Pest is available but not used — standard PHPUnit
- **Rule: every feature/fix must create or update tests.** New controller/service/command → add `tests/Feature` or `tests/Unit`; bugfix → add regression test that fails without fix; refactor → keep coverage; do not ship without `composer test` green
