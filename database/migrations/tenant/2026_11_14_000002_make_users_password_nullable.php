<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Google OAuth accounts are created without a password, so tenant
 * `users.password` has to accept NULL.
 *
 * A NULL hash is not a login bypass: `BcryptHasher::check()` returns false for
 * a null hash, and the passwordless account can still adopt a password later
 * through the profile / password-reset flows. Existing rows are untouched, and
 * `down()` writes a random unusable hash into the NULL rows before restoring
 * NOT NULL so the rollback can never fail (or leave a guessable password).
 */
return new class extends Migration
{
    public function up(): void
    {
        if ($this->passwordIsNullable() !== false) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        if ($this->passwordIsNullable() !== true) {
            return;
        }

        DB::table('users')
            ->whereNull('password')
            ->update(['password' => Hash::make(Str::random(60))]);

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });
    }

    /**
     * `true` = nullable, `false` = NOT NULL, `null` = no users.password column
     * (nothing to change either way).
     */
    private function passwordIsNullable(): ?bool
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'password')) {
            return null;
        }

        foreach (Schema::getColumns('users') as $column) {
            if (($column['name'] ?? null) === 'password') {
                return (bool) ($column['nullable'] ?? false);
            }
        }

        return null;
    }
};
