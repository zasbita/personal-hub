# Contract: Telegram /schedule 7 Days English

**Source**: `specs/006-schedule-7d-english/spec.md` FR-001…FR-010

## Triggers

- Primary English: `text === '/schedule'` ; aliases `'/jadwal'` and `'/next'` → same `handleJadwal`
- API: all sport services filter `isNext7Days(date)` 0–168h, DB `match_schedule` where `match_time` 0–168h

## Behavior

- DB: `Supabase select match_schedule 0–168h status NS/scheduled source_id:uUID` → format `DisplayTime` WIB sort asc
- Fallback per-sport 7d: `FootballService` 7 dates, `VolleyballService` 7 dates, `MobileLegendService` many days filtered 168h, `FutsalService` many days filtered 168h → `isNext7Days` + `NameMatcher` → merge sort cap10 English header
- MENU: `schedule => "Check schedule next 7 days — /schedule"` (English)

## Response Shapes (English)

- Empty: `📭 No schedule in the next 7 days.`
- No pref: `📭 No teams followed. Use /follow [sport] [team].`
- Populated: `📅 Schedule next 7 days` + `1. ⚽ Arsenal vs Chelsea — PL — ⏱️ 28/08/2026 20:00 WIB` + `… and N more`
- Error: `⚠️ Failed to fetch schedule: {msg}`
