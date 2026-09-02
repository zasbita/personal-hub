<!-- Sync Impact Report
Version change: [TEMPLATE] → 1.0.0 (initial ratification)
Modified principles: [PRINCIPLE_1..5] → I-V concrete principles (new)
Added sections: Technology Stack & Constraints, Development Workflow & Quality Gates, Governance (concrete)
Removed sections: placeholder [SECTION_2_NAME], [SECTION_3_NAME], [GOVERNANCE_RULES]
Templates requiring updates: ✅ checked (.pandawa/templates/plan-template.md, spec-template.md, tasks-template.md) — aligned, no structural change needed
Follow-up TODOs: none
-->
# Personal Hub Constitution

## Core Principles

### I. Spec-Driven Development

Every feature MUST start from a written spec (`specs/NNN-name/spec.md`) and a plan (`plan.md`) before code. Spec defines user stories, acceptance criteria, and scope; plan defines technical approach and trade-offs. No implementation without both. Constitution supersedes ad-hoc requests — if a request conflicts, constitution wins.

### II. Test-First (NON-NEGOTIABLE)

TDD mandatory: tests written → user approved (spec gate) → tests fail → then implement. Red-Green-Refactor strictly enforced. Every new controller/service/command gets Feature or Unit tests; every bugfix gets a regression test that fails without the fix. Do not ship without `composer test` green and Pint clean.

### III. Supabase & Google Sheets as Source of Truth

App data lives in Supabase (Postgres via HTTP API) and Google Sheets — NOT in local DB. Local SQLite is only for framework internals (sessions, cache) in tests. Services MUST go through `SupabaseService` / `SheetsService`; direct DB access for app data is prohibited. Migrations under `supabase/` are applied manually via Dashboard; verify via API calls.

### IV. Security & Auth Boundary

Dashboard API security boundary is `SupabaseAuth` middleware validating the httpOnly `sb_access_token` cookie against Supabase `/auth/v1/user` (5-min cache). Only `/auth/login` (rate-limited) and `/auth/logout` are open. `routes/api.php` has no `EncryptCookies` — cookies are verbatim; tests MUST use `withUnencryptedCookie`. Vue `localStorage` guard is UX only, never the security boundary. Secrets via `config/services.php` + `.env` and never committed.

### V. Simplicity & Minimal Diff

YAGNI and shortest working diff win. Reuse existing helpers/utils before writing new ones; prefer stdlib and native platform features over new dependencies. No speculative abstractions (single-implementation interfaces, factories for one product, config for constants). Fewest files, fewest lines that solve the problem. Mark deliberate ceilings with `ponytail:` comments and upgrade only on measured need.

## Technology Stack & Constraints

- **Backend**: Laravel 13, PHP 8.3, Firebase JWT, Supabase PHP
- **Frontend**: Vue 3.5, Vue Router 4, Pinia 4, Tailwind CSS 4 (`@theme` in `resources/css/app.css`, no `tailwind.config.js`), Vite 8 + laravel-vite-plugin 3
- **Bot**: Telegram long-polling (`bot:listen` dev, `bot:webhook` prod), `bot:notify` every 15min, `bot:digest` Mon 07:00 WIB
- **SPA routing**: `routes/web.php` catch-all serves `app.blade.php`; Vue Router handles client routing
- **Auth**: Supabase JWT + cookie, cached 5min
- **Performance**: API p95 < 300ms for Supabase-proxied reads; avoid N+1 Supabase calls
- **Compatibility**: PHP 8.3 null-coalescing in interpolation — use temp vars for nested array access in double-quoted strings

## Development Workflow & Quality Gates

- **Branching**: feature branches `NNN-short-name` off `main`; specs live under `specs/<branch>/`
- **Commands**: `composer dev` (Laravel+Vite), `npm run build`, `composer test` / `php artisan test`, `./vendor/bin/pint`
- **Env sync**: if `config/services.php` adds `env('FOO')`, update `.env.example` via `sync-env-example` script
- **Review gates**: Pint fix before Pint test; tests green; spec/plan/tasks artifacts present; Analyze coverage ≥ 80%
- **Docs**: AGENTS.md is runtime guidance; keep in sync with constitution changes

## Governance

Constitution supersedes all other practices. Amendments require: (1) documented rationale, (2) version bump per semver (MAJOR=breaking governance removal/redefinition, MINOR=new principle/section, PATCH=clarification), (3) Sync Impact Report in file header, (4) propagation check across `.pandawa/templates/*.md` and `pandawa.*` command files.

All PRs and reviews MUST verify compliance with principles I–V. Complexity MUST be justified against Principle V. Runtime guidance lives in `AGENTS.md`.

**Version**: 1.0.0 | **Ratified**: 2026-09-02 | **Last Amended**: 2026-09-02
