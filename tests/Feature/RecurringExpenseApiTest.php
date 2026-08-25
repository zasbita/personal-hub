<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RecurringExpenseApiTest extends TestCase
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
            '*/rest/v1/recurring_expenses*' => Http::response([['id' => '1', 'amount' => 50000, 'description' => 'Netflix']]),
        ]);

        $this->authed()->getJson('/api/recurring-expenses')->assertStatus(200)->assertJsonCount(1);
    }

    public function test_store_validates(): void
    {
        Http::fake(['*/auth/v1/user' => Http::response(['id' => 'a-user-uuid'])]);

        $this->authed()->postJson('/api/recurring-expenses', [])->assertStatus(422);
        $this->authed()->postJson('/api/recurring-expenses', ['amount' => -1, 'description' => 'A', 'day_of_month' => 32])->assertStatus(422);
        $this->authed()->postJson('/api/recurring-expenses', ['amount' => 1000, 'description' => 'A', 'day_of_month' => 0])->assertStatus(422);
    }

    public function test_store_creates(): void
    {
        Http::fake([
            '*/auth/v1/user' => Http::response(['id' => 'a-user-uuid']),
            '*/rest/v1/recurring_expenses*' => Http::response([['id' => 'new-id']], 201),
        ]);

        $this->authed()->postJson('/api/recurring-expenses', ['amount' => 50000, 'description' => 'Netflix', 'category' => 'Langganan', 'day_of_month' => 15])->assertStatus(201);
    }

    public function test_destroy_succeeds(): void
    {
        Http::fake([
            '*/auth/v1/user' => Http::response(['id' => 'a-user-uuid']),
            '*/rest/v1/recurring_expenses*' => Http::response([]),
        ]);

        $this->authed()->deleteJson('/api/recurring-expenses/1')->assertStatus(200);
    }

    public function test_recurring_command_appends_due_today(): void
    {
        $today = (int) now()->timezone('Asia/Jakarta')->day;
        Http::fake([
            '*oauth2.googleapis.com/token' => Http::response(['access_token' => 'a-token']),
            '*sheets.googleapis.com/*' => Http::response([]),
            '*/rest/v1/recurring_expenses*' => Http::response([
                ['id' => '1', 'amount' => 50000, 'description' => 'Netflix', 'category' => 'Langganan', 'day_of_month' => $today],
                ['id' => '2', 'amount' => 10000, 'description' => 'Lain', 'category' => 'General', 'day_of_month' => 99],
            ]),
        ]);

        // owner_id must be set for telegram, but command should succeed even without
        config(['services.telegram.owner_id' => 1]);

        $this->artisan('expenses:recurring')->assertExitCode(0);

        // Should have appended one expense (POST to sheets append)
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sheets.googleapis.com') && str_contains($request->url(), ':append') && str_contains(json_encode($request->data()), 'Netflix');
        });
    }

    private function authed(): static
    {
        return $this->withCredentials()->withUnencryptedCookie('sb_access_token', 'good');
    }
}
