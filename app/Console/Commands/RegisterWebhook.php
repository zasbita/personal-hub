<?php

namespace App\Console\Commands;

use App\Services\{BotRouter, TelegramService};
use Illuminate\Console\Command;

/**
 * Hands the bot over to this app. Run once after deploying, and again whenever
 * the menu or the URL changes — Telegram remembers both until told otherwise.
 */
class RegisterWebhook extends Command
{
    protected $signature = 'bot:webhook {url? : Defaults to APP_URL + /api/telegram/webhook} {--show : Only print who currently owns the bot}';
    protected $description = 'Register the Telegram webhook for this app and publish the command menu';

    public function handle(): int
    {
        $tg = new TelegramService();

        if ($this->option('show')) {
            $info = $tg->getWebhookInfo();
            $this->line('url: ' . ($info['url'] ?: '(none — long polling)'));
            $this->line('pending updates: ' . ($info['pending_update_count'] ?? 0));
            if (!empty($info['last_error_message'])) $this->warn("last error: {$info['last_error_message']}");
            return 0;
        }

        $secret = (string) config('services.telegram.webhook_secret', '');
        if (strlen($secret) < 16) {
            $this->error('TELEGRAM_WEBHOOK_SECRET missing or shorter than 16 chars. Without it the endpoint refuses every call.');
            return 1;
        }

        $url = $this->argument('url') ?: rtrim((string) config('app.url'), '/') . '/api/telegram/webhook';
        if (!str_starts_with($url, 'https://')) {
            $this->error("Telegram only calls https endpoints; got {$url}");
            return 1;
        }

        $tg->setWebhook($url, $secret);
        $tg->setCommands(BotRouter::MENU);
        $this->info("Webhook set to {$url}");
        $this->info('Command menu published: /' . implode(', /', array_keys(BotRouter::MENU)));
        $this->warn('Any other process serving this token (an old Worker, a stray bot:listen) is now bypassed — retire it.');
        return 0;
    }
}
