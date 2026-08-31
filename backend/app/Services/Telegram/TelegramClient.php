<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Wrapper tipis di atas Telegram Bot API. Webhook-based (bukan long-polling)
 * sesuai catatan arsitektur PRD.md §8 — cocok untuk shared hosting.
 */
class TelegramClient
{
    private function baseUrl(): string
    {
        return 'https://api.telegram.org/bot'.config('services.telegram.bot_token').'/';
    }

    public function sendMessage(string $chatId, string $text): void
    {
        if (! config('services.telegram.bot_token')) {
            Log::warning('TELEGRAM_BOT_TOKEN belum diset, sendMessage dilewati.', ['chat_id' => $chatId]);

            return;
        }

        $response = Http::asJson()->post($this->baseUrl().'sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
        ]);

        if ($response->failed()) {
            Log::error('Gagal mengirim pesan Telegram', [
                'chat_id' => $chatId,
                'response' => $response->body(),
            ]);
        }
    }

    public function setWebhook(string $url, ?string $secretToken = null): array
    {
        return Http::asJson()->post($this->baseUrl().'setWebhook', array_filter([
            'url' => $url,
            'secret_token' => $secretToken,
        ]))->json();
    }
}
