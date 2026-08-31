<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_custom_category(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/categories', ['name' => 'Donasi', 'type' => 'expense']);

        $response->assertStatus(201)
            ->assertJsonPath('name', 'Donasi')
            ->assertJsonPath('is_default', false);

        $this->assertDatabaseHas('categories', [
            'user_id' => $user->id,
            'name' => 'Donasi',
            'is_default' => false,
        ]);
    }

    public function test_user_can_rename_a_custom_category(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $category = $user->categories()->create(['name' => 'Donasi', 'type' => 'expense', 'is_default' => false]);

        $response = $this->putJson("/api/categories/{$category->id}", ['name' => 'Donasi & Zakat']);

        $response->assertOk()->assertJsonPath('name', 'Donasi & Zakat');
    }

    public function test_default_category_cannot_be_edited_or_deleted(): void
    {
        $user = User::factory()->create();
        $this->seed(CategorySeeder::class);
        Sanctum::actingAs($user);
        $makanan = $user->categories()->where('name', 'Makanan')->first();

        $this->putJson("/api/categories/{$makanan->id}", ['name' => 'Makan-makan'])->assertStatus(422);
        $this->deleteJson("/api/categories/{$makanan->id}")->assertStatus(422);
    }

    public function test_custom_category_can_be_deleted_when_unused(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $category = $user->categories()->create(['name' => 'Donasi', 'type' => 'expense', 'is_default' => false]);

        $this->deleteJson("/api/categories/{$category->id}")->assertStatus(204);
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_custom_category_cannot_be_deleted_when_used_by_a_transaction(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $category = $user->categories()->create(['name' => 'Donasi', 'type' => 'expense', 'is_default' => false]);
        $user->transactions()->create([
            'category_id' => $category->id, 'amount' => 10000, 'type' => 'expense',
            'description' => 'Donasi ke masjid', 'source' => 'web', 'transaction_date' => now()->toDateString(),
        ]);

        $this->deleteJson("/api/categories/{$category->id}")->assertStatus(422);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_user_cannot_modify_another_users_category(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $category = $owner->categories()->create(['name' => 'Donasi', 'type' => 'expense', 'is_default' => false]);

        Sanctum::actingAs($intruder);

        $this->putJson("/api/categories/{$category->id}", ['name' => 'hack'])->assertStatus(403);
        $this->deleteJson("/api/categories/{$category->id}")->assertStatus(403);
    }
}
