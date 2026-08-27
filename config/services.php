<?php

return [
    'supabase' => ['url' => env('SUPABASE_URL'), 'key' => env('SUPABASE_KEY')],
    'google' => ['sheet_id' => env('GOOGLE_SHEET_ID'), 'service_account_email' => env('GOOGLE_SERVICE_ACCOUNT_EMAIL'), 'private_key' => env('GOOGLE_PRIVATE_KEY')],
    'telegram' => ['bot_token' => env('BOT_TOKEN'), 'owner_id' => env('OWNER_ID'), 'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET')],
    'football' => ['api_key' => env('FOOTBALL_API_KEY')],
    // 0 disables the budget line entirely.
    'budget' => ['monthly' => env('MONTHLY_BUDGET', 0)],
    'api_sports' => ['key' => env('API_SPORTS_KEY') ?: env('FOOTBALL_API_KEY')],
    'mpl' => ['url' => env('MPL_API_URL'), 'key' => env('MPL_API_KEY')],
    'futsal' => ['url' => env('FUTSAL_API_URL', 'https://en.wikipedia.org/wiki/Indonesia_national_futsal_team')],
];
