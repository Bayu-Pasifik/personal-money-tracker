<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Kategori default sesuai PRD 6.1 (FR-1.3) & palet StyleGuide 5.3.
     */
    public const DEFAULT_CATEGORIES = [
        ['name' => 'Makanan', 'type' => 'expense', 'color_key' => 'category-makanan'],
        ['name' => 'Transportasi', 'type' => 'expense', 'color_key' => 'category-transportasi'],
        ['name' => 'Belanja', 'type' => 'expense', 'color_key' => 'category-belanja'],
        ['name' => 'Hiburan', 'type' => 'expense', 'color_key' => 'category-hiburan'],
        ['name' => 'Tagihan', 'type' => 'expense', 'color_key' => 'category-tagihan'],
        ['name' => 'Kesehatan', 'type' => 'expense', 'color_key' => 'category-kesehatan'],
        ['name' => 'Lainnya', 'type' => 'expense', 'color_key' => null],
        ['name' => 'Gaji', 'type' => 'income', 'color_key' => null],
    ];

    public function run(): void
    {
        User::all()->each(function (User $user) {
            foreach (self::DEFAULT_CATEGORIES as $category) {
                Category::firstOrCreate(
                    ['user_id' => $user->id, 'name' => $category['name'], 'type' => $category['type']],
                    ['is_default' => true, 'color_key' => $category['color_key']],
                );
            }
        });
    }
}
