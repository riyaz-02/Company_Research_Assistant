<?php

use App\Http\Controllers\AgentController;
use Illuminate\Support\Facades\Route;

Route::prefix('agent')->group(function () {
    Route::post('/message', [AgentController::class, 'message']);
});

