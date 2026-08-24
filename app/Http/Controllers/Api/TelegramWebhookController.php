<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\{BotRouter, TelegramService};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        // The endpoint is public, so the shared secret is the only proof the
        // caller is Telegram. Registered via bot:webhook, echoed in this header.
        $secret = (string) config('services.telegram.webhook_secret', '');
        $sent = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');
        if ($secret === '' || !hash_equals($secret, $sent)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $router = new BotRouter(new TelegramService(), (int) config('services.telegram.owner_id'));
            $router->handle($request->json()->all());
        } catch (\Throwable $e) {
            Log::error("Telegram webhook failed: {$e->getMessage()}");
        }

        // Always 200 once the caller is trusted: any other status makes Telegram
        // redeliver the same update, and a failing command would loop forever.
        return response()->json(['ok' => true]);
    }
}
