---
description: "Task list for timnas-futsal feature"
---

# Tasks: Timnas Futsal Indonesia — Jadwal 24 Jam di Telegram

**Input**: `specs/005-timnas-futsal/spec.md`, `plan.md`, `data-model.md`, `contracts/telegram-timnas-futsal.md`  
**Prerequisites**: plan.md, spec.md, data-model.md, research.md, contracts/  
**Branch**: `005-timnas-futsal`

## Format: `[ID] [P?] [Story] [FR-###?] Description`

## Phase 1: Setup (Shared Infrastructure)

- [X] T001 [done] Verify branch `005-timnas-futsal` and docs exist in `specs/005-timnas-futsal/`

---

## Phase 2: Foundational (Blocking Prerequisites)

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [X] T002 [done] [P] Create `app/Services/FutsalService.php` scrape Wikipedia `Results and fixtures` `Indonesia v Cambodia + 13:30 UTC+8` → `{id,date,home,away,league}` cache `futsal.upcoming` 3h, timeout 15s [FR-004,FR-008]
- [X] T003 [done] Extend `SportPrefsService::SPORTS` add `futsal` + alias normalize `timnas`/`garuda` → `Indonesia` entity in `app/Services/SportPrefsService.php` [FR-001,FR-010]

**Checkpoint**: Service + SPORTS ready

---

## Phase 3: User Story 1 - Follow Timnas Futsal Indonesia (Priority: P1)

**Goal**: `/follow futsal Indonesia|timnas|garuda` validasi statis map ke `Indonesia`

- [X] T004 [done] [P] [US1][FR-001][FR-002] Extend `BotRouter::handleFollow` futsal alias handling `futsal` second token `indonesia|timnas|garuda` normalize before SPORTS check in `app/Services/BotRouter.php`
- [X] T005 [done] [US1][FR-001] Extend `BotRouter::resolveEntity` futsal branch static validasi `Indonesia` else warning `Hanya Indonesia yang didukung` in `app/Services/BotRouter.php`
- [X] T006 [done] [P] [US1][FR-002] Feature test follow/unfollow/myteams futsal in `tests/Feature/BotRouterFutsalTest.php` — follow persists canonical, reject Thailand, myteams lists

**Checkpoint**: US1 independently testable

---

## Phase 4: User Story 2 - /jadwal tampilkan Timnas Futsal 24h DB-first + fallback (Priority: P1) 🎯 MVP

**Goal**: `/jadwal` menampilkan futsal ⚽ dari DB 0–24h, fallback Wikipedia per-sport

- [X] T007 [done] [US2][FR-003][FR-004][FR-005] Extend `BotRouter::handleJadwal` futsal branch — DB filter `sport_type=futsal` + fallback `FutsalService::getUpcomingMatches` + `isNext24Hours` + contains Indonesia, merge sort cap10 in `app/Services/BotRouter.php`
- [X] T008 [done] [US2][FR-005] Extend `formatJadwalRow` futsal case `⚽ Indonesia vs {lawan} — {league} — ⏱️ WIB` in `app/Services/BotRouter.php`
- [X] T009 [done] [P] [US2][FR-003][FR-004] Extend `tests/Feature/BotRouterFutsalTest.php` — DB shows futsal, DB empty fallback hits Wikipedia, per-sport isolation, empty case

**Checkpoint**: US2 MVP — /jadwal shows timnas futsal

---

## Phase 5: User Story 3 - Scheduler & notifier H-1 Timnas Futsal (Priority: P2)

**Goal**: `bot:schedule` H-1 insert futsal `notified=false`, `bot:notify` 1h send `notified=true`

- [X] T010 [done] [P] [US3][FR-006] Add futsal branch in `app/Console/Commands/MatchScheduler.php` — `getUpcomingMatches` + `isOneDayAway` + dedup `source_id:uUID` insert `futsal`
- [X] T011 [done] [P] [US3][FR-006] Add futsal branch in `app/Console/Commands/MatchNotifier.php` — 1h `startsSoon` send `⚽ 1 jam lagi!` update `notified`
- [X] T012 [done] [US3][FR-006] Feature tests scheduler/notifier futsal in `tests/Feature/MatchSchedulerFutsalTest.php` — schedule idempotent, notify updates
- [X] T013 [done] [US3][FR-008] Verify per-sport try/catch futsal without crash (already in handleJadwal/scheduler)

**Checkpoint**: All 3 stories functional

---

## Phase 6: Polish & Cross-Cutting Concerns

- [X] T014 [done] [P] Run `./vendor/bin/pint` on `app/Services/FutsalService.php`, `app/Services/SportPrefsService.php`, `app/Services/BotRouter.php`, `app/Console/Commands/MatchScheduler.php`, `app/Console/Commands/MatchNotifier.php`
- [X] T015 [done] [P] Run `composer test` (121+ incl. new futsal tests) verify pint green
- [X] T016 [done] Validate `specs/005-timnas-futsal/quickstart.md` manual `/follow futsal timnas` + `/jadwal`

---

## Requirement Coverage

| FR | Task(s) |
| -- | ------- |
| FR-001 futsal SPORTS alias | T003, T004, T005 |
| FR-002 prefs CRUD myteams | T004, T006 |
| FR-003 jadwal DB-first futsal | T007, T009 |
| FR-004 fallback Wikipedia per-sport | T002, T007, T009 |
| FR-005 format ⚽ | T007, T008 |
| FR-006 scheduler/notifier futsal | T010, T011, T012 |
| FR-007 reuse helper | T007, T010, T011 |
| FR-008 error catch 200 | T002, T013 |
| FR-009 no new table | implicit |
| FR-010 alias timnas/garuda | T003, T004, T005 |
