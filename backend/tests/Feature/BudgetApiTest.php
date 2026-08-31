<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BudgetApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_budgets_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/budgets')->assertStatus(401);
    }

    public function test_user_can_set_a_budget_for_expense_category(): void
    {
        $user = User::factory()->create();
        $this->seed(CategorySeeder::class);
        Sanctum::actingAs($user);
        $makanan = $user->categories()->where('name', 'Makanan')->first();

        $response = $this->postJson('/api/budgets', [
            'category_id' => $makanan->id,
            'month' => now()->format('Y-m'),
            'limit_amount' => 500000,
        ]);

        $response->assertStatus(201)->assertJsonPath('limit_amount', 500000);
        $this->assertDatabaseHas('budgets', ['user_id' => $user->id, 'category_id' => $makanan->id]);
    }

    public function test_budget_cannot_be_set_on_income_category(): void
    {
        $user = User::factory()->create();
        $this->seed(CategorySeeder::class);
        Sanctum::actingAs($user);
        $gaji = $user->categories()->where('name', 'Gaji')->first();

        $this->postJson('/api/budgets', [
            'category_id' => $gaji->id,
            'month' => now()->format('Y-m'),
            'limit_amount' => 500000,
        ])->assertStatus(422);
    }

    public function test_setting_budget_twice_for_same_month_updates_instead_of_duplicating(): void
    {
        $user = User::factory()->create();
        $this->seed(CategorySeeder::class);
        Sanctum::actingAs($user);
        $makanan = $user->categories()->where('name', 'Makanan')->first();

        $this->postJson('/api/budgets', ['category_id' => $makanan->id, 'month' => now()->format('Y-m'), 'limit_amount' => 500000]);
        $this->postJson('/api/budgets', ['category_id' => $makanan->id, 'month' => now()->format('Y-m'), 'limit_amount' => 700000]);

        $this->assertDatabaseCount('budgets', 1);
        $this->assertDatabaseHas('budgets', ['category_id' => $makanan->id, 'limit_amount' => 700000]);
    }

    public function test_index_returns_spent_amount_alongside_limit(): void
    {
        $user = User::factory()->create();
        $this->seed(CategorySeeder::class);
        Sanctum::actingAs($user);
        $makanan = $user->categories()->where('name', 'Makanan')->first();

        $user->budgets()->create(['category_id' => $makanan->id, 'month' => now()->startOfMonth(), 'limit_amount' => 500000]);
        $user->transactions()->create([
            'category_id' => $makanan->id, 'amount' => 200000, 'type' => 'expense',
            'description' => 'Belanja bulanan', 'source' => 'web', 'transaction_date' => now()->toDateString(),
        ]);

        $response = $this->getJson('/api/budgets?month='.now()->format('Y-m'));

        $response->assertOk();
        $this->assertSame(500000, $response->json('0.limit_amount'));
        $this->assertSame(200000, $response->json('0.spent'));
    }

    public function test_user_can_delete_own_budget(): void
    {
        $user = User::factory()->create();
        $this->seed(CategorySeeder::class);
        Sanctum::actingAs($user);
        $makanan = $user->categories()->where('name', 'Makanan')->first();
        $budget = $user->budgets()->create(['category_id' => $makanan->id, 'month' => now()->startOfMonth(), 'limit_amount' => 500000]);

        $this->deleteJson("/api/budgets/{$budget->id}")->assertStatus(204);
        $this->assertDatabaseMissing('budgets', ['id' => $budget->id]);
    }

    public function test_user_cannot_delete_another_users_budget(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $this->seed(CategorySeeder::class);
        $makanan = $owner->categories()->where('name', 'Makanan')->first();
        $budget = $owner->budgets()->create(['category_id' => $makanan->id, 'month' => now()->startOfMonth(), 'limit_amount' => 500000]);

        Sanctum::actingAs($intruder);
        $this->deleteJson("/api/budgets/{$budget->id}")->assertStatus(403);
    }
}
