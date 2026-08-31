<?php

namespace App\Services\Telegram;

use App\Exceptions\AiParsingException;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AdvisoryService;
use App\Services\CommentGeneratorService;
use App\Services\TransactionParserService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Orkestrasi handler pesan Telegram. Lihat PRD.md §6.1 (FR-1.1—1.7) dan
 * §10 Alur 1 untuk alur input transaksi via Telegram.
 */
class TelegramBotService
{
    private const PENDING_TTL_MINUTES = 10;

    public function __construct(
        private readonly TelegramClient $telegram,
        private readonly TransactionParserService $parser,
        private readonly CommentGeneratorService $commentGenerator,
        private readonly AdvisoryService $advisory,
    ) {}

    public function handleUpdate(array $update): void
    {
        $message = $update['message'] ?? null;

        if (! $message || ! isset($message['text'])) {
            return;
        }

        $chatId = (string) $message['chat']['id'];
        $text = trim($message['text']);

        if ($text === '') {
            return;
        }

        match (true) {
            str_starts_with($text, '/start') => $this->handleStart($chatId, $text),
            $text === '/undo' => $this->handleUndo($chatId),
            str_starts_with($text, '/tanya') => $this->handleTanya($chatId, $text),
            default => $this->handleTransactionInput($chatId, $text),
        };
    }

    private function handleStart(string $chatId, string $text): void
    {
        $code = trim(substr($text, strlen('/start')));

        if ($code === '') {
            $this->telegram->sendMessage(
                $chatId,
                "Untuk menghubungkan akun, ambil kode koneksi dari halaman Pengaturan di web FinTrack AI, lalu kirim: /start KODE",
            );

            return;
        }

        $userId = Cache::pull("telegram_connect:{$code}");

        if (! $userId) {
            $this->telegram->sendMessage($chatId, 'Kode tidak valid atau sudah kedaluwarsa. Ambil kode baru dari halaman Pengaturan.');

            return;
        }

        $user = User::find($userId);

        if (! $user) {
            $this->telegram->sendMessage($chatId, 'Akun tidak ditemukan. Coba ambil kode baru dari halaman Pengaturan.');

            return;
        }

        $user->update(['telegram_chat_id' => $chatId]);

        $this->telegram->sendMessage($chatId, "Akun Telegram berhasil terhubung ke {$user->name}. Sekarang kamu bisa langsung ketik transaksi, mis. \"makan malam 30rb\".");
    }

    private function handleUndo(string $chatId): void
    {
        $user = $this->resolveUser($chatId);

        if (! $user) {
            $this->telegram->sendMessage($chatId, $this->notLinkedMessage());

            return;
        }

        $last = $user->transactions()->latest('id')->first();

        if (! $last) {
            $this->telegram->sendMessage($chatId, 'Belum ada transaksi yang bisa dibatalkan.');

            return;
        }

        $description = $last->description;
        $amount = $this->formatRupiah($last->amount);
        $last->delete();

        $this->telegram->sendMessage($chatId, "Dibatalkan: {$description} — {$amount}.");
    }

    private function handleTanya(string $chatId, string $text): void
    {
        $user = $this->resolveUser($chatId);

        if (! $user) {
            $this->telegram->sendMessage($chatId, $this->notLinkedMessage());

            return;
        }

        $question = trim(substr($text, strlen('/tanya')));

        if ($question === '') {
            $this->telegram->sendMessage($chatId, "Ketik pertanyaanmu setelah /tanya, mis. /tanya aku mau upgrade PC 8 juta gimana kondisiku?");

            return;
        }

        try {
            $result = $this->advisory->ask($user, $question, 'telegram');
        } catch (AiParsingException) {
            $this->telegram->sendMessage($chatId, 'Lagi ada gangguan waktu memproses pertanyaanmu. Coba tanya lagi sebentar lagi.');

            return;
        }

        $this->telegram->sendMessage($chatId, $result['answer']);
    }

