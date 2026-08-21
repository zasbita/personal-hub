<?php

namespace Tests\Unit;

use App\Services\VolleyballService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VolleyballServiceTest extends TestCase
{
    public function test_keeps_only_not_started_games_and_caches(): void
    {
        Cache::flush();
        Http::fake(['*/games*' => Http::response(['response' => [
            ['id' => 1, 'date' => '2099-01-01T10:00:00+00:00', 'status' => ['short' => 'NS'], 'teams' => ['home' => ['name' => 'Indonesia W'], 'away' => ['name' => 'Iran W']], 'league' => ['name' => 'Asian Championship Women']],
            ['id' => 2, 'date' => '2099-01-01T12:00:00+00:00', 'status' => ['short' => 'S2'], 'teams' => ['home' => ['name' => 'A'], 'away' => ['name' => 'B']], 'league' => ['name' => 'X']],
            ['id' => 3, 'date' => '2099-01-01T14:00:00+00:00', 'status' => ['short' => 'CANC'], 'teams' => ['home' => ['name' => 'C'], 'away' => ['name' => 'D']], 'league' => ['name' => 'X']],
        ]])]);

        $games = (new VolleyballService())->getUpcomingGames();

        $this->assertSame(['1'], array_column($games, 'id')); // NS only, and a game listed under both dates counts once
        $this->assertSame('Indonesia W', $games[0]['home']);
        $this->assertSame('Asian Championship Women', $games[0]['league']);
        Http::assertSentCount(2);

        (new VolleyballService())->getUpcomingGames();
        Http::assertSentCount(2); // served from cache
    }

    public function test_api_errors_are_thrown(): void
    {
        Cache::flush();
        Http::fake(['*/games*' => Http::response(['errors' => ['token' => 'invalid']])]);
        $this->expectException(\RuntimeException::class);
        (new VolleyballService())->getUpcomingGames();
    }
}
