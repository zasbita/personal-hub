<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExpenseApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::preventStrayRequests();
    }

    public function test_update_rejects_negative_amount(): void
    {
        $this->fakeSheetForUpdate();

        $this->authed()->patchJson('/api/expenses/exp-1', ['amount' => -5000])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_update_rejects_amount_over_limit(): void
    {
        $this->fakeSheetForUpdate();

        $this->authed()->patchJson('/api/expenses/exp-1', ['amount' => 200000000])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_update_rejects_too_long_category(): void
    {
        $this->fakeSheetForUpdate();

        $this->authed()->patchJson('/api/expenses/exp-1', ['category' => str_repeat('a', 31)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['category']);
    }

    public function test_update_rejects_invalid_date(): void
    {
        $this->fakeSheetForUpdate();

        $this->authed()->patchJson('/api/expenses/exp-1', ['date' => 'not-a-date'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }

    public function test_update_succeeds_with_valid_data(): void
    {
        $this->fakeSheetForUpdate();

        $this->authed()->patchJson('/api/expenses/exp-1', ['amount' => 75000, 'description' => 'Kopi baru'])
            ->assertStatus(200)
            ->assertJson(['ok' => true]);
    }

    public function test_update_returns_404_for_unknown_id(): void
    {
        $this->fakeSheetForUpdate();

        $this->authed()->patchJson('/api/expenses/does-not-exist', ['amount' => 10000])
            ->assertStatus(404);
    }

    public function test_export_returns_csv(): void
    {
        Http::fake([
            '*/auth/v1/user' => Http::response(['id' => 'a-user-uuid']),
            '*oauth2.googleapis.com/token' => Http::response(['access_token' => 'a-token']),
            '*sheets.googleapis.com/*' => Http::response(['values' => [
                ['Date', 'Amount', 'Description', 'Category', 'ID'],
                ['2026-08-20', '50000', 'Makan siang', 'General', 'id-1'],
                ['2026-08-21', '25000', 'Kopi', 'Jajan', 'id-2'],
            ]]),
        ]);

        $res = $this->authed()->get('/api/expenses/export');
        $res->assertStatus(200);
        $this->assertStringContainsString('text/csv', $res->headers->get('Content-Type'));
        $body = $res->streamedContent();
        $this->assertStringContainsString('date,amount,description,category,id', $body);
        $this->assertStringContainsString('Makan siang', $body);
    }

    public function test_export_requires_auth(): void
    {
        $this->get('/api/expenses/export')->assertStatus(401);
    }

    public function test_stats_returns_bycategory_and_daily(): void
    {
        $a = now()->startOfMonth()->addDays(2)->format('Y-m-d');
        $b = now()->startOfMonth()->addDays(3)->format('Y-m-d');
        Http::fake([
            '*/auth/v1/user' => Http::response(['id' => 'a-user-uuid']),
            '*oauth2.googleapis.com/token' => Http::response(['access_token' => 'a-token']),
            '*sheets.googleapis.com/*' => Http::response(['values' => [
                ['Date', 'Amount', 'Description', 'Category'],
                [$a, '50000', 'Makan siang', 'General'],
                [$a, '25000', 'Kopi', 'Jajan'],
                [$b, '10000', 'Parkir', 'General'],
            ]]),
        ]);

        $res = $this->authed()->getJson('/api/stats/expenses');
        $res->assertStatus(200)
            ->assertJsonPath('total', 85000)
            ->assertJsonPath('count', 3);
        $json = $res->json();
        $this->assertArrayHasKey('byCategory', $json);
        $this->assertArrayHasKey('daily', $json);
        $this->assertEquals(60000, $json['byCategory']['General']);
        $this->assertCount(2, $json['daily']);
    }

    public function test_vehicle_update_rejects_negative_km(): void
    {
        $this->fakeAuth();

        $this->authed()->patchJson('/api/vehicle', ['last_km' => -1])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['last_km']);
    }

    public function test_vehicle_update_rejects_non_integer(): void
    {
        $this->fakeAuth();

        $this->authed()->patchJson('/api/vehicle', ['last_km' => 'abc'])
            ->assertStatus(422);
    }

    private function fakeAuth(): void
    {
        // Pre-seed fake for auth-only cases; sheet fakes override as needed
        Http::fake([
            '*/auth/v1/user' => Http::response(['id' => 'a-user-uuid']),
            '*oauth2.googleapis.com/token' => Http::response(['access_token' => 'a-token']),
            '*sheets.googleapis.com/*' => Http::response(['values' => []]),
            '*/rest/v1/*' => Http::response([]),
        ]);
    }

    private function fakeSheetForUpdate(): void
    {
        Http::fake([
            '*/auth/v1/user' => Http::response(['id' => 'a-user-uuid']),
            '*oauth2.googleapis.com/token' => Http::response(['access_token' => 'a-token']),
            '*sheets.googleapis.com/*' => function ($request) {
                $url = urldecode($request->url());
                if (str_contains($url, 'fields=sheets')) {
                    return Http::response(['sheets' => [['properties' => ['sheetId' => 0, 'title' => 'Sheet1']]]]);
                }
                if ($request->method() === 'GET') {
                    return Http::response(['values' => [
                        ['Date', 'Amount', 'Description', 'Category', 'ID'],
                        ['2026-08-20', '50000', 'Makan siang', 'General', 'exp-1'],
                        ['2026-08-21', '25000', 'Kopi', 'Jajan', 'exp-2'],
                    ]]);
                }

                return Http::response([]);
            },
            '*/rest/v1/*' => Http::response([]),
        ]);
    }

    private function authed(): static
    {
        return $this->withCredentials()->withUnencryptedCookie('sb_access_token', 'good');
    }
}