    private function handleTransactionInput(string $chatId, string $text): void
    {
        $user = $this->resolveUser($chatId);

        if (! $user) {
            $this->telegram->sendMessage($chatId, $this->notLinkedMessage());

            return;
        }

        $pendingKey = "telegram_pending:{$chatId}";
        $pending = Cache::pull($pendingKey);
        $fullText = $pending ? "{$pending}\n{$text}" : $text;

        $categories = $user->categories()->pluck('name')->all();

        try {
            $result = $this->parser->parse($fullText, $categories);
        } catch (AiParsingException) {
            $this->telegram->sendMessage($chatId, 'Lagi ada gangguan waktu memproses transaksimu. Coba kirim ulang beberapa saat lagi.');

            return;
        }

        if ($result['clarifications'] !== []) {
            Cache::put($pendingKey, $fullText, now()->addMinutes(self::PENDING_TTL_MINUTES));
            $this->telegram->sendMessage($chatId, implode("\n", $result['clarifications']));
        }

        if ($result['corrections'] !== []) {
            $this->applyCorrections($chatId, $user, $result['corrections']);
        }

        if ($result['transactions'] === []) {
            return;
        }

        $confirmations = [];
        $walletId = $user->defaultWallet()->id;

        foreach ($result['transactions'] as $parsed) {
            $category = $this->resolveCategory($user, $parsed['category'], $parsed['type']);

            $transaction = $user->transactions()->create([
                'category_id' => $category->id,
                'wallet_id' => $walletId,
                'amount' => $parsed['amount'],
                'type' => $parsed['type'],
                'description' => $parsed['description'],
                'raw_input_text' => $fullText,
                'source' => 'telegram',
                'transaction_date' => Carbon::today(),
            ]);

            $monthlyTotal = $user->transactions()
                ->where('category_id', $category->id)
                ->where('type', $parsed['type'])
                ->whereBetween('transaction_date', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('amount');

            $aiComment = $this->commentGenerator->generate($transaction, (int) $monthlyTotal);
            $transaction->update(['ai_comment' => $aiComment]);

            $sign = $parsed['type'] === 'income' ? '+' : '-';
            $confirmations[] = "✅ Tercatat: {$transaction->description} — {$sign}".$this->formatRupiah($transaction->amount)." (Kategori: {$category->name})\n{$aiComment}";
        }

        $this->telegram->sendMessage($chatId, implode("\n\n", $confirmations));
    }

    /**
     * PRD.md Task.md Fase 2: koreksi transaksi via chat selain `/undo`, mis.
     * "ganti kategori transaksi terakhir jadi Hiburan".
     *
     * @param  array<int, array{category: ?string, amount: ?int, description: ?string}>  $corrections
     */
    private function applyCorrections(string $chatId, User $user, array $corrections): void
    {
        $last = $user->transactions()->latest('id')->first();

        if (! $last) {
            $this->telegram->sendMessage($chatId, 'Belum ada transaksi buat dikoreksi.');

            return;
        }

        $changes = [];

        foreach ($corrections as $correction) {
            if (! empty($correction['category'])) {
                $category = $this->resolveCategory($user, $correction['category'], $last->type);
                $last->category_id = $category->id;
                $changes[] = "kategori jadi {$category->name}";
            }

            if (! empty($correction['amount'])) {
                $last->amount = $correction['amount'];
                $changes[] = 'nominal jadi '.$this->formatRupiah($correction['amount']);
            }

            if (! empty($correction['description'])) {
                $last->description = $correction['description'];
                $changes[] = "deskripsi jadi \"{$correction['description']}\"";
            }
        }

        if ($changes === []) {
            $this->telegram->sendMessage($chatId, 'Aku belum nangkep apa yang mau diubah. Coba lebih spesifik, mis. "ganti kategori transaksi terakhir jadi Hiburan".');

            return;
        }

        $last->save();

        $this->telegram->sendMessage($chatId, 'Dikoreksi: '.implode(', ', $changes).".");
    }

    private function resolveUser(string $chatId): ?User
    {
        return User::where('telegram_chat_id', $chatId)->first();
    }

    private function resolveCategory(User $user, string $name, string $type): Category
    {
        $category = $user->categories()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->where('type', $type)
            ->first();

        if ($category) {
            return $category;
        }

        $fallbackName = $type === 'income' ? 'Gaji' : 'Lainnya';

        $category = $user->categories()
            ->where('name', $fallbackName)
            ->where('type', $type)
            ->first();

        if ($category) {
            return $category;
        }

        return $user->categories()->create([
            'name' => $name,
            'type' => $type,
            'is_default' => false,
        ]);
    }

    private function formatRupiah(int $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }

    private function notLinkedMessage(): string
    {
        return "Akun Telegram-mu belum terhubung. Ambil kode koneksi dari halaman Pengaturan di web FinTrack AI, lalu kirim: /start KODE";
    }
}
