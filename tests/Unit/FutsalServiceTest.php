<?php

namespace Tests\Unit;

use App\Services\FutsalService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FutsalServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['services.futsal.url' => 'https://en.wikipedia.org/wiki/Indonesia_national_futsal_team']);
    }

    public function test_search_teams_static_indonesia(): void
    {
        $this->assertSame(['Indonesia'], (new FutsalService)->searchTeams('Indonesia'));
        $this->assertSame(['Indonesia'], (new FutsalService)->searchTeams('timnas'));
        $this->assertSame([], (new FutsalService)->searchTeams('Thailand'));
    }

    public function test_upcoming_parses_wikipedia(): void
    {
        $future = now()->addDays(2);
        $html = "Indonesia v Cambodia\n".$future->format('j F Y')." 13:30 UTC+8\nIndonesia v Myanmar\n".$future->format('j F Y').' 15:00 UTC+7';
        Http::fake(['*wikipedia.org*' => Http::response($html, 200, ['Content-Type' => 'text/html'])]);

        $out = (new FutsalService)->getUpcomingMatches();
        $this->assertCount(2, $out);
        $this->assertSame('Indonesia', $out[0]['home']);
    }
}
