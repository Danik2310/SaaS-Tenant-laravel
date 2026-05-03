<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AdminUser;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@example.com');
        $password = env('ADMIN_PASSWORD', 'password');

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
