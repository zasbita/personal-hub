<?php

namespace Tests\Unit;

use App\Services\TelegramService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramServiceTest extends TestCase
{
    public function test_the_command_menu_is_published_as_a_list_of_objects(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        (new TelegramService())->setCommands(['log' => 'Catat pengeluaran', 'summary' => 'Ringkasan 7 hari']);

        Http::assertSent(fn($request) => str_contains($request->url(), '/setMyCommands')
            && $request['commands'] === [
                ['command' => 'log', 'description' => 'Catat pengeluaran'],
                ['command' => 'summary', 'description' => 'Ringkasan 7 hari'],
            ]);
    }

    public function test_a_message_rejected_by_markdown_is_retried_as_plain_text(): void
    {
        $sent = [];
        Http::fake(['api.telegram.org/*' => function ($request) use (&$sent) {
            $sent[] = $request['parse_mode'] ?? null;
            return isset($request['parse_mode'])
                ? Http::response(['ok' => false, 'description' => "can't parse entities"], 400)
                : Http::response(['ok' => true]);
        }]);

        (new TelegramService())->sendMessage(7, 'kopi_susu *50k');

        $this->assertSame(['Markdown', null], $sent);
    }
}
