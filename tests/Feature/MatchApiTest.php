<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MatchApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_a_manual_entry_only_sends_columns_match_schedule_has(): void
    {
        $this->fakeSupabase(Http::response([['id' => 1]], 201));

        $this->authed()->postJson('/api/matches', [
            'sport_type' => 'volly',
            'home_team' => 'Indonesia',
            'away_team' => 'Japan',
            'tournament' => 'AVC Cup',
            'match_time' => '2099-09-01T19:00',
        ])->assertStatus(201);

        Http::assertSent(function ($request) {
            if (!str_contains($request->url(), 'rest/v1/match_schedule') || $request->method() !== 'POST') return false;
            // entity_name is not a column on the table — sending it fails the insert.
            return !array_key_exists('entity_name', $request->data())
                && $request['competition'] === 'AVC Cup'
                && $request['home_team'] === 'Indonesia';
        });
    }

    public function test_a_manual_entry_without_a_home_team_is_rejected(): void
    {
        $this->fakeSupabase(Http::response([['id' => 1]], 201));

        $this->authed()->postJson('/api/matches', ['sport_type' => 'volly', 'match_time' => '2099-09-01T19:00'])
            ->assertStatus(400);
    }

    public function test_a_failing_supabase_is_reported_not_hidden_as_an_empty_list(): void
    {
        $this->fakeSupabase(Http::response('boom', 500));

        $this->authed()->getJson('/api/matches')->assertStatus(500);
    }

    private function fakeSupabase($matchScheduleResponse): void
    {
        Http::fake([
            '*/auth/v1/user' => Http::response(['id' => 'a-user-uuid']),
            '*/rest/v1/match_schedule*' => $matchScheduleResponse,
        ]);
    }

    private function authed(): static
    {
        return $this->withCredentials()->withUnencryptedCookie('sb_access_token', 'good');
    }
}
