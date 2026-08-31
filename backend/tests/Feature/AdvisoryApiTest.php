<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdvisoryApiTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGeminiTextResponse(string $text): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => $text]]]]],
            ]),
        ]);
    }

    public function test_ask_requires_authentication(): void
    {
        $this->postJson('/api/advisory/ask', ['question' => 'halo'])->assertStatus(401);
    }

    public function test_ask_returns_grounded_answer(): void
    {
        $user = User::factory()->create();
        $this->seed(CategorySeeder::class);
        $gaji = $user->categories()->where('name', 'Gaji')->first();
        $user->transactions()->create([
            'category_id' => $gaji->id, 'amount' => 500000, 'type' => 'income',
            'description' => 'Gaji', 'source' => 'web', 'transaction_date' => now()->toDateString(),
        ]);

        Sanctum::actingAs($user);
        $this->fakeGeminiTextResponse('Saldo bersihmu bulan ini sekitar Rp500.000.');

        $response = $this->postJson('/api/advisory/ask', ['question' => 'Gimana kondisi keuanganku?']);

        $response->assertOk()->assertJsonStructure(['answer', 'session_id']);
        $this->assertSame('Saldo bersihmu bulan ini sekitar Rp500.000.', $response->json('answer'));

        Http::assertSent(function ($request) {
            $body = $request->data();
            $firstText = $body['contents'][0]['parts'][0]['text'] ?? '';

            return str_contains($firstText, 'DATA KEUANGAN')
                && str_contains($firstText, 'Rp500.000');
        });
    }

    public function test_conversation_history_persists_within_same_session(): void
    {
        $user = User::factory()->create();
        $this->seed(CategorySeeder::class);
        Sanctum::actingAs($user);

        $this->fakeGeminiTextResponse('Jawaban pertama.');
        $first = $this->postJson('/api/advisory/ask', ['question' => 'Pertanyaan pertama']);
        $first->assertOk();
        $sessionId = $first->json('session_id');

        $this->fakeGeminiTextResponse('Jawaban kedua.');
        $second = $this->postJson('/api/advisory/ask', ['question' => 'Pertanyaan kedua']);
        $second->assertOk();

        $this->assertSame($sessionId, $second->json('session_id'));

        $this->assertDatabaseHas('advisory_sessions', ['id' => $sessionId]);
        $session = \App\Models\AdvisorySession::find($sessionId);
        $this->assertCount(4, $session->messages);
    }
}
