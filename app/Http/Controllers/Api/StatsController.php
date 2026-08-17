<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SheetsService;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    public function expenses(): JsonResponse
    {
        try {
            $s = new SheetsService();
            $all = $s->listExpenses();
            $start = now()->startOfMonth();
            $month = array_filter($all, fn($e) => new \DateTime($e['date']) >= $start);
            return response()->json(['total' => array_sum(array_column($month, 'amount')), 'count' => count($month)]);
        } catch (\Exception $e) { return response()->json(['total' => 0, 'count' => 0]); }
    }
}
