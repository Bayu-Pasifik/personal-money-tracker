<?php

use App\Http\Controllers\Api\AiController;
use App\Http\Controllers\Api\TelegramConnectController;
use App\Http\Controllers\Api\TelegramWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return response()->json(['status' => 'ok', 'app' => config('app.name')]);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/ai/parse-transaction', [AiController::class, 'parseTransaction']);
    Route::post('/telegram/connect-code', [TelegramConnectController::class, 'generateCode']);
});

Route::post('/telegram/webhook', TelegramWebhookController::class);
