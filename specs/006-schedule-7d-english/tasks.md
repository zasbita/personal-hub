---
description: "Task list for schedule-7d-english feature"
---

# Tasks: Schedule 7 Days English — /schedule 1 Minggu

**Input**: `specs/006-schedule-7d-english/spec.md`, `plan.md`, `data-model.md`, `contracts/telegram-schedule-7d.md`  
**Prerequisites**: plan.md, spec.md, data-model.md, research.md, contracts/  
**Branch**: `006-schedule-7d-english`

## Format: `[ID] [P?] [Story] [FR-###?] Description`

## Phase 1: Setup (Shared Infrastructure)

- [X] T001 [done] Verify branch `006-schedule-7d-english` and docs exist in `specs/006-schedule-7d-english/`

---

## Phase 2: Foundational (Blocking Prerequisites)

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [X] T002 [done] [P] Add `NEXT_7D_HOURS=168` and `isNext7Days()` wrapper to `app/Support/MatchHelper.php` (`isInWindow(now, now+168h)`) [FR-007]
- [X] T003 [done] [P] Extend `FootballService::getUpcomingFixtures` to fetch 7 dates (today..+6) with cache 3h in `app/Services/FootballService.php` [FR-003]
- [X] T004 [done] [P] Extend `VolleyballService::getUpcomingGames` to fetch 7 dates with cache 3h in `app/Services/VolleyballService.php` [FR-003]

**Checkpoint**: Helper + 7d fetch ready

---

## Phase 3: User Story 1 - Cek jadwal 1 minggu via /schedule English (Priority: P1) 🎯 MVP

**Goal**: `/schedule` menampilkan 0–168h English, per-sport fallback 7d

- [X] T005 [done] [US1][FR-001][FR-002][FR-007] Update `BotRouter::handleJadwal` to use `isNext7Days` for DB and fallback, header `📅 Schedule next 7 days` in `app/Services/BotRouter.php`
- [X] T006 [done] [US1][FR-006][FR-010] Translate empty/error/overflow to English `No schedule in the next 7 days.`, `No teams followed...`, `… and N more`, `Failed to fetch schedule` in `app/Services/BotRouter.php`
- [X] T007 [done] [P] [US1][FR-001][FR-002][FR-003][FR-004] Tests update `tests/Feature/BotRouterJadwalTest.php` and extend `tests/Unit/MatchHelperWindowTest.php` for isNext7Days (+5d within, +8d outside)

**Checkpoint**: US1 MVP — /schedule 7d works

---

## Phase 4: User Story 2 - Alias backward compatibility & MENU English (Priority: P1)

**Goal**: `/jadwal` alias still works, MENU/WELCOME English

- [X] T008 [done] [US2][FR-001][FR-005][FR-008] Update `BotRouter::MENU` primary `schedule => "Check schedule next 7 days — /schedule"` and `WELCOME` line `/schedule` English in `app/Services/BotRouter.php`
- [X] T009 [done] [US2][FR-001][FR-005] Tests `BotRouter::MENU` contains `schedule` and handler responds to `/jadwal`, `/schedule`, `/next` with English header in `tests/Feature/BotRouterScheduleTest.php` (or extend)

**Checkpoint**: US2 — MENU English, alias back-compat

---

## Phase 5: User Story 3 - Fallback API 7 hari per-sport (Priority: P1)

**Goal**: Fallback fetch 7 days for MLBB/Futsal already filtered 7d

- [X] T010 [done] [US3][FR-003][FR-004] Verify `MobileLegendService` and `FutsalService` filter `isNext7Days` for 7d (already many days, no fetch change) — add test for +5d within 7d in `tests/Feature/BotRouterMlbbTest.php` / `BotRouterFutsalTest.php`
- [X] T011 [done] [US3][FR-004] Per-sport isolation test for 7d window: football DB 7d not hit API, volly fallback 7d in `tests/Feature/BotRouterJadwalTest.php`

**Checkpoint**: All 3 stories functional 7d

---

## Phase 6: Polish & Cross-Cutting Concerns

- [X] T012 [done] [P] Run `./vendor/bin/pint` on `app/Support/MatchHelper.php`, `app/Services/FootballService.php`, `app/Services/VolleyballService.php`, `app/Services/BotRouter.php`
- [X] T013 [done] [P] Run `composer test` (128+ incl. updated schedule tests) verify pint green
- [X] T014 [done] Validate `specs/006-schedule-7d-english/quickstart.md` manual `/schedule` 7d

---

## Requirement Coverage

| FR | Task(s) |
| -- | ------- |
| FR-001 aliases /schedule | T005, T008, T009 |
| FR-002 0-168h window | T002, T005, T007 |
| FR-003 fetch 7 dates | T003, T004, T010 |
| FR-004 per-sport isolation 7d | T005, T010, T011 |
| FR-005 MENU/WELCOME English | T008, T009 |
| FR-006 English messages | T006 |
| FR-007 isNext7Days | T002, T005 |
| FR-008 alias back-compat | T008 |
| FR-009 no new table | implicit |
| FR-010 cap10 sorted | T005 |
