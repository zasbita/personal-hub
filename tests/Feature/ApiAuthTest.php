<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_dashboard_api_rejects_a_request_without_a_session_cookie(): void
    {
        Http::fake();

        $this->getJson('/api/matches')->assertStatus(401);

        Http::assertNothingSent();
    }

    public function test_dashboard_api_rejects_a_token_supabase_does_not_recognise(): void
    {
        Http::fake(['*/auth/v1/user' => Http::response(['msg' => 'invalid JWT'], 401)]);

        $this->withCredentials()->withUnencryptedCookie('sb_access_token', 'forged')
            ->getJson('/api/matches')
            ->assertStatus(401);
    }

    public function test_dashboard_api_allows_a_token_supabase_vouches_for(): void
    {
        Http::fake([
            '*/auth/v1/user' => Http::response(['id' => 'a-user-uuid']),
            '*/rest/v1/match_schedule*' => Http::response([]),
        ]);

        $this->withCredentials()->withUnencryptedCookie('sb_access_token', 'good')
            ->getJson('/api/matches')
            ->assertStatus(200);
    }

    public function test_login_stays_reachable_without_a_cookie(): void
    {
        Http::fake(['*/auth/v1/token*' => Http::response(['msg' => 'bad creds'], 400)]);

        $this->postJson('/api/auth/login', ['email' => 'a@b.com', 'password' => 'secret'])
            ->assertStatus(401);
    }
}
