<?php

namespace Tests\Unit;

use App\Services\TransactionParserService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TransactionParserServiceTest extends TestCase
{
    private function fakeToolUseResponse(string $toolName, array $input, string $id = 'toolu_01'): array
    {
        return [
            'id' => 'msg_01',
            'type' => 'message',
            'role' => 'assistant',
            'content' => [
                [
                    'type' => 'tool_use',
                    'id' => $id,
                    'name' => $toolName,
                    'input' => $input,
                ],
            ],
        ];
    }

    public function test_parses_simple_expense_with_rb_suffix(): void
    {
        Http::fake([
            '*' => Http::response($this->fakeToolUseResponse('record_transaction', [
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
            '*' => Http::response($this->fakeToolUseResponse('record_transaction', [
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
            '*' => Http::response($this->fakeToolUseResponse('record_transaction', [
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
            '*' => Http::response($this->fakeToolUseResponse('request_clarification', [
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
                'id' => 'msg_01',
                'type' => 'message',
                'role' => 'assistant',
                'content' => [
                    [
                        'type' => 'tool_use',
                        'id' => 'toolu_01',
                        'name' => 'record_transaction',
                        'input' => ['amount' => 30000, 'type' => 'expense', 'category' => 'Makanan', 'description' => 'Makan siang'],
                    ],
                    [
                        'type' => 'tool_use',
                        'id' => 'toolu_02',
                        'name' => 'record_transaction',
                        'input' => ['amount' => 15000, 'type' => 'expense', 'category' => 'Transportasi', 'description' => 'Bensin'],
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
            '*' => Http::response($this->fakeToolUseResponse('correct_last_transaction', [
                'category' => 'Hiburan',
            ])),
        ]);

        $result = app(TransactionParserService::class)->parse('ganti kategori transaksi terakhir jadi Hiburan');

        $this->assertSame('parsed', $result['status']);
        $this->assertEmpty($result['transactions']);
        $this->assertCount(1, $result['corrections']);
        $this->assertSame('Hiburan', $result['corrections'][0]['category']);
    }

    public function test_sends_tool_choice_any_so_model_must_use_a_tool(): void
    {
        Http::fake([
            '*' => Http::response($this->fakeToolUseResponse('record_transaction', [
                'amount' => 30000,
                'type' => 'expense',
                'category' => 'Makanan',
                'description' => 'Makan malam',
            ])),
        ]);

        app(TransactionParserService::class)->parse('makan malam 30rb');

        Http::assertSent(function ($request) {
            return $request['tool_choice']['type'] === 'any'
                && collect($request['tools'])->pluck('name')->contains('record_transaction')
                && collect($request['tools'])->pluck('name')->contains('request_clarification');
        });
    }
}
