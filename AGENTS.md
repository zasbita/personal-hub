# AI Agent Guidelines: Serene Darwin

This document serves as the primary operational manual for all AI agents working on the **Serene Darwin** project. It defines the technical constraints, coding standards, and architectural decisions to ensure consistency and prevent technical debt.

## 🎯 Project Goal
A lightweight, personal API & Notification Hub deployed on serverless infrastructure to automate expense logging, vehicle maintenance reminders, and sports notifications.

---

## 🛠️ Technical Stack (Strict)

The following technologies are mandated. Do NOT suggest alternatives unless explicitly requested by the user.

| Layer | Technology | Notes |
| :--- | :--- | :--- |
| **Runtime** | `Cloudflare Workers` | Must use the Edge Runtime. No Node.js native modules that aren't supported by CF Workers. |
| **Framework** | `Hono.js` | Used for routing and webhook handling. |
| **Language** | `TypeScript` | Strict typing is required. Avoid `any`. |
| **Database** | `Supabase` | PostgreSQL accessed via Supabase Client SDK. |
| **External API** | `Google Sheets API` | Primary storage for expense logs. |
| **Automation** | `GitHub Actions` | Used for cron jobs and scraping tasks. |
| **Bot Platform** | `Telegram Bot API` | Primary interface via Webhooks. |

---

## ⚠️ System Constraints & Guardrails

1. **Cost Efficiency (100% Free Tier):**
   - All solutions must fit within the free tiers of Cloudflare, Supabase, and GitHub.
   - Avoid any paid service or infrastructure that requires a credit card unless approved.

2. **Security (Owner-Only Access):**
   - The bot is for private use. **Every request must be validated against a whitelist of User IDs.**
   - Never expose `BOT_TOKEN`, `SUPABASE_KEY`, or `GOOGLE_SERVICE_ACCOUNT` in the code. Use Environment Variables (Secrets).

3. **Stateless Architecture:**
   - Since it runs on Cloudflare Workers, the application must be stateless. All state must reside in Supabase or Google Sheets.

4. **Performance:**
   - Response time must be $< 2s$. Optimize API calls and avoid heavy processing inside the webhook handler.

---

## 💻 Coding Standards

### 1. Project Structure
Maintain a clean, modular structure:
- `src/index.ts` $\rightarrow$ Entry point and routing.
- `src/handlers/` $\rightarrow$ Logic for different bot commands (expense, service, sports).
- `src/services/` $\rightarrow$ API wrappers (Supabase client, Google Sheets client).
- `src/utils/` $\rightarrow$ Regex parsers, date formatters, and helpers.
- `src/types/` $\rightarrow$ TypeScript interfaces and types.

### 2. Implementation Patterns
- **Regex-First Parsing:** Use robust Regular Expressions for natural language input (e.g., `/catat 50k makan siang`).
- **Error Handling:** Every external API call must be wrapped in `try-catch` blocks with user-friendly error messages returned via the bot.
- **Type Safety:** Define strict interfaces for all database tables and API responses based on `DATABASE.md`.

---

## 🔄 Workflow & Verification

Before marking a task as completed, the agent must:
1. **Verify Runtime Compatibility:** Ensure no `fs`, `path`, or other Node.js-specific modules are used if the target is Cloudflare Workers.
2. **Validate against PRD:** Cross-check the implemented feature against the User Stories in `PRD.md`.
3. **Check Design Tokens:** If building UI components, strictly follow the colors and typography defined in `DESIGN.md`.
4. **Test the Regex:** For the expense logger, test the parser against multiple input variations (e.g., "10k", "10.000", "10rb").

---

## 📖 Reference Order
When in doubt, consult the documents in this order:
1. `AGENTS.md` (How to build)
2. `PRD.md` (What to build)
3. `DATABASE.md` (How data is structured)
4. `DESIGN.md` (How it should look)
