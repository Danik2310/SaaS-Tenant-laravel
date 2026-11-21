<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps a revoked identity as a tombstone.
 *
 * Simply deleting the row is not enough: the next Google sign-in would fall
 * back to verified-email matching and silently recreate the same link. A
 * timestamp lets the linker refuse that identity while keeping the row unique.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('oauth_accounts', 'unlinked_at')) {
            return;
        }

        Schema::table('oauth_accounts', function (Blueprint $table) {
            $table->timestamp('unlinked_at')->nullable()->after('avatar_url');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('oauth_accounts', 'unlinked_at')) {
            return;
        }

        Schema::table('oauth_accounts', function (Blueprint $table) {
            $table->dropColumn('unlinked_at');
        });
    }
};
