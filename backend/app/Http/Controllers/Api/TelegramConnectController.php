<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TelegramConnectController extends Controller
{
    private const TTL_MINUTES = 10;

    /**
     * Bikin kode koneksi sekali pakai yang ditukar user via /start KODE di bot,
     * supaya telegram_chat_id terverifikasi milik akun yang sedang login.
     */
    public function generateCode(Request $request): JsonResponse
    {
        $code = strtoupper(Str::random(6));

        Cache::put("telegram_connect:{$code}", $request->user()->id, now()->addMinutes(self::TTL_MINUTES));

        return response()->json([
            'code' => $code,
            'expires_in_minutes' => self::TTL_MINUTES,
        ]);
    }
}
