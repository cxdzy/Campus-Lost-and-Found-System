<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $matric = env('ADMIN_MATRIC', 'A1234567');
        $password = env('ADMIN_PASSWORD', 'password');
        $name = env('ADMIN_NAME', 'Admin User');

        User::firstOrCreate([
            'matric_number' => $matric,
        ], [
            'name' => $name,
            'role' => 'Admin',
            'telegram_chat_id' => null,
            'password' => bcrypt($password),
        ]);
    }
}
