<?php

namespace Tests\Feature;

use App\Services\BotRouter;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BotCategoryTest extends TestCase
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

    public function test_schedule_with_valid_category_filters_only_that_category(): void
    {
        $mt = now()->addHours(5)->toIso8601String();
        $mt2 = now()->addHours(6)->toIso8601String();
        Http::fake([
            '*/rest/v1/user_preferences*' => Http::response([
                ['user_id' => 99, 'sport_type' => 'football', 'entity_name' => 'Arsenal', 'notification_enabled' => true],
                ['user_id' => 99, 'sport_type' => 'volly', 'entity_name' => 'Indonesia', 'notification_enabled' => true],
            ]),
            '*/rest/v1/match_schedule*' => Http::response([
                ['id' => 1, 'source_id' => '55:u99', 'sport_type' => 'football', 'competition' => 'PL', 'home_team' => 'Arsenal', 'away_team' => 'Chelsea', 'match_time' => $mt, 'status' => 'NS'],
                ['id' => 2, 'source_id' => '66:u99', 'sport_type' => 'volly', 'competition' => 'AVC', 'home_team' => 'Indonesia', 'away_team' => 'Japan', 'match_time' => $mt2, 'status' => 'NS'],
            ]),
            'api.telegram.org/*' => Http::response(['ok' => true]),
            '*football.api-sports.io/*' => Http::response(['response' => []]),
            '*volleyball.api-sports.io/*' => Http::response(['response' => []]),
        ]);
        $sent = [];
        $this->routerWithMock($sent)->handle($this->update('/schedule football'));

        $this->assertStringContainsString('Schedule football', $sent[0]);
        $this->assertStringContainsString('Arsenal vs Chelsea', $sent[0]);
        $this->assertStringNotContainsString('Indonesia vs Japan', $sent[0]);
    }

    public function test_schedule_with_invalid_category_returns_error(): void
    {
        Http::fake([
            '*/rest/v1/user_preferences*' => Http::response([['user_id' => 99, 'sport_type' => 'football', 'entity_name' => 'Arsenal', 'notification_enabled' => true]]),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);
        $sent = [];
        $this->routerWithMock($sent)->handle($this->update('/schedule xyz'));
        $this->assertStringContainsString('Kategori tidak dikenal', $sent[0]);
        $this->assertStringContainsString('football', $sent[0]);
    }

    public function test_schedule_mlbb_alias_normalized(): void
    {
        $mt = now()->addHours(5)->toIso8601String();
        Http::fake([
            '*/rest/v1/user_preferences*' => Http::response([['user_id' => 99, 'sport_type' => 'mobilelegend', 'entity_name' => 'ONIC', 'notification_enabled' => true]]),
            '*/rest/v1/match_schedule*' => Http::response([['id' => 1, 'source_id' => 'x:u99', 'sport_type' => 'mobilelegend', 'competition' => 'MPL', 'home_team' => 'ONIC', 'away_team' => 'RRQ', 'match_time' => $mt, 'status' => 'NS']]),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);
        $sent = [];
        $this->routerWithMock($sent)->handle($this->update('/schedule mlbb'));
        $this->assertStringContainsString('ONIC vs RRQ', $sent[0]);
        $this->assertStringContainsString('Schedule mobilelegend', $sent[0]);
    }

    public function test_schedule_motogp_group(): void
    {
        $mt = now()->addHours(5)->toIso8601String();
        $mt2 = now()->addHours(6)->toIso8601String();
        Http::fake([
            '*/rest/v1/user_preferences*' => Http::response([
                ['user_id' => 99, 'sport_type' => 'motogp', 'entity_name' => 'Qatar GP', 'notification_enabled' => true],
                ['user_id' => 99, 'sport_type' => 'moto2', 'entity_name' => 'Qatar Moto2', 'notification_enabled' => true],
            ]),
            '*/rest/v1/match_schedule*' => Http::response([
                ['id' => 1, 'source_id' => 'a:u99', 'sport_type' => 'motogp', 'competition' => 'Qatar GP', 'home_team' => 'Lusail', 'away_team' => '', 'match_time' => $mt, 'status' => 'NS'],
                ['id' => 2, 'source_id' => 'b:u99', 'sport_type' => 'moto2', 'competition' => 'Qatar Moto2', 'home_team' => 'Lusail', 'away_team' => '', 'match_time' => $mt2, 'status' => 'NS'],
            ]),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);
        $sent = [];
        $this->routerWithMock($sent)->handle($this->update('/schedule motogp'));
        $this->assertStringContainsString('Qatar GP', $sent[0]);
        $this->assertStringContainsString('Qatar Moto2', $sent[0]);
    }

    public function test_schedule_category_without_pref_returns_specific_empty(): void
    {
        Http::fake([
            '*/rest/v1/user_preferences*' => Http::response([['user_id' => 99, 'sport_type' => 'football', 'entity_name' => 'Arsenal', 'notification_enabled' => true]]),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);
        $sent = [];
        $this->routerWithMock($sent)->handle($this->update('/schedule futsal'));
        $this->assertStringContainsString('No schedule for futsal', $sent[0]);
    }

    public function test_categories_lists_counts(): void
    {
        Http::fake([
            '*/rest/v1/user_preferences*' => Http::response([
                ['user_id' => 99, 'sport_type' => 'football', 'entity_name' => 'Arsenal', 'notification_enabled' => true],
                ['user_id' => 99, 'sport_type' => 'football', 'entity_name' => 'Chelsea', 'notification_enabled' => true],
                ['user_id' => 99, 'sport_type' => 'futsal', 'entity_name' => 'Indonesia', 'notification_enabled' => true],
            ]),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);
        $sent = [];
        $this->routerWithMock($sent)->handle($this->update('/categories'));
        $this->assertStringContainsString('football (2)', $sent[0]);
        $this->assertStringContainsString('futsal (1)', $sent[0]);
        $this->assertStringContainsString('/schedule', $sent[0]);
    }

    public function test_schedule_jadwal_alias_with_category_works(): void
    {
        $mt = now()->addHours(5)->toIso8601String();
        Http::fake([
            '*/rest/v1/user_preferences*' => Http::response([['user_id' => 99, 'sport_type' => 'futsal', 'entity_name' => 'Indonesia', 'notification_enabled' => true]]),
            '*/rest/v1/match_schedule*' => Http::response([['id' => 1, 'source_id' => 'x:u99', 'sport_type' => 'futsal', 'competition' => 'AFC', 'home_team' => 'Indonesia', 'away_team' => 'Thailand', 'match_time' => $mt, 'status' => 'NS']]),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);
        $sent = [];
        $this->routerWithMock($sent)->handle($this->update('/jadwal futsal'));
        $this->assertStringContainsString('Indonesia vs Thailand', $sent[0]);
    }

    public function test_unauthorized_with_category(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $sent = [];
        $this->routerWithMock($sent)->handle($this->update('/schedule football', from: 999));
        $this->assertStringContainsString('Unauthorized', $sent[0]);
    }
}
