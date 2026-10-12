<?php

declare(strict_types=1);

namespace App\Tenants\Jobs;

use App\Models\Tenant;
use App\Models\User;
use App\Shared\Constants\PermissionNames;
use App\Shared\Jobs\TenantAwareJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CreateTenantAdminUser extends TenantAwareJob
{
    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(
        Tenant $tenant,
        private readonly string $name,
        private readonly string $email,
        private readonly string $passwordHash,
    ) {
        parent::__construct($tenant);
    }

    protected function execute(): void
    {
        try {
            $user = User::firstOrNew(['email' => $this->email]);
            $user->name = $this->name;
            $user->is_active = true;
            $user->password = Str::random(32);
            $user->save();

            // Persist the pre-computed hash verbatim, bypassing the model's
            // 'hashed' cast (which would re-hash an already-hashed value and
            // break logins). The hash is computed once at submission so no
            // plaintext password ever crosses the session or the queue.
            DB::connection()->table('users')
                ->where('id', $user->getKey())
                ->update(['password' => $this->passwordHash]);

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
