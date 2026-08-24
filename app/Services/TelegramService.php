<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private string $token;

    public function __construct()
    {
        $this->token = config('services.telegram.bot_token', '');
    }

    public function sendMessage(int $chatId, string $text): array
    {
        $r = $this->post($chatId, $text, 'Markdown');
        // Telegram rejects the whole message when text it cannot parse slips in
        // (an underscore in an expense description is enough), so retry plain.
        if ($r->failed()) {
            $r = $this->post($chatId, $text, null);
            if ($r->failed()) Log::warning("Telegram sendMessage failed: {$r->body()}");
        }
        return $r->json() ?? [];
    }

    public function getUpdates(int $offset = 0, int $timeout = 30): array
    {
        $r = Http::timeout($timeout + 10)->get("https://api.telegram.org/bot{$this->token}/getUpdates", ['offset' => $offset, 'timeout' => $timeout]);
        if ($r->failed()) throw new \RuntimeException("Telegram getUpdates failed: {$r->body()}");
        return $r->json();
    }

    /**
     * Point Telegram at $url and have it prove itself with $secret on every call.
     * Registering a webhook also stops getUpdates working, by Telegram's design.
     */
    public function setWebhook(string $url, string $secret): array
    {
        $r = Http::timeout(15)->post("https://api.telegram.org/bot{$this->token}/setWebhook", [
            'url' => $url,
            'secret_token' => $secret,
            'allowed_updates' => ['message'],
        ]);
        if ($r->failed()) throw new \RuntimeException("Telegram setWebhook failed: {$r->body()}");
        return $r->json() ?? [];
    }

    public function getWebhookInfo(): array
    {
        $r = Http::timeout(15)->get("https://api.telegram.org/bot{$this->token}/getWebhookInfo");
        if ($r->failed()) throw new \RuntimeException("Telegram getWebhookInfo failed: {$r->body()}");
        return $r->json()['result'] ?? [];
    }

    /**
     * Publish the "/" menu Telegram renders in its own client.
     * @param array<string,string> $commands command => description
     */
    public function setCommands(array $commands): void
    {
        $list = [];
        foreach ($commands as $command => $description) { $list[] = ['command' => $command, 'description' => $description]; }
        $r = Http::timeout(10)->post("https://api.telegram.org/bot{$this->token}/setMyCommands", ['commands' => $list]);
        if ($r->failed()) Log::warning("Telegram setMyCommands failed: {$r->body()}");
    }

    private function post(int $chatId, string $text, ?string $parseMode): Response
    {
        $payload = ['chat_id' => $chatId, 'text' => $text];
        if ($parseMode !== null) $payload['parse_mode'] = $parseMode;
        return Http::timeout(10)->post("https://api.telegram.org/bot{$this->token}/sendMessage", $payload);
    }
}
