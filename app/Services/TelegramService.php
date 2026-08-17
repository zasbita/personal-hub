<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TelegramService
{
    private string $token;

    public function __construct()
    {
        $this->token = config('services.telegram.bot_token', '');
    }

    public function sendMessage(int $chatId, string $text): array
    {
        $r = Http::post("https://api.telegram.org/bot{$this->token}/sendMessage", ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'Markdown']);
        return $r->json();
    }

    public function getUpdates(int $offset = 0, int $timeout = 30): array
    {
        $r = Http::timeout($timeout + 10)->get("https://api.telegram.org/bot{$this->token}/getUpdates", ['offset' => $offset, 'timeout' => $timeout]);
        if ($r->failed()) throw new \RuntimeException("Telegram getUpdates failed: {$r->body()}");
        return $r->json();
    }
}
