# Personal Hub - Serene Darwin Bot

**A personal API & notification hub** for automating expense logging, vehicle maintenance reminders, and sports notifications. Built with TypeScript, Cloudflare Workers, Supabase, and Telegram Bot API.

## 🎯 Features

### 💰 Expense Quick-Logger
- Log expenses with natural language: `/log 50k lunch` or `/catat 100rb gas`
- Automatic unit conversion (50k → 50,000)
- Integration with Google Sheets API
- Weekly expense summary
- Instant confirmation messages

### 🏍️ Vehicle Service Tracker
- Update motorcycle mileage: `/update_km 15000`
- Automatic service schedule calculation
- Check service status: `/check_service`
- Alerts when service is due

### ⚽ Sports Notifications
- Follow favorite teams: `/follow football Liverpool`
- Manage team preferences: `/unfollow`, `/myteams`
- Upcoming match tracking
- Sports: Football, Mobile Legends, Volleyball, Futsal

## 🛠️ Tech Stack

| Layer | Technology | Purpose |
|-------|-----------|---------|
| **Runtime** | Cloudflare Workers | Serverless edge computing |
| **Framework** | Hono.js | Lightweight HTTP framework |
| **Language** | TypeScript | Type-safe development |
| **Database** | Supabase (PostgreSQL) | Real-time data storage |
| **Sheets API** | Google Sheets | Expense backup storage |
| **Bot API** | Telegram Bot API | User interface |
| **Automation** | GitHub Actions | Cron jobs & scraping |

## 📦 Project Structure

```
personal-hub/
├── src/
│   ├── index.ts                 # Entry point & routing
│   ├── handlers/               # Command handlers
│   │   ├── expense.ts
│   │   ├── vehicle.ts
│   │   └── sport.ts
│   ├── services/               # API wrappers
│   │   ├── supabase.ts        # Database client
│   │   ├── google-sheets.ts   # Google Sheets integration
│   │   ├── vehicle.ts         # Vehicle logic
│   │   └── sport-handler.ts   # Sport commands
│   ├── utils/                 # Utilities
│   │   ├── parsers.ts         # Regex parsers
│   │   └── validators.ts      # Input validation
│   └── types/                 # TypeScript interfaces
│       ├── expense.ts
│       └── sport.ts
├── tests/                     # Test files
│   ├── expense-parser.test.ts
│   ├── vehicle.test.ts
│   └── sport-prefs.test.ts
├── wrangler.toml             # Cloudflare config
├── package.json              # Dependencies
├── tsconfig.json             # TypeScript config
└── .env.example              # Environment template
```

## 🚀 Quick Start

### Prerequisites
- Node.js 18+
- Telegram Bot Token
- Supabase Account & API Keys
- Google Sheets API Credentials
- Cloudflare Account

### 1. Clone Repository
```bash
git clone https://github.com/zasbita/personal-hub.git
cd personal-hub
```

### 2. Install Dependencies
```bash
npm install
```

### 3. Configure Environment
```bash
cp .env.example .env
```

Edit `.env` with your credentials:
```env
BOT_TOKEN=your_telegram_bot_token
OWNER_ID=your_telegram_user_id
SUPABASE_URL=your_supabase_url
SUPABASE_KEY=your_supabase_service_key
GOOGLE_SHEET_ID=your_google_sheet_id
GOOGLE_SERVICE_ACCOUNT_EMAIL=your_service_account_email
GOOGLE_PRIVATE_KEY=your_service_account_private_key
FOOTBALL_API_KEY=your_football_api_key
```

### 4. Run Tests
```bash
npm run test
```

### 5. Deploy to Cloudflare
```bash
npx wrangler deploy
```

## 📋 Bot Commands

### Expense Logger
```
/log <amount> <description>     Log expense to Sheets
/catat <amount> <deskripsi>    Log expense (Indonesian)
/summary                        Weekly expense summary
```

**Examples:**
```
/log 50k lunch
/catat 100rb bensin
/summary
```

### Vehicle Tracker
```
/update_km <number>    Update current odometer
/check_service         Check service status
```

**Examples:**
```
/update_km 15000
/check_service
```

### Sports Management
```
/follow <sport> <team>      Follow a team
/unfollow <sport> <team>    Unfollow a team
/myteams                    List followed teams
```

**Supported Sports:**
- `football` - Football (Premier League, Liga 1, etc.)
- `mobile_legends` - Mobile Legends (esports)
- `volleyball` - Volleyball
- `futsal` - Futsal

**Examples:**
```
/follow football Liverpool
/follow mobile_legends "RRQ Hoshi"
/unfollow football Liverpool
/myteams
```

## 🗄️ Database Schema

### users
- `user_id` (BIGINT) - Telegram User ID
- `username` (TEXT) - Telegram username
- `pref_language` (TEXT) - Language preference
- `created_at` (TIMESTAMP)

### expenses
- `id` (UUID) - Unique expense ID
- `user_id` (BIGINT) - User reference
- `amount` (DECIMAL) - Transaction amount
- `description` (TEXT) - Item/service name
- `category` (TEXT) - Expense category
- `created_at` (TIMESTAMP)

