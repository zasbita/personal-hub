<?php

namespace Tests\Unit;

use App\Services\BotRouter;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BotRouterDuplicateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::preventStrayRequests();
        config(['services.telegram.owner_id' => 99]);
    }

    public function test_duplicate_expense_warns(): void
    {
        $today = date('Y-m-d');
        Http::fake([
            '*oauth2.googleapis.com/token' => Http::response(['access_token' => 'a-token']),
            '*sheets.googleapis.com/*' => function ($request) use ($today) {
                $url = urldecode($request->url());
                if (str_contains($url, 'fields=sheets')) {
                    return Http::response(['sheets' => [['properties' => ['sheetId' => 0, 'title' => 'Sheet1']]]]);
                }
                if (str_contains($url, ':append') || str_contains($url, 'values/Sheet1!A:E:append')) {
                    return Http::response([]);
                }
                if (str_contains($url, '/values/Sheet1!A:E')) {
                    return Http::response(['values' => [
                        ['Date', 'Amount', 'Description', 'Category', 'ID'],
                        [$today, '50000', 'Makan siang', 'General', 'id-1'],
                    ]]);
                }
                if (str_contains($url, '/values/Sheet1!A:D')) {
                    return Http::response(['values' => [
                        ['Date', 'Amount', 'Description', 'Category'],
                        [$today, '50000', 'Makan siang', 'General'],
                    ]]);
                }

                return Http::response(['values' => []]);
            },
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);

        $tg = \Mockery::mock(TelegramService::class);
        $tg->shouldReceive('sendMessage')->once()->with(123, \Mockery::on(fn ($t) => str_contains($t, 'Duplikat') && str_contains($t, 'Rp')));

        $router = new BotRouter($tg, 99);
        $router->handle([
            'message' => [
                'from' => ['id' => 99, 'username' => 'test'],
                'chat' => ['id' => 123],
                'text' => '50k makan siang',
            ],
        ]);
    }

    public function test_non_duplicate_no_warning(): void
    {
        $today = date('Y-m-d');
        Http::fake([
            '*oauth2.googleapis.com/token' => Http::response(['access_token' => 'a-token']),
            '*sheets.googleapis.com/*' => function ($request) use ($today) {
                $url = urldecode($request->url());
                if (str_contains($url, 'fields=sheets')) {
                    return Http::response(['sheets' => [['properties' => ['sheetId' => 0, 'title' => 'Sheet1']]]]);
                }
                if (str_contains($url, ':append')) {
                    return Http::response([]);
                }
                if (str_contains($url, '/values/Sheet1!A:E')) {
                    return Http::response(['values' => [
                        ['Date', 'Amount', 'Description', 'Category', 'ID'],
                        [$today, '25000', 'Kopi', 'Jajan', 'id-1'],
                    ]]);
                }
                if (str_contains($url, '/values/Sheet1!A:D')) {
                    return Http::response(['values' => [
                        ['Date', 'Amount', 'Description', 'Category'],
                        [$today, '10000', 'Parkir', 'General'],
                    ]]);
                }

                return Http::response(['values' => []]);
            },
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);

        $tg = \Mockery::mock(TelegramService::class);
        $tg->shouldReceive('sendMessage')->once()->with(123, \Mockery::on(fn ($t) => ! str_contains($t, 'Duplikat')));

        $router = new BotRouter($tg, 99);
        $router->handle([
            'message' => [
                'from' => ['id' => 99],
                'chat' => ['id' => 123],
                'text' => '50k makan siang',
            ],
        ]);
    }
}
