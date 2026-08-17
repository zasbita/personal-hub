<?php

use App\Http\Controllers\Api\{AuthController, ExpenseController, MatchController, PreferenceController, StatsController, VehicleController};
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/logout', [AuthController::class, 'logout']);
Route::get('/stats/expenses', [StatsController::class, 'expenses']);
Route::get('/expenses', [ExpenseController::class, 'index']);
Route::patch('/expenses/{id}', [ExpenseController::class, 'update']);
Route::delete('/expenses/{id}', [ExpenseController::class, 'destroy']);
Route::get('/vehicle', [VehicleController::class, 'index']);
Route::patch('/vehicle', [VehicleController::class, 'update']);
Route::get('/matches', [MatchController::class, 'index']);
Route::post('/matches', [MatchController::class, 'store']);
Route::get('/preferences', [PreferenceController::class, 'index']);
Route::patch('/preferences/{id}', [PreferenceController::class, 'update']);
Route::delete('/preferences/{id}', [PreferenceController::class, 'destroy']);
