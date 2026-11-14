<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * External identities used to sign in to a tenant account.
 *
 * A separate table (instead of a `google_id` column on `users`) keeps the
 * provider set open, allows one user to link several providers, and keeps the
 * tenant `users` table aligned with the central one — `TenantMigrationTest`
 * compares the two column lists.
 *
 * `provider_user_id` is the provider's stable subject id (`sub` for Google),
 * never the email: emails can change and are not unique across providers.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('oauth_accounts')) {
            return;
        }

        Schema::create('oauth_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('provider_user_id', 191);
            // Stored for support/debugging only; never used for lookups — the
            // authoritative link is provider + provider_user_id.
            $table->string('provider_email')->nullable();
            $table->string('avatar_url', 2048)->nullable();
            $table->timestamps();

            // One provider identity maps to exactly one account.
            $table->unique(['provider', 'provider_user_id']);
            // A user links a given provider at most once.
            $table->unique(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_accounts');
    }
};
