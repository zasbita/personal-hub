-- Wave2 grants — run after supabase/wave2.sql in Supabase SQL Editor
-- Grants wave2 tables to service_role (SupabaseService) and authenticated.
-- Idempotent: GRANT is idempotent in Postgres; safe to re-run.

grant usage on schema public to service_role, authenticated;

grant all on table public.category_budgets to service_role, authenticated;
grant all on table public.recurring_expenses to service_role, authenticated;
grant all on table public.vehicles to service_role, authenticated;
grant all on table public.service_logs to service_role, authenticated;
grant all on table public.fuel_logs to service_role, authenticated;

-- Sequences (uuid default gen_random_uuid uses no sequence, but be explicit for future serial)
-- grant usage, select on all sequences in schema public to service_role;

-- Verify:
-- select table_name, grantee, privilege_type from information_schema.role_table_grants where table_name in ('category_budgets','recurring_expenses','vehicles','service_logs','fuel_logs') and grantee in ('service_role','authenticated');
