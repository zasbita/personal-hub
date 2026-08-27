# Implementation Plan: Wave2 Migration — Supabase Grants & Laravel Mirrors

**Branch**: `002-wave2-migration` | **Date**: 2026-08-28 | **Spec**: `specs/002-wave2-migration/spec.md`
**Input**: Feature specification from `specs/002-wave2-migration/spec.md`

## Summary

Wave2 DDL (`supabase/wave2.sql`) sudah buat 5 tabel tapi `service_role` kena `42501 permission denied` karena tanpa `GRANT`. Plan: tambah file `supabase/wave2_grants.sql` idempotent grant ke 5 tabel, dan mirror yang sama sebagai Laravel migrations `database/migrations/*_wave2_*.php` untuk SQLite lokal (`DB_CONNECTION=sqlite`) sehingga `php artisan migrate` + `php artisan test` hijau tanpa mock Supabase. Tidak ada UI.

## Technical Context

**Language/Version**: PHP 8.3, Laravel 13  
**Primary Dependencies**: SupabaseService (Rest API via Http), Illuminate Database Migrations, phpunit 12  
**Storage**: Supabase Postgres (prod) + SQLite in-memory (`phpunit.xml` DB_CONNECTION=sqlite)  
**Testing**: PHPUnit 12, `Http::fake` untuk Supabase Rest, `RefreshDatabase` optional untuk SQLite mirror  
**Target Platform**: Linux server (`deploy.sh:3` `/var/www/personal-hub` — `git fetch + artisan migrate --force`) + Supabase Cloud  
**Project Type**: Web application — monolith Laravel + Vue SPA  
**Architecture Type**: Monolith — single Laravel app (no ModuleFederationPlugin, no webpack federated, no `single-spa`, one `composer.json` root) — **N/A Integration Target**  
**Integration Target**: N/A — standalone monolith, no host shell  
**Existing Design System**: None for this feature — backend migration only; repo frontend is Tailwind 4 + Emerald Nocturne (`resources/css/app.css` `@theme`) + Lucide icons, but this plan touches no screens — carry spec's "no UI"  
**Performance Goals**: N/A — migration <2s, grant re-run <500ms  
**Constraints**: `DB_CONNECTION=sqlite` di `.env:20` → SQLite tidak punya `pgcrypto`/`gen_random_uuid()`; mirror pakai string uuid. Supabase `RLS stays off` per constitution — no policies.  
**Scale/Scope**: 5 tables wave2 (`category_budgets`, `recurring_expenses`, `vehicles`, `service_logs`, `fuel_logs`)

## UI/UX & Screens (carried from spec)

- **Design reference**: none — backend migration only, follow existing repo (Emerald Nocturne not used)
- **Screens**: none new
- **Primary interactions/flows**: Dev run `supabase/wave2_grants.sql` di SQL Editor → `SupabaseService->select` 200; dev run `php artisan migrate` → sqlite tables exist → tests pass

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. External Data Only**: PASS — Supabase grant fix is prod path; Laravel mirror ONLY for `sqlite` test DB (`phpunit.xml`), not for app data. No Eloquent local model for prod.
- **II. Single-Owner Scope**: PASS — no change.
- **III. Tests Ship With Changes**: PASS — will add migration + grant verification test.
- **IV. One Handler Path**: PASS — no business logic duplication.
- **V. Timezone Discipline**: PASS — `timestamptz default now()` UTC; no schedule change.
- **VI. Simplicity Over Speculation**: PASS — minimal grant + 1-2 migration files, no abstraction.

## Project Structure

### Documentation (this feature)

```text
specs/002-wave2-migration/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # empty — no new API
└── tasks.md             # Phase 2 output (NOT created by /pandawa.plan)
```

### Source Code (repository root)

```text
supabase/
├── wave2.sql            # existing — idempotent DDL, untouched
└── wave2_grants.sql     # NEW — idempotent GRANTs for 5 tables

database/
└── migrations/
    ├── 0001_01_01_000000_create_users_table.php
    ├── 0001_01_01_000001_create_cache_table.php
    ├── 0001_01_01_000002_create_jobs_table.php
    └── 2026_08_28_000000_create_wave2_tables.php  # NEW — mirror wave2 for sqlite

tests/
├── Feature/Wave2MigrationTest.php  # NEW — verify migrate + Supabase fake
└── Unit/... (if needed)
```

**Structure Decision**: Laravel standard `database/migrations` + `supabase/*.sql` idempotent. Single file `2026_08_28_000000_create_wave2_tables.php` bundles 5 tables with FK (ponytail: one migration vs 5 files — simpler to land, add when need per-table rollback).

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
| --------- | ---------- | ------------------------------------ |
| I. External Data Only — SQLite mirror `database/migrations/2026_08_28_000000_create_wave2_tables.php` | Test/CI only (`phpunit.xml` DB_CONNECTION=sqlite), prod data stays Supabase REST per `constitution.md:I` | No mirror → tests must Http::fake only, loses FK cascade coverage |

