<?php

use App\Http\Controllers\AgentController;
use Illuminate\Support\Facades\Route;

Route::prefix('agent')->group(function () {
    Route::post('/message', [AgentController::class, 'message']);
    Route::get('/plan', [AgentController::class, 'getPlan']);
    Route::post('/plan/section', [AgentController::class, 'updatePlanSection']);
    Route::post('/plan/regenerate', [AgentController::class, 'regenerateSection']);
    Route::get('/history', [AgentController::class, 'getHistory']);
    Route::delete('/history', [AgentController::class, 'clearHistory']);
});

