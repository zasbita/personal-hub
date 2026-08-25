<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MatchIcsExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_ics_export_returns_calendar(): void
    {
        Http::fake([
            '*/auth/v1/user' => Http::response(['id' => 'a-user-uuid']),
            '*/rest/v1/match_schedule*' => Http::response([
                ['id' => '1', 'home_team' => 'Indonesia', 'away_team' => 'Japan', 'competition' => 'AVC', 'sport_type' => 'volly', 'match_time' => now()->addDay()->toIso8601String()],
            ]),
        ]);

        $res = $this->withCredentials()->withUnencryptedCookie('sb_access_token', 'good')->get('/api/matches/export.ics');
        $res->assertStatus(200);
        $this->assertStringContainsString('text/calendar', $res->headers->get('Content-Type'));
        $body = $res->streamedContent();
        $this->assertStringContainsString('BEGIN:VCALENDAR', $body);
        $this->assertStringContainsString('SUMMARY:Indonesia vs Japan', $body);
    }

    public function test_ics_export_requires_auth(): void
    {
        $this->get('/api/matches/export.ics')->assertStatus(401);
    }
}
