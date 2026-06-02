<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminPassword = env('ADMIN_PASSWORD');

        if (blank($adminPassword)) {
            $adminPassword = app()->environment(['local', 'testing'])
                ? 'password'
                : Str::random(32);
        }

        $user = User::firstOrCreate(
            ['email' => 'admin@growthos.com'],
            [
                'name' => 'Administrator',
                'password' => Hash::make($adminPassword),
            ]
        );

        $user->assignRole('Super Admin');
    }
}
