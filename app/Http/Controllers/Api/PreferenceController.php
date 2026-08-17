<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SupabaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PreferenceController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $s = new SupabaseService();
            return response()->json($s->select('user_preferences', ['select' => '*', 'user_id' => 'eq.' . config('services.telegram.owner_id'), 'order' => 'created_at.desc']) ?? []);
        } catch (\Exception $e) { return response()->json([], 500); }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $s = new SupabaseService();
            $s->update('user_preferences', ['notification_enabled' => $request->json('notification_enabled', true)], ['id' => "eq.{$id}"]);
            return response()->json(['ok' => true]);
        } catch (\Exception $e) { return response()->json(['error' => 'Failed to update'], 500); }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $s = new SupabaseService();
            $s->delete('user_preferences', ['id' => "eq.{$id}"]);
            return response()->json(['ok' => true]);
        } catch (\Exception $e) { return response()->json(['error' => 'Failed to delete'], 500); }
    }
}
