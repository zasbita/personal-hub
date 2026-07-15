# Database Schema - Serene Darwin
**Project:** Personal API & Notification Hub
**Database Engine:** PostgreSQL (via Supabase)
**Version:** 1.0

## 1. ER Diagram Logic
The database follows a relational structure where the `users` table acts as the central authority for security (Whitelisting) and preferences.

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

---

## 3. SQL DDL (Direct implementation for Supabase SQL Editor)

```sql
-- Create Users Table
CREATE TABLE users (
    user_id BIGINT PRIMARY KEY,
    username TEXT,
    created_at TIMESTAMPTZ DEFAULT now(),
    pref_language TEXT DEFAULT 'id'
);

-- Create Vehicle Service Table
CREATE TABLE vehicle_service (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id BIGINT REFERENCES users(user_id) ON DELETE CASCADE,
    last_km INTEGER NOT NULL,
    service_interval INTEGER DEFAULT 2000,
    next_service_km INTEGER NOT NULL,
    updated_at TIMESTAMPTZ DEFAULT now()
);

-- Create Expenses Table
CREATE TABLE expenses (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id BIGINT REFERENCES users(user_id) ON DELETE CASCADE,
    amount DECIMAL NOT NULL,
    description TEXT NOT NULL,
    category TEXT,
    created_at TIMESTAMPTZ DEFAULT now()
);

-- Indexing for performance
CREATE INDEX idx_expenses_user ON expenses(user_id);
CREATE INDEX idx_service_user ON vehicle_service(user_id);
```
