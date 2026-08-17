<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SupabaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $s = new SupabaseService();
            $d = $s->selectSingle('vehicle_service', ['select' => '*', 'user_id' => 'eq.' . config('services.telegram.owner_id')]);
            if (!$d) return response()->json(['remaining_km' => 0, 'status' => 'No data']);
            $rem = max(0, $d['next_service_km'] - $d['last_km']);
            return response()->json(['remaining_km' => $rem, 'last_km' => $d['last_km'], 'next_service_km' => $d['next_service_km'], 'status' => $rem <= 500 ? '⚠️ Service Due Soon' : '✅ Good Condition']);
        } catch (\Exception $e) { return response()->json(['remaining_km' => 0, 'status' => 'Error loading data']); }
    }

    public function update(Request $request): JsonResponse
    {
        try {
            $b = $request->json()->all();
            $u = [];
            if (isset($b['last_km'])) { $v = (int) $b['last_km']; if ($v < 0) return response()->json(['error' => 'Invalid last_km'], 400); $u['last_km'] = $v; }
            if (isset($b['next_service_km'])) { $v = (int) $b['next_service_km']; if ($v < 0) return response()->json(['error' => 'Invalid next_service_km'], 400); $u['next_service_km'] = $v; }
            if (empty($u)) return response()->json(['error' => 'No valid fields to update'], 400);
            $s = new SupabaseService();
            $oid = config('services.telegram.owner_id');
            $s->update('vehicle_service', $u, ['user_id' => "eq.{$oid}"]);
            $d = $s->selectSingle('vehicle_service', ['select' => 'last_km,next_service_km', 'user_id' => "eq.{$oid}"]);
            $rem = max(0, $d['next_service_km'] - $d['last_km']);
            return response()->json(['remaining_km' => $rem, 'last_km' => $d['last_km'], 'next_service_km' => $d['next_service_km'], 'status' => $rem <= 500 ? '⚠️ Service Due Soon' : '✅ Good Condition']);
        } catch (\Exception $e) { return response()->json(['error' => 'Internal Server Error'], 500); }
    }
}
