<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database (Clean production setup without demo data).
     */
    public function run(): void
    {
        // 1. Admin User
        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Administrator Sambut',
                'email' => 'admin@example.com',
                'password' => Hash::make('GenZ2026'),
            ]
        );

        // 2. Default Event Settings
        Setting::updateOrCreate(
            ['id' => 1],
            [
                'name' => 'Christmas Celebration 2026',
                'school' => 'Panitia Natal 2026',
                'date' => '',
                'location' => '',
                'tagline' => 'Merajut Kasih, Berbagi Damai & Sukacita Natal',
                'contact' => '',
                'group_photo' => '',
            ]
        );
    }
}
