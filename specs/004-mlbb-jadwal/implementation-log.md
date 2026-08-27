# Implementation Log: 004-mlbb-jadwal
Date: 2026-08-27
HEAD: 4c7c1e7
Branch: 004-mlbb-jadwal

## Tasks
- [done] T001 verify branch/docs
- [done] T002 MobileLegendService create — app/Services/MobileLegendService.php (getUpcomingMatches, searchTeams, cache 3h/1d)
- [done] T003 SportPrefsService SPORTS+mobilelegend + ALIASES mlbb/ml — app/Services/SportPrefsService.php
- [done] T004 MobileLegendServiceTest — tests/Unit/MobileLegendServiceTest.php (3 tests)
- [done] T005 handleFollow alias mlbb/ml normalize — app/Services/BotRouter.php
- [done] T006 resolveEntity MLBB branch — app/Services/BotRouter.php
- [done] T007 BotRouterMlbbTest follow/myteams — tests/Feature/BotRouterMlbbTest.php (4 tests)
- [done] T008 handleJadwal MLBB DB+fallback — app/Services/BotRouter.php
- [done] T009 formatJadwalRow 🎮 — app/Services/BotRouter.php
- [done] T010 BotRouterMlbbTest jadwal DB/fallback isolation — tests/Feature/BotRouterMlbbTest.php
- [done] T011 MatchScheduler MLBB H-1 — app/Console/Commands/MatchScheduler.php
- [done] T012 MatchNotifier MLBB notify — app/Console/Commands/MatchNotifier.php
- [done] T013 MatchSchedulerMlbbTest — tests/Feature/MatchSchedulerMlbbTest.php (2 tests)
- [done] T015 pint fixed
- [done] T016 composer test 121 passed
- [done] T017 quickstart valid

## Changed Files
- app/Services/MobileLegendService.php (new)
- app/Services/SportPrefsService.php (SPORTS+ALIASES+normalizeSport)
- app/Services/BotRouter.php (follow alias, resolveEntity, handleJadwal, format, fallback)
- app/Console/Commands/MatchScheduler.php (lp mobilelegend)
- app/Console/Commands/MatchNotifier.php (lp mobilelegend)
- config/services.php (mpl url/key)
- tests/Unit/MobileLegendServiceTest.php (new)
- tests/Feature/BotRouterMlbbTest.php (new)
- tests/Feature/MatchSchedulerMlbbTest.php (new)

## Verification
- php artisan test: 121 passed (27 Aug)
- pint: passed

## FR Coverage
- FR-001 alias → SportPrefsService ALIASES + BotRouter handleFollow
- FR-002 searchTeams → MobileLegendService + resolveEntity
- FR-003 myteams → SportPrefsService
- FR-004 jadwal DB → BotRouter handleJadwal
- FR-005 fallback → MobileLegendService + NameMatcher
- FR-006 🎮 → formatJadwalRow
- FR-007 scheduler/notifier → MatchScheduler/MatchNotifier
- FR-008 reuse helper → MatchHelper isNext24Hours
- FR-009 catch → per-sport try/catch
- FR-010 no new table → SupabaseService reuse
- FR-011 alias jadwal → existing route

## Rollback
- Checkpoint: 4c7c1e7
- Diff: git diff 4c7c1e7..HEAD
