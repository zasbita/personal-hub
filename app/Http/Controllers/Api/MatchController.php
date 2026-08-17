<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SupabaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MatchController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $s = new SupabaseService();
            return response()->json($s->select('match_schedule', ['select' => '*', 'match_time' => 'gte.' . now()->toIso8601String(), 'order' => 'match_time.asc', 'limit' => 10]) ?? []);
        } catch (\Exception $e) { return response()->json([]); }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $b = $request->json()->all();
            if (empty($b['sport_type']) || empty($b['entity_name']) || empty($b['match_time'])) return response()->json(['error' => 'sport_type, entity_name, match_time required'], 400);
            $s = new SupabaseService();
            $r = $s->insert('match_schedule', ['sport_type' => $b['sport_type'], 'entity_name' => $b['entity_name'], 'match_time' => \Carbon\Carbon::parse($b['match_time'])->toIso8601String(), 'competition' => $b['tournament'] ?? null, 'home_team' => $b['home_team'] ?? null, 'away_team' => $b['away_team'] ?? null]);
            return response()->json($r[0] ?? $r, 201);
        } catch (\Exception $e) { return response()->json(['error' => 'Internal server error'], 500); }
    }
}
