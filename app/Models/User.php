<?php

namespace App\Models;

use App\Shared\Constants\OAuthProvider;
use App\Shared\Traits\EnforcesActivePermissions;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject, MustVerifyEmail
{
    use EnforcesActivePermissions, HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $guard_name = 'web';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    /** @return HasMany<OAuthAccount, $this> */
    public function oauthAccounts(): HasMany
    {
        return $this->hasMany(OAuthAccount::class);
    }

    /**
     * Minimal, JS-safe view of external sign-in methods.
     *
     * The provider subject id is deliberately omitted: the UI only needs a
     * label and the informational email Google last reported.
     *
     * @return Collection<int, array{provider: string, label: string, email: string}>
     */
    public function connectedAccounts(): Collection
    {
        return $this->oauthAccounts()
            ->linked()
            ->get()
            ->map(fn (OAuthAccount $account): array => [
                'provider' => $account->provider,
                'label' => OAuthProvider::label($account->provider),
                'email' => (string) ($account->provider_email ?? ''),
            ])
            ->values();
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return ['ten' => tenant('id')];
    }
}
