---
description: "Task list for mlbb-jadwal feature"
---

# Tasks: Jadwal Mobile Legends (MPL ID) 24 Jam di Telegram

**Input**: `specs/004-mlbb-jadwal/spec.md`, `plan.md`, `data-model.md`, `contracts/telegram-mlbb-jadwal.md`  
**Prerequisites**: plan.md, spec.md, data-model.md, research.md, contracts/  
**Branch**: `004-mlbb-jadwal`

## Format: `[ID] [P?] [Story] [FR-###?] Description`

## Phase 1: Setup (Shared Infrastructure)

- [X] T001 [done] Verify branch `004-mlbb-jadwal` and docs exist in `specs/004-mlbb-jadwal/`

---

## Phase 2: Foundational (Blocking Prerequisites)

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [X] T002 [done] [P] Create `app/Services/MobileLegendService.php` with `getUpcomingMatches()` `searchTeams()` shape `{id,date,home,away,league}` cache `mlbb.upcoming` 3h / `mlbb.teams.*` 1d, timeout 15s, provisional Liquipedia/MPL provider [FR-002,FR-005,FR-009]
- [X] T003 [done] Extend `SportPrefsService::SPORTS` add `mobilelegend` + alias helper normalize `mlbb`/`ml` → `mobilelegend` in `app/Services/SportPrefsService.php` [FR-001]

**Checkpoint**: Service + SPORTS ready — US1-3 can build on it

---

## Phase 3: User Story 1 - Follow tim MLBB dan validasi (Priority: P1)

**Goal**: `/follow mobilelegend|mlbb|ml <team>` tervalidasi via `searchTeams`, suggestion on miss

- [X] T004 [done] [P] [US1][FR-001][FR-002] Unit test `tests/Unit/MobileLegendServiceTest.php` — searchTeams exact, suggestion fuzzy not required, empty on provider error
- [X] T005 [done] [US1][FR-001][FR-002][FR-003] Extend `BotRouter::handleFollow` alias handling `mobilelegend|mlbb|ml` normalize before `SPORTS` check in `app/Services/BotRouter.php`
- [X] T006 [done] [US1][FR-002] Extend `BotRouter::resolveEntity` with MLBB branch `searchTeams` + suggestion reply `app/Services/BotRouter.php`
- [X] T007 [done] [US1][FR-003] Feature test follow/unfollow/myteams MLBB in `tests/Feature/BotRouterMlbbTest.php` — follow persists, unfollow deletes, myteams lists

**Checkpoint**: US1 independently testable: follow MLBB works

---

## Phase 4: User Story 2 - /jadwal tampilkan MLBB 24h DB-first + fallback (Priority: P1) 🎯 MVP

**Goal**: `/jadwal` menampilkan MLBB 🎮 dari DB 0–24h, fallback API per-sport

- [X] T008 [done] [US2][FR-004][FR-005][FR-006][FR-008] Extend `BotRouter::handleJadwal` MLBB branch — DB filter `sport_type=mobilelegend` + fallback `MobileLegendService::getUpcomingMatches` + `isNext24Hours` + `NameMatcher`, merge sort cap10 in `app/Services/BotRouter.php`
- [X] T009 [done] [US2][FR-006][FR-008] Extend `formatJadwalRow` with `mobilelegend` → `🎮 home vs away — competition — ⏱️ WIB` in `app/Services/BotRouter.php`
- [X] T010 [done] [P] [US2][FR-004][FR-005] Extend `tests/Feature/BotRouterMlbbTest.php` — DB shows MLBB, DB empty fallback hits API, per-sport isolation (football DB not hit when MLBB fallback), empty case, cap10

**Checkpoint**: US2 MVP — /jadwal shows MLBB

---

## Phase 5: User Story 3 - Scheduler & notifier H-1 MLBB (Priority: P2)

**Goal**: `bot:schedule` H-1 (20–30h) insert MLBB notified=false, `bot:notify` 1h send notified=true

- [X] T011 [done] [P] [US3][FR-007] Add MLBB branch in `app/Console/Commands/MatchScheduler.php` — `getUpcomingMatches` + `isOneDayAway` + NameMatcher + dedup `source_id:uUID` insert
- [X] T012 [done] [P] [US3][FR-007] Add MLBB branch in `app/Console/Commands/MatchNotifier.php::notify*` — 1h window send `🎮 1 jam lagi!` + update `notified`
- [X] T013 [done] [US3][FR-007] Feature tests scheduler/notifier MLBB in `tests/Feature/MatchSchedulerMlbbTest.php` + extend `tests/Feature/MatchNotifierTest.php` or BotRouterMlbbTest — schedule idempotent, notify updates, skips notified true
- [X] T014 [done] [US3][FR-009] Add alias handling already in T005; verify `BotRouter` sport hint lists `mobilelegend` on unknown sport

**Checkpoint**: All 3 stories functional, MLBB lifecycle complete

---

## Phase 6: Polish & Cross-Cutting Concerns

- [X] T015 [done] [P] Run `./vendor/bin/pint` on `app/Services/MobileLegendService.php`, `app/Services/SportPrefsService.php`, `app/Services/BotRouter.php`, `app/Console/Commands/MatchScheduler.php`, `app/Console/Commands/MatchNotifier.php`
- [X] T016 [done] [P] Run `composer test` (112+ incl. new MLBB tests) — verify pint green
- [X] T017 [done] Validate `specs/004-mlbb-jadwal/quickstart.md` manual flow via `/follow mlbb` + `/jadwal` (+ `bot:schedule` hourly)

---

## Requirement Coverage

| FR | Task(s) |
| -- | ------- |
| FR-001 alias SPORTS | T003, T005 |
| FR-002 searchTeams validation | T002, T004, T006 |
| FR-003 prefs CRUD myteams | T005, T007 |
| FR-004 jadwal DB-first MLBB | T008, T010 |
| FR-005 fallback per-sport MLBB | T002, T008, T010 |
| FR-006 format 🎮 | T008, T009 |
| FR-007 scheduler/notifier MLBB | T011, T012, T013 |
| FR-008 reuse isNext24Hours | T008, T009, T011, T012 |
| FR-009 error catch 200 | T002, T013, T014 |
| FR-010 no new table | implicit verify |
| FR-011 alias jadwal existing | T008 |
