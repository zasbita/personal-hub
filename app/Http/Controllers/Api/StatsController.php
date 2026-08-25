<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SheetsService;
use App\Services\SupabaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class StatsController extends Controller
{
    public function expenses(): JsonResponse
    {
        try {
            $s = new SheetsService;
            $r = $s->getExpensesSince(now()->startOfMonth());
            // daily totals for current month
            $daily = [];
            foreach ($r['items'] as $it) {
                $d = substr($it['date'], 0, 10);
                $daily[$d] = ($daily[$d] ?? 0) + $it['amount'];
            }
            ksort($daily);
            $dailyList = array_map(fn ($date, $total) => ['date' => $date, 'total' => $total], array_keys($daily), $daily);

            // ponytail: fetch category budgets best-effort, stats still useful if Supabase down
            $budgets = [];
            try {
                $rows = (new SupabaseService)->select('category_budgets', ['select' => 'category,monthly_limit', 'user_id' => 'eq.'.config('services.telegram.owner_id')]);
                foreach ($rows as $row) {
                    $budgets[$row['category']] = (float) $row['monthly_limit'];
                }
            } catch (\Throwable $e) {
                // ignore — budgets just won't show
            }

            return response()->json([
                'total' => $r['total'],
                'count' => count($r['items']),
                'byCategory' => $r['byCategory'],
                'daily' => $dailyList,
                'budgets' => $budgets,
            ]);
        } catch (\Exception $e) {
            // Reporting zero spend for a Sheets outage reads as a real answer.
            Log::error("Expense stats failed: {$e->getMessage()}");

            return response()->json(['error' => 'Failed to fetch stats'], 500);
        }
    }
}
