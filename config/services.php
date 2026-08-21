<?php

return [
    'supabase' => ['url' => env('SUPABASE_URL'), 'key' => env('SUPABASE_KEY')],
    'google' => ['sheet_id' => env('GOOGLE_SHEET_ID'), 'service_account_email' => env('GOOGLE_SERVICE_ACCOUNT_EMAIL'), 'private_key' => env('GOOGLE_PRIVATE_KEY')],
    'telegram' => ['bot_token' => env('BOT_TOKEN'), 'owner_id' => env('OWNER_ID')],
    'football' => ['api_key' => env('FOOTBALL_API_KEY')],
    'api_sports' => ['key' => env('API_SPORTS_KEY') ?: env('FOOTBALL_API_KEY')],
];
