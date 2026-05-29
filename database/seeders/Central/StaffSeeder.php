<?php

namespace Database\Seeders\Central;

use App\Models\AdminUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StaffSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@example.com');
        $password = env('ADMIN_PASSWORD', 'password');

        $staff = [
            [
                'name' => 'System Administrator',
                'email' => $email,
                'password' => Hash::make($password),
                'is_active' => true,
                'role' => 'super-admin',
            ],
            [
                'name' => 'Staff Manager',
                'email' => 'manager@example.com',
                'password' => Hash::make('password'),
                'is_active' => true,
                'role' => 'staff',
            ],
            [
                'name' => 'Staff Viewer',
                'email' => 'viewer@example.com',
                'password' => Hash::make('password'),
                'is_active' => true,
                'role' => 'staff',
            ],
            [
                'name' => 'Inactive Staff',
                'email' => 'inactive@example.com',
                'password' => Hash::make('password'),
                'is_active' => false,
                'role' => 'staff',
            ],
        ];

        foreach ($staff as $data) {
            $role = $data['role'];
            unset($data['role']);

            $user = AdminUser::updateOrCreate(
                ['email' => $data['email']],
                $data,
            );

            if (! $user->hasRole($role)) {
                $user->assignRole($role);
            }
        }
    }
}
