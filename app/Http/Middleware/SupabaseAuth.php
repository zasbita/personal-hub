<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the dashboard API with the Supabase session cookie set by AuthController.
 * The token is opaque to us, so Supabase is asked to vouch for it. Positive
 * answers are cached briefly — one page load fans out into several API calls and
 * each would otherwise cost an auth round trip.
 */
class SupabaseAuth
{
    private const CACHE_TTL = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->cookie('sb_access_token');
        if (! is_string($token) || $token === '' || ! $this->valid($token)) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        return $next($request);
    }

    private function valid(string $token): bool
    {
        $key = 'sb.token.'.hash('sha256', $token);
        if (Cache::get($key)) {
            return true;
        }

        $r = Http::withHeaders(['apikey' => config('services.supabase.key', '')])
            ->withToken($token)
            ->timeout(10)
            ->get(config('services.supabase.url', '').'/auth/v1/user');

        // Rejections are not cached: a good token must not stay locked out
        // because Supabase had a blip.
        if (! $r->successful() || empty($r->json()['id'])) {
            return false;
        }

        Cache::put($key, true, self::CACHE_TTL);

        return true;
    }
}
