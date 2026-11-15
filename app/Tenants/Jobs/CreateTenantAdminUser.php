<?php

declare(strict_types=1);

namespace App\Tenants\Jobs;

use App\Models\Tenant;
use App\Models\User;
use App\Shared\Constants\PermissionNames;
use App\Shared\Jobs\TenantAwareJob;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CreateTenantAdminUser extends TenantAwareJob
{
    // The job is dispatched right after provisioning, but the tenant database
    // itself is created by a separate queued pipeline (CreateDatabase ->
    // MigrateDatabase -> SeedDatabase). On multi-worker queues this job can be
    // picked up before that pipeline finishes, so it retries with backoff until
    // the tenant database exists and is migrated.
    public int $tries = 6;

    public int $backoff = 20;

    private readonly string $email;

    /**
     * `$passwordHash` is NULL for a Google-verified signup: the account is
     * passwordless and already verified, so no hash is stored and no
     * verification mail is sent. Everything else receives the hash computed at
     * submission.
     */
    public function __construct(
        Tenant $tenant,
        private readonly string $name,
        string $email,
        private readonly ?string $passwordHash = null,
    ) {
        $this->email = strtolower(trim($email));

        parent::__construct($tenant);
    }

    protected function execute(): void
    {
        try {
            $passwordless = $this->passwordHash === null;

            $user = User::firstOrNew(['email' => $this->email]);
            $user->name = $this->name;
            $user->is_active = true;

            if ($passwordless) {
                // Google vouched for the address. The tenant-side flag is
                // authoritative and the central projection is what unlocks the
                // pending signup screen, so both are written here.
                $user->password = null;
                $user->email_verified_at = now();
            } else {
                $user->password = Str::random(32);
            }

            $user->save();

            if (! $passwordless) {
                // Persist the pre-computed hash verbatim, bypassing the model's
                // 'hashed' cast (which would re-hash an already-hashed value and
                // break logins). The hash is computed once at submission so no
                // plaintext password ever crosses the session or the queue.
                DB::connection()->table('users')
                    ->where('id', $user->getKey())
                    ->update(['password' => $this->passwordHash]);
            }

            if (! $user->hasRole(PermissionNames::ROLE_TENANT_ADMIN)) {
                $user->assignRole(PermissionNames::ROLE_TENANT_ADMIN);
            }

            if ($passwordless) {
                $this->tenant->markOwnerEmailVerified();
            }

            // The owner is provisioned but not yet usable: `verified` holds the
            // app back until the signed link is clicked. Firing the framework
            // event keeps the notification wiring in one place
            // (`EventServiceProvider::$listen`) instead of mailing from the job.
            if (! $user->hasVerifiedEmail()) {
                event(new Registered($user));
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
