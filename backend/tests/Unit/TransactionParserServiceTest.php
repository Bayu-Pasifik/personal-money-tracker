<?php

namespace Tests\Unit;

use App\Services\TransactionParserService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TransactionParserServiceTest extends TestCase
{
    private function fakeFunctionCallResponse(string $name, array $args): array
    {
        return [
            'candidates' => [
                [
                    'content' => [
                        'role' => 'model',
                        'parts' => [
                            ['functionCall' => ['name' => $name, 'args' => $args]],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_parses_simple_expense_with_rb_suffix(): void
    {
        Http::fake([
            '*' => Http::response($this->fakeFunctionCallResponse('record_transaction', [
                'amount' => 30000,
                'type' => 'expense',
                'category' => 'Makanan',
                'description' => 'Makan malam',
            ])),
        ]);

        $result = app(TransactionParserService::class)->parse('makan malam 30rb');

        $this->assertSame('parsed', $result['status']);
        $this->assertSame(30000, $result['transactions'][0]['amount']);
        $this->assertSame('expense', $result['transactions'][0]['type']);
        $this->assertSame('Makanan', $result['transactions'][0]['category']);
    }

    public function test_parses_income_with_k_suffix(): void
    {
        Http::fake([
            '*' => Http::response($this->fakeFunctionCallResponse('record_transaction', [
                'amount' => 500000,
                'type' => 'income',
                'category' => 'Gaji',
                'description' => 'Gaji freelance',
            ])),
        ]);

        $result = app(TransactionParserService::class)->parse('gaji freelance 500k');

        $this->assertSame('parsed', $result['status']);
        $this->assertSame(500000, $result['transactions'][0]['amount']);
        $this->assertSame('income', $result['transactions'][0]['type']);
    }

    public function test_parses_dotted_rupiah_format(): void
    {
        Http::fake([
            '*' => Http::response($this->fakeFunctionCallResponse('record_transaction', [
                'amount' => 150000,
                'type' => 'expense',
                'category' => 'Belanja',
                'description' => 'Beli baju',
            ])),
        ]);

        $result = app(TransactionParserService::class)->parse('beli baju 150.000');

        $this->assertSame(150000, $result['transactions'][0]['amount']);
    }

    public function test_ambiguous_input_returns_clarification_instead_of_guessing(): void
    {
        Http::fake([
            '*' => Http::response($this->fakeFunctionCallResponse('request_clarification', [
                'question' => "Nominalnya belum kebaca. Coba format seperti 'beli barang 50rb'.",
            ])),
        ]);

        $result = app(TransactionParserService::class)->parse('beli barang');

        $this->assertSame('needs_clarification', $result['status']);
        $this->assertEmpty($result['transactions']);
        $this->assertNotEmpty($result['clarifications']);
    }

    public function test_multi_line_message_produces_multiple_transactions(): void
    {
        Http::fake([
            '*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'role' => 'model',
                            'parts' => [
                                ['functionCall' => ['name' => 'record_transaction', 'args' => ['amount' => 30000, 'type' => 'expense', 'category' => 'Makanan', 'description' => 'Makan siang']]],
                                ['functionCall' => ['name' => 'record_transaction', 'args' => ['amount' => 15000, 'type' => 'expense', 'category' => 'Transportasi', 'description' => 'Bensin']]],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $result = app(TransactionParserService::class)->parse("makan siang 30rb\nbensin 15rb");

        $this->assertSame('parsed', $result['status']);
        $this->assertCount(2, $result['transactions']);
    }

    public function test_correction_intent_is_parsed_separately_from_new_transactions(): void
    {
        Http::fake([
            '*' => Http::response($this->fakeFunctionCallResponse('correct_last_transaction', [
                'category' => 'Hiburan',
            ])),
        ]);

        $result = app(TransactionParserService::class)->parse('ganti kategori transaksi terakhir jadi Hiburan');

        $this->assertSame('parsed', $result['status']);
        $this->assertEmpty($result['transactions']);
        $this->assertCount(1, $result['corrections']);
        $this->assertSame('Hiburan', $result['corrections'][0]['category']);
    }

    public function test_forces_function_call_so_model_must_use_a_tool(): void
    {
        Http::fake([
            '*' => Http::response($this->fakeFunctionCallResponse('record_transaction', [
                'amount' => 30000,
                'type' => 'expense',
                'category' => 'Makanan',
                'description' => 'Makan malam',
            ])),
        ]);

        app(TransactionParserService::class)->parse('makan malam 30rb');

        Http::assertSent(function ($request) {
            $body = $request->data();
            $names = collect($body['tools'][0]['functionDeclarations'])->pluck('name');

            return ($body['toolConfig']['functionCallingConfig']['mode'] ?? null) === 'ANY'
                && $names->contains('record_transaction')
                && $names->contains('request_clarification');
        });
    }
}
