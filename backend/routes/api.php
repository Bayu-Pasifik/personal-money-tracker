<?php

use App\Http\Controllers\Api\AdvisoryController;
use App\Http\Controllers\Api\AiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\SummaryController;
use App\Http\Controllers\Api\TelegramConnectController;
use App\Http\Controllers\Api\TelegramWebhookController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return response()->json(['status' => 'ok', 'app' => config('app.name')]);
});

Route::post('/login', [AuthController::class, 'login']);
Route::post('/telegram/webhook', TelegramWebhookController::class);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/ai/parse-transaction', [AiController::class, 'parseTransaction']);
    Route::post('/advisory/ask', [AdvisoryController::class, 'ask']);
    Route::post('/telegram/connect-code', [TelegramConnectController::class, 'generateCode']);

    Route::apiResource('transactions', TransactionController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::apiResource('categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('/summary', SummaryController::class);
});
