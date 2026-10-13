<?php

namespace App\Shared\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

/**
 * A lightweight, non-persisted identity representing an administrator
 * currently impersonating a tenant (god mode).
 *
 * This intentionally does NOT map to a tenant "users" table row: impersonated
 * admins are not real tenant users, they are central administrators granted a
 * temporary, read-only scoped session inside the tenant.
 */
class ImpersonatedAdmin implements Authenticatable
{
    public function __construct(
        protected array $attributes = []
    ) {}

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): string|int|null
    {
        return $this->attributes['id'] ?? null;
    }

    public function getAuthPassword(): ?string
    {
        return null;
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
        // Not persisted.
    }

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function __get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function only(array $keys): array
    {
        return collect($keys)->mapWithKeys(fn ($key) => [$key => $this->attributes[$key] ?? null])->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization surface
    |--------------------------------------------------------------------------
    |
    | God mode represents a central administrator, not a tenant user, so it is
    | granted every tenant permission. Server-side writes are still blocked by
    | the read-only guard in EnsureImpersonationValid. These methods exist so
    | Spatie's PermissionMiddleware (which requires `hasAnyPermission`) and
    | Laravel's `canAny` do not throw on permission-gated tenant routes.
    */

    public function hasPermissionTo($permission, $guardName = null): bool
    {
        return true;
    }

    public function hasAnyPermission(...$permissions): bool
    {
        return true;
    }

    public function hasAllPermissions(...$permissions): bool
    {
        return true;
    }

    public function hasRole($roles, ?string $guard = null): bool
    {
        return true;
    }

    public function hasAnyRole(...$roles): bool
    {
        return true;
    }

    /**
     * @return Collection<int, mixed>
     */
    public function getAllPermissions()
    {
        return collect();
    }

    /**
     * @return array<int, string>
     */
    public function getRoleNames(): array
    {
        return [];
    }

    public function can($ability, $arguments = []): bool
    {
        return true;
    }

    public function canAny($abilities, $arguments = []): bool
    {
        return true;
    }
}
