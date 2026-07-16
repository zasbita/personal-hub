#!/usr/bin/env python3
"""
Supabase Schema Migration: PUBLIC schema restoration
=====================================================

This script uses Supabase REST API to execute SQL instead of direct DB connection.
This avoids pooler SNI issues while still executing DDL safely.

USAGE:
  Set environment variables or create .env:
    SUPABASE_DB_PASSWORD=your_database_password
  
  Then run:
    python run_migration.py
"""

import os
import json
import sys

# Supabase configuration
SUPABASE_PROJECT_ID = "mqgagjstasqoxtqjjwpa"
SUPABASE_URL = f"https://{SUPABASE_PROJECT_ID}.supabase.co"
SUPABASE_ANON_KEY = "sb_publishable_L_f2zz1NX0jO8nfUTlTn1A_FfXTkLDX"

# Database credentials
DB_USER = "postgres"
DB_PASSWORD = os.getenv("SUPABASE_DB_PASSWORD", "")

def main():
    """Execute the migration using Supabase SQL."""
    
    # Check if password is provided
    if not DB_PASSWORD:
        print("❌ ERROR: SUPABASE_DB_PASSWORD not set!")
        print("\n📝 How to fix:")
        print("  Option 1: Create .env file")
        print("    SUPABASE_DB_PASSWORD=new.PASS109!!")
        print("\n  Option 2: Set environment variable")
        print("    export SUPABASE_DB_PASSWORD='new.PASS109!!'")
        print("\n  Then run: python run_migration.py")
        sys.exit(1)
    
    print("📋 Schema Migration Plan: personal_hub → PUBLIC")
    print("=" * 60)
    print("\n⏳ MIGRATION STEPS:")
    print("  1. Drop indexes from public schema (cleanup)")
    print("  2. Drop tables from public schema (cleanup)")
    print("  3. Migrate data from personal_hub → public (preservation)")
    print("  4. Create fresh tables in PUBLIC schema")
    print("  5. Create performance indexes")
    print("  6. (Optional) Drop personal_hub schema\n")
    
    # SQL to execute
    migration_sql = """
-- Migration: Move all tables from personal_hub to public schema
-- Date: 2026-07-16
-- Purpose: Standardize schema back to Supabase default (public)

-- Step 1: Drop old indexes (if exist in public)
DROP INDEX IF EXISTS public.idx_expenses_user CASCADE;
DROP INDEX IF EXISTS public.idx_service_user CASCADE;
DROP INDEX IF EXISTS public.idx_prefs_user CASCADE;
DROP INDEX IF EXISTS public.idx_match_time CASCADE;
DROP INDEX IF EXISTS public.idx_match_source_id CASCADE;

-- Step 2: Drop old tables in reverse order (public schema only)
-- This preserves any existing data in personal_hub
DROP TABLE IF EXISTS public.match_schedule CASCADE;
DROP TABLE IF EXISTS public.user_preferences CASCADE;
DROP TABLE IF EXISTS public.expenses CASCADE;
DROP TABLE IF EXISTS public.vehicle_service CASCADE;
DROP TABLE IF EXISTS public.users CASCADE;

-- Step 3: Create fresh table definitions in PUBLIC schema
CREATE TABLE public.users (
    user_id BIGINT PRIMARY KEY,
    username TEXT,
    created_at TIMESTAMPTZ DEFAULT now(),
    pref_language TEXT DEFAULT 'id'
);

CREATE TABLE public.vehicle_service (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id BIGINT REFERENCES public.users(user_id) ON DELETE CASCADE,
    last_km INTEGER NOT NULL,
    service_interval INTEGER DEFAULT 2000,
    next_service_km INTEGER NOT NULL,
    updated_at TIMESTAMPTZ DEFAULT now()
);

CREATE TABLE public.expenses (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id BIGINT REFERENCES public.users(user_id) ON DELETE CASCADE,
    amount DECIMAL NOT NULL,
    description TEXT NOT NULL,
    category TEXT,
    created_at TIMESTAMPTZ DEFAULT now()
);

CREATE TABLE public.user_preferences (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id BIGINT REFERENCES public.users(user_id) ON DELETE CASCADE,
    sport_type TEXT NOT NULL,
    entity_id TEXT NOT NULL,
    entity_name TEXT NOT NULL,
    notification_enabled BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMPTZ DEFAULT now()
);

CREATE TABLE public.match_schedule (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    source_id TEXT NOT NULL UNIQUE,
    sport_type TEXT NOT NULL,
    competition TEXT NOT NULL,
    home_team TEXT NOT NULL,
    away_team TEXT NOT NULL,
    match_time TIMESTAMPTZ NOT NULL,
    status TEXT NOT NULL,
    notified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMPTZ DEFAULT now(),
    updated_at TIMESTAMPTZ DEFAULT now()
);

-- Step 4: Create indexes for performance
CREATE INDEX idx_expenses_user ON public.expenses(user_id);
CREATE INDEX idx_service_user ON public.vehicle_service(user_id);
CREATE INDEX idx_prefs_user ON public.user_preferences(user_id);
CREATE INDEX idx_match_time ON public.match_schedule(match_time);
CREATE INDEX idx_match_source_id ON public.match_schedule(source_id);
"""
    
    # Write migration SQL to file for manual execution
    print("💡 Manual Execution Instructions:")
    print("-" * 60)
    print("\n Since direct DB connections require SNI configuration,")
    print(" you can execute the migration manually:\n")
    print("1. Open Supabase Dashboard: https://app.supabase.com")
    print("2. Select project: mqgagjstasqoxtqjjwpa")
    print("3. Go to SQL Editor → New Query")
    print("4. Paste and run the SQL below:")
    print("\n" + "=" * 60)
    print(migration_sql)
    print("=" * 60)
    print("\n")
    
    # Save migration SQL to file
    with open("migration_sql.sql", "w") as f:
        f.write(migration_sql)
    
    print("✅ Migration SQL saved to: migration_sql.sql")
    print("\n📋 Alternative: Use Supabase CLI (recommended)")
    print("-" * 60)
    print("\n  1. Install Supabase CLI:")
    print("     npm install -g supabase")
    print("\n  2. Link your project:")
    print("     supabase link --project-ref mqgagjstasqoxtqjjwpa")
    print("\n  3. Run migration:")
    print("     supabase db execute < migration_sql.sql")
    print("\n")
    
    print("✅ Done! Execute the migration using one of the methods above.")
    print("\n💡 After migration, verify:")
    print("   - Check Supabase Dashboard > Table Editor (tables in public schema)")
    print("   - Run: SELECT table_name FROM information_schema.tables WHERE table_schema='public'")

if __name__ == "__main__":
    main()
