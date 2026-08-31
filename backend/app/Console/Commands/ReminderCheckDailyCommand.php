<?php

namespace App\Console\Commands;

use App\Models\ReminderLog;
use App\Models\User;
use App\Services\Telegram\TelegramClient;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

/**
 * PRD.md §6.4 (FR-4.1—4.3) & §10 Alur 3. Dijadwalkan tiap menit lewat Laravel
 * Scheduler (lihat routes/console.php) — di dalamnya hanya user yang jam
 * `reminder_time`-nya cocok dengan menit berjalan yang diproses, supaya tiap
 * user bisa punya jam reminder sendiri walau cron cPanel cuma satu baris.
 *
 * Idempotent lewat unique index (user_id, date) di reminder_logs: kalau
 * command jalan dobel di menit yang sama (retry/restart/concurrent cron),
 * percobaan insert kedua akan gagal kena constraint dan dilewati saja,
 * bukan mengirim reminder dua kali.
 */
class ReminderCheckDailyCommand extends Command
{
    protected $signature = 'reminder:check-daily';

    protected $description = 'Cek transaksi hari ini per user, kirim reminder Telegram jika kosong (idempotent)';

    public function handle(TelegramClient $telegram): int
    {
        $now = Carbon::now();
        $today = $now->toDateString();

        $processed = 0;

        $users = User::whereNotNull('telegram_chat_id')->get();

        foreach ($users as $user) {
            if ($user->reminder_time->format('H:i') !== $now->format('H:i')) {
                continue;
            }

            $alreadyProcessed = ReminderLog::where('user_id', $user->id)
                ->whereDate('date', $today)
                ->exists();

            if ($alreadyProcessed) {
                continue;
            }

            try {
                $log = ReminderLog::create([
                    'user_id' => $user->id,
                    'date' => $today,
                    'was_needed' => false,
                ]);
            } catch (QueryException) {
                // Constraint unik (user_id, date) kena race dari proses lain — sudah ditangani, skip.
                continue;
            }

            $hasTransactionToday = $user->transactions()
                ->whereDate('transaction_date', $today)
                ->exists();

            if ($hasTransactionToday) {
                $log->update(['was_needed' => false]);

                continue;
            }

            $telegram->sendMessage(
                $user->telegram_chat_id,
                'Belum ada catatan hari ini — ketik pengeluaranmu di Telegram, langsung tersimpan.',
            );

            $log->update(['was_needed' => true, 'sent_at' => now()]);
            $processed++;
        }

        $this->info("Reminder check selesai. {$processed} reminder terkirim.");

        return self::SUCCESS;
    }
}
