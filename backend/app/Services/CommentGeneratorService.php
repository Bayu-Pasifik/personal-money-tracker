<?php

namespace App\Services;

use App\Exceptions\AiParsingException;
use App\Models\Transaction;
use App\Services\Ai\AnthropicClient;
use Illuminate\Support\Facades\Log;

/**
 * Menghasilkan komentar singkat AI setelah transaksi tersimpan.
 * Lihat PRD.md §11 dan StyleGuide.md §7 — 1 kalimat, bervariasi,
 * proporsional, tidak menghakimi.
 */
class CommentGeneratorService
{
    public function __construct(private readonly AnthropicClient $client) {}

    public function generate(Transaction $transaction, int $categoryMonthlyTotal): string
    {
        $systemPrompt = <<<'PROMPT'
            Kamu adalah asisten keuangan santai untuk aplikasi FinTrack AI. Setelah satu
            transaksi tersimpan, kamu memberi komentar SATU kalimat singkat dalam Bahasa
            Indonesia santai-jelas (bukan formal-kaku, bukan gaul berlebihan).

            Aturan:
            - Untuk pengeluaran wajar: santai, boleh sedikit humor ringan, tidak generik.
            - Untuk pengeluaran di luar kebiasaan (jauh di atas rata-rata kategori bulan ini):
              soroti secara halus, JANGAN menghakimi atau menggurui.
            - Untuk pemasukan: apresiatif singkat.
            - Jangan mengulang nominal secara verbatim jika sudah jelas dari konteks,
              fokus ke observasi/insight singkat.
            - Balas HANYA satu kalimat komentar, tanpa salam pembuka/penutup, tanpa tanda kutip.
            PROMPT;

        $type = $transaction->type === 'income' ? 'pemasukan' : 'pengeluaran';

        $userMessage = sprintf(
            'Transaksi baru: "%s" — Rp%s (%s, kategori %s). Total kategori "%s" bulan ini setelah transaksi ini: Rp%s.',
            $transaction->description,
            number_format($transaction->amount, 0, ',', '.'),
            $type,
            $transaction->category->name,
            $transaction->category->name,
            number_format($categoryMonthlyTotal, 0, ',', '.'),
        );

        try {
            $response = $this->client->send(
                model: config('services.anthropic.parser_model'),
                systemPrompt: $systemPrompt,
                messages: [['role' => 'user', 'content' => $userMessage]],
                maxTokens: 150,
            );

            foreach ($response['content'] ?? [] as $block) {
                if (($block['type'] ?? null) === 'text' && trim($block['text'] ?? '') !== '') {
                    return trim($block['text']);
                }
            }
        } catch (AiParsingException $e) {
            Log::warning('Gagal generate komentar AI, pakai fallback.', ['error' => $e->getMessage()]);
        }

        return $this->fallbackComment($transaction);
    }

    private function fallbackComment(Transaction $transaction): string
    {
        return $transaction->type === 'income'
            ? 'Tercatat, mantap ada pemasukan baru.'
            : 'Tercatat, tetap semangat jaga arus kasnya.';
    }
}
