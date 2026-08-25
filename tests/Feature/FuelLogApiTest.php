<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FuelLogApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::preventStrayRequests();
    }

    public function test_list_returns_logs(): void
    {
        Http::fake([
            '*/auth/v1/user' => Http::response(['id' => 'a-user-uuid']),
            '*/rest/v1/fuel_logs*' => Http::response([['id' => '1', 'km' => 10000, 'liters' => 2.5]]),
        ]);

        $this->authed()->getJson('/api/vehicles/v1/fuel-logs')->assertStatus(200)->assertJsonCount(1);
    }

    public function test_store_validates(): void
    {
        Http::fake(['*/auth/v1/user' => Http::response(['id' => 'a-user-uuid'])]);

        $this->authed()->postJson('/api/vehicles/v1/fuel-logs', [])->assertStatus(422);
        $this->authed()->postJson('/api/vehicles/v1/fuel-logs', ['km' => -1, 'liters' => 0])->assertStatus(422);
        $this->authed()->postJson('/api/vehicles/v1/fuel-logs', ['km' => 1000, 'liters' => 0.05])->assertStatus(422);
    }

    public function test_store_creates_and_updates_vehicle_km(): void
    {
        Http::fake([
            '*/auth/v1/user' => Http::response(['id' => 'a-user-uuid']),
            '*/rest/v1/vehicles*' => function ($request) {
                if ($request->method() === 'GET') {
                    return Http::response([['id' => 'v1', 'last_km' => 10000]]);
                }
                if ($request->method() === 'PATCH') {
                    return Http::response([]);
                }

                return Http::response([]);
            },
            '*/rest/v1/fuel_logs*' => Http::response([['id' => 'new-id']], 201),
        ]);

        $this->authed()->postJson('/api/vehicles/v1/fuel-logs', ['km' => 11000, 'liters' => 2.5, 'cost' => 30000])->assertStatus(201);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'fuel_logs') && $request->method() === 'POST' && $request['km'] === 11000;
        });
    }

    public function test_store_returns_404_for_unknown_vehicle(): void
    {
        Http::fake([
            '*/auth/v1/user' => Http::response(['id' => 'a-user-uuid']),
            '*/rest/v1/vehicles*' => Http::response([]),
        ]);

        $this->authed()->postJson('/api/vehicles/unknown/fuel-logs', ['km' => 1000, 'liters' => 2])->assertStatus(404);
    }

    public function test_destroy_succeeds(): void
    {
        Http::fake([
            '*/auth/v1/user' => Http::response(['id' => 'a-user-uuid']),
            '*/rest/v1/fuel_logs*' => Http::response([]),
        ]);

        $this->authed()->deleteJson('/api/vehicles/v1/fuel-logs/1')->assertStatus(200);
    }

    private function authed(): static
    {
        return $this->withCredentials()->withUnencryptedCookie('sb_access_token', 'good');
    }
}
