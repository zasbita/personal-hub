<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SupabaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryBudgetController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $s = new SupabaseService;
            $data = $s->select('category_budgets', ['select' => '*', 'user_id' => 'eq.'.config('services.telegram.owner_id'), 'order' => 'category.asc']);
            if (empty($data)) {
                return response()->json(['error' => 'No budgets', 'data' => []], 404);
            }

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch', 'data' => []], 404);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['required', 'string', 'max:30'],
            'monthly_limit' => ['required', 'numeric', 'min:0', 'max:100000000'],
        ]);

        try {
            $s = new SupabaseService;
            $oid = config('services.telegram.owner_id');
            // upsert by user_id+category to avoid duplicates
            $existing = $s->select('category_budgets', ['select' => 'id', 'user_id' => "eq.{$oid}", 'category' => "eq.{$validated['category']}"]);
            if (! empty($existing)) {
                $s->update('category_budgets', ['monthly_limit' => $validated['monthly_limit']], ['id' => "eq.{$existing[0]['id']}"]);

                return response()->json($s->selectSingle('category_budgets', ['select' => '*', 'id' => "eq.{$existing[0]['id']}"]), 200);
            }
            $row = $s->insert('category_budgets', ['user_id' => $oid, 'category' => $validated['category'], 'monthly_limit' => $validated['monthly_limit']]);

            return response()->json($row[0] ?? $row, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create budget'], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'monthly_limit' => ['required', 'numeric', 'min:0', 'max:100000000'],
        ]);

        try {
            $s = new SupabaseService;
            $s->update('category_budgets', ['monthly_limit' => $validated['monthly_limit']], ['id' => "eq.{$id}"]);

            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update'], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $s = new SupabaseService;
            $s->delete('category_budgets', ['id' => "eq.{$id}"]);

            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete'], 500);
        }
    }
}
