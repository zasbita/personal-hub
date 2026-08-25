<?php

namespace App\Console\Commands;

use App\Services\BotRouter;
use App\Services\TelegramService;
use Illuminate\Console\Command;

/**
 * Long-polling loop for local development. Production runs the webhook route
 * instead — Telegram allows a token only one delivery mode, and getUpdates
 * answers 409 Conflict while a webhook is registered.
 */
class TelegramBot extends Command
{
    protected $signature = 'bot:listen';

    protected $description = 'Listen for Telegram bot updates via long-polling (dev; production uses the webhook)';

    private int $offset = 0;

    public function handle(): int
    {
        $tg = new TelegramService;
        $oid = (int) config('services.telegram.owner_id');
        $router = new BotRouter($tg, $oid);
        $this->info("Bot listening... Owner ID: {$oid}");

        while (true) {
            try {
                $updates = $tg->getUpdates($this->offset, 30);
                foreach ($updates['result'] ?? [] as $u) {
                    $this->offset = $u['update_id'] + 1;
                    $router->handle($u);
                }
            } catch (\Exception $e) {
                $this->error("Error: {$e->getMessage()}");
                sleep(5);
            }
        }
    }
}
