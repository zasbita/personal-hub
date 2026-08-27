# Contract: Telegram /jadwal Command

**Source**: `specs/003-cek-jadwal-bot/spec.md` FR-001…FR-011
**Router**: `app/Services/BotRouter.php` → `handleJadwal(int uid, int cid)`
**Auth**: `from.id == config(services.telegram.owner_id)` else `Unauthorized`

## Triggers

- `text === '/jadwal'` or `text === '/schedule'` or `text === '/next'`
- Also handle `str_starts_with`? No args — exact match only (no `/jadwal <args>`)

## Behavior

1. Auth check.
2. `SportPrefsService::getActivePreferences` → filter by `user_id == uid`? Actually service returns all, but handler filters by uid or uses `getPreferences(uid)`. Prefer `getPreferences(uid)`.
3. DB: `SupabaseService::select('match_schedule', ['select'=>'sport_type,competition,home_team,away_team,match_time,status', 'status'=>'eq.NS', ...])` then client `isNext24Hours(match_time)`; group by `sport_type`.
4. For each sport with empty DB result, fallback: `FootballService::getUpcomingFixtures()` etc., filter `isNext24Hours(date)` + `NameMatcher::matches`.
5. Sort asc `match_time`, cap 10, format via `DisplayTime::format`, `TelegramService::sendMessage(cid, formatted, parse_mode=Markdown)`.

## Response Shapes

- Empty (no prefs): `📭 Belum ada yang dipantau. Gunakan /follow ...`
- Empty (no jadwal): `📭 Tidak ada jadwal dalam 24 jam ke depan.`
- Populated: `📅 *Jadwal 24 jam ke depan*\n\n⚽ Arsenal vs Chelsea — Premier League — ⏱️ Besok 20:00 WIB` (per line) + optional `… dan N lainnya`
- Error: `⚠️ Gagal ambil jadwal: <msg>`
- Unauthorized: `❌ Unauthorized. Your ID: <uid>`

## MENU

- `BotRouter::MENU['jadwal'] = 'Cek jadwal 1 hari ke depan — /jadwal'`
- `WELCOME` sport section: "`/jadwal — cek jadwal 24 jam ke depan`"
