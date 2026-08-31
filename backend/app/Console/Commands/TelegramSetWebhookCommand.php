<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramClient;
use Illuminate\Console\Command;

class TelegramSetWebhookCommand extends Command
{
    protected $signature = 'telegram:set-webhook';

    protected $description = 'Daftarkan URL webhook Telegram (POST /api/telegram/webhook) ke Bot API';

    public function handle(TelegramClient $telegram): int
    {
        if (! config('services.telegram.bot_token')) {
            $this->error('TELEGRAM_BOT_TOKEN belum diset di .env.');

            return self::FAILURE;
        }

        $url = rtrim(config('app.url'), '/').'/api/telegram/webhook';

        $result = $telegram->setWebhook($url, config('services.telegram.webhook_secret'));

        if (! ($result['ok'] ?? false)) {
            $this->error('Gagal set webhook: '.($result['description'] ?? 'unknown error'));

            return self::FAILURE;
        }

        $this->info("Webhook terdaftar: {$url}");

        return self::SUCCESS;
    }
}
