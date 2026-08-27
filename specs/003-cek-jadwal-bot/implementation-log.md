# Implementation Log: 003-cek-jadwal-bot
Date: 2026-08-27
HEAD: 2a9431a
Branch: 003-cek-jadwal-bot

## Sessions
- 2026-08-27 T001-T016 implement — BotRouter /jadwal DB-first + per-sport API fallback

## Tasks
- [done] T001 verify branch/docs
- [done] T002 MatchHelper NEXT_24H_HOURS=24 + isNext24Hours() — app/Support/MatchHelper.php:14,41
- [done] T002b DisplayTime verified (no change)
- [done] T003 BotRouterJadwalTest DB path — tests/Feature/BotRouterJadwalTest.php (5 tests)
- [done] T004 route /jadwal /schedule /next — app/Services/BotRouter.php:139
- [done] T005 handleJadwal DB-first + sort/cap/format — app/Services/BotRouter.php:378
- [done] T006 formatJadwalRow — app/Services/BotRouter.php:469
- [done] T007 extend test fallback per-sport + cap/sorted — tests/Feature/BotRouterJadwalTest.php (+4 tests)
- [done] T008 fetchFootballFallback/fetchVollyFallback/fetchMotoFallback — app/Services/BotRouter.php:490,518,546
- [done] T009 cache reuse verified (FootballService 3h TTL reused, no new cache)
- [done] T010 try/catch per sport + global catch — app/Services/BotRouter.php:398,412,464
- [done] T011 MENU jadwal — app/Services/BotRouter.php:32
- [done] T012 WELCOME /jadwal — app/Services/BotRouter.php:50
- [done] T013 RegisterWebhook already generic via BotRouter::MENU (no code change)
- [done] T014 pint fixed — BotRouter.php + MatchHelper.php + BotRouterJadwalTest.php
- [done] T015 composer test 112 passed, BotRouterJadwalTest 9 passed, MatchHelperWindowTest 1 passed
- [done] T016 quickstart manual flow documented

## Changed Files
- app/Support/MatchHelper.php (added NEXT_24H_HOURS + isNext24Hours)
- app/Services/BotRouter.php (MENU, WELCOME, handle()+handleJadwal+helpers)
- tests/Feature/BotRouterJadwalTest.php (new, 9 tests)
- tests/Unit/MatchHelperWindowTest.php (new, 1 test)
- specs/003-cek-jadwal-bot/* (spec, plan, research, data-model, contracts, tasks)

## Verification
- php artisan test: 112 passed (2026-08-27)
- ./vendor/bin/pint: passed
- php artisan test --filter=BotRouterJadwalTest: 9 passed

## FR Coverage Evidence
- FR-001 alias → BotRouter.php:139 + BotRouterJadwalTest::test_alias_schedule_and_next
- FR-002 DB 0-24h → BotRouter.php:400 + test_jadwal_shows_db_results
- FR-003 fallback → BotRouter.php:490-569 + test_fallback_hits_api_when_db_empty
- FR-004 per-sport → BotRouter.php:429-444 + test_per_sport_fallback_only_missing_sport
- FR-005 WIB sort → BotRouter.php:452,473 + test_cap_ten_and_sorted
- FR-006 MENU → BotRouter.php:32,50 + test_menu_contains_jadwal
- FR-007 reuse → MatchHelper.php:41
- FR-008 cap10 → BotRouter.php:453-461 + cap test
- FR-009 unauthorized → BotRouter.php:88-92 + test_unauthorized_does_not_query_db
- FR-010 no write → BotRouter.php handleJadwal read-only (no insert/update)
- FR-011 cache → FootballService::getUpcomingFixtures 3h reused

## Rollback
- Checkpoint HEAD: 2a9431a
- Diff: git diff 2a9431a..HEAD
- Single file revert: git checkout 2a9431a -- app/Services/BotRouter.php
- Full reset (discards): git reset --hard 2a9431a
