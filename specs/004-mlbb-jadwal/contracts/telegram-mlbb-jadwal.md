# Contract: Mobile Legends Jadwal + Follow

**Source**: `specs/004-mlbb-jadwal/spec.md` FR-001…FR-011
**Router**: `app/Services/BotRouter.php` + `SportPrefsService` + `MobileLegendService`

## Triggers

- Follow: text `str_starts_with('/follow')` dengan token[1] in `['mobilelegend','mlbb','ml']` → normalize ke `mobilelegend`
- Jadwal: existing `/jadwal|/schedule|/next` → `handleJadwal` includes MLBB branch
- API: `MobileLegendService::getUpcomingMatches()` `searchTeams(query)` cache 3h/1d, timeout 15s

## Follow Behavior

- Input: `/follow mobilelegend ONIC` → `resolveEntity('mobilelegend', 'ONIC')` → `searchTeams('ONIC')` → exact case-insensitive match → `addPreference(uid,'mobilelegend','onic','ONIC')` → reply `✅ Memantau ONIC di mobilelegend`
- Not found: reply suggestion `⚠️ Tim X tidak ditemukan` atau `Maksudmu: ONIC, EVOS,...`
- Alias: `/follow mlbb EVOS` → same canonical

## Jadwal Behavior

- DB: `Supabase select match_schedule where sport_type=mobilelegend & source_id endswith :uUID & isNext24Hours(match_time)` → `formatJadwalRow` 🎮
- Fallback per-sport: if empty, `MobileLegendService::getUpcomingMatches()` → `isNext24Hours(date)` + `NameMatcher::matches(home/entity)` → `🎮 home vs away — league — ⏱️ WIB` merge sort cap10

## Scheduler/Notifier

- Schedule: `MatchScheduler` H-1 20–30h `isOneDayAway` → dedup `source_id` → insert `mobilelegend` notified false
- Notify: `MatchNotifier` 1h `startsSoon`/`isNext24Hours` → send `🎮 1 jam lagi!` → update `notified true`

## Response Shapes (MLBB)

- DB/ fallback line: `🎮 ONIC vs EVOS — MPL Indonesia S15 — ⏱️ 28/08/2026 19:00 WIB`
- Empty MLBB alone still `📭 Tidak ada jadwal dalam 24 jam` if all sports empty
- Unauthorized: same `❌ Unauthorized` before any MLBB query
