<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SheetsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $s = new SheetsService();
            $limit = min((int) $request->query('limit', 50), 100);
            $sorted = collect($s->listExpenses())->sortByDesc('date')->take($limit)->values()->all();
            return response()->json($sorted);
        } catch (\Exception $e) { return response()->json(['error' => 'Failed to fetch expenses'], 500); }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $s = new SheetsService();
            $exp = $s->findExpenseById($id);
            if (!$exp) return response()->json(['error' => 'Not found'], 404);
            $b = $request->json()->all();
            $s->updateExpenseRow($exp['row'], [$b['date'] ?? $exp['date'], $b['amount'] ?? $exp['amount'], $b['description'] ?? $exp['description'], $b['category'] ?? $exp['category'], $exp['id']]);
            return response()->json(['ok' => true]);
        } catch (\Exception $e) { return response()->json(['error' => 'Internal Server Error'], 500); }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $s = new SheetsService();
            $exp = $s->findExpenseById($id);
            if (!$exp) return response()->json(['error' => 'Not found'], 404);
            $s->deleteExpenseRow($exp['row']);
            return response()->json(['ok' => true]);
        } catch (\Exception $e) { return response()->json(['error' => 'Internal Server Error'], 500); }
    }
}
