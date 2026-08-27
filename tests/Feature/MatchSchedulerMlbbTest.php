<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MatchSchedulerMlbbTest extends TestCase
{
    public function test_mlbb_h1_is_stored_not_notified(): void
    {
        Cache::flush();
        config(['services.mpl.url' => 'https://api.mpl.example']);
        $kickoff = now()->addHours(25)->toIso8601String();
        Http::fake([
            '*/rest/v1/user_preferences*' => Http::response([
                ['user_id' => 7, 'sport_type' => 'mobilelegend', 'entity_id' => 'onic', 'entity_name' => 'ONIC', 'notification_enabled' => true],
            ]),
            '*/rest/v1/match_schedule*' => Http::response([]),
            '*api.mpl.example/matches*' => Http::response(['data' => [
                ['id' => 'ml1', 'date' => $kickoff, 'home' => 'ONIC', 'away' => 'EVOS', 'league' => 'MPL ID', 'status' => 'NS'],
            ]]),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);

        $this->artisan('bot:schedule')->assertExitCode(0);
        Http::assertSent(function ($req) {
            return $req->method() === 'POST' && str_contains($req->url(), 'match_schedule')
                && $req['sport_type'] === 'mobilelegend' && $req['notified'] === false;
        });
    }

    public function test_mlbb_notify_sends_with_emoji(): void
    {
        Cache::flush();
        config(['services.mpl.url' => 'https://api.mpl.example']);
        $soon = now()->addMinutes(30)->toIso8601String();
        Http::fake([
            '*/rest/v1/user_preferences*' => Http::response([
                ['user_id' => 7, 'sport_type' => 'mobilelegend', 'entity_id' => 'onic', 'entity_name' => 'ONIC', 'notification_enabled' => true],
            ]),
            '*/rest/v1/match_schedule*' => function ($req) {
                if ($req->method() === 'GET' && str_contains(urldecode($req->url()), 'source_id=eq.ml1:u7')) {
                    return Http::response([['id' => 'row1', 'notified' => false]]);
                }
                if ($req->method() === 'GET') {
                    return Http::response([['id' => 'row1', 'notified' => false]]);
                }

                return Http::response([]);
            },
            '*api.mpl.example/matches*' => Http::response(['data' => [
                ['id' => 'ml1', 'date' => $soon, 'home' => 'ONIC', 'away' => 'EVOS', 'league' => 'MPL ID', 'status' => 'NS'],
            ]]),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);

        $this->artisan('bot:notify')->assertExitCode(0);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'api.telegram.org') && str_contains($r['text'], 'ONIC vs EVOS'));
        Http::assertSent(function ($r) {
            return str_contains($r->url(), 'api.telegram.org') && str_contains($r['text'], '🎮');
        });
    }
}
