-- Wave 2 — run once in Supabase SQL Editor (https://supabase.com/dashboard/project/mqgagjstasqoxtqjjwpa/sql)
-- Creates tables used by: category_budgets, recurring_expenses, vehicles, service_logs, fuel_logs
-- Safe to re-run: uses IF NOT EXISTS, keeps existing data.

create extension if not exists "pgcrypto";

create table if not exists category_budgets (
  id uuid primary key default gen_random_uuid(),
  user_id bigint not null,
  category text not null,
  monthly_limit numeric not null check (monthly_limit >= 0 and monthly_limit <= 100000000),
  created_at timestamptz default now(),
  unique(user_id, category)
);

create table if not exists recurring_expenses (
  id uuid primary key default gen_random_uuid(),
  user_id bigint not null,
  amount numeric not null check (amount >= 0 and amount <= 100000000),
  description text not null check (char_length(description) between 1 and 200),
  category text not null default 'General' check (char_length(category) <= 30),
  day_of_month int not null check (day_of_month between 1 and 31),
  created_at timestamptz default now()
);

create table if not exists vehicles (
  id uuid primary key default gen_random_uuid(),
  user_id bigint not null,
  name text not null check (char_length(name) between 1 and 50),
  last_km int not null check (last_km >= 0 and last_km <= 9999999),
  next_service_km int not null check (next_service_km >= 0 and next_service_km <= 9999999),
  service_interval int not null default 2000 check (service_interval between 500 and 20000),
  created_at timestamptz default now()
);

create table if not exists service_logs (
  id uuid primary key default gen_random_uuid(),
  vehicle_id uuid not null references vehicles(id) on delete cascade,
  old_km int,
  new_km int,
  created_at timestamptz default now()
);

create table if not exists fuel_logs (
  id uuid primary key default gen_random_uuid(),
  vehicle_id uuid not null references vehicles(id) on delete cascade,
  user_id bigint,
  km int not null check (km >= 0 and km <= 9999999),
  liters numeric not null check (liters >= 0.1 and liters <= 1000),
  cost numeric check (cost is null or (cost >= 0 and cost <= 100000000)),
  created_at timestamptz default now()
);

-- Optional: enable RLS if you use it, then add policies. For service_role key (used by app), RLS can stay off.
-- alter table category_budgets enable row level security;
-- alter table recurring_expenses enable row level security;
-- alter table vehicles enable row level security;
-- alter table service_logs enable row level security;
-- alter table fuel_logs enable row level security;

-- Verify:
-- select table_name from information_schema.tables where table_schema='public' and table_name in ('category_budgets','recurring_expenses','vehicles','service_logs','fuel_logs');
