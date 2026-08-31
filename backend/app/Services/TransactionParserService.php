<?php

namespace App\Services;

use App\Exceptions\AiParsingException;
use App\Services\Ai\GeminiClient;
use Database\Seeders\CategorySeeder;

/**
 * Mem-parsing teks bebas (Telegram / input manual web) menjadi transaksi
 * terstruktur lewat Gemini function calling. Lihat PRD.md §6.1 (FR-1.1—1.4)
 * dan §11 untuk desain prompt.
 */
class TransactionParserService
{
    public function __construct(private readonly GeminiClient $client) {}

    /**
     * @param  array<int, string>  $availableCategories  Nama kategori milik user (default + custom)
     * @return array{
     *     status: 'parsed'|'needs_clarification'|'mixed',
     *     transactions: array<int, array{amount: int, type: string, category: string, description: string}>,
     *     clarifications: array<int, string>,
     *     corrections: array<int, array{category: ?string, amount: ?int, description: ?string}>,
     * }
     */
    public function parse(string $text, array $availableCategories = []): array
    {
        $categories = $availableCategories !== []
            ? $availableCategories
            : array_column(CategorySeeder::DEFAULT_CATEGORIES, 'name');

        $response = $this->client->send(
            model: config('services.gemini.parser_model'),
            systemPrompt: $this->systemPrompt($categories),
            messages: [
                ['role' => 'user', 'content' => $text],
            ],
            tools: $this->tools($categories),
            forceFunctionCall: true,
            maxTokens: 1024,
        );

        return $this->extractFunctionCalls($response);
    }

    /**
     * @param  array<int, string>  $categories
     */
    private function systemPrompt(array $categories): string
    {
        $categoryList = implode(', ', $categories);

        return <<<PROMPT
            Kamu adalah parser transaksi keuangan untuk aplikasi FinTrack AI. Tugasmu HANYA
            mengubah satu pesan pengguna (Bahasa Indonesia, gaya chat santai) menjadi satu
            atau beberapa transaksi terstruktur lewat function `record_transaction`.

            Kategori yang tersedia: {$categoryList}
            Jika tidak ada yang cocok, pakai "Lainnya".

            Format nominal gaya Indonesia yang harus kamu pahami:
            - "30rb" / "30 rb" / "30k" -> 30000
            - "1jt" / "1 juta" -> 1000000
            - "1,5jt" -> 1500000
            - "Rp30.000" / "30.000" -> 30000
            - Angka polos tanpa satuan seperti "30" dalam konteks transaksi kecil sehari-hari
              biasanya berarti 30000 (ribuan), TAPI jika tidak yakin, jangan menebak.

            Aturan penting:
            1. Jika pesan mengandung lebih dari satu transaksi (mis. beberapa baris), panggil
               `record_transaction` beberapa kali, satu kali per transaksi.
            2. Jika nominal tidak jelas ATAU kalimat tidak mengandung transaksi keuangan sama
               sekali, panggil `request_clarification` alih-alih menebak. Fail-safe, bukan
               fail-silent.
            3. `description` singkat, berbasis kata-kata pengguna, jangan ditambah opini.
            4. Selalu tentukan `type` income atau expense berdasarkan konteks (gaji, bonus,
               transfer masuk = income; sisanya umumnya expense).
            5. Jika pesan adalah KOREKSI ke transaksi TERAKHIR yang sudah tersimpan (bukan
               transaksi baru) — ditandai kata seperti "ganti", "harusnya", "salah",
               "ubah", "koreksi", "terakhir" — panggil `correct_last_transaction`, BUKAN
               `record_transaction`. Isi hanya field yang memang disebut user berubah.

            Contoh:
            - "makan malam 30rb" -> record_transaction(amount=30000, type=expense, category=Makanan, description="Makan malam")
            - "gaji freelance 500k" -> record_transaction(amount=500000, type=income, category=Gaji, description="Gaji freelance")
            - "beli baju 150.000" -> record_transaction(amount=150000, type=expense, category=Belanja, description="Beli baju")
            - "1,5jt buat servis motor" -> record_transaction(amount=1500000, type=expense, category=Transportasi, description="Servis motor")
            - "beli barang" (tanpa nominal) -> request_clarification("Nominalnya belum kebaca. Coba format seperti 'beli barang 50rb'.")
            - "ganti kategori transaksi terakhir jadi Hiburan" -> correct_last_transaction(category=Hiburan)
            - "eh salah, harusnya 20rb bukan 15rb" -> correct_last_transaction(amount=20000)
            - "transaksi terakhir harusnya bensin motor" -> correct_last_transaction(description="Bensin motor")
            PROMPT;
    }

