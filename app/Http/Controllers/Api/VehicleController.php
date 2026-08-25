<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SupabaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VehicleController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $s = new SupabaseService;
            $d = $s->selectSingle('vehicle_service', ['select' => '*', 'user_id' => 'eq.'.config('services.telegram.owner_id')]);
            if (! $d) {
                return response()->json(['remaining_km' => 0, 'status' => 'No data']);
            }
            $rem = max(0, $d['next_service_km'] - $d['last_km']);

            return response()->json(['remaining_km' => $rem, 'last_km' => $d['last_km'], 'next_service_km' => $d['next_service_km'], 'status' => $rem <= 500 ? '⚠️ Service Due Soon' : '✅ Good Condition']);
        } catch (\Exception $e) {
            Log::error("Vehicle status failed: {$e->getMessage()}");

            return response()->json(['error' => 'Failed to fetch vehicle status'], 500);
        }
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'last_km' => ['sometimes', 'integer', 'min:0', 'max:9999999'],
            'next_service_km' => ['sometimes', 'integer', 'min:0', 'max:9999999'],
        ]);
        if (empty($validated)) {
            return response()->json(['error' => 'No valid fields to update'], 400);
        }
        try {
            $u = $validated;
            $s = new SupabaseService;
            $oid = config('services.telegram.owner_id');
            $s->update('vehicle_service', $u, ['user_id' => "eq.{$oid}"]);
            $d = $s->selectSingle('vehicle_service', ['select' => 'last_km,next_service_km', 'user_id' => "eq.{$oid}"]);
            $rem = max(0, $d['next_service_km'] - $d['last_km']);

            return response()->json(['remaining_km' => $rem, 'last_km' => $d['last_km'], 'next_service_km' => $d['next_service_km'], 'status' => $rem <= 500 ? '⚠️ Service Due Soon' : '✅ Good Condition']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Internal Server Error'], 500);
        }
    }

    // Multi-vehicle — ponytail: keep legacy /vehicle for single, /vehicles for many
    public function list(): JsonResponse
    {
        try {
            $s = new SupabaseService;
            $data = $s->select('vehicles', ['select' => '*', 'user_id' => 'eq.'.config('services.telegram.owner_id'), 'order' => 'created_at.asc']);

            return response()->json($data ?? []);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch'], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'last_km' => ['required', 'integer', 'min:0', 'max:9999999'],
            'next_service_km' => ['required', 'integer', 'min:0', 'max:9999999'],
            'service_interval' => ['sometimes', 'integer', 'min:500', 'max:20000'],
        ]);

        try {
            $s = new SupabaseService;
            $row = $s->insert('vehicles', [
                'user_id' => config('services.telegram.owner_id'),
                'name' => $validated['name'],
                'last_km' => $validated['last_km'],
                'next_service_km' => $validated['next_service_km'],
                'service_interval' => $validated['service_interval'] ?? 2000,
            ]);

            return response()->json($row[0] ?? $row, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create'], 500);
        }
    }

    public function updateOne(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:50'],
            'last_km' => ['sometimes', 'integer', 'min:0', 'max:9999999'],
            'next_service_km' => ['sometimes', 'integer', 'min:0', 'max:9999999'],
        ]);
        if (empty($validated)) {
            return response()->json(['error' => 'No valid fields'], 400);
        }
        try {
            $s = new SupabaseService;
            $before = $s->selectSingle('vehicles', ['select' => 'last_km', 'id' => "eq.{$id}"]);
            $s->update('vehicles', $validated, ['id' => "eq.{$id}"]);
            // log history if km changed
            if (isset($validated['last_km']) && $before && (int) $before['last_km'] !== (int) $validated['last_km']) {
                try {
                    $s->insert('service_logs', ['vehicle_id' => $id, 'old_km' => $before['last_km'], 'new_km' => $validated['last_km']]);
                } catch (\Throwable $e) {
                    // ignore log failure
                }
            }
            $d = $s->selectSingle('vehicles', ['select' => '*', 'id' => "eq.{$id}"]);

            return response()->json($d);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update'], 500);
        }
    }

    public function destroyOne(string $id): JsonResponse
    {
        try {
            $s = new SupabaseService;
            $s->delete('vehicles', ['id' => "eq.{$id}"]);

            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete'], 500);
        }
    }
}
