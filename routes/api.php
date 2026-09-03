<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GameController;

Route::post('/games', [GameController::class, 'create']);
Route::post('/games/{game}/fire', [GameController::class, 'fire']);

Route::get('/leaderboard', [GameController::class, 'getLeaderboard']);
Route::post('/games/{game}/save-score', [GameController::class, 'saveScore']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