### vehicle_service
- `id` (UUID) - Unique record ID
- `user_id` (BIGINT) - User reference
- `last_km` (INTEGER) - Last recorded mileage
- `service_interval` (INTEGER) - Interval in KM (default: 2000)
- `next_service_km` (INTEGER) - Next service milestone
- `updated_at` (TIMESTAMP)

### user_preferences
- `id` (UUID) - Unique preference ID
- `user_id` (BIGINT) - User reference
- `sport_type` (TEXT) - Sport category
- `entity_id` (TEXT) - Team/league identifier
- `entity_name` (TEXT) - Display name
- `notification_enabled` (BOOLEAN) - Alert toggle
- `created_at` (TIMESTAMP)

### match_schedule
- `id` (UUID) - Unique match ID
- `source_id` (TEXT) - External API ID
- `sport_type` (TEXT) - Sport category
- `competition` (TEXT) - League name
- `home_team` (TEXT) - Home team
- `away_team` (TEXT) - Away team
- `match_time` (TIMESTAMP) - Match start time
- `status` (TEXT) - Match status (scheduled/live/finished)
- `notified` (BOOLEAN) - Notification sent flag
- `created_at` (TIMESTAMP)

## 🧪 Testing

Run all tests:
```bash
npm run test
```

Test coverage includes:
- ✅ Expense parser (natural language)
- ✅ Vehicle service logic
- ✅ Sport preferences
- ✅ Bot routing & commands

**Current Status:** 17/17 tests passing ✅

## 📊 Project Phases

| Phase | Status | Features |
|-------|--------|----------|
| **Phase 1** | ✅ COMPLETE | Expense logger + Google Sheets |
| **Phase 2** | ✅ COMPLETE | Vehicle tracking + Supabase |
| **Phase 3** | ⏳ PARTIAL | Sport notifications (70% - cron pending) |
| **Phase 4** | ✅ COMPLETE | Admin Dashboard (separate repo) |

## 🔐 Security

✅ **User Whitelisting:** Only owner Telegram ID can use bot  
✅ **Environment Variables:** All secrets in `.env` (not committed)  
✅ **Supabase Service Key:** Uses service role for write operations  
✅ **Rate Limiting:** Implicit via Cloudflare Workers limits  
✅ **Input Validation:** Strict Regex parsing for all inputs  

## 📈 Deployment

### Cloudflare Workers
```bash
# Deploy to production
npx wrangler deploy

# Check logs
npx wrangler tail

# View worker details
npx wrangler list
```

### Environment Variables in Cloudflare
Set secrets in Wrangler:
```bash
npx wrangler secret put BOT_TOKEN
npx wrangler secret put SUPABASE_KEY
# ... etc
```

Or in `wrangler.toml`:
```toml
[env.production]
vars = { OWNER_ID = "811031481" }
```

## 🤝 Contributing

1. Create feature branch: `git checkout -b feature/amazing-feature`
2. Write tests for new functionality
3. Commit changes: `git commit -m "Add amazing feature"`
4. Push to branch: `git push origin feature/amazing-feature`
5. Open Pull Request

## 🐛 Troubleshooting

### Bot not responding?
- Check Telegram token in `.env`
- Verify webhook URL is correct
- Check Cloudflare Worker logs: `npx wrangler tail`

### Supabase connection errors?
- Verify `SUPABASE_URL` and `SUPABASE_KEY`
- Check database tables exist
- Ensure RLS policies allow SELECT/INSERT/UPDATE

### Google Sheets not updating?
- Verify `GOOGLE_SERVICE_ACCOUNT_EMAIL` has sheet access
- Check `GOOGLE_SHEET_ID` is correct
- Ensure service account key has Sheets API enabled

## 📚 Documentation

- **[AGENTS.md](./AGENTS.md)** - Project guidelines & standards
- **[PRD.md](./PRD.md)** - Product requirements & user stories
- **[DATABASE.md](./DATABASE.md)** - Database schema & DDL
- **[DESIGN.md](./DESIGN.md)** - Design tokens & UI guidelines

## 🔗 Related Projects

- **[personal-hub-dashboard](https://github.com/zasbita/personal-hub-dashboard)** - Admin dashboard (Phase 4)
- **Telegram Bot API** - https://core.telegram.org/bots
- **Cloudflare Workers** - https://workers.cloudflare.com
- **Hono.js** - https://hono.dev

## 📊 Statistics

- **Lines of Code:** ~2,500
- **Test Coverage:** 17 tests, 100% critical paths
- **Endpoints:** 8 bot commands
- **Database Tables:** 5
- **API Integrations:** 4 (Telegram, Supabase, Google Sheets, Football API)

## 📝 License

MIT - Personal project

## 👤 Author

**Muhamad Ikram Zasbita**
- GitHub: [@zasbita](https://github.com/zasbita)
- Website: Personal Hub Project

---

**Status:** ✅ Production Ready

*Last Updated: 2026-07-16*
