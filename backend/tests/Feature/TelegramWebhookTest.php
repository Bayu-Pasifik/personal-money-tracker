<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function fakeAiAndTelegram(array $anthropicToolResponse, ?string $anthropicTextComment = 'Wajar kok, masih dalam batas normal.'): void
    {
        Http::fake(function ($request) use ($anthropicToolResponse, $anthropicTextComment) {
            if (str_contains($request->url(), 'api.anthropic.com')) {
                $body = $request->data();

                if (isset($body['tools']) && $body['tools'] !== []) {
                    return Http::response($anthropicToolResponse);
                }

                return Http::response([
                    'content' => [['type' => 'text', 'text' => $anthropicTextComment]],
                ]);
            }

            if (str_contains($request->url(), 'api.telegram.org')) {
                return Http::response(['ok' => true, 'result' => ['message_id' => 1]]);
            }

            return Http::response([], 404);
        });
    }

    private function webhookPayload(string $chatId, string $text): array
    {
        return [
            'update_id' => 1,
            'message' => [
                'message_id' => 1,
                'chat' => ['id' => $chatId],
                'text' => $text,
            ],
        ];
    }

    public function test_start_command_links_telegram_chat_id_to_user(): void
    {
        $user = User::factory()->create();
        Cache::put('telegram_connect:ABC123', $user->id, now()->addMinutes(10));
        $this->fakeAiAndTelegram([]);

        $response = $this->postJson('/api/telegram/webhook', $this->webhookPayload('999888', '/start ABC123'));

        $response->assertOk();
        $this->assertSame('999888', $user->fresh()->telegram_chat_id);
    }

    public function test_expense_message_is_parsed_and_saved(): void
    {
        $user = User::factory()->create(['telegram_chat_id' => '111']);
        $this->seed(CategorySeeder::class);

        $this->fakeAiAndTelegram([
            'content' => [[
                'type' => 'tool_use',
                'id' => 'toolu_01',
                'name' => 'record_transaction',
                'input' => ['amount' => 30000, 'type' => 'expense', 'category' => 'Makanan', 'description' => 'Makan malam'],
            ]],
        ]);

        $response = $this->postJson('/api/telegram/webhook', $this->webhookPayload('111', 'makan malam 30rb'));

        $response->assertOk();
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'amount' => 30000,
            'type' => 'expense',
            'description' => 'Makan malam',
            'source' => 'telegram',
        ]);

        $transaction = $user->transactions()->first();
        $this->assertNotEmpty($transaction->ai_comment);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.telegram.org/bot')
            && str_contains($request->url(), 'sendMessage')
            && str_contains($request->data()['text'] ?? '', 'Tercatat'));
    }

    public function test_ambiguous_message_stores_pending_state_instead_of_saving(): void
    {
        $user = User::factory()->create(['telegram_chat_id' => '222']);
        $this->seed(CategorySeeder::class);

        $this->fakeAiAndTelegram([
            'content' => [[
                'type' => 'tool_use',
                'id' => 'toolu_01',
                'name' => 'request_clarification',
                'input' => ['question' => "Nominalnya belum kebaca. Coba format seperti 'beli barang 50rb'."],
            ]],
        ]);

        $response = $this->postJson('/api/telegram/webhook', $this->webhookPayload('222', 'beli barang'));

        $response->assertOk();
        $this->assertDatabaseCount('transactions', 0);
        $this->assertNotNull(Cache::get('telegram_pending:222'));
    }

    public function test_undo_deletes_last_transaction(): void
    {
        $user = User::factory()->create(['telegram_chat_id' => '333']);
        $this->seed(CategorySeeder::class);
        $category = $user->categories()->where('name', 'Makanan')->first();

        $user->transactions()->create([
            'category_id' => $category->id,
            'amount' => 20000,
            'type' => 'expense',
            'description' => 'Sarapan',
            'source' => 'telegram',
            'transaction_date' => now(),
        ]);

        $this->fakeAiAndTelegram([]);

        $response = $this->postJson('/api/telegram/webhook', $this->webhookPayload('333', '/undo'));

        $response->assertOk();
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_tanya_command_returns_advisory_answer(): void
    {
        $user = User::factory()->create(['telegram_chat_id' => '555']);
        $this->seed(CategorySeeder::class);

        $this->fakeAiAndTelegram([], 'Saldo bersihmu bulan ini masih aman.');

        $response = $this->postJson('/api/telegram/webhook', $this->webhookPayload('555', '/tanya gimana kondisi keuanganku?'));

        $response->assertOk();
        $this->assertDatabaseCount('advisory_sessions', 1);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains($request->data()['text'] ?? '', 'Saldo bersihmu bulan ini masih aman.'));
    }

    public function test_tanya_without_question_asks_for_one(): void
    {
        $user = User::factory()->create(['telegram_chat_id' => '666']);
        $this->fakeAiAndTelegram([]);

        $response = $this->postJson('/api/telegram/webhook', $this->webhookPayload('666', '/tanya'));

        $response->assertOk();
        $this->assertDatabaseCount('advisory_sessions', 0);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains($request->data()['text'] ?? '', 'Ketik pertanyaanmu'));
    }
}
