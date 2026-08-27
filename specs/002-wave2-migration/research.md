# Research: Wave2 Migration

**Branch**: `002-wave2-migration` | **Date**: 2026-08-28

## Decision: Supabase GRANT fix

- **Decision**: Tambah `supabase/wave2_grants.sql` dengan `GRANT ALL ON public.<table> TO service_role, authenticated;` untuk 5 tabel wave2, idempotent re-run.
- **Rationale**: `SupabaseService.php:33` pakai `service_role` key; tabel ada tapi `select vehicles` kena `42501 Grant required`. `supabase/wave2.sql` tidak punya `GRANT`. Postgres `GRANT` idempotent, aman re-run.
- **Alternatives considered**: `GRANT SELECT/INSERT/UPDATE/DELETE` per operation vs `ALL` — dipilih `ALL` karena app butuh full CRUD via REST, dan `authenticated` disertakan jika RLS nanti ON.

## Decision: Laravel mirror untuk SQLite

- **Decision**: Satu file `database/migrations/2026_08_28_000000_create_wave2_tables.php` buat 5 tabel via `Schema::create`, pakai `uuid()->primary()` string (tanpa `gen_random_uuid`), `foreignUuid()->constrained()->cascadeOnDelete()`.
- **Rationale**: `DB_CONNECTION=sqlite` di `.env:20`, SQLite tidak punya `pgcrypto`. Laravel `uuid()` generates string PK, app-level `Str::uuid()` jika perlu. Satu file cukup (ponytail) — tidak perlu 5 file per tabel.
- **Alternatives considered**: 5 separate migrations — rejected karena over-scaffolding untuk wave2 bundle; native Postgres `gen_random_uuid()` expression — rejected karena SQLite incompatible, test CI pakai sqlite in-memory.

## Decision: No Supabase RLS

- **Decision**: RLS tetap OFF per constitution `Constraints & Security: Supabase RLS stays off while service_role` — tidak tambah `ENABLE RLS` / policies.
- **Rationale**: `service_role` bypass RLS, app server-side only. Menyalakan RLS tanpa policies akan block semua.

## Decision: Idempotency

- **Decision**: Supabase DDL pakai `IF NOT EXISTS`, Laravel pakai `if (!Schema::hasTable(...))`, GRANT tanpa `IF NOT EXISTS` (Postgres GRANT idempotent native).
- **Rationale**: Re-run migration/grant di prod tidak boleh hilang data atau error duplikat.

## Decision: No new API contracts

- **Decision**: `/contracts` kosong — migration only, REST path sudah `/rest/v1/<table>`.
- **Rationale**: Tidak ada endpoint baru, cuma privilege fix.

## Open items

- None — all NEEDS CLARIFICATION resolved.
