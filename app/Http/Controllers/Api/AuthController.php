<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $url = config('services.supabase.url');
        $key = config('services.supabase.key');
        $r = Http::withHeaders(['apikey' => $key, 'Content-Type' => 'application/json'])
            ->post("{$url}/auth/v1/token?grant_type=password", ['email' => $request->email, 'password' => $request->password]);
        if ($r->failed() || !isset($r->json()['access_token'])) return response()->json(['error' => 'Invalid email or password'], 401);
        $d = $r->json();
        $expires = $d['expires_in'] ?? 3600;
        return response()->json(['user' => ['id' => $d['user']['id'], 'email' => $d['user']['email'] ?? '']])
            ->withCookie(cookie('sb_access_token', $d['access_token'], $expires, '/', null, false, true, false, 'strict'))
            ->withCookie(cookie('sb_refresh_token', $d['refresh_token'], $expires, '/', null, false, true, false, 'strict'));
    }

    public function logout(): JsonResponse
    {
        return response()->json(['ok' => true])->withoutCookie('sb_access_token')->withoutCookie('sb_refresh_token');
    }
}
