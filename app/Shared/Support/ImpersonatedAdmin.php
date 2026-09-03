<?php

namespace App\Shared\Support;

use Illuminate\Contracts\Auth\Authenticatable;

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
}
