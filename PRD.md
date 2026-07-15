# Product Requirement Document (PRD)
**Project Name:** Serene Darwin - Personal API & Notification Hub
**Version:** 1.0 (MVP)
**Owner:** Muhamad Ikram Zasbita
**Objective:** Build a personal assistant via Bot (Telegram/Discord) to automate important reminders, fast financial tracking, and sports schedule information without the need to open multiple applications.

---

## 1. User Personas
**User:** High-mobility users who want to record expenses instantly and frequently forget vehicle service schedules or favorite sports matches.

---

## 2. Functional Requirements (Core Features)

### 2.1 Expense Quick-Logger (Priority: High)
* **Goal:** Record expenses through chat using natural language formatting.
* **User Story:** *"As a user, I want to send a message like '/log 50k lunch' and have that data automatically saved to Google Sheets/Database."*
* **Requirements:**
    * Text parsing system using Regex to capture `Amount` and `Description`.
    * Automatic unit conversion (e.g., `50k` $\rightarrow$ `50,000`).
    * Integration with Google Sheets API or Database (Supabase).
    * Bot confirmation response: *"✅ Successfully recorded Rp 50,000 for Lunch."*

### 2.2 Vehicle Service Reminder (Priority: Medium)
* **Goal:** Remind the user of motorcycle service schedules based on mileage.
* **User Story:** *"As a user, I want to update my motorcycle's mileage and get a warning when it's time for an oil change."*
* **Requirements:**
    * `/update_km [number]` command to update the latest odometer reading.
    * Calculation system: `Last KM + Service Interval (e.g., 2000km)`.
    * Automatic notifications (Push Notifications) when current KM approaches the service limit.
    * `/check_service` command to see remaining KM before the next service.

### 2.3 Sports/Esports Notifier (Priority: Low/Medium)
* **Goal:** Send notifications for favorite match schedules.
* **User Story:** *"As a sports fan, I want the bot to notify me 15 minutes before a match starts."*
* **Requirements:**
    * Scraping match schedule data from sports/esports websites.
    * Scheduling system (Cron Job) to check match times every few minutes.
    * Send alert message: *"🚨 The match [Team A] vs [Team B] will start in 15 minutes!"*

---

## 3. Non-Functional Requirements

* **Cost:** 100% Free Tier (utilizing Cloudflare Workers/Render, Supabase, GitHub Actions).
* **Availability:** Bot must be active 24/7.
* **Performance:** Bot response time to user input must be a maximum of 2 seconds.
* **Security:** Only the owner's Telegram/Discord ID can access bot functions (User ID Whitelisting).

---

## 4. Technical Constraints & Stack (Proposed)

| Component | Technology |
| :--- | :--- |
| **Language** | TypeScript / Node.js |
| **Runtime** | Cloudflare Workers (via Hono.js) $\rightarrow$ Webhook based |
| **Database** | Supabase (PostgreSQL) |
| **External Storage** | Google Sheets API (for Expense Log) |
| **Automation** | GitHub Actions (for Scraping & Cron) |
| **Bot Platform** | Telegram Bot API |

---

## 5. User Flow (Simplified)

1. **Expense:** `User` $\rightarrow$ `/log 20k coffee` $\rightarrow$ `Bot` $\rightarrow$ `Regex Parser` $\rightarrow$ `Google Sheets` $\rightarrow$ `Confirm Response`.
2. **Service:** `User` $\rightarrow$ `/update_km 15000` $\rightarrow$ `Bot` $\rightarrow$ `Update Supabase` $\rightarrow$ `Calc Next Service` $\rightarrow$ `Save Reminder`.
3. **Sports:** `GitHub Action` $\rightarrow$ `Scrape Site` $\rightarrow$ `Compare Time` $\rightarrow$ `Send Telegram Message`.

---

## 6. Roadmap (Phase Implementation)

* **Phase 1 (MVP):** Setup Telegram Bot $\rightarrow$ Integrate Google Sheets $\rightarrow$ Expense Logger Feature.
* **Phase 2:** Setup Supabase $\rightarrow$ Update KM Feature $\rightarrow$ Service Reminder Logic.
* **Phase 3:** Setup GitHub Actions $\rightarrow$ Scraping Engine $\rightarrow$ Match Notifications.
* **Phase 4 (Scaling):** Create Admin Dashboard using Google Stitch $\rightarrow$ Deploy UI to Vercel.
