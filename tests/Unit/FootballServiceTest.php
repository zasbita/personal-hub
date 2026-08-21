<?php

namespace Tests\Unit;

use App\Services\FootballService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FootballServiceTest extends TestCase
{
    public function test_upcoming_fixtures_keep_not_started_only_and_dedupe(): void
    {
        Cache::flush();
        Http::fake(['*/fixtures*' => Http::response(['response' => [
            ['fixture' => ['id' => 7, 'date' => '2099-01-01T10:00:00+00:00', 'status' => ['short' => 'NS']],
             'teams' => ['home' => ['name' => 'Liverpool'], 'away' => ['name' => 'Arsenal']], 'league' => ['name' => 'Premier League']],
            ['fixture' => ['id' => 8, 'date' => '2099-01-01T12:00:00+00:00', 'status' => ['short' => 'FT']],
             'teams' => ['home' => ['name' => 'A'], 'away' => ['name' => 'B']], 'league' => ['name' => 'L']],
        ]])]);

        $fixtures = (new FootballService())->getUpcomingFixtures();

        $this->assertSame([['id' => '7', 'date' => '2099-01-01T10:00:00+00:00', 'home' => 'Liverpool', 'away' => 'Arsenal', 'league' => 'Premier League']], $fixtures);
    }

    public function test_team_search_returns_names(): void
    {
        Cache::flush();
        Http::fake(['*/teams*' => Http::response(['response' => [
            ['team' => ['name' => 'Liverpool']],
            ['team' => ['name' => 'Liverpool U21']],
        ]])]);

        $this->assertSame(['Liverpool', 'Liverpool U21'], (new FootballService())->searchTeams('liverpool'));
    }
}
