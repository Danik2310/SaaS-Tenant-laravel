<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * NOTE: The timestamp suffix is `000002` (not `000001`) so this tenant
     * migration has a distinct ledger key from the central
     * `2019_12_14_000001_create_personal_access_tokens_table`. In the local
     * dual-connection dev shortcut both connections share one `migrations`
     * ledger, so identical names would cause one copy to be silently skipped.
     * The `hasTable` guard keeps this safe if it re-runs on an existing schema.
     */
    public function up(): void
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->morphs('tokenable');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
