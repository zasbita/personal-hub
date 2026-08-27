<?php

namespace Tests\Unit;

use App\Services\MobileLegendService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MobileLegendServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['services.mpl.url' => 'https://api.mpl.example']);
    }

    public function test_upcoming_matches_returns_normalized_shape(): void
    {
        $date = now()->format('Y-m-d');
        Http::fake(['*api.mpl.example/matches*' => Http::response(['data' => [
            ['id' => '1', 'date' => now()->addHours(5)->toIso8601String(), 'home' => 'ONIC', 'away' => 'EVOS', 'league' => 'MPL ID S15', 'status' => 'NS'],
            ['id' => '1', 'date' => now()->addHours(5)->toIso8601String(), 'home' => 'ONIC', 'away' => 'EVOS', 'league' => 'MPL ID S15', 'status' => 'NS'],
        ]])]);

        $out = (new MobileLegendService)->getUpcomingMatches();
        $this->assertCount(1, $out);
        $this->assertSame('ONIC', $out[0]['home']);
        $this->assertSame('MPL ID S15', $out[0]['league']);
    }

    public function test_search_teams_filters_by_query(): void
    {
        Http::fake(['*api.mpl.example/matches*' => Http::response(['data' => [
            ['id' => '1', 'date' => now()->addHours(5)->toIso8601String(), 'home' => 'ONIC', 'away' => 'EVOS', 'league' => 'MPL ID'],
            ['id' => '2', 'date' => now()->addHours(6)->toIso8601String(), 'home' => 'RRQ', 'away' => 'ONIC', 'league' => 'MPL ID'],
        ]])]);

        $res = (new MobileLegendService)->searchTeams('onic');
        $this->assertContains('ONIC', $res);
        $this->assertNotContains('EVOS', $res);
    }

    public function test_no_url_returns_empty_without_http(): void
    {
        config(['services.mpl.url' => '']);
        Http::fake();
        $out = (new MobileLegendService)->getUpcomingMatches();
        $this->assertSame([], $out);
        Http::assertNothingSent();
    }
}
