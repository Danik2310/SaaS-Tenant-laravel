<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Feature\ModelConnectionContractTest;

/**
 * A tenant user's external identity (Google today).
 *
 * Lives in the tenant database, so it deliberately does **not** declare
 * `$connection = 'mysql_central'` ({@see ModelConnectionContractTest}
 * only pins central tables).
 *
 * `provider_email` is informational; the authoritative key is
 * (`provider`, `provider_user_id`).
 */
class OAuthAccount extends Model
{
    use HasFactory;

    /**
     * Eloquent would pluralise `OAuthAccount` to `o_auth_accounts`; the table is
     * spelled `oauth_accounts` (`2026_11_14_000001`), so it is pinned here.
     */
    protected $table = 'oauth_accounts';

    protected $fillable = [
        'user_id',
        'provider',
        'provider_user_id',
        'provider_email',
        'avatar_url',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
