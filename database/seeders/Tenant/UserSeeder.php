<?php

namespace Database\Seeders\Tenant;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(int $count, string $tenantEmail): void
    {
        $existing = User::count();

        if ($existing >= $count) {
            return;
        }

        $needed = $count - $existing;

        $users = [
            [
                'name' => 'Admin Usuario',
                'email' => $tenantEmail,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Gerente',
                'email' => 'gerente@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Cajero Principal',
                'email' => 'cajero@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        ];

        $roles = ['tenant-admin', 'manager', 'cashier'];

        foreach ($users as $i => $data) {
            if ($i >= $needed) {
                break;
            }

            $user = User::updateOrCreate(
                ['email' => $data['email']],
                $data,
            );

            if (isset($roles[$i]) && ! $user->hasRole($roles[$i])) {
                $user->assignRole($roles[$i]);
            }
        }

        for ($i = count($users); $i < $needed; $i++) {
            $user = User::factory()->create();
            $user->assignRole('cashier');
        }
    }
}
