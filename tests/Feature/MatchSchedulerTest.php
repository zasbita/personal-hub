<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MatchSchedulerTest extends TestCase
{
    public function test_football_fixture_h1_is_stored_not_notified(): void
    {
        Cache::flush();
        $kickoff = now()->addHours(25)->toIso8601String();
        Http::fake([
            '*/rest/v1/user_preferences*' => Http::response([
                ['user_id' => 7, 'sport_type' => 'football', 'entity_id' => 'liverpool', 'entity_name' => 'Liverpool', 'notification_enabled' => true],
            ]),
            '*/rest/v1/match_schedule*' => Http::response([]), // no existing row
            '*football.api-sports.io/fixtures*' => Http::response(['response' => [
                ['fixture' => ['id' => 55, 'date' => $kickoff, 'status' => ['short' => 'NS']],
                    'teams' => ['home' => ['name' => 'Liverpool'], 'away' => ['name' => 'Arsenal']], 'league' => ['name' => 'Premier League']],
            ]]),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);

        $this->artisan('bot:schedule')->assertExitCode(0);

        Http::assertSent(function ($req) {
            return $req->method() === 'POST'
                && str_contains($req->url(), 'match_schedule')
                && $req['source_id'] === '55:u7'
                && $req['notified'] === false
                && $req['status'] === 'NS';
        });
        // scheduler must NOT send telegram
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'api.telegram.org'));
    }

    public function test_fixture_outside_h1_window_is_not_stored(): void
    {
        Cache::flush();
        Http::fake([
            '*/rest/v1/user_preferences*' => Http::response([
                ['user_id' => 7, 'sport_type' => 'football', 'entity_id' => 'liverpool', 'entity_name' => 'Liverpool', 'notification_enabled' => true],
            ]),
            '*/rest/v1/match_schedule*' => Http::response([]),
            '*football.api-sports.io/fixtures*' => Http::response(['response' => [
                ['fixture' => ['id' => 55, 'date' => now()->addHours(5)->toIso8601String(), 'status' => ['short' => 'NS']],
                    'teams' => ['home' => ['name' => 'Liverpool'], 'away' => ['name' => 'Arsenal']], 'league' => ['name' => 'PL']],
                ['fixture' => ['id' => 56, 'date' => now()->addHours(40)->toIso8601String(), 'status' => ['short' => 'NS']],
                    'teams' => ['home' => ['name' => 'Liverpool'], 'away' => ['name' => 'Chelsea']], 'league' => ['name' => 'PL']],
            ]]),
        ]);

        $this->artisan('bot:schedule')->assertExitCode(0);

        Http::assertNotSent(fn ($r) => $r->method() === 'POST' && str_contains($r->url(), 'match_schedule'));
    }

    public function test_already_scheduled_is_skipped(): void
    {
        Cache::flush();
        $kickoff = now()->addHours(24)->toIso8601String();
        Http::fake([
            '*/rest/v1/user_preferences*' => Http::response([
                ['user_id' => 7, 'sport_type' => 'football', 'entity_id' => 'liverpool', 'entity_name' => 'Liverpool', 'notification_enabled' => true],
            ]),
            '*/rest/v1/match_schedule*' => Http::response([['id' => 'existing']]), // select returns existing
            '*football.api-sports.io/fixtures*' => Http::response(['response' => [
                ['fixture' => ['id' => 55, 'date' => $kickoff, 'status' => ['short' => 'NS']],
                    'teams' => ['home' => ['name' => 'Liverpool'], 'away' => ['name' => 'Arsenal']], 'league' => ['name' => 'PL']],
            ]]),
        ]);

        $this->artisan('bot:schedule')->assertExitCode(0);
        Http::assertNotSent(fn ($r) => $r->method() === 'POST' && str_contains($r->url(), 'match_schedule'));
    }

    public function test_notify_reuses_h1_row_instead_of_duplicate(): void
    {
        Cache::flush();
        $kickoffSoon = now()->addMinutes(30)->toIso8601String();
        Http::fake([
            '*/rest/v1/user_preferences*' => Http::response([
                ['user_id' => 7, 'sport_type' => 'football', 'entity_id' => 'liverpool', 'entity_name' => 'Liverpool', 'notification_enabled' => true],
            ]),
            // scheduler would have inserted with notified=false; notify sees it
            '*/rest/v1/match_schedule*' => function ($req) {
                if ($req->method() === 'GET' && str_contains(urldecode($req->url()), 'source_id=eq.55:u7')) {
                    return Http::response([['id' => 'h1-row', 'notified' => false]]);
                }
                // reportResults GET returns empty
                if ($req->method() === 'GET') {
                    return Http::response([['id' => 'h1-row', 'notified' => false]]);
                }

                return Http::response([]);
            },
            '*football.api-sports.io/fixtures*' => Http::response(['response' => [
                ['fixture' => ['id' => 55, 'date' => $kickoffSoon, 'status' => ['short' => 'NS']],
                    'teams' => ['home' => ['name' => 'Liverpool'], 'away' => ['name' => 'Arsenal']], 'league' => ['name' => 'PL']],
            ]]),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);

        $this->artisan('bot:notify')->assertExitCode(0);

        // should PATCH the h1 row to notified=true, not POST a new one
        Http::assertSent(fn ($r) => $r->method() === 'PATCH' && str_contains($r->url(), 'match_schedule') && $r['notified'] === true);
        $texts = collect(Http::recorded())->filter(fn ($p) => str_contains($p[0]->url(), 'api.telegram.org'))->map(fn ($p) => $p[0]['text'])->values()->all();
        $this->assertCount(1, $texts);
        $this->assertStringContainsString('Liverpool vs Arsenal', $texts[0]);
    }

    public function test_volleyball_h1_is_stored(): void
    {
        Cache::flush();
        $kickoff = now()->addHours(26)->toIso8601String();
        Http::fake([
            '*/rest/v1/user_preferences*' => Http::response([
                ['user_id' => 123, 'sport_type' => 'volly', 'entity_id' => 'indonesia', 'entity_name' => 'Indonesia', 'notification_enabled' => true],
            ]),
            '*/rest/v1/match_schedule*' => Http::response([]),
            '*volleyball.api-sports.io/games*' => Http::response(['response' => [
                ['id' => 99, 'date' => $kickoff, 'status' => ['short' => 'NS'], 'league' => ['name' => 'Asian'], 'teams' => ['home' => ['name' => 'Indonesia W'], 'away' => ['name' => 'Japan W']]],
            ]]),
        ]);

        $this->artisan('bot:schedule')->assertExitCode(0);
        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_contains($r->url(), 'match_schedule') && $r['source_id'] === '99:u123');
    }

    public function test_motogp_h1_is_stored(): void
    {
        Cache::flush();
        $raceTime = now()->addHours(23);
        Http::fake([
            '*/rest/v1/user_preferences*' => Http::response([
                ['user_id' => 7, 'sport_type' => 'motogp', 'entity_id' => 'aragon', 'entity_name' => 'Aragon', 'notification_enabled' => true],
            ]),
            '*/rest/v1/match_schedule*' => Http::response([]),
            '*pulselive.com/motogp/v1/results/seasons*' => Http::response([['year' => 2026, 'current' => true]]),
            '*pulselive.com/motogp/v1/events*' => Http::response([
                ['kind' => 'GP', 'name' => 'GRAND PRIX OF ARAGON', 'sequence' => 14, 'date_end' => now()->addDays(2)->toIso8601String(),
                    'circuit' => ['name' => 'MotorLand', 'city' => 'Alcañiz', 'country' => 'Spain'], 'broadcasts' => [
                        ['kind' => 'RACE', 'shortname' => 'RAC', 'name' => 'Grand Prix', 'category' => ['acronym' => 'MGP'], 'date_start' => $raceTime->toIso8601String()],
                    ]],
            ]),
        ]);

        $this->artisan('bot:schedule')->assertExitCode(0);
        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_contains($r->url(), 'match_schedule') && str_contains($r['source_id'], 'motogp-14-RAC:u7'));
    }
}
