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
            if (!str_contains($request->url(), 'api.telegram.org')) return false;
            return $request['chat_id'] === 123
                && str_contains($request['text'], '1 jam lagi')
                && str_contains($request['text'], 'Kazakhstan W vs Indonesia W');
        });
        $texts = collect(Http::recorded())->filter(fn($pair) => str_contains($pair[0]->url(), 'api.telegram.org'))->map(fn($pair) => $pair[0]['text'])->values()->all();
        $this->assertCount(1, $texts, 'telegram messages: ' . json_encode($texts, JSON_UNESCAPED_UNICODE));
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

        Http::assertNotSent(fn($request) => str_contains($request->url(), 'api.telegram.org'));
    }
}
