<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\AchievementController;
use App\Http\Controllers\PvpController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('battleship');
});

// Auth Routes (Session-based)
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/auth/me', [AuthController::class, 'me']);
Route::post('/auth/logout', [AuthController::class, 'logout']);

// Game Routes
Route::post('/api/games', [GameController::class, 'create']);
Route::post('/api/games/{game}/fire', [GameController::class, 'fire']);
Route::get('/api/leaderboard', [GameController::class, 'getLeaderboard']);
Route::post('/api/games/{game}/save-score', [GameController::class, 'saveScore']);

// Shop Routes
Route::post('/api/shop/buy', [ShopController::class, 'buy']);
Route::post('/api/shop/buy-gem', [ShopController::class, 'buyWithGems']);
Route::post('/api/shop/reset-restock', [ShopController::class, 'resetDailyRestock']);
Route::post('/api/games/{game}/use-powerup', [ShopController::class, 'use']);

// Achievement Routes
Route::get('/api/achievements', [AchievementController::class, 'index']);
Route::post('/api/achievements/claim', [AchievementController::class, 'claim']);

// PVP Routes
Route::post('/api/pvp/create', [PvpController::class, 'createRoom']);
Route::post('/api/pvp/join', [PvpController::class, 'joinRoom']);
Route::post('/api/pvp/ready', [PvpController::class, 'ready']);
Route::post('/api/pvp/fire', [PvpController::class, 'fire']);
Route::post('/api/pvp/use-powerup', [PvpController::class, 'useSkill']);