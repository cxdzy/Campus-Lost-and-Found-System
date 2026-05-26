<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $now = now();

        foreach ([
            ['category_name' => 'Wallets', 'icon_identifier' => 'wallet'],
            ['category_name' => 'Accessories', 'icon_identifier' => 'accessory'],
            ['category_name' => 'Keys', 'icon_identifier' => 'keys'],
            ['category_name' => 'Electronics', 'icon_identifier' => 'electronics'],
            ['category_name' => 'IDs', 'icon_identifier' => 'id-card'],
            ['category_name' => 'Bags & Backpacks', 'icon_identifier' => 'bag'],
        ] as $category) {
            DB::table('categories')->updateOrInsert(
                ['category_name' => $category['category_name']],
                [
                    'icon_identifier' => $category['icon_identifier'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        User::firstOrCreate([
            'matric_number' => 'ADMIN-001',
        ], [
            'name' => 'Mohamad Haziq Naqib bin Zaid',
            'role' => 'Admin',
            'telegram_chat_id' => null,
            'password' => Hash::make('password123'),
        ]);
    }
}
