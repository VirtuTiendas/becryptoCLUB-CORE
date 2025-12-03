<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::middleware(['throttle:api', 'cache.headers:public;max_age=30'])->group(function () {
        Route::get('/markets', fn () => response()->json(['message' => 'Market endpoints placeholder']));
        Route::get('/mining', fn () => response()->json(['message' => 'Mining endpoints placeholder']));
        Route::get('/miners', fn () => response()->json(['message' => 'Miner leaderboard placeholder']));
    });

    Route::middleware(['auth:sanctum', 'throttle:private_api'])->group(function () {
        Route::get('/ai-insights', fn () => response()->json(['message' => 'Private AI insights placeholder']));
        Route::get('/rigs', fn () => response()->json(['message' => 'Private rigs placeholder']));
    });
});
