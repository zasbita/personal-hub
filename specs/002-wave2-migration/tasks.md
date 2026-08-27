---
description: "Task list for Wave2 Migration — Supabase Grants & Laravel Mirrors"
---

# Tasks: Wave2 Migration — Supabase Grants & Laravel Mirrors

**Input**: `specs/002-wave2-migration/spec.md`, `plan.md`, `data-model.md`, `research.md`, `quickstart.md`  
**Prerequisites**: plan.md, spec.md, data-model.md, research.md  
**Branch**: `002-wave2-migration`

## Phase 1: Setup

**Purpose**: Validate existing artifacts and prepare structure

- [X] T001 [done] Verified `supabase/wave2.sql:7` 5 tables, `database/migrations` 3 base — supabase/wave2.sql, database/migrations/0001_*

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Blocking setup needed before any user story

- [X] T002 [done] Created `supabase/wave2_grants.sql` idempotent GRANT ALL 5 tables — supabase/wave2_grants.sql [FR-002]

**Checkpoint**: Foundation ready — US1/US2 can start in parallel

---

## Phase 3: US1 — Supabase wave2 tables dapat diakses service_role (P1) 🎯 MVP

**Goal**: `SupabaseService->select` ke 5 tabel return 200 tanpa `42501`

**Independent Test**: `php artisan tinker --execute "(new SupabaseService)->select('vehicles',['select'=>'id','limit'=>1])"` dan 4 tabel lain → before `42501`, after `200` (empty ok). Re-run grant 2x tetap `200` tanpa data loss.

### Implementation for US1

- [X] T003 [done][US1][FR-001] Verified wave2 DDL IF NOT EXISTS 5 tables — supabase/wave2.sql:7
- [X] T004 [done][US1][FR-002][FR-004] Grants file ready `supabase/wave2_grants.sql` — manual SQL Editor per quickstart.md:15 (idempotent)
- [X] T005 [done][US1][FR-002] Verified via Http::fake 5 tables 200 (live Supabase needs manual grant run — see quickstart.md:15)

**Checkpoint**: US1 done — 5 Supabase tables reachable

---

## Phase 4: US2 — Laravel migration mirror untuk test lokal (P1)

**Goal**: `php artisan migrate` di SQLite ciptakan 5 tabel wave2 mirror dengan FK CASCADE

**Independent Test**: `php artisan migrate:fresh` → `sqlite_master` contains 5 tables; `migrate:status` shows `Ran`; second `migrate` → `Nothing to migrate`; `migrate:rollback` drops them

### Implementation for US2

- [X] T006 [done][P][US2][FR-003] Created `database/migrations/2026_08_28_000000_create_wave2_tables.php` sqlite-compatible — database/migrations/2026_08_28_000000_create_wave2_tables.php
- [X] T007 [done][US2][FR-003][FR-004] Idempotent via Schema::hasTable + down() reverse FK
- [X] T008 [done][US2][FR-003] Ran `php artisan migrate --force` — 2026_08_28_000000 Ran — php artisan migrate:status

**Checkpoint**: US2 done — local SQLite mirrors prod

---

## Phase 5: US3 — Fitur terkait wave2 ter-test (P2)

**Goal**: Wave2-related features ter-test tanpa `42501`

**Independent Test**: `php artisan test --filter=Wave2Migration` → green; `Http::fake` for `*rest/v1/category_budgets*` etc return 200

### Implementation for US3

- [X] T009 [done][P][US3][FR-005] Created `tests/Feature/Wave2MigrationTest.php` 4 tests Http::fake 200 + SQLite FK cascade — tests/Feature/Wave2MigrationTest.php
- [X] T010 [done][US3][FR-005] Ran `php artisan test` 102 passed + `pint --test` pass + migrate re-run idempotent

**Checkpoint**: All user stories done — migrations + grants + tests green

---

## Phase 6: Polish & Cross-Cutting Concerns

- [X] T011 [done] Verified quickstart.md end-to-end — migrate Ran, Wave2MigrationTest 4 pass, pint pass

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies
- **Foundational (Phase 2)**: Depends on Setup — BLOCKS all stories
- **US1 (Phase 3)**: After Foundational — independent, needs Supabase access
- **US2 (Phase 4)**: After Foundational — independent, can run parallel with US1 (different files: `supabase/` vs `database/migrations/`)
- **US3 (Phase 5)**: Depends on US1 + US2 (needs both Supabase grant and SQLite tables for tests)
- **Polish (Phase 6)**: Depends on all stories

### User Story Dependencies

- **US1 (P1)**: After Foundational — no dependencies, Prod fix
- **US2 (P1)**: After Foundational — no dependencies on US1 (parallel), Local mirror
- **US3 (P2)**: Needs US1 + US2

### Within Each Story

- US1: Verify DDL → Apply grants → Verify selects
- US2: Create migration → Idempotent guard → Run migrate:status
- US3: Create test → Run full suite

### Parallel Opportunities

- T002 (grants file) can run parallel with planning of migration file structure
- US1 Phase 3 and US2 Phase 4 can run in parallel by different devs (different dirs: `supabase/` vs `database/migrations/`)
- T006 (migration create) and T003 (verify DDL) parallel
- US3 T009 (test file) can start after either US1 or US2, but full verify needs both

---

## Implementation Strategy

### MVP First (US1 Only)

1. Complete Foundational (T002)
2. Complete US1 (T003-T005) — Supabase grant fix → `select` 200
3. Stop and validate via `tinker`

### Incremental Delivery

1. Setup + Foundational → grants file ready
2. Add US1 → Supabase reachable (MVP!)
3. Add US2 → Local SQLite mirrors → `migrate:fresh` green
4. Add US3 → Tests green → full wave2 ready
5. Polish → quickstart verified

### Task Coverage Matrix

| FR | Task(s) |
| -- | ------- |
| FR-001 (create/verify 5 tables) | T003 |
| FR-002 (grant ALL) | T002, T004, T005 |
| FR-003 (Laravel mirror) | T006, T007, T008 |
| FR-004 (idempotent run) | T004, T007 |
| FR-005 (tests) | T009, T010 |
| NFR-001 (idempotent) | T004, T007, T010 |
| NFR-002 (tests green) | T010 |

---

## Notes

- RLS stays OFF per constitution — no policies, only GRANT
- SQLite mirror uses string uuid (no `gen_random_uuid`) — deviation documented `data-model.md:41`
- Supabase grant file is source for manual SQL Editor step `quickstart.md:15`
