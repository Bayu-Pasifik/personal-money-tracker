<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Transaction;
use App\Services\Telegram\TelegramClient;
use Illuminate\Support\Carbon;

/**
 * PRD.md FR-4.4 / Task.md Fase 3: notifikasi early-warning saat pengeluaran
 * kategori mendekati (>=80%) atau melewati (>=100%) limit budget bulanan.
 * Dipanggil lewat TransactionObserver setiap transaksi baru tersimpan,
 * dari sumber manapun (Telegram atau web).
 */
class BudgetWarningService
{
    private const APPROACHING_THRESHOLD = 0.8;

    public function __construct(private readonly TelegramClient $telegram) {}

    public function checkAndNotify(Transaction $transaction): void
    {
        if ($transaction->type !== 'expense') {
            return;
        }

        $user = $transaction->user;

        if (! $user->telegram_chat_id) {
            return;
        }

        $monthStart = Carbon::parse($transaction->transaction_date)->startOfMonth();

        $budget = Budget::where('user_id', $transaction->user_id)
            ->where('category_id', $transaction->category_id)
            ->whereDate('month', $monthStart)
            ->first();

        if (! $budget || $budget->limit_amount <= 0) {
            return;
        }

        $spentAfter = Transaction::where('user_id', $transaction->user_id)
            ->where('category_id', $transaction->category_id)
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [$monthStart, $monthStart->copy()->endOfMonth()])
            ->sum('amount');

        $spentBefore = $spentAfter - $transaction->amount;

        $ratioBefore = $spentBefore / $budget->limit_amount;
        $ratioAfter = $spentAfter / $budget->limit_amount;

        // Hanya kirim sekali per kali "melewati ambang" — bukan tiap transaksi
        // sesudahnya — supaya tidak spam notifikasi tiap hari (StyleGuide §7).
        if ($ratioBefore < 1.0 && $ratioAfter >= 1.0) {
            $this->telegram->sendMessage(
                $user->telegram_chat_id,
                "⚠️ Pengeluaran kategori {$transaction->category->name} bulan ini sudah melewati limit budget Rp".number_format($budget->limit_amount, 0, ',', '.').' — total sekarang Rp'.number_format((int) $spentAfter, 0, ',', '.').'.',
            );
        } elseif ($ratioBefore < self::APPROACHING_THRESHOLD && $ratioAfter >= self::APPROACHING_THRESHOLD) {
            $this->telegram->sendMessage(
                $user->telegram_chat_id,
                "Pengeluaran kategori {$transaction->category->name} bulan ini sudah mendekati limit budget (".round($ratioAfter * 100)."% dari Rp".number_format($budget->limit_amount, 0, ',', '.').').',
            );
        }
    }
}
