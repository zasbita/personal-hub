<?php

namespace Tests\Feature;

use App\Services\BotRouter;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BotRouterFutsalTest extends TestCase
{
    private const OWNER = 99;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::preventStrayRequests();
        config(['services.telegram.owner_id' => self::OWNER]);
        config(['services.futsal.url' => 'https://en.wikipedia.org/wiki/Indonesia_national_futsal_team']);
        config(['app.display_timezone' => 'Asia/Jakarta']);
    }

    private function router(array &$sent): BotRouter
    {
        $tg = \Mockery::mock(TelegramService::class);
        $tg->shouldReceive('sendMessage')->andReturnUsing(function ($cid, $text) use (&$sent) {
            $sent[] = $text;

            return ['ok' => true];
        });

        return new BotRouter($tg, self::OWNER);
    }

    private function update(string $text): array
    {
        return ['message' => ['from' => ['id' => self::OWNER, 'username' => 't'], 'chat' => ['id' => 123], 'text' => $text]];
    }

    public function test_follow_futsal_timnas_normalized(): void
    {
        Http::fake(['*/rest/v1/user_preferences*' => Http::response([]), 'api.telegram.org/*' => Http::response(['ok' => true])]);
        $sent = [];
        $this->router($sent)->handle($this->update('/follow futsal timnas'));
        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_contains($r->url(), 'user_preferences') && $r['sport_type'] === 'futsal' && $r['entity_name'] === 'Indonesia');
        $this->assertStringContainsString('Indonesia', $sent[0]);
    }

    public function test_follow_futsal_rejects_other_country(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $sent = [];
        $this->router($sent)->handle($this->update('/follow futsal Thailand'));
        $this->assertStringContainsString('Hanya', $sent[0]);
        $this->assertStringContainsString('Indonesia', $sent[0]);
        Http::assertNothingSent();
    }

    public function test_jadwal_futsal_from_db(): void
    {
        $mt = now()->addHours(5)->toIso8601String();
        Http::fake([
            '*/rest/v1/user_preferences*' => Http::response([['user_id' => 99, 'sport_type' => 'futsal', 'entity_name' => 'Indonesia', 'notification_enabled' => true]]),
            '*/rest/v1/match_schedule*' => Http::response([['id' => 1, 'source_id' => 'futsal-1:u99', 'sport_type' => 'futsal', 'competition' => 'ASEAN Futsal', 'home_team' => 'Indonesia', 'away_team' => 'Thailand', 'match_time' => $mt, 'status' => 'NS']]),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);
        $sent = [];
        $this->router($sent)->handle($this->update('/jadwal'));
        $this->assertStringContainsString('Indonesia vs Thailand', $sent[0]);
    }

    public function test_jadwal_futsal_fallback_wikipedia(): void
    {
        $futureWib = now()->addHours(6)->setTimezone(new \DateTimeZone('Asia/Jakarta'));
        $html = "Indonesia v Thailand\n".$futureWib->format('j F Y').' '.$futureWib->format('H:i').' UTC+7';
        Http::fake([
            '*/rest/v1/user_preferences*' => Http::response([['user_id' => 99, 'sport_type' => 'futsal', 'entity_name' => 'Indonesia', 'notification_enabled' => true]]),
            '*/rest/v1/match_schedule*' => Http::response([]),
            '*wikipedia.org*' => Http::response($html),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);
        $sent = [];
        $this->router($sent)->handle($this->update('/jadwal'));
        $this->assertStringContainsString('Indonesia', $sent[0]);
        $this->assertStringContainsString('Thailand', $sent[0]);
    }
}
