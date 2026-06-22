<?php

namespace Database\Seeders\Tenant;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed tenant users.
     *
     * Password is resolved from the TENANT_PASSWORD environment variable.
     * In production, the variable MUST be set — the seeder will throw if missing.
     *
     * The known emails seeded here (gerente@example.com, cajero@example.com)
     * are shared across ALL tenants in non-production environments. In production,
     * either override TENANT_PASSWORD or create users manually.
     */
    public function run(int $count, string $tenantEmail): void
    {
        $existing = User::count();

        if ($existing >= $count) {
            return;
        }

        $password = env('TENANT_PASSWORD');
        if (! $password) {
            if (app()->environment('production')) {
                throw new \RuntimeException(
                    'TENANT_PASSWORD environment variable is not set. '
                    .'All tenant user passwords would be predictable.'
                );
            }
            $password = 'password';
        }
        $hashedPassword = Hash::make($password);

        $needed = $count - $existing;

        $users = [
            [
                'name' => 'Admin Usuario',
                'email' => $tenantEmail,
                'password' => $hashedPassword,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Gerente',
                'email' => 'gerente@example.com',
                'password' => $hashedPassword,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Cajero Principal',
                'email' => 'cajero@example.com',
                'password' => $hashedPassword,
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
