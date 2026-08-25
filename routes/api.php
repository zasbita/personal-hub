<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryBudgetController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\FuelLogController;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\PreferenceController;
use App\Http\Controllers\Api\RecurringExpenseController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\TelegramWebhookController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Middleware\SupabaseAuth;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok', 'time' => now()->toIso8601String()]));

Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/auth/logout', [AuthController::class, 'logout']);

// Telegram calls this; it authenticates itself with the webhook secret, not a session.
Route::post('/telegram/webhook', TelegramWebhookController::class);

Route::middleware(SupabaseAuth::class)->group(function () {
    Route::get('/stats/expenses', [StatsController::class, 'expenses']);
    Route::get('/expenses/export', [ExpenseController::class, 'export']);
    Route::get('/expenses', [ExpenseController::class, 'index']);
    Route::patch('/expenses/{id}', [ExpenseController::class, 'update']);
    Route::delete('/expenses/{id}', [ExpenseController::class, 'destroy']);
    Route::get('/vehicle', [VehicleController::class, 'index']);
    Route::patch('/vehicle', [VehicleController::class, 'update']);
    Route::get('/vehicles', [VehicleController::class, 'list']);
    Route::post('/vehicles', [VehicleController::class, 'store']);
    Route::patch('/vehicles/{id}', [VehicleController::class, 'updateOne']);
    Route::delete('/vehicles/{id}', [VehicleController::class, 'destroyOne']);
    Route::get('/vehicles/{vehicleId}/fuel-logs', [FuelLogController::class, 'index']);
    Route::post('/vehicles/{vehicleId}/fuel-logs', [FuelLogController::class, 'store']);
    Route::delete('/vehicles/{vehicleId}/fuel-logs/{id}', [FuelLogController::class, 'destroy']);
    Route::get('/matches/export.ics', [MatchController::class, 'exportIcs']);
    Route::get('/matches', [MatchController::class, 'index']);
    Route::post('/matches', [MatchController::class, 'store']);
    Route::get('/preferences', [PreferenceController::class, 'index']);
    Route::patch('/preferences/{id}', [PreferenceController::class, 'update']);
    Route::delete('/preferences/{id}', [PreferenceController::class, 'destroy']);
    Route::get('/category-budgets', [CategoryBudgetController::class, 'index']);
    Route::post('/category-budgets', [CategoryBudgetController::class, 'store']);
    Route::patch('/category-budgets/{id}', [CategoryBudgetController::class, 'update']);
    Route::delete('/category-budgets/{id}', [CategoryBudgetController::class, 'destroy']);
    Route::get('/recurring-expenses', [RecurringExpenseController::class, 'index']);
    Route::post('/recurring-expenses', [RecurringExpenseController::class, 'store']);
    Route::delete('/recurring-expenses/{id}', [RecurringExpenseController::class, 'destroy']);
});
