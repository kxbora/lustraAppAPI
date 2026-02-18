<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'borakim@gmail.com'],
            [
                'name' => 'Admin Borakim',
                'password' => Hash::make('1234567'),
                'is_admin' => true,
            ]
        );
    }
}
