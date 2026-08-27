---
description: "Task list for cek-jadwal-bot feature"
---

# Tasks: Cek Jadwal Pertandingan 1 Hari via Telegram Bot (DB-first, API fallback)

**Input**: `specs/003-cek-jadwal-bot/spec.md`, `plan.md`, `data-model.md`, `contracts/telegram-jadwal-command.md`  
**Prerequisites**: plan.md, spec.md, data-model.md, research.md, contracts/  
**Branch**: `003-cek-jadwal-bot`
**Tests**: Included per constitution (Principle III — Tests Ship With Changes)

## Format: `[ID] [P?] [Story] [FR-###?] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1, US2, US3)
- **[FR-###]**: Which functional requirement(s) this task satisfies

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Branch & docs already initialized by `create-new-feature`

- [X] T001 [done] Verify branch `003-cek-jadwal-bot` and docs exist in `specs/003-cek-jadwal-bot/`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Shared helper that all user stories depend on

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [X] T002 [done] [P] Add `isNext24Hours` wrapper and `NEXT_24H_HOURS=24` constant to `app/Support/MatchHelper.php` (`isInWindow(now, now+24h)`) [FR-007]
- [X] T002b [done] Verify `DisplayTime::format` handles ISO with timezone correctly (no code change if pass) in `app/Services/DisplayTime.php`

**Checkpoint**: Helper ready — US1/US2/US3 can now build on it

---

## Phase 3: User Story 1 - Cek jadwal 1 hari via Telegram (Priority: P1) 🎯 MVP

**Goal**: `/jadwal` membalas jadwal 0–24h dari `match_schedule` untuk yang difollow, WIB, urut asc, cap 10, tanpa API

**Independent Test**: Follow Arsenal, insert mock `match_schedule` row Arsenal `match_time` +12h, kirim `/jadwal` via mocked `BotRouter::handle` → balas mengandung "Arsenal vs ..." (DisplayTime WIB), tidak call `FootballService`

### Tests for User Story 1

- [X] T003 [done] [P] [US1][FR-001][FR-002][FR-009] Create `tests/Feature/BotRouterJadwalTest.php` — DB path: authorized returns list, unauthorized returns Unauthorized, no-pref hint, cap 10, sort asc

### Implementation for User Story 1

- [X] T004 [done] [US1][FR-001][FR-009] Route `/jadwal` `/schedule` `/next` in `app/Services/BotRouter.php::handle()` (before generic `str_starts_with('/')` fallback) → `handleJadwal(uid,cid)`
- [X] T005 [done] [US1][FR-002][FR-004][FR-005][FR-008][FR-010] Implement `handleJadwal` DB-first in `app/Services/BotRouter.php` — load prefs `SportPrefsService::getPreferences(uid)` filter `notification_enabled`, query `SupabaseService::select('match_schedule', ... status NS/scheduled ...)` (or select all for uid then client `isNext24Hours`), filter per sport, sort asc, cap 10, format `DisplayTime::format`, `TelegramService::sendMessage` [FR-002 FR-005 FR-008 FR-010]
- [X] T006 [done] [US1][FR-005][FR-008] Extract formatter `formatJadwalRow(array $r)` → header "📅 *Jadwal 24 jam ke depan*" + lines `emoji home vs away — competition — ⏱️ WIB` + overflow "… dan N lainnya", handled in `app/Services/BotRouter.php`

**Checkpoint**: US1 independently testable: `/jadwal` works from DB without API

---

## Phase 4: User Story 2 - Fallback ke API jika DB kosong (Priority: P1)

**Goal**: Per-sport fallback hit API langsung jika `match_schedule` kosong untuk sport itu

**Independent Test**: Mock `SupabaseService::select` return [] for football, mock `FootballService::getUpcomingFixtures` return fixture Arsenal +12h matching pref → `/jadwal` tetap balas fixture api; mock quota error → balas error ramah tanpa crash

### Tests for User Story 2

- [X] T007 [done] [P] [US2][FR-003][FR-004] Extend `tests/Feature/BotRouterJadwalTest.php` — fallback per sport: DB empty → API hit, per-sport isolation (football has DB so no API for football), API empty still empty, timeout → error message

### Implementation for User Story 2

- [X] T008 [done] [US2][FR-003][FR-004][FR-011] Add per-sport API fallback in `app/Services/BotRouter.php::handleJadwal` — for each sport with empty DB result: call `FootballService::getUpcomingFixtures` / `VolleyballService::getUpcomingGames` / `MotoGPService::getCurrentSeasonRaces`, filter `MatchHelper::isNext24Hours(date)` + `NameMatcher::matches`/`matchesRace`, merge with DB results [FR-003 FR-004 FR-011]
- [X] T009 [done] [P] [US2][FR-003][FR-011] Handle `FootballService` cache reuse verified: no extra cache layer; ensure `getUpcomingFixtures` 3h TTL still used (code inspection in `app/Services/FootballService.php`)
- [X] T010 [done] [US2][FR-003] Per-sport try/catch in `app/Services/BotRouter.php::handleJadwal` — Supabase throw falls through to API, API throw → "⚠️ Gagal ambil jadwal ..." without propagate (webhook stays 200)

**Checkpoint**: US1+US2 both work: DB-first with per-sport API fallback

---

## Phase 5: User Story 3 - Command terdaftar & discoverable (Priority: P2)

**Goal**: MENU, WELCOME help, and webhook publish include `jadwal`

**Independent Test**: Assert `BotRouter::MENU` contains `jadwal`, `WELCOME` contains `/jadwal`, call `RegisterWebhook` logic publishes `jadwal` in `setMyCommands` payload

### Implementation for User Story 3

- [X] T011 [done] [US3][FR-006] Add `jadwal` entry to `app/Services/BotRouter.php::MENU` — `'jadwal' => 'Cek jadwal 1 hari ke depan — /jadwal'`
- [X] T012 [done] [US3][FR-006] Add `/jadwal` line to `app/Services/BotRouter.php::WELCOME` sport section — "`/jadwal — cek jadwal 24 jam ke depan`"
- [X] T013 [done] [US3][FR-006] Verify `app/Console/Commands/RegisterWebhook.php` publishes `BotRouter::MENU` to Telegram (no code change if already generic), else wire `jadwal` into `setMyCommands`

**Checkpoint**: All 3 stories functional, command discoverable

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Style, coverage, and quickstart validation

- [X] T014 [done] [P] Run `./vendor/bin/pint` on touched files `app/Services/BotRouter.php`, `app/Support/MatchHelper.php`, `tests/Feature/BotRouterJadwalTest.php`
- [X] T015 [done] [P] Run `composer test` and verify 98+ passed including new `BotRouterJadwalTest` and `MatchHelperWindowTest`
- [X] T016 [done] Validate `specs/003-cek-jadwal-bot/quickstart.md` manual flow via `php artisan bot:listen` or webhook (optional manual)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — T001 immediate
- **Foundational (Phase 2)**: Depends on Setup — BLOCKS all user stories (T002 must done before T003-T013)
- **User Stories (Phase 3-5)**: All depend on Foundational
  - US1 (P1) → US2 (P1) sequential (US2 extends same `handleJadwal` file, cannot parallelize T005/T008)
  - US3 (P2) is file-disjoint from T008? Both touch `BotRouter.php` — run after US1 to avoid merge conflict; but can be reviewed in parallel
- **Polish (Phase 6)**: Depends on US1+US2+US3

### Within Each User Story

- Tests written first and FAIL before implementation (T003 before T004, T007 before T008)
- Foundational helper before handler
- Handler before formatter
-MENU after handler verified

### Parallel Opportunities

- T002 and T002b can run in parallel (different files, but T002b is trivial check)
- T003 and T007 test scaffolding different methods but same file — sequential to avoid conflict (keep T007 as extend, not parallel)
- T009 and T010 within US2 can run in parallel conceptually (T009 is inspection, T010 is error handling)
- T011+T012 different constants in same file — sequential; T013 touches different file → could parallel with T011/T012 if using separate commits
- Polish T014 and T015 can run in parallel (pint vs test)

### Requirement Coverage

| FR | Task(s) |
| -- | ------- |
| FR-001 `/jadwal` + alias | T003, T004 |
| FR-002 DB first 0–24h | T003, T005 |
| FR-003 fallback API | T007, T008, T010 |
| FR-004 per-sport fallback | T005, T007, T008 |
| FR-005 WIB + sort | T005, T006 |
| FR-006 MENU+WELCOME+webhook | T011, T012, T013 |
| FR-007 reuse isInWindow | T002 |
| FR-008 cap 10 | T005, T006 |
| FR-009 unauthorized | T003, T004 |
| FR-010 no write | T005 (read-only assertion) |
| FR-011 cache reuse | T008, T009 |
| NFR-001 <3s/<5s | implicit, not task (manual observe) |
| NFR-002 no new dep | T009 |
| NFR-003 15s timeout | T010 |
| NFR-004 webhook 200 | T010 |

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. T001 → T002 → T003 → T004 → T005 → T006 → validate DB-only `/jadwal` works
2. STOP and VALIDATE: `composer test --filter=BotRouterJadwalTest` green
3. Deploy/demo MVP (read-only, no fallback yet still useful)

### Incremental Delivery

1. Setup+Foundational → helper ready
2. US1 Bolt → `/jadwal` DB-only → checkpoint
3. US2 Bolt → per-sport API fallback → checkpoint
4. US3 Bolt → MENU+help → checkpoint
5. Polish → pint + full test

---

## Notes

- [P] only where files truly disjoint; BotRouter is single file — most handler tasks sequential
- Each phase ends at checkpoint commit: `git commit -m "feat(jadwal): ..."`
- Verify with `php artisan bot:webhook` after T011-T013
