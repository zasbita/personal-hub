<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramWebhookTest extends TestCase
{
    private const OWNER = 811031481;
    private const SECRET = 'a-long-enough-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'services.telegram.owner_id' => self::OWNER,
            'services.telegram.webhook_secret' => self::SECRET,
            'services.budget.monthly' => 0,
        ]);
    }

    public function test_a_call_without_the_secret_is_refused(): void
    {
        Http::fake();

        $this->postJson('/api/telegram/webhook', $this->update('/start'))->assertStatus(401);

        Http::assertNothingSent();
    }

    public function test_a_call_with_the_wrong_secret_is_refused(): void
    {
        Http::fake();

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'guessed')
            ->postJson('/api/telegram/webhook', $this->update('/start'))
            ->assertStatus(401);

        Http::assertNothingSent();
    }

    public function test_start_replies_with_the_menu(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->webhook($this->update('/start'))->assertStatus(200);

        $text = $this->replies()[0];
        $this->assertStringContainsString('Serene Darwin', $text);
        $this->assertStringContainsString('/undo', $text);
        // The bug that started all this: escapes must arrive as real newlines.
        $this->assertStringNotContainsString('\n', $text);
        $this->assertStringContainsString("\n", $text);
    }

    public function test_plain_text_is_logged_as_an_expense(): void
    {
        Http::fake([
            '*oauth2.googleapis.com/token' => Http::response(['access_token' => 'a-token']),
            '*/values/*:append*' => Http::response(['updates' => ['updatedRows' => 1]]),
            '*/values/*' => Http::response(['values' => [['Date', 'Amount', 'Description', 'Category']]]),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);

        $this->webhook($this->update('50k makan siang'))->assertStatus(200);

        $this->assertStringContainsString('Rp 50.000', $this->replies()[0]);
        Http::assertSent(fn($request) => str_contains($request->url(), 'A:E:append')
            && $request['values'][0][1] === 50000.0
            && $request['values'][0][2] === 'makan siang');
    }

    public function test_a_stranger_is_turned_away(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->webhook($this->update('/start', from: 999))->assertStatus(200);

        $this->assertStringContainsString('Unauthorized', $this->replies()[0]);
    }

    public function test_a_broken_command_still_answers_telegram_with_200(): void
    {
        // A non-2xx makes Telegram redeliver the same update forever.
        Http::fake([
            '*oauth2.googleapis.com/token' => Http::response('nope', 500),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);

        $this->webhook($this->update('/summary'))->assertStatus(200);

        $this->assertStringContainsString('Error mengambil data', $this->replies()[0]);
    }

    private function webhook(array $update)
    {
        return $this->withHeader('X-Telegram-Bot-Api-Secret-Token', self::SECRET)
            ->postJson('/api/telegram/webhook', $update);
    }

    private function update(string $text, int $from = self::OWNER): array
    {
        return ['update_id' => 1, 'message' => [
            'message_id' => 1,
            'from' => ['id' => $from, 'username' => 'zasbita'],
            'chat' => ['id' => $from],
            'text' => $text,
        ]];
    }

    /** @return string[] */
    private function replies(): array
    {
        return collect(Http::recorded())
            ->filter(fn($pair) => str_contains($pair[0]->url(), 'api.telegram.org'))
            ->map(fn($pair) => $pair[0]['text'])
            ->values()->all();
    }
}