    /**
     * @param  array<int, string>  $categories
     * @return array<int, array<string, mixed>>
     */
    private function tools(array $categories): array
    {
        return [
            [
                'name' => 'record_transaction',
                'description' => 'Catat satu transaksi keuangan yang berhasil dipahami dengan yakin dari teks pengguna.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'amount' => [
                            'type' => 'INTEGER',
                            'description' => 'Nominal dalam Rupiah, angka bulat positif tanpa titik/koma. Contoh: 30000',
                        ],
                        'type' => [
                            'type' => 'STRING',
                            'enum' => ['income', 'expense'],
                        ],
                        'category' => [
                            'type' => 'STRING',
                            'enum' => $categories,
                        ],
                        'description' => [
                            'type' => 'STRING',
                            'description' => 'Deskripsi singkat transaksi berdasarkan kata-kata pengguna',
                        ],
                    ],
                    'required' => ['amount', 'type', 'category', 'description'],
                ],
            ],
            [
                'name' => 'request_clarification',
                'description' => 'Dipanggil jika input ambigu (nominal tidak jelas) atau tidak mengandung transaksi keuangan. Jangan menebak.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'question' => [
                            'type' => 'STRING',
                            'description' => 'Pertanyaan klarifikasi singkat dalam Bahasa Indonesia untuk ditanyakan balik ke pengguna',
                        ],
                    ],
                    'required' => ['question'],
                ],
            ],
            [
                'name' => 'correct_last_transaction',
                'description' => 'Dipanggil kalau pesan user adalah koreksi ke transaksi TERAKHIR yang sudah tersimpan (bukan transaksi baru). Isi hanya field yang disebut berubah.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'category' => ['type' => 'STRING', 'enum' => $categories],
                        'amount' => ['type' => 'INTEGER', 'description' => 'Nominal baru dalam Rupiah, angka bulat positif'],
                        'description' => ['type' => 'STRING', 'description' => 'Deskripsi baru transaksi'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array{status: string, transactions: array<int, array<string, mixed>>, clarifications: array<int, string>, corrections: array<int, array<string, mixed>>}
     */
    private function extractFunctionCalls(array $response): array
    {
        $parts = $response['candidates'][0]['content']['parts'] ?? null;

        if (! is_array($parts)) {
            throw new AiParsingException('Respons Gemini API tidak berisi content yang valid.');
        }

        $transactions = [];
        $clarifications = [];
        $corrections = [];

        foreach ($parts as $part) {
            $call = $part['functionCall'] ?? null;

            if (! $call) {
                continue;
            }

            $input = $call['args'] ?? [];

            if ($call['name'] === 'record_transaction') {
                $transactions[] = [
                    'amount' => (int) ($input['amount'] ?? 0),
                    'type' => $input['type'] ?? 'expense',
                    'category' => $input['category'] ?? 'Lainnya',
                    'description' => $input['description'] ?? '',
                ];
            } elseif ($call['name'] === 'request_clarification') {
                $clarifications[] = $input['question'] ?? 'Bisa diperjelas lagi transaksinya?';
            } elseif ($call['name'] === 'correct_last_transaction') {
                $corrections[] = [
                    'category' => $input['category'] ?? null,
                    'amount' => isset($input['amount']) ? (int) $input['amount'] : null,
                    'description' => $input['description'] ?? null,
                ];
            }
        }

        if ($transactions === [] && $clarifications === [] && $corrections === []) {
            throw new AiParsingException('Gemini tidak memanggil function manapun untuk input ini.');
        }

        $status = match (true) {
            $clarifications !== [] && ($transactions !== [] || $corrections !== []) => 'mixed',
            $clarifications !== [] => 'needs_clarification',
            default => 'parsed',
        };

        return [
            'status' => $status,
            'transactions' => $transactions,
            'clarifications' => $clarifications,
            'corrections' => $corrections,
        ];
    }
}
