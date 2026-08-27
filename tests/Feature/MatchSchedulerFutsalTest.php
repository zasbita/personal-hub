<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MatchSchedulerFutsalTest extends TestCase
{
    public function test_futsal_h1_stored(): void
    {
        Cache::flush();
        config(['services.futsal.url' => 'https://en.wikipedia.org/wiki/Indonesia_national_futsal_team']);
        $future = now()->addHours(25);
        $html = "Indonesia v Cambodia\n".$future->format('j F Y').' 13:30 UTC+7';
        Http::fake([
            '*/rest/v1/user_preferences*' => Http::response([['user_id' => 7, 'sport_type' => 'futsal', 'entity_id' => 'indonesia', 'entity_name' => 'Indonesia', 'notification_enabled' => true]]),
            '*/rest/v1/match_schedule*' => Http::response([]),
            '*wikipedia.org*' => Http::response($html),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);
        $this->artisan('bot:schedule')->assertExitCode(0);
        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_contains($r->url(), 'match_schedule') && $r['sport_type'] === 'futsal');
    }
}
