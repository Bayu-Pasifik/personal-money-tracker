<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_token_for_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong',
        ]);

        $response->assertStatus(422);
    }

    public function test_transactions_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/transactions')->assertStatus(401);
    }

    public function test_authenticated_user_can_crud_transactions(): void
    {
        $user = User::factory()->create();
        $this->seed(CategorySeeder::class);
        Sanctum::actingAs($user);

        $category = $user->categories()->where('name', 'Makanan')->first();

        $store = $this->postJson('/api/transactions', [
            'category_id' => $category->id,
            'amount' => 25000,
            'type' => 'expense',
            'description' => 'Sarapan',
            'transaction_date' => now()->toDateString(),
        ]);
        $store->assertStatus(201);
        $transactionId = $store->json('id');

        $this->getJson('/api/transactions')->assertOk()->assertJsonCount(1, 'data');

        $update = $this->putJson("/api/transactions/{$transactionId}", [
            'category_id' => $category->id,
            'amount' => 30000,
            'type' => 'expense',
            'description' => 'Sarapan + kopi',
            'transaction_date' => now()->toDateString(),
        ]);
        $update->assertOk()->assertJsonPath('amount', 30000);

        $this->deleteJson("/api/transactions/{$transactionId}")->assertStatus(204);
        $this->getJson('/api/transactions')->assertJsonCount(0, 'data');
    }

    public function test_user_cannot_modify_another_users_transaction(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $this->seed(CategorySeeder::class);

        $category = $owner->categories()->where('name', 'Makanan')->first();
        $transaction = $owner->transactions()->create([
            'category_id' => $category->id,
            'amount' => 10000,
            'type' => 'expense',
            'description' => 'Punya owner',
            'source' => 'web',
            'transaction_date' => now(),
        ]);

        Sanctum::actingAs($intruder);

        $this->putJson("/api/transactions/{$transaction->id}", [
            'category_id' => $category->id,
            'amount' => 1,
            'type' => 'expense',
            'description' => 'hack',
            'transaction_date' => now()->toDateString(),
        ])->assertStatus(403);
    }

    public function test_categories_endpoint_returns_seeded_categories(): void
    {
        $user = User::factory()->create();
        $this->seed(CategorySeeder::class);
        Sanctum::actingAs($user);

        $this->getJson('/api/categories')->assertOk()->assertJsonCount(count(CategorySeeder::DEFAULT_CATEGORIES));
    }

    public function test_summary_returns_totals_and_category_breakdown(): void
    {
        $user = User::factory()->create();
        $this->seed(CategorySeeder::class);
        Sanctum::actingAs($user);

        $makanan = $user->categories()->where('name', 'Makanan')->first();
        $gaji = $user->categories()->where('name', 'Gaji')->first();

        $user->transactions()->create([
            'category_id' => $makanan->id, 'amount' => 30000, 'type' => 'expense',
            'description' => 'Makan malam', 'source' => 'web', 'transaction_date' => now(),
        ]);
        $user->transactions()->create([
            'category_id' => $gaji->id, 'amount' => 500000, 'type' => 'income',
            'description' => 'Gaji freelance', 'source' => 'web', 'transaction_date' => now(),
        ]);

        $response = $this->getJson('/api/summary?month='.now()->format('Y-m'));

        $response->assertOk()
            ->assertJsonPath('total_income', 500000)
            ->assertJsonPath('total_expense', 30000)
            ->assertJsonPath('balance', 470000);
    }
}
