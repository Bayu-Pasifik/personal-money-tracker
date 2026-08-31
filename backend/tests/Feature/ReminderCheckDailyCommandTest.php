<?php

namespace Tests\Feature;

use App\Models\ReminderLog;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReminderCheckDailyCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_sends_reminder_when_user_has_no_transaction_today_at_their_reminder_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-31 20:00:00'));
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $user = User::factory()->create([
            'telegram_chat_id' => '111',
            'reminder_time' => '20:00:00',
        ]);

        $this->artisan('reminder:check-daily')->assertSuccessful();

        $this->assertDatabaseHas('reminder_logs', [
            'user_id' => $user->id,
            'was_needed' => true,
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains($request->data()['text'] ?? '', 'Belum ada catatan'));
    }

    public function test_skips_reminder_when_user_already_recorded_a_transaction_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-31 20:00:00'));
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $user = User::factory()->create([
            'telegram_chat_id' => '222',
            'reminder_time' => '20:00:00',
        ]);
        $this->seed(CategorySeeder::class);
        $category = $user->categories()->first();
        $user->transactions()->create([
            'category_id' => $category->id,
            'amount' => 10000,
            'type' => 'expense',
            'description' => 'Sudah dicatat',
            'source' => 'web',
            'transaction_date' => now(),
        ]);

        $this->artisan('reminder:check-daily')->assertSuccessful();

        $this->assertDatabaseHas('reminder_logs', [
            'user_id' => $user->id,
            'was_needed' => false,
        ]);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'sendMessage'));
    }

    public function test_does_not_send_duplicate_reminder_when_run_twice_same_minute(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-31 20:00:00'));
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        User::factory()->create([
            'telegram_chat_id' => '333',
            'reminder_time' => '20:00:00',
        ]);

        $this->artisan('reminder:check-daily')->assertSuccessful();
        $this->artisan('reminder:check-daily')->assertSuccessful();

        $this->assertDatabaseCount('reminder_logs', 1);

        Http::assertSentCount(1);
    }

    public function test_skips_users_whose_reminder_time_does_not_match_current_minute(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-31 20:00:00'));
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        User::factory()->create([
            'telegram_chat_id' => '444',
            'reminder_time' => '08:00:00',
        ]);

        $this->artisan('reminder:check-daily')->assertSuccessful();

        $this->assertDatabaseCount('reminder_logs', 0);
        Http::assertNothingSent();
    }

    public function test_skips_users_without_linked_telegram(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-31 20:00:00'));
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        User::factory()->create([
            'telegram_chat_id' => null,
            'reminder_time' => '20:00:00',
        ]);

        $this->artisan('reminder:check-daily')->assertSuccessful();

        $this->assertDatabaseCount('reminder_logs', 0);
        Http::assertNothingSent();
    }
}
