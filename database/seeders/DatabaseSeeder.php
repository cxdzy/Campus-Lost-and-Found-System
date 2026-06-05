<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Seed all categories via the dedicated seeder
        $this->call(CategorySeeder::class);

        // Ensure a default admin account exists
        User::firstOrCreate(
            ['matric_number' => 'ADMIN-001'],
            [
                'name'             => 'Mohamad Haziq Naqib bin Zaid',
                'role'             => 'Admin',
                'telegram_chat_id' => null,
                'password'         => Hash::make('password123'),
            ]
        );
    }
}
