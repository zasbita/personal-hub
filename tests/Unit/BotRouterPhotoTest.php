<?php

namespace Tests\Unit;

use App\Services\BotRouter;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BotRouterPhotoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::preventStrayRequests();
        config(['services.telegram.owner_id' => 99]);
    }

    public function test_photo_with_caption_is_treated_as_expense(): void
    {
        Http::fake([
            '*oauth2.googleapis.com/token' => Http::response(['access_token' => 'a-token']),
            '*sheets.googleapis.com/*' => Http::response([]),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);

        $tg = \Mockery::mock(TelegramService::class);
        $tg->shouldReceive('sendMessage')->once()->with(123, \Mockery::on(fn ($text) => str_contains($text, 'Rp')));

        $router = new BotRouter($tg, 99);
        $router->handle([
            'message' => [
                'from' => ['id' => 99, 'username' => 'test'],
                'chat' => ['id' => 123],
                'photo' => [['file_id' => 'abc', 'width' => 100]],
                'caption' => '50k bensin #Transport',
            ],
        ]);

        // Should have tried to append to sheets
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sheets.googleapis.com') && str_contains($request->url(), ':append');
        });
    }

    public function test_photo_without_caption_replies_with_instruction(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $tg = \Mockery::mock(TelegramService::class);
        $tg->shouldReceive('sendMessage')->once()->with(123, \Mockery::on(fn ($t) => str_contains($t, 'Foto diterima')));

        $router = new BotRouter($tg, 99);
        $router->handle([
            'message' => [
                'from' => ['id' => 99],
                'chat' => ['id' => 123],
                'photo' => [['file_id' => 'abc']],
            ],
        ]);

        Http::assertNothingSent(); // no sheets call
    }
}
