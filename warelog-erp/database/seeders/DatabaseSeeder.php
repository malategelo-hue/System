<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::where('email', 'admin@warelog.com')->first();
        if ($admin) {
            $admin->update(['role' => 'admin']);

            return;
        }

        $admin = User::where('role', 'admin')->first();
        if ($admin) {
            $admin->update([
                'name' => 'Admin',
                'email' => 'admin@warelog.com',
                'password' => Hash::make('admin123'),
            ]);

            return;
        }

        User::create([
            'name' => 'Admin',
            'email' => 'admin@warelog.com',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
        ]);
    }
}
