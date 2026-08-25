<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SheetsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $s = new SheetsService;
            $limit = min((int) $request->query('limit', 50), 100);
            $sorted = collect($s->listExpenses())->sortByDesc('date')->take($limit)->values()->all();

            return response()->json($sorted);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch expenses'], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['sometimes', 'date'],
            'amount' => ['sometimes', 'numeric', 'min:0', 'max:100000000'],
            'description' => ['sometimes', 'string', 'max:200'],
            'category' => ['sometimes', 'nullable', 'string', 'max:30'],
        ]);

        try {
            $s = new SheetsService;
            $exp = $s->findExpenseById($id);
            if (! $exp) {
                return response()->json(['error' => 'Not found'], 404);
            }
            $b = array_merge($exp, $validated);
            // Preserve id from original row; validated keys override date/amount/description/category
            $s->updateExpenseRow($exp['row'], [$b['date'], $b['amount'], $b['description'], $b['category'] ?? $exp['category'], $exp['id']]);

            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Internal Server Error'], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $s = new SheetsService;
            $exp = $s->findExpenseById($id);
            if (! $exp) {
                return response()->json(['error' => 'Not found'], 404);
            }
            $s->deleteExpenseRow($exp['row']);

            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Internal Server Error'], 500);
        }
    }

    public function export(): StreamedResponse
    {
        $rows = (new SheetsService)->listExpenses();
        $filename = 'expenses-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['date', 'amount', 'description', 'category', 'id']);
            foreach ($rows as $r) {
                fputcsv($out, [$r['date'], $r['amount'], $r['description'], $r['category'], $r['id']]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
