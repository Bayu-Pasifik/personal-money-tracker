<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_report_requires_authentication(): void
    {
        $this->getJson('/api/reports/monthly')->assertStatus(401);
    }

    public function test_monthly_report_returns_a_pdf(): void
    {
        $user = User::factory()->create();
        $this->seed(CategorySeeder::class);
        Sanctum::actingAs($user);
        $makanan = $user->categories()->where('name', 'Makanan')->first();
        $user->transactions()->create([
            'category_id' => $makanan->id, 'amount' => 30000, 'type' => 'expense',
            'description' => 'Makan malam', 'source' => 'web', 'transaction_date' => now()->toDateString(),
        ]);

        $response = $this->get('/api/reports/monthly?month='.now()->format('Y-m'));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_monthly_report_works_even_with_no_transactions(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->get('/api/reports/monthly');

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }
}
