<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MatchCategoryApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function fakeSupabase($resp): void
    {
        Http::fake([
            '*/auth/v1/user' => Http::response(['id' => 'uuid']),
            '*/rest/v1/match_schedule*' => $resp,
        ]);
    }

    private function authed()
    {
        return $this->withCredentials()->withUnencryptedCookie('sb_access_token', 'good');
    }

    public function test_filter_by_football_returns_only_football(): void
    {
        $this->fakeSupabase(Http::response([['id' => 1, 'sport_type' => 'football', 'home_team' => 'A']]));
        $this->authed()->getJson('/api/matches?sport_type=football')->assertStatus(200);
        Http::assertSent(function ($r) {
            return str_contains($r->url(), 'match_schedule') && str_contains($r->url(), 'sport_type=eq.football');
        });
    }

    public function test_filter_motogp_expands_to_group(): void
    {
        $this->fakeSupabase(Http::response([['id' => 1]]));
        $this->authed()->getJson('/api/matches?sport_type=motogp')->assertStatus(200);
        Http::assertSent(function ($r) {
            return str_contains($r->url(), 'sport_type=in.') && str_contains($r->url(), 'motogp');
        });
    }

    public function test_mlbb_alias_normalized(): void
    {
        $this->fakeSupabase(Http::response([['id' => 1]]));
        $this->authed()->getJson('/api/matches?sport_type=mlbb')->assertStatus(200);
        Http::assertSent(function ($r) {
            return str_contains($r->url(), 'sport_type=eq.mobilelegend');
        });
    }

    public function test_invalid_sport_returns_400(): void
    {
        $this->fakeSupabase(Http::response([['id' => 1]]));
        $this->authed()->getJson('/api/matches?sport_type=xyz')->assertStatus(400)->assertJson(['error' => 'invalid sport_type']);
    }

    public function test_no_param_returns_all(): void
    {
        $this->fakeSupabase(Http::response([['id' => 1]]));
        $this->authed()->getJson('/api/matches')->assertStatus(200);
        Http::assertSent(function ($r) {
            return str_contains($r->url(), 'match_schedule') && !str_contains($r->url(), 'sport_type');
        });
    }
}
