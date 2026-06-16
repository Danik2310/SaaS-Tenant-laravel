<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@example.com');
        $password = env('ADMIN_PASSWORD');
        if (! $password) {
            if (app()->environment('production')) {
                throw new \RuntimeException('ADMIN_PASSWORD environment variable is not set.');
            }
            $password = 'password';
        }

        $user = AdminUser::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'System Administrator',
                'password' => Hash::make($password),
            ]
        );

        // ensure super-admin role assigned
        if (! $user->hasRole('super-admin')) {
            $user->assignRole('super-admin');
        }
    }
}
