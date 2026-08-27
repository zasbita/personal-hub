# Contract: Timnas Futsal Jadwal + Follow

**Source**: `specs/005-timnas-futsal/spec.md` FR-001…FR-010
**Router**: `app/Services/BotRouter.php` + `SportPrefsService` + `FutsalService`

## Triggers

- Follow: `str_starts_with('/follow')` token[1] normalized `futsal`, token[2] `indonesia|timnas|garuda` → canonical `Indonesia`
- Jadwal: existing `/jadwal|/schedule|/next` → `handleJadwal` includes futsal branch
- API: `FutsalService::getUpcomingMatches()` scrape Wikipedia `Results and fixtures` cache 3h, timeout 15s

## Follow Behavior

- Input: `/follow futsal Indonesia` → `resolveEntityFutsal('Indonesia')` static valid → `addPreference(uid,'futsal','indonesia','Indonesia')` → `✅ Memantau Indonesia di futsal`
- Alias: `/follow futsal timnas` → same canonical; `/follow futsal Thailand` → `⚠️ Hanya Indonesia yang didukung untuk futsal saat ini.`
- Unfollow: `/unfollow futsal Indonesia` → delete

## Jadwal Behavior

- DB: `Supaselect match_schedule where sport_type=futsal & source_id:uUID & isNext24Hours(match_time)` → `formatJadwalRow` ⚽
- Fallback per-sport: if empty, `FutsalService::getUpcomingMatches()` → `isNext24Hours(date)` + contains `Indonesia` → `⚽ Indonesia vs Cambodia — CFA — ⏱️ WIB` merge sort cap10

## Scheduler/Notifier

- Schedule: `MatchScheduler` H-1 20–30h `isOneDayAway` → dedup `source_id:uUID` → insert `futsal` notified false
- Notify: `MatchNotifier` 1h `startsSoon` → send `⚽ 1 jam lagi! Indonesia vs Cambodia` → update `notified true`

## Response Shapes

- DB/fallback line: `⚽ Indonesia vs Cambodia — CFA International — ⏱️ 05/09/2025 20:30 WIB`
- Empty futsal alone still `📭 Tidak ada jadwal dalam 24 jam` if all empty
