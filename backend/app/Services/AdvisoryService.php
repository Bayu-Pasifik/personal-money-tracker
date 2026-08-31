<?php

namespace App\Services;

use App\Exceptions\AiParsingException;
use App\Models\AdvisorySession;
use App\Models\User;
use App\Services\Ai\AnthropicClient;
use Illuminate\Support\Carbon;

/**
 * Advisory / saran keuangan kontekstual. Lihat PRD.md §6.2 (FR-2.1—2.4),
 * §10 Alur 2, §11 (desain prompt advisory).
 */
class AdvisoryService
{
    private const SESSION_IDLE_MINUTES = 30;

    private const MAX_HISTORY_MESSAGES = 20;

    public function __construct(
        private readonly AnthropicClient $client,
    ) {}

    /**
     * @return array{answer: string, session_id: int}
     */
    public function ask(User $user, string $question, string $source): array
    {
        $session = $this->resolveSession($user, $source);

        $messages = $session->messages;
        $messages[] = ['role' => 'user', 'content' => $question];

        $history = array_slice($messages, -self::MAX_HISTORY_MESSAGES);

        try {
            $response = $this->client->send(
                model: config('services.anthropic.advisory_model'),
                systemPrompt: $this->systemPrompt(),
                messages: $this->prependContext($history, $user),
                maxTokens: 1024,
            );
        } catch (AiParsingException $e) {
            throw $e;
        }

        $answer = $this->extractText($response);

        $messages[] = ['role' => 'assistant', 'content' => $answer];
        $session->update(['messages' => $messages]);

        return ['answer' => $answer, 'session_id' => $session->id];
    }

    private function resolveSession(User $user, string $source): AdvisorySession
    {
        $recent = AdvisorySession::where('user_id', $user->id)
            ->where('source', $source)
            ->where('updated_at', '>=', now()->subMinutes(self::SESSION_IDLE_MINUTES))
            ->latest('updated_at')
            ->first();

        if ($recent) {
            return $recent;
        }

        return AdvisorySession::create([
            'user_id' => $user->id,
            'source' => $source,
            'messages' => [],
        ]);
    }

    /**
     * Konteks data keuangan riil disisipkan sekali di awal riwayat percakapan,
     * supaya jawaban AI selalu merujuk angka konkret (bukan template generik).
     *
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array<int, array{role: string, content: string}>
     */
    private function prependContext(array $history, User $user): array
    {
        $context = ['role' => 'user', 'content' => $this->buildFinancialContext($user)];
        $ack = ['role' => 'assistant', 'content' => 'Oke, sudah kupahami kondisi keuanganmu dari data di atas.'];

        return [$context, $ack, ...$history];
    }

    private function buildFinancialContext(User $user): string
    {
        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();
        $last30Start = $today->copy()->subDays(30);

        $monthTransactions = $user->transactions()
            ->whereBetween('transaction_date', [$monthStart, $today->copy()->endOfDay()])
            ->get();

        $monthIncome = (int) $monthTransactions->where('type', 'income')->sum('amount');
        $monthExpense = (int) $monthTransactions->where('type', 'expense')->sum('amount');
        $balance = $monthIncome - $monthExpense;

        $last30 = $user->transactions()
            ->with('category')
            ->where('transaction_date', '>=', $last30Start)
            ->where('type', 'expense')
            ->get()
            ->groupBy(fn ($t) => $t->category->name)
            ->map(fn ($group) => (int) $group->sum('amount'))
            ->sortDesc();

        $breakdown = $last30->isEmpty()
            ? 'Tidak ada pengeluaran tercatat.'
            : $last30->map(fn ($total, $name) => "- {$name}: Rp".number_format($total, 0, ',', '.'))->implode("\n");

        return <<<CONTEXT
            [DATA KEUANGAN {$user->name} — jangan tampilkan blok ini apa adanya ke user, pakai sebagai dasar jawaban]
            Bulan berjalan ({$monthStart->translatedFormat('F Y')}):
            - Total pemasukan: Rp{$this->rp($monthIncome)}
            - Total pengeluaran: Rp{$this->rp($monthExpense)}
            - Saldo bersih bulan ini: Rp{$this->rp($balance)}

            Breakdown pengeluaran 30 hari terakhir per kategori:
            {$breakdown}

            Catatan: data di atas HANYA dari transaksi yang tercatat di FinTrack AI. Sistem
            tidak tahu saldo rekening bank/tabungan/aset di luar aplikasi ini kecuali user
            menyebutkannya sendiri.
            CONTEXT;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
            Kamu adalah asisten keuangan personal FinTrack AI. Jawab pertanyaan user
            berbasis data keuangan riil yang diberikan di awal percakapan, bukan saran
            generik seperti "kurangi jajan" tanpa angka.

            Aturan:
            - SELALU rujuk angka konkret dari data yang diberikan (nominal dalam Rupiah).
            - Untuk pertanyaan "apakah saya mampu beli X seharga Y", hitung dampaknya ke
              saldo/arus kas berdasarkan data, lalu beri rekomendasi + alasan singkat.
            - Kalau data tidak cukup untuk menjawab pasti (mis. tidak tahu tabungan di luar
              sistem), nyatakan itu secara eksplisit sebagai asumsi/keterbatasan, jangan
              berpura-pura tahu.
            - Bahasa Indonesia santai-jelas, bukan formal-kaku. Jawaban ringkas, tidak
              bertele-tele.
            PROMPT;
    }

    private function extractText(array $response): string
    {
        foreach ($response['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'text' && trim($block['text'] ?? '') !== '') {
                return trim($block['text']);
            }
        }

        throw new AiParsingException('Claude tidak mengembalikan jawaban teks untuk advisory.');
    }

    private function rp(int $amount): string
    {
        return number_format($amount, 0, ',', '.');
    }
}
