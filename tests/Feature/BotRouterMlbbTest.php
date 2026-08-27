<?php

namespace Tests\Feature;

use App\Services\BotRouter;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BotRouterMlbbTest extends TestCase
{
    private const OWNER = 99;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::preventStrayRequests();
        config(['services.telegram.owner_id' => self::OWNER]);
        config(['services.mpl.url' => 'https://api.mpl.example']);
        config(['app.display_timezone' => 'Asia/Jakarta']);
    }

    private function router(array &$sent): BotRouter
    {
        $tg = \Mockery::mock(TelegramService::class);
        $tg->shouldReceive('sendMessage')->andReturnUsing(function ($cid, $text) use (&$sent) {
            $sent[] = $text;

            return ['ok' => true];
        });

        return new BotRouter($tg, self::OWNER);
    }

    private function update(string $text, int $from = self::OWNER): array
    {
        return ['message' => ['from' => ['id' => $from, 'username' => 't'], 'chat' => ['id' => 123], 'text' => $text]];
    }

    public function test_follow_mlbb_alias_normalized_to_mobilelegend(): void
    {
        Http::fake([
            '*api.mpl.example/matches*' => Http::response(['data' => [
                ['id' => '1', 'date' => now()->addHours(5)->toIso8601String(), 'home' => 'ONIC', 'away' => 'EVOS', 'league' => 'MPL ID'],
            ]]),
            '*/rest/v1/user_preferences*' => Http::response([]),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);

        $sent = [];
        $this->router($sent)->handle($this->update('/follow mlbb ONIC'));
        // should have inserted via Supabase POST, and replied success
        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_contains($r->url(), 'user_preferences') && $r['sport_type'] === 'mobilelegend' && $r['entity_name'] === 'ONIC');
        $this->assertStringContainsString('ONIC', $sent[0]);
        $this->assertStringContainsString('mobilelegend', $sent[0]);
    }

    public function test_jadwal_shows_mlbb_from_db(): void
    {
        $mt = now()->addHours(5)->toIso8601String();
        Http::fake([
            '*/rest/v1/user_preferences*' => Http::response([['user_id' => 99, 'sport_type' => 'mobilelegend', 'entity_name' => 'ONIC', 'notification_enabled' => true]]),
            '*/rest/v1/match_schedule*' => Http::response([['id' => 1, 'source_id' => 'mlbb-1:u99', 'sport_type' => 'mobilelegend', 'competition' => 'MPL ID', 'home_team' => 'ONIC', 'away_team' => 'EVOS', 'match_time' => $mt, 'status' => 'NS']]),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);
        $sent = [];
        $this->router($sent)->handle($this->update('/jadwal'));
        $this->assertStringContainsString('ONIC vs EVOS', $sent[0]);
        $this->assertStringContainsString('🎮', $sent[0]);
    }

    public function test_jadwal_fallback_hits_mlbb_api_when_db_empty(): void
    {
        $kickoff = now()->addHours(6)->toIso8601String();
        Http::fake([
            '*/rest/v1/user_preferences*' => Http::response([['user_id' => 99, 'sport_type' => 'mobilelegend', 'entity_name' => 'ONIC', 'notification_enabled' => true]]),
            '*/rest/v1/match_schedule*' => Http::response([]),
            '*api.mpl.example/matches*' => Http::response(['data' => [
                ['id' => '10', 'date' => $kickoff, 'home' => 'ONIC', 'away' => 'RRQ', 'league' => 'MPL ID S15', 'status' => 'NS'],
            ]]),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);
        $sent = [];
        $this->router($sent)->handle($this->update('/jadwal'));
        $this->assertStringContainsString('ONIC vs RRQ', $sent[0]);
    }

    public function test_per_sport_fallback_isolation(): void
    {
        $mt = now()->addHours(5)->toIso8601String();
        $mlKickoff = now()->addHours(6)->toIso8601String();
        Http::fake([
            '*/rest/v1/user_preferences*' => Http::response([
                ['user_id' => 99, 'sport_type' => 'football', 'entity_name' => 'Arsenal', 'notification_enabled' => true],
                ['user_id' => 99, 'sport_type' => 'mobilelegend', 'entity_name' => 'ONIC', 'notification_enabled' => true],
            ]),
            '*/rest/v1/match_schedule*' => Http::response([['id' => 1, 'source_id' => '1:u99', 'sport_type' => 'football', 'competition' => 'PL', 'home_team' => 'Arsenal', 'away_team' => 'Chelsea', 'match_time' => $mt, 'status' => 'NS']]),
            '*api.mpl.example/matches*' => Http::response(['data' => [
                ['id' => '10', 'date' => $mlKickoff, 'home' => 'ONIC', 'away' => 'EVOS', 'league' => 'MPL ID'],
            ]]),
            '*football.api-sports.io/*' => Http::response(['response' => []]),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);
        $sent = [];
        $this->router($sent)->handle($this->update('/jadwal'));
        $this->assertStringContainsString('Arsenal vs Chelsea', $sent[0]);
        $this->assertStringContainsString('ONIC vs EVOS', $sent[0]);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'football.api-sports.io/fixtures'));
    }
}
