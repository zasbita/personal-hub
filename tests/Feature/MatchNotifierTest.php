<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MatchNotifierTest extends TestCase
{
    public function test_volleyball_game_within_the_hour_is_notified_once(): void
    {
        Cache::flush();
        $kickoff = now()->addMinutes(30)->toIso8601String();
        Http::fake([
            '*/rest/v1/user_preferences*' => Http::response([
                ['user_id' => 123, 'sport_type' => 'volly', 'entity_id' => 'indonesia', 'entity_name' => 'Indonesia', 'notification_enabled' => true],
            ]),
            '*/rest/v1/match_schedule*' => Http::response([]),
            '*volleyball.api-sports.io/games*' => Http::response(['response' => [
                ['id' => 99, 'date' => $kickoff, 'status' => ['short' => 'NS'], 'league' => ['name' => 'Asian Championship Women'],
                    'teams' => ['home' => ['name' => 'Kazakhstan W'], 'away' => ['name' => 'Indonesia W']]],
                ['id' => 100, 'date' => now()->addDays(2)->toIso8601String(), 'status' => ['short' => 'NS'], 'league' => ['name' => 'Asian Championship Women'],
                    'teams' => ['home' => ['name' => 'Thailand W'], 'away' => ['name' => 'Indonesia W']]], // too far off
            ]]),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);

        $this->artisan('bot:notify')->assertExitCode(0);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'api.telegram.org')) {
                return false;
            }

            return $request['chat_id'] === 123
                && str_contains($request['text'], '1 jam lagi')
                && str_contains($request['text'], 'Kazakhstan W vs Indonesia W');
        });
        $texts = $this->telegramTexts();
        $this->assertCount(1, $texts, 'telegram messages: '.json_encode($texts, JSON_UNESCAPED_UNICODE));
    }

    public function test_game_outside_the_window_sends_nothing(): void
    {
        Cache::flush();
        Http::fake([
            '*/rest/v1/user_preferences*' => Http::response([
                ['user_id' => 123, 'sport_type' => 'volly', 'entity_id' => 'indonesia', 'entity_name' => 'Indonesia', 'notification_enabled' => true],
            ]),
            '*/rest/v1/match_schedule*' => Http::response([]),
            '*volleyball.api-sports.io/games*' => Http::response(['response' => [
                ['id' => 99, 'date' => now()->addHours(5)->toIso8601String(), 'status' => ['short' => 'NS'], 'league' => ['name' => 'L'],
                    'teams' => ['home' => ['name' => 'Indonesia W'], 'away' => ['name' => 'Japan W']]],
            ]]),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);

        $this->artisan('bot:notify')->assertExitCode(0);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.telegram.org'));
    }

    public function test_football_fixture_within_the_hour_is_notified(): void
    {
        Cache::flush();
        Http::fake([
            '*/rest/v1/user_preferences*' => Http::response([
                ['user_id' => 7, 'sport_type' => 'football', 'entity_id' => 'liverpool', 'entity_name' => 'Liverpool', 'notification_enabled' => true],
            ]),
            '*/rest/v1/match_schedule*' => Http::response([]),
            '*football.api-sports.io/fixtures*' => Http::response(['response' => [
                ['fixture' => ['id' => 55, 'date' => now()->addMinutes(20)->toIso8601String(), 'status' => ['short' => 'NS']],
                    'teams' => ['home' => ['name' => 'Liverpool'], 'away' => ['name' => 'Arsenal']], 'league' => ['name' => 'Premier League']],
                ['fixture' => ['id' => 56, 'date' => now()->addMinutes(20)->toIso8601String(), 'status' => ['short' => 'NS']],
                    'teams' => ['home' => ['name' => 'Liverpool U21'], 'away' => ['name' => 'Everton U21']], 'league' => ['name' => 'PL2']], // youth side, not followed
            ]]),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);

        $this->artisan('bot:notify')->assertExitCode(0);

        $texts = $this->telegramTexts();
        $this->assertCount(1, $texts, json_encode($texts, JSON_UNESCAPED_UNICODE));
        $this->assertStringContainsString('Liverpool vs Arsenal', $texts[0]);
        $this->assertStringContainsString('WIB', $texts[0]);
    }

    public function test_motogp_sprint_and_race_are_both_notified(): void
    {
        Cache::flush();
        Http::fake([
            '*/rest/v1/user_preferences*' => Http::response([
                ['user_id' => 7, 'sport_type' => 'motogp', 'entity_id' => 'aragon', 'entity_name' => 'Aragon', 'notification_enabled' => true],
            ]),
            '*/rest/v1/match_schedule*' => Http::response([]),
            '*pulselive.com/motogp/v1/results/seasons*' => Http::response([['year' => 2026, 'current' => true]]),
            '*pulselive.com/motogp/v1/events*' => Http::response([
                ['kind' => 'GP', 'name' => 'GRAND PRIX OF ARAGON', 'sequence' => 14, 'date_end' => now()->addDay()->toIso8601String(),
                    'circuit' => ['name' => 'MotorLand Aragón', 'city' => 'Alcañiz', 'country' => 'Spain'], 'broadcasts' => [
                        ['kind' => 'RACE', 'shortname' => 'SPR', 'name' => 'Tissot Sprint', 'category' => ['acronym' => 'MGP'], 'date_start' => now()->addMinutes(10)->toIso8601String()],
                        ['kind' => 'RACE', 'shortname' => 'RAC', 'name' => 'Grand Prix', 'category' => ['acronym' => 'MGP'], 'date_start' => now()->addMinutes(50)->toIso8601String()],
                        ['kind' => 'QUALIFYING', 'shortname' => 'Q2', 'name' => 'Qualifying Nr. 2', 'category' => ['acronym' => 'MGP'], 'date_start' => now()->addMinutes(30)->toIso8601String()],
                    ]],
            ]),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);

        $this->artisan('bot:notify')->assertExitCode(0);

        $texts = $this->telegramTexts();
        $this->assertCount(2, $texts, json_encode($texts, JSON_UNESCAPED_UNICODE));
        $this->assertStringContainsString('Tissot Sprint', $texts[0]);
        $this->assertStringContainsString('Grand Prix', $texts[1]);
    }

    public function test_already_notified_match_is_skipped(): void
    {
        Cache::flush();
        Http::fake([
            '*/rest/v1/user_preferences*' => Http::response([
                ['user_id' => 7, 'sport_type' => 'volly', 'entity_id' => 'indonesia', 'entity_name' => 'Indonesia', 'notification_enabled' => true],
            ]),
            '*/rest/v1/match_schedule*' => Http::response([['id' => 'existing-row']]),
            '*volleyball.api-sports.io/games*' => Http::response(['response' => [
                ['id' => 99, 'date' => now()->addMinutes(30)->toIso8601String(), 'status' => ['short' => 'NS'], 'league' => ['name' => 'L'],
                    'teams' => ['home' => ['name' => 'Indonesia W'], 'away' => ['name' => 'Japan W']]],
            ]]),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);

        $this->artisan('bot:notify')->assertExitCode(0);

        $this->assertSame([], $this->telegramTexts());
    }

    public function test_a_match_already_notified_to_one_follower_still_reaches_the_others(): void
    {
        Cache::flush();
        Http::fake([
            '*/rest/v1/user_preferences*' => Http::response([
                ['user_id' => 123, 'sport_type' => 'volly', 'entity_id' => 'indonesia', 'entity_name' => 'Indonesia', 'notification_enabled' => true],
                ['user_id' => 456, 'sport_type' => 'volly', 'entity_id' => 'indonesia', 'entity_name' => 'Indonesia', 'notification_enabled' => true],
            ]),
            // Game 99 was already announced to user 123 only.
            '*/rest/v1/match_schedule*' => function ($request) {
                $seen = $request->method() === 'GET' && str_contains(urldecode($request->url()), 'source_id=eq.99:u123');

                return Http::response($seen ? [['id' => 'existing-row']] : []);
            },
            '*volleyball.api-sports.io/games*' => Http::response(['response' => [
                ['id' => 99, 'date' => now()->addMinutes(30)->toIso8601String(), 'status' => ['short' => 'NS'], 'league' => ['name' => 'L'],
                    'teams' => ['home' => ['name' => 'Indonesia W'], 'away' => ['name' => 'Japan W']]],
            ]]),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);

        $this->artisan('bot:notify')->assertExitCode(0);

        $recipients = collect(Http::recorded())
            ->filter(fn ($pair) => str_contains($pair[0]->url(), 'api.telegram.org'))
            ->map(fn ($pair) => $pair[0]['chat_id'])
            ->values()->all();
        $this->assertSame([456], $recipients);
    }

    public function test_a_message_telegram_cannot_parse_is_resent_as_plain_text(): void
    {
        Cache::flush();
        $attempts = 0;
        Http::fake([
            '*/rest/v1/user_preferences*' => Http::response([
                ['user_id' => 7, 'sport_type' => 'volly', 'entity_id' => 'indonesia', 'entity_name' => 'Indonesia', 'notification_enabled' => true],
            ]),
            '*/rest/v1/match_schedule*' => Http::response([]),
            '*volleyball.api-sports.io/games*' => Http::response(['response' => [
                ['id' => 99, 'date' => now()->addMinutes(30)->toIso8601String(), 'status' => ['short' => 'NS'], 'league' => ['name' => 'Liga_1'],
                    'teams' => ['home' => ['name' => 'Indonesia W'], 'away' => ['name' => 'Japan W']]],
            ]]),
            'api.telegram.org/*' => function ($request) use (&$attempts) {
                $attempts++;

                return isset($request['parse_mode'])
                    ? Http::response(['ok' => false, 'description' => "can't parse entities"], 400)
                    : Http::response(['ok' => true]);
            },
        ]);

        $this->artisan('bot:notify')->assertExitCode(0);

        $this->assertSame(2, $attempts, 'expected a Markdown attempt then a plain-text retry');
    }

    public function test_a_finished_match_gets_its_score_reported_once(): void
    {
        Cache::flush();
        Http::fake([
            '*/rest/v1/user_preferences*' => Http::response([]),
            '*/rest/v1/match_schedule*' => function ($request) {
                if ($request->method() !== 'GET') {
                    return Http::response([]);
                }

                return Http::response([[
                    'id' => 'row-1', 'source_id' => '55:u7', 'sport_type' => 'football',
                    'home_team' => 'Liverpool', 'away_team' => 'Arsenal', 'competition' => 'Premier League',
                ]]);
            },
            '*football.api-sports.io/fixtures*' => Http::response(['response' => [
                ['fixture' => ['id' => 55, 'status' => ['short' => 'FT']], 'goals' => ['home' => 2, 'away' => 1]],
            ]]),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);

        $this->artisan('bot:notify')->assertExitCode(0);

        $texts = $this->telegramTexts();
        $this->assertCount(1, $texts, json_encode($texts, JSON_UNESCAPED_UNICODE));
        $this->assertStringContainsString('Liverpool *2 - 1* Arsenal', $texts[0]);
        // The row is marked so the next run does not report it again.
        Http::assertSent(fn ($request) => $request->method() === 'PATCH'
            && str_contains($request->url(), 'match_schedule')
            && str_contains($request->url(), 'id=eq.row-1')
            && $request['status'] === 'FT');
    }

    public function test_a_match_still_in_play_is_not_reported(): void
    {
        Cache::flush();
        Http::fake([
            '*/rest/v1/user_preferences*' => Http::response([]),
            '*/rest/v1/match_schedule*' => function ($request) {
                if ($request->method() !== 'GET') {
                    return Http::response([]);
                }

                return Http::response([[
                    'id' => 'row-1', 'source_id' => '55:u7', 'sport_type' => 'football',
                    'home_team' => 'Liverpool', 'away_team' => 'Arsenal', 'competition' => 'Premier League',
                ]]);
            },
            '*football.api-sports.io/fixtures*' => Http::response(['response' => [
                ['fixture' => ['id' => 55, 'status' => ['short' => '2H']], 'goals' => ['home' => 1, 'away' => 1]],
            ]]),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);

        $this->artisan('bot:notify')->assertExitCode(0);

        $this->assertSame([], $this->telegramTexts());
    }

    /** @return string[] */
    private function telegramTexts(): array
    {
        return collect(Http::recorded())
            ->filter(fn ($pair) => str_contains($pair[0]->url(), 'api.telegram.org'))
            ->map(fn ($pair) => $pair[0]['text'])
            ->values()->all();
    }
}
