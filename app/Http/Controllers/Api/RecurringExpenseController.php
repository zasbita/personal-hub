<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SupabaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecurringExpenseController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $s = new SupabaseService;
            $data = $s->select('recurring_expenses', ['select' => '*', 'user_id' => 'eq.'.config('services.telegram.owner_id'), 'order' => 'day_of_month.asc']);
            if (empty($data)) {
                return response()->json(['error' => 'No recurring expenses', 'data' => []], 404);
            }

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch', 'data' => []], 404);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'description' => ['required', 'string', 'max:200'],
            'category' => ['nullable', 'string', 'max:30'],
            'day_of_month' => ['required', 'integer', 'min:1', 'max:31'],
        ]);

        try {
            $s = new SupabaseService;
            $row = $s->insert('recurring_expenses', [
                'user_id' => config('services.telegram.owner_id'),
                'amount' => $validated['amount'],
                'description' => $validated['description'],
                'category' => $validated['category'] ?? 'General',
                'day_of_month' => $validated['day_of_month'],
            ]);

            return response()->json($row[0] ?? $row, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create'], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $s = new SupabaseService;
            $s->delete('recurring_expenses', ['id' => "eq.{$id}"]);

            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete'], 500);
        }
    }
}
