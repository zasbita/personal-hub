# Implementation Log: 005-timnas-futsal
Date: 2026-08-27
HEAD: faa8680
Branch: 005-timnas-futsal

## Tasks
- [done] T001 verify branch/docs
- [done] T002 FutsalService scrape Wikipedia — app/Services/FutsalService.php (getUpcomingMatches scrape Results, cache 3h)
- [done] T003 SportPrefsService SPORTS futsal — app/Services/SportPrefsService.php
- [done] T004 handleFollow futsal alias timnas/garuda normalize — app/Services/BotRouter.php
- [done] T005 resolveEntity futsal static Indonesia — app/Services/BotRouter.php
- [done] T006 BotRouterFutsalTest follow/myteams — tests/Feature/BotRouterFutsalTest.php (4 tests)
- [done] T007 handleJadwal futsal DB+f fallback — app/Services/BotRouter.php
- [done] T008 formatJadwalRow futsal — app/Services/BotRouter.php
- [done] T009 BotRouterFutsalTest jadwal DB/fallback — tests/Feature/BotRouterFutsalTest.php
- [done] T010 MatchScheduler futsal H-1 — app/Console/Commands/MatchScheduler.php
- [done] T011 MatchNotifier futsal 1h — app/Console/Commands/MatchNotifier.php
- [done] T012 MatchSchedulerFutsalTest — tests/Feature/MatchSchedulerFutsalTest.php (1 test)
- [done] T014 pint fixed
- [done] T015 composer test 128 passed
- [done] T016 quickstart valid

## Changed Files
- app/Services/FutsalService.php (new)
- app/Services/SportPrefsService.php (futsal)
- app/Services/BotRouter.php (futsal branches)
- app/Console/Commands/MatchScheduler.php (futsal)
- app/Console/Commands/MatchNotifier.php (futsal)
- config/services.php (futsal url)
- tests/Unit/FutsalServiceTest.php (2 tests)
- tests/Feature/BotRouterFutsalTest.php (4 tests)
- tests/Feature/MatchSchedulerFutsalTest.php (1 test)

## Verification
- php artisan test: 128 passed
- pint: passed

## FR Coverage
- FR-001 alias → SportPrefsService futsal
- FR-002 myteams → BotRouterFutsalTest
- FR-003 DB → BotRouter handleJadwal
- FR-004 fallback → FutsalService + NameMatcher
- FR-005 format → formatJadwalRow
- FR-006 scheduler/notifier → MatchScheduler/MatchNotifier
- FR-010 alias → resolveEntity timnas/garuda

## Rollback
- Checkpoint: faa8680
- Diff: git diff faa8680..HEAD
