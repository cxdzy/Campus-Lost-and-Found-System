<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(CategorySeeder::class);

        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'matric_number' => 'A1234567',
            'role' => 'Admin',
            'telegram_chat_id' => null,
        ]);
    }
}
