<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VehicleMultiApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::preventStrayRequests();
    }

    public function test_vehicles_list_returns_array(): void
    {
        Http::fake([
            '*/auth/v1/user' => Http::response(['id' => 'a-user-uuid']),
            '*/rest/v1/vehicles*' => Http::response([['id' => 'v1', 'name' => 'Beat']]),
        ]);

        $this->authed()->getJson('/api/vehicles')->assertStatus(200)->assertJsonCount(1);
    }

    public function test_vehicles_store_validates(): void
    {
        Http::fake(['*/auth/v1/user' => Http::response(['id' => 'a-user-uuid'])]);

        $this->authed()->postJson('/api/vehicles', [])->assertStatus(422);
        $this->authed()->postJson('/api/vehicles', ['name' => 'Beat', 'last_km' => -1, 'next_service_km' => 1000])->assertStatus(422);
    }

    public function test_vehicles_store_creates(): void
    {
        Http::fake([
            '*/auth/v1/user' => Http::response(['id' => 'a-user-uuid']),
            '*/rest/v1/vehicles*' => Http::response([['id' => 'new-v']], 201),
        ]);

        $this->authed()->postJson('/api/vehicles', ['name' => 'Beat', 'last_km' => 10000, 'next_service_km' => 12000])->assertStatus(201);
    }

    public function test_vehicles_update_validates(): void
    {
        Http::fake(['*/auth/v1/user' => Http::response(['id' => 'a-user-uuid'])]);

        $this->authed()->patchJson('/api/vehicles/v1', [])->assertStatus(400);
        $this->authed()->patchJson('/api/vehicles/v1', ['last_km' => -5])->assertStatus(422);
    }

    public function test_vehicles_update_logs_history(): void
    {
        Http::fake([
            '*/auth/v1/user' => Http::response(['id' => 'a-user-uuid']),
            '*/rest/v1/vehicles*' => function ($request) {
                if ($request->method() === 'GET' && str_contains($request->url(), 'id=eq.v1')) {
                    // first GET for before, second for after
                    static $call = 0;
                    $call++;
                    if ($call === 1) {
                        return Http::response([['last_km' => 10000]]);
                    }

                    return Http::response([['id' => 'v1', 'last_km' => 11000]]);
                }

                return Http::response([]);
            },
            '*/rest/v1/service_logs*' => Http::response([], 201),
        ]);

        $this->authed()->patchJson('/api/vehicles/v1', ['last_km' => 11000])->assertStatus(200);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'service_logs') && $request->method() === 'POST';
        });
    }

    public function test_vehicles_delete_succeeds(): void
    {
        Http::fake([
            '*/auth/v1/user' => Http::response(['id' => 'a-user-uuid']),
            '*/rest/v1/vehicles*' => Http::response([]),
        ]);

        $this->authed()->deleteJson('/api/vehicles/v1')->assertStatus(200);
    }

    private function authed(): static
    {
        return $this->withCredentials()->withUnencryptedCookie('sb_access_token', 'good');
    }
}
