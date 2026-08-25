<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SupabaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FuelLogController extends Controller
{
    public function index(string $vehicleId): JsonResponse
    {
        try {
            $s = new SupabaseService;
            $data = $s->select('fuel_logs', ['select' => '*', 'vehicle_id' => "eq.{$vehicleId}", 'order' => 'km.desc', 'limit' => 50]);

            return response()->json($data ?? []);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch'], 500);
        }
    }

    public function store(Request $request, string $vehicleId): JsonResponse
    {
        $validated = $request->validate([
            'km' => ['required', 'integer', 'min:0', 'max:9999999'],
            'liters' => ['required', 'numeric', 'min:0.1', 'max:1000'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
        ]);

        try {
            $s = new SupabaseService;
            // ensure vehicle exists and belongs to owner — best-effort
            $veh = $s->selectSingle('vehicles', ['select' => 'id,last_km', 'id' => "eq.{$vehicleId}"]);
            if (! $veh) {
                return response()->json(['error' => 'Vehicle not found'], 404);
            }
            $row = $s->insert('fuel_logs', [
                'vehicle_id' => $vehicleId,
                'user_id' => config('services.telegram.owner_id'),
                'km' => $validated['km'],
                'liters' => $validated['liters'],
                'cost' => $validated['cost'] ?? null,
            ]);
            // update vehicle last_km if bigger
            if ((int) $validated['km'] > (int) ($veh['last_km'] ?? 0)) {
                $s->update('vehicles', ['last_km' => $validated['km']], ['id' => "eq.{$vehicleId}"]);
            }

            return response()->json($row[0] ?? $row, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create'], 500);
        }
    }

    public function destroy(string $vehicleId, string $id): JsonResponse
    {
        try {
            $s = new SupabaseService;
            $s->delete('fuel_logs', ['id' => "eq.{$id}", 'vehicle_id' => "eq.{$vehicleId}"]);

            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete'], 500);
        }
    }
}
