---
description: "Task list for H-1 jadwal feature"
---

# Tasks: Simpan Jadwal H-1 Pertandingan Mendatang

**Input**: `specs/001-jadwal-h-1/spec.md`, `plan.md`  
**Prerequisites**: plan.md, spec.md  
**Branch**: `001-jadwal-h-1`

## Phase 1: Setup

- [X] T001 [done] Create `specs/001-jadwal-h-1` branch & docs

## Phase 2: Foundational

- [X] T002 [done] Extract helper `isInWindow`/`sourceId` → `app/Support/MatchHelper.php`; `MatchNotifier.php:15` now delegates to `MatchHelper`

## Phase 3: US1 — Jadwal tersimpan H-1 (P1) 🎯 MVP

**Goal**: Fixture besok tersimpan H-1 dengan notified=false

- [X] T003 [done] Buat `app/Console/Commands/MatchScheduler.php` (`bot:schedule`) — fetch via `FootballService::getUpcomingFixtures`, filter `isOneDayAway` (20-30h), match `NameMatcher`, insert notified=false
- [X] T004 [done] Deduplicate `source_id`+`sport_type` via `SupabaseService::select` sebelum insert; shape konsisten dengan `MatchNotifier.php:93`
- [X] T005 [done] Register schedule di `routes/console.php:7` → `Schedule::command('bot:schedule')->hourly()->withoutOverlapping()`
- [X] T006 [done] Handle `bot:notify` reuse row H-1: `MatchNotifier.php:89,143` cek `notified` → jika false update true, jika true/missing skip

## Phase 4: US2 — Idempotent & Edge Cases (P1)

- [X] T007 [done] Tidak duplikat: `select` + `source_id` key + `array_column(..., null, 'id')` di `MatchScheduler.php:38`
- [X] T008 [done] Error handling: try/catch per sport + `TelegramService` report owner di `MatchScheduler.php:52,79`

## Phase 5: US3 — Volly & MotoGP (P2)

- [X] T009 [done] Volly branch di `MatchScheduler.php:48` via `VolleyballService::getUpcomingGames`
- [X] T010 [done] MotoGP branch di `MatchScheduler.php:58` via `MotoGPService::getCurrentSeasonRaces` + shape `MatchNotifier.php:137`
- [X] T011 [done] `routes/console.php` sudah hourly — no-op verified via `schedule:list`

## Phase 6: Polish

- [X] T012 [done] Tests: `tests/Feature/MatchSchedulerTest.php` (6 tests) + `tests/Unit/MatchHelperTest.php` (3 tests)
- [X] T013 [done] `php artisan test` 98 passed + `php artisan schedule:list` shows `bot:schedule` hourly

## Dependencies

- T002 blocks T003
- T003 blocks T004,T005,T006
- T003 blocks T009,T010 (parallel setelah T003)
- T012 after T003-T010

## Parallel Example

```bash
# Setelah T003 selesai:
# - T007 + T008 parallel
# - T009 + T010 parallel
```
