<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CategoryBudgetApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::preventStrayRequests();
    }

    public function test_index_returns_list(): void
    {
        Http::fake([
            '*/auth/v1/user' => Http::response(['id' => 'a-user-uuid']),
            '*/rest/v1/category_budgets*' => Http::response([['id' => '1', 'category' => 'Jajan', 'monthly_limit' => 500000]]),
        ]);

        $this->authed()->getJson('/api/category-budgets')->assertStatus(200)->assertJsonCount(1);
    }

    public function test_store_validates_required_fields(): void
    {
        Http::fake(['*/auth/v1/user' => Http::response(['id' => 'a-user-uuid'])]);

        $this->authed()->postJson('/api/category-budgets', [])->assertStatus(422);
        $this->authed()->postJson('/api/category-budgets', ['category' => str_repeat('a', 31), 'monthly_limit' => 1000])->assertStatus(422);
        $this->authed()->postJson('/api/category-budgets', ['category' => 'Jajan', 'monthly_limit' => -1])->assertStatus(422);
    }

    public function test_store_creates_new_budget(): void
    {
        Http::fake([
            '*/auth/v1/user' => Http::response(['id' => 'a-user-uuid']),
            '*/rest/v1/category_budgets*' => function ($request) {
                if ($request->method() === 'GET') {
                    return Http::response([]); // no existing
                }

                return Http::response([['id' => 'new-id', 'category' => 'Jajan', 'monthly_limit' => 500000]], 201);
            },
        ]);

        $this->authed()->postJson('/api/category-budgets', ['category' => 'Jajan', 'monthly_limit' => 500000])->assertStatus(201);
    }

    public function test_store_upserts_when_category_exists(): void
    {
        Http::fake([
            '*/auth/v1/user' => Http::response(['id' => 'a-user-uuid']),
            '*/rest/v1/category_budgets*' => function ($request) {
                if ($request->method() === 'GET' && str_contains($request->url(), 'category=eq.Jajan')) {
                    return Http::response([['id' => 'existing-id', 'category' => 'Jajan']]);
                }
                if ($request->method() === 'GET') {
                    return Http::response([['id' => 'existing-id', 'category' => 'Jajan', 'monthly_limit' => 600000]]);
                }
                if ($request->method() === 'PATCH') {
                    return Http::response([]);
                }

                return Http::response([['id' => 'existing-id']]);
            },
        ]);

        $this->authed()->postJson('/api/category-budgets', ['category' => 'Jajan', 'monthly_limit' => 600000])->assertStatus(200);
    }

    public function test_update_validates_limit(): void
    {
        Http::fake(['*/auth/v1/user' => Http::response(['id' => 'a-user-uuid'])]);

        $this->authed()->patchJson('/api/category-budgets/1', [])->assertStatus(422);
        $this->authed()->patchJson('/api/category-budgets/1', ['monthly_limit' => -5])->assertStatus(422);
    }

    public function test_destroy_succeeds(): void
    {
        Http::fake([
            '*/auth/v1/user' => Http::response(['id' => 'a-user-uuid']),
            '*/rest/v1/category_budgets*' => Http::response([], 200),
        ]);

        $this->authed()->deleteJson('/api/category-budgets/1')->assertStatus(200)->assertJson(['ok' => true]);
    }

    public function test_stats_includes_budgets(): void
    {
        $a = now()->startOfMonth()->addDays(1)->format('Y-m-d');
        Http::fake([
            '*/auth/v1/user' => Http::response(['id' => 'a-user-uuid']),
            '*oauth2.googleapis.com/token' => Http::response(['access_token' => 'a-token']),
            '*sheets.googleapis.com/*' => Http::response(['values' => [
                ['Date', 'Amount', 'Description', 'Category'],
                [$a, '100000', 'Makan', 'Jajan'],
            ]]),
            '*/rest/v1/category_budgets*' => Http::response([['category' => 'Jajan', 'monthly_limit' => 500000]]),
        ]);

        $res = $this->authed()->getJson('/api/stats/expenses');
        $res->assertStatus(200);
        $this->assertArrayHasKey('budgets', $res->json());
        $this->assertEquals(500000, $res->json('budgets.Jajan'));
    }

    private function authed(): static
    {
        return $this->withCredentials()->withUnencryptedCookie('sb_access_token', 'good');
    }
}
