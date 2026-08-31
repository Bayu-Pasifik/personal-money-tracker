<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BudgetWarningTest extends TestCase
{
    use RefreshDatabase;

    private function fakeTelegram(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    }

    private function makeTransaction(User $user, int $categoryId, int $amount, string $type = 'expense'): void
    {
        $user->transactions()->create([
            'category_id' => $categoryId,
            'amount' => $amount,
            'type' => $type,
            'description' => 'test',
            'source' => 'web',
            'transaction_date' => now(),
        ]);
    }

    public function test_no_notification_when_no_budget_set(): void
    {
        $user = User::factory()->create(['telegram_chat_id' => '111']);
        $this->seed(CategorySeeder::class);
        $this->fakeTelegram();
        $makanan = $user->categories()->where('name', 'Makanan')->first();

        $this->makeTransaction($user, $makanan->id, 300000);

        Http::assertNothingSent();
    }

    public function test_no_notification_when_telegram_not_linked(): void
    {
        $user = User::factory()->create(['telegram_chat_id' => null]);
        $this->seed(CategorySeeder::class);
        $this->fakeTelegram();
        $makanan = $user->categories()->where('name', 'Makanan')->first();
        $user->budgets()->create(['category_id' => $makanan->id, 'month' => now()->startOfMonth(), 'limit_amount' => 200000]);

        $this->makeTransaction($user, $makanan->id, 300000);

        Http::assertNothingSent();
    }

    public function test_sends_approaching_warning_once_when_crossing_80_percent(): void
    {
        $user = User::factory()->create(['telegram_chat_id' => '222']);
        $this->seed(CategorySeeder::class);
        $this->fakeTelegram();
        $makanan = $user->categories()->where('name', 'Makanan')->first();
        $user->budgets()->create(['category_id' => $makanan->id, 'month' => now()->startOfMonth(), 'limit_amount' => 100000]);

        // 85rb dari limit 100rb = 85% -> lewati ambang 80%
        $this->makeTransaction($user, $makanan->id, 85000);

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request->data()['text'] ?? '', 'mendekati limit'));

        // Transaksi kedua masih di bawah 100%, ambang 80% sudah pernah dilewati -> tidak kirim lagi
        $this->makeTransaction($user, $makanan->id, 5000);

        Http::assertSentCount(1);
    }

    public function test_sends_exceeded_warning_when_crossing_100_percent(): void
    {
        $user = User::factory()->create(['telegram_chat_id' => '333']);
        $this->seed(CategorySeeder::class);
        $this->fakeTelegram();
        $makanan = $user->categories()->where('name', 'Makanan')->first();
        $user->budgets()->create(['category_id' => $makanan->id, 'month' => now()->startOfMonth(), 'limit_amount' => 100000]);

        $this->makeTransaction($user, $makanan->id, 85000);
        Http::assertSentCount(1);

        // Total jadi 120rb dari limit 100rb -> lewati ambang 100%
        $this->makeTransaction($user, $makanan->id, 35000);

        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_contains($request->data()['text'] ?? '', 'melewati limit'));
    }

    public function test_income_transactions_never_trigger_budget_warning(): void
    {
        $user = User::factory()->create(['telegram_chat_id' => '444']);
        $this->seed(CategorySeeder::class);
        $this->fakeTelegram();
        $gaji = $user->categories()->where('name', 'Gaji')->first();

        $this->makeTransaction($user, $gaji->id, 5000000, 'income');

        Http::assertNothingSent();
    }
}
