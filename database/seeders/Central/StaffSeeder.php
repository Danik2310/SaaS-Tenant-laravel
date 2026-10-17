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
        $password = env('ADMIN_PASSWORD');
        if (! $password) {
            if (app()->environment('production')) {
                throw new \RuntimeException('ADMIN_PASSWORD environment variable is not set.');
            }
            $password = 'password';
        }

        $staff = [
            [
                'name' => 'System Administrator',
                'email' => $email,
                'password' => Hash::make($password),
                'is_active' => true,
                'is_main_admin' => true,
                'role' => 'super-admin',
            ],
            [
                'name' => 'Staff Manager',
                'email' => 'manager@example.com',
                'password' => Hash::make($password),
                'is_active' => true,
                'role' => 'staff',
            ],
            [
                'name' => 'Staff Viewer',
                'email' => 'viewer@example.com',
                'password' => Hash::make($password),
                'is_active' => true,
                'role' => 'staff',
            ],
            [
                'name' => 'Inactive Staff',
                'email' => 'inactive@example.com',
                'password' => Hash::make($password),
                'is_active' => false,
                'role' => 'staff',
            ],
        ];

        foreach ($staff as $data) {
            $role = $data['role'];
            unset($data['role']);

            $isMainAdmin = (bool) ($data['is_main_admin'] ?? false);
            unset($data['is_main_admin']);

            $user = AdminUser::updateOrCreate(
                ['email' => $data['email']],
                $data,
            );

            // `is_main_admin` is intentionally not mass-assignable (privilege flag).
            if ($isMainAdmin && ! $user->is_main_admin) {
                $user->forceFill(['is_main_admin' => true])->save();
            }

            if (! $user->hasRole($role)) {
                $user->assignRole($role);
            }
        }
    }
}
