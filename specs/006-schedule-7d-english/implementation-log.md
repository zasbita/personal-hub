# Implementation Log: 006-schedule-7d-english
Date: 2026-08-27
HEAD: 776b713
Branch: 006-schedule-7d-english

## Tasks
- [done] T001 verify branch/docs
- [done] T002 MatchHelper NEXT_7D_HOURS=168 + isNext7Days (fix sourceId restore)
- [done] T003 FootballService 7 dates today..+6 cache 3h
- [done] T004 VolleyballService 7 dates today..+6 cache 3h
- [done] T005 BotRouter handleJadwal 0-168h isNext7Days
- [done] T006 English messages Schedule next 7 days / No schedule...
- [done] T007 BotRouterJadwalTest updated English + 7d
- [done] T008 MENU schedule English + WELCOME
- [done] T010 MLBB/Futsal filter 7d verified
- [done] T012 pint fixed
- [done] T013 composer test 128 passed
- [done] T014 quickstart valid

## Changed Files
- app/Support/MatchHelper.php (sourceId restored + NEXT_7D)
- app/Services/FootballService.php (7d loop)
- app/Services/VolleyballService.php (7d loop)
- app/Services/BotRouter.php (MENU schedule English, handleJadwal 7d)
- tests/Feature/BotRouterJadwalTest.php (English assertions)
- tests/Unit/VolleyballServiceTest.php (assert 7)

## Verification
- php artisan test: 128 passed (2026-08-27)
- pint: passed
- volly schedule error fixed via sourceId restore

## Rollback
- Checkpoint: e434fc8
- Diff: git diff e434fc8..776b713
