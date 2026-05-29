<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SystemSettingsSeeder::class,
        ]);

        User::create([
            'name' => 'Abdullah Mohamed',
            'email' => 'abdullah-swe@outlook.com',
            'password' => Hash::make('123456789'),
        ]);
    }
}
