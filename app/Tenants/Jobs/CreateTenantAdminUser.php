<?php

declare(strict_types=1);

namespace App\Tenants\Jobs;

use App\Models\Tenant;
use App\Models\User;
use App\Shared\Constants\PermissionNames;
use App\Shared\Jobs\TenantAwareJob;
use Illuminate\Support\Facades\Log;

class CreateTenantAdminUser extends TenantAwareJob
{
    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(
        Tenant $tenant,
        private readonly string $name,
        private readonly string $email,
        private readonly string $password,
    ) {
        parent::__construct($tenant);
    }

    protected function execute(): void
    {
        try {
            $user = User::updateOrCreate(
                ['email' => $this->email],
                [
                    'name' => $this->name,
                    'password' => $this->password,
                    'is_active' => true,
                ],
            );

            if (! $user->hasRole(PermissionNames::ROLE_TENANT_ADMIN)) {
                $user->assignRole(PermissionNames::ROLE_TENANT_ADMIN);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to create tenant admin user', [
                'tenant_id' => $this->tenant->id,
                'email' => $this->email,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
