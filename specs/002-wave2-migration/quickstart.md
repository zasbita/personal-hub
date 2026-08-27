# Quickstart: Wave2 Migration

## Prereqs

- `.env` has `SUPABASE_URL` + `SUPABASE_KEY` (service_role)
- `DB_CONNECTION=sqlite` for local tests

## 1. Supabase — grant fix (sekali di SQL Editor)

Buka https://supabase.com/dashboard/project/mqgagjstasqoxtqjjwpa/sql → run:

```sql
-- from supabase/wave2_grants.sql
GRANT ALL ON public.category_budgets TO service_role, authenticated;
GRANT ALL ON public.recurring_expenses TO service_role, authenticated;
GRANT ALL ON public.vehicles TO service_role, authenticated;
GRANT ALL ON public.service_logs TO service_role, authenticated;
GRANT ALL ON public.fuel_logs TO service_role, authenticated;
```

Re-run aman (idempotent). Verify:

```bash
php artisan tinker --execute "(new App\Services\SupabaseService)->select('vehicles',['select'=>'id','limit'=>1])"
# expect array, not 42501
```

## 2. Lokal — Laravel mirror

```bash
php artisan migrate --force
php artisan migrate:status  # should show 2026_08_28_000000_create_wave2_tables Ran
php artisan test --filter=Wave2Migration
```

Rollback:

```bash
php artisan migrate:rollback --step=1
```

## 3. Test

```bash
composer test   # or php artisan test
./vendor/bin/pint --test
```

Expected: `category_budgets` etc 200 via Http::fake, no 42501.
