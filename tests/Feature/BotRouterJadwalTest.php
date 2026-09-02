<?php

namespace Tests\Feature;

use App\Services\BotRouter;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BotRouterJadwalTest extends TestCase
{
    private const OWNER = 99;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::preventStrayRequests();
        config(['services.telegram.owner_id' => self::OWNER]);
        config(['services.football.api_key' => 'test-key']);
        config(['services.api_sports.key' => 'test-key']);
        config(['app.display_timezone' => 'Asia/Jakarta']);
    }

    private function routerWithMock(array &$sent): BotRouter
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
        return ['message' => ['from' => ['id' => $from, 'chat' => ['id' => 123], 'username' => 't'], 'chat' => ['id' => 123], 'text' => $text]];
    }

    public function test_jadwal_shows_db_results(): void
    {
        $mt = now()->addHours(5)->toIso8601String();
        Http::fake([
            '*/rest/v1/user_preferences*' => Http::response([['user_id' => 99, 'sport_type' => 'football', 'entity_name' => 'Liverpool', 'notification_enabled' => true]]),
            '*/rest/v1/match_schedule*' => Http::response([['id' => 1, 'source_id' => '55:u99', 'sport_type' => 'football', 'competition' => 'Premier League', 'home_team' => 'Liverpool', 'away_team' => 'Arsenal', 'match_time' => $mt, 'status' => 'NS']]),
            'api.telegram.org/*' => Http::response(['ok' => true]),
            '*football.api-sports.io/*' => Http::response(['response' => []]),
        ]);

        $sent = [];
        $this->routerWithMock($sent)->handle($this->update('/schedule'));

        $this->assertNotEmpty($sent);
        $this->assertStringContainsString('Schedule next 7 days', $sent[0]);
        $this->assertStringContainsString('Liverpool vs Arsenal', $sent[0]);
    }

    public function test_alias_schedule_and_next(): void
    {
        $mt = now()->addHours(5)->toIso8601String();
        foreach (['/jadwal', '/schedule', '/next'] as $alias) {
            Cache::flush();
            Http::fake([
                '*/rest/v1/user_preferences*' => Http::response([['user_id' => 99, 'sport_type' => 'football', 'entity_name' => 'Liverpool', 'notification_enabled' => true]]),
                '*/rest/v1/match_schedule*' => Http::response([['id' => 1, 'source_id' => '55:u99', 'sport_type' => 'football', 'competition' => 'PL', 'home_team' => 'Liverpool', 'away_team' => 'Arsenal', 'match_time' => $mt, 'status' => 'NS']]),
                '*football.api-sports.io/*' => Http::response(['response' => []]),
                'api.telegram.org/*' => Http::response(['ok' => true]),
            ]);
            $sent = [];
            $this->routerWithMock($sent)->handle($this->update($alias));
            $this->assertStringContainsString('Liverpool', $sent[0]);
        }
    }

    public function test_unauthorized_does_not_query_db(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $sent = [];
        $this->routerWithMock($sent)->handle($this->update('/schedule', from: 999));
        $this->assertStringContainsString('Unauthorized', $sent[0]);
        Http::assertNothingSent();
    }

    public function test_no_pref_hint(): void
    {
        Http::fake([
            '*/rest/v1/user_preferences*' => Http::response([]),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);
        $sent = [];
        $this->routerWithMock($sent)->handle($this->update('/schedule'));
        $this->assertStringContainsString('No teams followed', $sent[0]);
    }

    public function test_no_jadwal_in_24h_shows_empty(): void
    {
        Http::fake([
            '*/rest/v1/user_preferences*' => Http::response([['user_id' => 99, 'sport_type' => 'football', 'entity_name' => 'Liverpool', 'notification_enabled' => true]]),
            '*/rest/v1/match_schedule*' => Http::response([]),
            '*football.api-sports.io/fixtures*' => Http::response(['response' => []]),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);
        $sent = [];
        $this->routerWithMock($sent)->handle($this->update('/schedule'));
        $this->assertStringContainsString('No schedule in the next 7 days', $sent[0]);
    }

    public function test_fallback_hits_api_when_db_empty(): void
    {
        $kickoff = now()->addHours(5)->toIso8601String();
        Http::fake([
            '*/rest/v1/user_preferences*' => Http::response([['user_id' => 99, 'sport_type' => 'football', 'entity_name' => 'Liverpool', 'notification_enabled' => true]]),
            '*/rest/v1/match_schedule*' => Http::response([]),
            '*football.api-sports.io/fixtures*' => Http::response(['response' => [
                ['fixture' => ['id' => 77, 'date' => $kickoff, 'status' => ['short' => 'NS']], 'teams' => ['home' => ['name' => 'Liverpool'], 'away' => ['name' => 'Chelsea']], 'league' => ['name' => 'Premier League']],
            ]]),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);
        $sent = [];
        $this->routerWithMock($sent)->handle($this->update('/schedule'));
        $this->assertStringContainsString('Liverpool vs Chelsea', $sent[0]);
    }

    public function test_per_sport_fallback_only_missing_sport(): void
    {
        $mt = now()->addHours(5)->toIso8601String();
        $kickoffVolly = now()->addHours(6)->toIso8601String();
        Http::fake([
            '*/rest/v1/user_preferences*' => Http::response([
                ['user_id' => 99, 'sport_type' => 'football', 'entity_name' => 'Liverpool', 'notification_enabled' => true],
                ['user_id' => 99, 'sport_type' => 'volly', 'entity_name' => 'Indonesia', 'notification_enabled' => true],
            ]),
            '*/rest/v1/match_schedule*' => Http::response([['id' => 1, 'source_id' => '55:u99', 'sport_type' => 'football', 'competition' => 'PL', 'home_team' => 'Liverpool', 'away_team' => 'Arsenal', 'match_time' => $mt, 'status' => 'NS']]),
            '*volleyball.api-sports.io/games*' => Http::response(['response' => [
                ['id' => 9, 'date' => $kickoffVolly, 'status' => ['short' => 'NS'], 'league' => ['name' => 'Asian'], 'teams' => ['home' => ['name' => 'Indonesia W'], 'away' => ['name' => 'Japan W']]],
            ]]),
            '*football.api-sports.io/*' => Http::response(['response' => []]),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);
        $sent = [];
        $this->routerWithMock($sent)->handle($this->update('/schedule'));
        $this->assertStringContainsString('Liverpool vs Arsenal', $sent[0]);
        $this->assertStringContainsString('Indonesia', $sent[0]);
        // football API should not be called because DB had football
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'football.api-sports.io/fixtures') && str_contains($r->url(), 'date='));
    }

    public function test_cap_ten_and_sorted(): void
    {
        $rows = [];
        for ($i = 12; $i >= 1; $i--) {
            $rows[] = ['id' => $i, 'source_id' => "id{$i}:u99", 'sport_type' => 'football', 'competition' => 'PL', 'home_team' => "Team{$i}", 'away_team' => 'Opp', 'match_time' => now()->addHours($i)->toIso8601String(), 'status' => 'NS'];
        }
        Http::fake([
            '*/rest/v1/user_preferences*' => Http::response([['user_id' => 99, 'sport_type' => 'football', 'entity_name' => 'Team', 'notification_enabled' => true]]),
            '*/rest/v1/match_schedule*' => Http::response($rows),
            '*football.api-sports.io/*' => Http::response(['response' => []]),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);
        $sent = [];
        $this->routerWithMock($sent)->handle($this->update('/schedule'));
        $this->assertStringContainsString('and 2 more', $sent[0]);
        $this->assertStringContainsString('Team1 vs', $sent[0]);
        // Team12 is 12th oldest? Actually Team1 is earliest, should be included; Team11 beyond cap 10 should not be in slice but overflow counts it
        $this->assertStringNotContainsString('Team11 vs', $sent[0]);
        $this->assertStringNotContainsString('Team12 vs', $sent[0]);
    }

    public function test_menu_contains_schedule(): void
    {
        $this->assertArrayHasKey('schedule', BotRouter::MENU);
        $this->assertStringContainsString('/schedule', (new \ReflectionClass(BotRouter::class))->getConstant('WELCOME'));
    }
}
