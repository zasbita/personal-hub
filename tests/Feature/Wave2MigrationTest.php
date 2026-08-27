<?php

namespace Tests\Feature;

use App\Services\SupabaseService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class Wave2MigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sqlite_wave2_tables_exist_and_fk_cascade(): void
    {
        foreach (['category_budgets', 'recurring_expenses', 'vehicles', 'service_logs', 'fuel_logs'] as $t) {
            $this->assertTrue(DB::getSchemaBuilder()->hasTable($t), "missing $t");
        }

        $vid = (string) Str::uuid();
        DB::table('vehicles')->insert([
            'id' => $vid,
            'user_id' => 811031481,
            'name' => 'Test Car',
            'last_km' => 1000,
            'next_service_km' => 3000,
            'service_interval' => 2000,
        ]);
        $sid = (string) Str::uuid();
        DB::table('service_logs')->insert([
            'id' => $sid,
            'vehicle_id' => $vid,
            'old_km' => 1000,
            'new_km' => 3000,
        ]);
        $this->assertDatabaseHas('service_logs', ['id' => $sid]);

        // FK violation for invalid vehicle_id
        $this->expectException(QueryException::class);
        DB::table('service_logs')->insert([
            'id' => (string) Str::uuid(),
            'vehicle_id' => (string) Str::uuid(),
            'old_km' => 0,
            'new_km' => 0,
        ]);
    }

    public function test_sqlite_cascade_delete_removes_logs(): void
    {
        $vid = (string) Str::uuid();
        DB::table('vehicles')->insert([
            'id' => $vid, 'user_id' => 811031481, 'name' => 'Cascade Car',
            'last_km' => 0, 'next_service_km' => 2000, 'service_interval' => 2000,
        ]);
        $sid = (string) Str::uuid();
        $fid = (string) Str::uuid();
        DB::table('service_logs')->insert(['id' => $sid, 'vehicle_id' => $vid, 'old_km' => 0, 'new_km' => 2000]);
        DB::table('fuel_logs')->insert(['id' => $fid, 'vehicle_id' => $vid, 'user_id' => 811031481, 'km' => 100, 'liters' => 10, 'cost' => 10000]);

        DB::table('vehicles')->where('id', $vid)->delete();

        $this->assertDatabaseMissing('service_logs', ['id' => $sid]);
        $this->assertDatabaseMissing('fuel_logs', ['id' => $fid]);
    }

    public function test_supabase_wave2_tables_select_via_http_fake(): void
    {
        Http::fake([
            '*/rest/v1/category_budgets*' => Http::response([['id' => 'b1']]),
            '*/rest/v1/recurring_expenses*' => Http::response([['id' => 'r1']]),
            '*/rest/v1/vehicles*' => Http::response([['id' => 'v1', 'name' => 'Car']]),
            '*/rest/v1/service_logs*' => Http::response([]),
            '*/rest/v1/fuel_logs*' => Http::response([]),
        ]);

        $s = new SupabaseService;
        foreach (['category_budgets', 'recurring_expenses', 'vehicles', 'service_logs', 'fuel_logs'] as $t) {
            $res = $s->select($t, ['select' => 'id', 'limit' => 1]);
            $this->assertIsArray($res, "$t select failed");
        }
    }

    public function test_migration_is_idempotent(): void
    {
        // second migrate should say nothing to migrate
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        // RefreshDatabase already migrated fresh for this test class, verify still has tables
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('vehicles'));
    }
}