---

## Technical Diagrams

### Data Design Decisions

| Source resource / sub-resource | Table(s) | Mapping | Rationale |
| ------------------------------ | -------- | ------- | --------- |
| category_budgets | category_budgets | mirror — 1:1 dari wave2.sql:7 | DDL source exact |
| recurring_expenses | recurring_expenses | mirror | — |
| vehicles | vehicles | mirror | — |
| service_logs (FK vehicles.id CASCADE) | service_logs | mirror (child) | sub-resource 1:N |
| fuel_logs (FK vehicles.id CASCADE) | fuel_logs | mirror (child) | sub-resource 1:N |
| grants (operational, not entity) | GRANT ALL ON 5 tables TO service_role | operational addition | constitution RLS off, need privilege |
| sqlite uuid PK default | Laravel mirror: `uuid('id')->primary()` tanpa `gen_random_uuid()` | deviation: sqlite-compatible | SQLite no `pgcrypto`; app generates UUID via model/Str::uuid() |

### Data Model (Entity Relationship Diagram)

```mermaid
erDiagram
    vehicles ||--o{ service_logs : has
    vehicles ||--o{ fuel_logs : has
    category_budgets {
        uuid id PK
        bigint user_id
        text category
        numeric monthly_limit
        timestamptz created_at
    }
    recurring_expenses {
        uuid id PK
        bigint user_id
        numeric amount
        text description
        text category
        int day_of_month
        timestamptz created_at
    }
    vehicles {
        uuid id PK
        bigint user_id
        text name
        int last_km
        int next_service_km
        int service_interval
        timestamptz created_at
    }
    service_logs {
        uuid id PK
        uuid vehicle_id FK
        int old_km
        int new_km
        timestamptz created_at
    }
    fuel_logs {
        uuid id PK
        uuid vehicle_id FK
        bigint user_id
        int km
        numeric liters
        numeric cost
        timestamptz created_at
    }
```

### System Architecture

```mermaid
graph TB
    Dev["Dev / CI"]
    SupaSQL["Supabase SQL Editor\nwave2.sql + wave2_grants.sql"]
    SupaDB["Supabase Postgres\n5 tables + GRANT"]
    LaravelMigrate["php artisan migrate\nsqlite / postgres"]
    LocalDB["SQLite test DB\nmirror wave2"]
    App["Laravel App\nSupabaseService"]
    Tests["php artisan test\nHttp::fake + RefreshDatabase"]
    
    Dev -->|run| SupaSQL
    SupaSQL --> SupaDB
    Dev -->|run| LaravelMigrate
    LaravelMigrate --> LocalDB
    App -->|REST| SupaDB
    Tests -->|fake/mock| SupaDB
    Tests -->|migrate| LocalDB
    
    style SupaDB fill:#FFD700
    style LocalDB fill:#98FB98
    style App fill:#87CEEB
```

### Use Case Diagram

```mermaid
graph LR
    Dev["👤 Dev"]
    App["App service_role"]
    Dev -->|Apply DDL+GRANT| Supa["Supabase"]
    Dev -->|Run migrate| Local["Local DB"]
    App -->|select/insert| Supa
    Dev -->|Run tests| Tests["Tests"]
    
    style Dev fill:#90EE90
    style App fill:#87CEEB
```

### Data Flow Diagram (Level 0)

```mermaid
graph LR
    WaveSQL["wave2.sql + grants"] --> SupaDB2["Supabase"]
    MigrationPHP["Laravel migration"] --> SQLite["SQLite"]
    SupaDB2 --> App2["SupabaseService"]
    SQLite --> TestDB["Test DB"]
    App2 --> Tests2["Tests"]
    TestDB --> Tests2
    
    style WaveSQL fill:#B0E0E6
    style SupaDB2 fill:#FFD700
```

### API Contract Overview

| Operation | Endpoint | Method | Purpose |
| --------- | -------- | ------ | ------- |
| — | — | — | No new API — migration only (existing Supabase REST `/rest/v1/<wave2 table>`) |

### Deployment Architecture

```mermaid
graph TB
    Dev2["Dev"] -->|push| GH["GitHub main"]
    GH -->|fetch| Server["/var/www/personal-hub\ndeploy.sh:5 git reset --hard origin/main"]
    Server -->|composer install| Install["composer install"]
    Install -->|php artisan migrate --force| MigrateProd["migrate (sqlite jobs/cache only)"]
    Dev2 -->|manual| SupaEditor["Supabase SQL Editor\nrun wave2_grants.sql"]
    SupaEditor --> SupaProd["Supabase Cloud"]
    
    style Server fill:#98FB98
    style SupaProd fill:#FFD700
```
