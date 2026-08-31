<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WalletApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallets_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/wallets')->assertStatus(401);
    }

    public function test_index_creates_default_wallet_if_missing(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/wallets');

        $response->assertOk()->assertJsonCount(1);
        $this->assertDatabaseHas('wallets', ['user_id' => $user->id, 'name' => 'Dompet Utama', 'is_default' => true]);
    }

    public function test_user_can_create_a_custom_wallet(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/wallets', ['name' => 'Rekening BCA']);

        $response->assertStatus(201)->assertJsonPath('name', 'Rekening BCA');
        $this->assertDatabaseHas('wallets', ['user_id' => $user->id, 'name' => 'Rekening BCA', 'is_default' => false]);
    }

    public function test_default_wallet_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $wallet = $user->defaultWallet();

        $this->deleteJson("/api/wallets/{$wallet->id}")->assertStatus(422);
    }

    public function test_custom_wallet_can_be_deleted(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $wallet = $user->wallets()->create(['name' => 'Tunai', 'is_default' => false]);

        $this->deleteJson("/api/wallets/{$wallet->id}")->assertStatus(204);
        $this->assertDatabaseMissing('wallets', ['id' => $wallet->id]);
    }

    public function test_deleting_wallet_detaches_but_keeps_transaction_history(): void
    {
        $user = User::factory()->create();
        $this->seed(CategorySeeder::class);
        Sanctum::actingAs($user);
        $wallet = $user->wallets()->create(['name' => 'Tunai', 'is_default' => false]);
        $makanan = $user->categories()->where('name', 'Makanan')->first();
        $transaction = $user->transactions()->create([
            'category_id' => $makanan->id, 'wallet_id' => $wallet->id, 'amount' => 10000, 'type' => 'expense',
            'description' => 'Jajan', 'source' => 'web', 'transaction_date' => now()->toDateString(),
        ]);

        $this->deleteJson("/api/wallets/{$wallet->id}")->assertStatus(204);

        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'wallet_id' => null]);
    }

    public function test_transaction_defaults_to_default_wallet_when_not_specified(): void
    {
        $user = User::factory()->create();
        $this->seed(CategorySeeder::class);
        Sanctum::actingAs($user);
        $makanan = $user->categories()->where('name', 'Makanan')->first();

        $response = $this->postJson('/api/transactions', [
            'category_id' => $makanan->id, 'amount' => 10000, 'type' => 'expense',
            'description' => 'Jajan', 'transaction_date' => now()->toDateString(),
        ]);

        $response->assertStatus(201);
        $this->assertSame($user->defaultWallet()->id, $response->json('wallet_id'));
    }

    public function test_user_cannot_delete_another_users_wallet(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $wallet = $owner->wallets()->create(['name' => 'Tunai', 'is_default' => false]);

        Sanctum::actingAs($intruder);
        $this->deleteJson("/api/wallets/{$wallet->id}")->assertStatus(403);
    }
}
