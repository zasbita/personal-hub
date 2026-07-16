# Database Schema - Serene Darwin
**Project:** Personal API & Notification Hub
**Database Engine:** PostgreSQL (via Supabase)
**Version:** 1.2

## 1. ER Diagram Logic
The database follows a relational structure where the `users` table acts as the central authority for security (Whitelisting) and preferences. New tables for sport preferences and match schedules are added.

---

## 2. Tables Definition

### 2.1 Table: `users`
Stores authenticated user information to prevent unauthorized access to the bot.

| Column | Type | Constraints | Description |
| :--- | :--- | :--- | :--- |
| `user_id` | `BIGINT` | PRIMARY KEY | Telegram User ID (Unique) |
| `username` | `TEXT` | NULLABLE | Telegram handle (@username) |
| `created_at` | `TIMESTAMPTZ` | DEFAULT `now()` | Account creation timestamp |
| `pref_language` | `TEXT` | DEFAULT `'id'` | User preferred language |

### 2.2 Table: `vehicle_service`
Tracks motorcycle mileage and calculates the next service date.

| Column | Type | Constraints | Description |
| :--- | :--- | :--- | :--- |
| `id` | `UUID` | PRIMARY KEY, DEFAULT `gen_random_uuid()` | Unique record ID |
| `user_id` | `BIGINT` | FOREIGN KEY (`users.user_id`) | Link to the user |
| `last_km` | `INTEGER` | NOT NULL | Last recorded odometer reading |
| `service_interval` | `INTEGER` | DEFAULT `2000` | Interval between services (in km) |
| `next_service_km` | `INTEGER` | NOT NULL | `last_km` + `service_interval` |
| `updated_at` | `TIMESTAMPTZ` | DEFAULT `now()` | Last update timestamp |

### 2.3 Table: `expenses`
Backup storage for expense logs (primary data goes to Google Sheets).

| Column | Type | Constraints | Description |
| :--- | :--- | :--- | :--- |
| `id` | `UUID` | PRIMARY KEY, DEFAULT `gen_random_uuid()` | Unique record ID |
| `user_id` | `BIGINT` | FOREIGN KEY (`users.user_id`) | Link to the user |
| `amount` | `DECIMAL` | NOT NULL | Transaction amount |
| `description` | `TEXT` | NOT NULL | Item or service name |
| `category` | `TEXT` | NULLABLE | Expense category (e.g., Food, Transport) |
| `created_at` | `TIMESTAMPTZ` | DEFAULT `now()` | Transaction timestamp |

### 2.4 Table: `user_preferences`
Stores user-specific settings and preferences for sports notifications.

| Column | Type | Constraints | Description |
| :--- | :--- | :--- | :--- |
| `id` | `UUID` | PRIMARY KEY, DEFAULT `gen_random_uuid()` | Unique record ID |
| `user_id` | `BIGINT` | FOREIGN KEY (`users.user_id`) | Link to the user |
| `sport_type` | `TEXT` | NOT NULL | e.g., 'football', 'mobile_legends', 'volleyball', 'futsal' |
| `entity_id` | `TEXT` | NOT NULL | ID or name of team/league (e.g., 'Liverpool', 'MPL Indonesia') |
| `entity_name` | `TEXT` | NOT NULL | Display name of team/league |
| `notification_enabled` | `BOOLEAN` | DEFAULT `TRUE` | Whether notifications are active |
| `created_at` | `TIMESTAMPTZ` | DEFAULT `now()` | Preference creation timestamp |

### 2.5 Table: `match_schedule`
Caches upcoming match schedules fetched from external APIs or scraping.

| Column | Type | Constraints | Description |
| :--- | :--- | :--- | :--- |
| `id` | `UUID` | PRIMARY KEY, DEFAULT `gen_random_uuid()` | Unique match ID |
| `source_id` | `TEXT` | NOT NULL UNIQUE | ID from external API (e.g., api-football fixture ID) |
| `sport_type` | `TEXT` | NOT NULL | e.g., 'football', 'mobile_legends', 'volleyball', 'futsal' |
| `competition` | `TEXT` | NOT NULL | League or tournament name (e.g., 'Premier League', 'MPL ID') |
| `home_team` | `TEXT` | NOT NULL | Home team name |
| `away_team` | `TEXT` | NOT NULL | Away team name |
| `match_time` | `TIMESTAMPTZ` | NOT NULL | Exact match start time (UTC) |
| `status` | `TEXT` | NOT NULL | 'scheduled', 'live', 'finished', 'postponed' |
| `notified` | `BOOLEAN` | DEFAULT `FALSE` | Has a notification been sent for this match? |
| `created_at` | `TIMESTAMPTZ` | DEFAULT `now()` | Record creation timestamp |
| `updated_at` | `TIMESTAMPTZ` | DEFAULT `now()` | Last update timestamp |

---

## 3. SQL DDL (Direct implementation for Supabase SQL Editor)

```sql
-- Create Users Table
CREATE TABLE IF NOT EXISTS users (
    user_id BIGINT PRIMARY KEY,
    username TEXT,
    created_at TIMESTAMPTZ DEFAULT now(),
    pref_language TEXT DEFAULT 'id'
);

-- Create Vehicle Service Table
CREATE TABLE IF NOT EXISTS vehicle_service (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id BIGINT REFERENCES users(user_id) ON DELETE CASCADE,
    last_km INTEGER NOT NULL,
    service_interval INTEGER DEFAULT 2000,
    next_service_km INTEGER NOT NULL,
    updated_at TIMESTAMPTZ DEFAULT now()
);

-- Create Expenses Table
CREATE TABLE IF NOT EXISTS expenses (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id BIGINT REFERENCES users(user_id) ON DELETE CASCADE,
    amount DECIMAL NOT NULL,
    description TEXT NOT NULL,
    category TEXT,
    created_at TIMESTAMPTZ DEFAULT now()
);

-- Create User Preferences Table
CREATE TABLE IF NOT EXISTS user_preferences (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id BIGINT REFERENCES users(user_id) ON DELETE CASCADE,
    sport_type TEXT NOT NULL,
    entity_id TEXT NOT NULL,
    entity_name TEXT NOT NULL,
    notification_enabled BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMPTZ DEFAULT now()
);

-- Create Match Schedule Table
CREATE TABLE IF NOT EXISTS match_schedule (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    source_id TEXT NOT NULL UNIQUE,
    sport_type TEXT NOT NULL,
    competition TEXT NOT NULL,
    home_team TEXT NOT NULL,
    away_team TEXT NOT NULL,
    match_time TIMESTAMPTZ NOT NULL,
    status TEXT NOT NULL,
    notified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMPTZ DEFAULT now(),
    updated_at TIMESTAMPTZ DEFAULT now()
);

-- Indexing for performance
CREATE INDEX IF NOT EXISTS idx_expenses_user ON expenses(user_id);
CREATE INDEX IF NOT EXISTS idx_service_user ON vehicle_service(user_id);
CREATE INDEX IF NOT EXISTS idx_prefs_user ON user_preferences(user_id);
CREATE INDEX IF NOT EXISTS idx_match_time ON match_schedule(match_time);
CREATE INDEX IF NOT EXISTS idx_match_source_id ON match_schedule(source_id);
```