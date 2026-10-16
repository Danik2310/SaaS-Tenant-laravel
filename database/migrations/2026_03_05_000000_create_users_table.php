<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * NOTE: The `users` table intentionally exists in BOTH the central and
     * tenant migration trees: `App\Models\User` (web guard) is used by tenant
     * flows and is registered centrally (observers), so the model must resolve
     * regardless of context. Keep this schema aligned with
     * `database/migrations/tenant/2014_10_12_000000_create_users_table.php`
     * (e.g. 2026_10_16_000003_add_is_active_to_central_users_table adds the
     * `is_active` column the tenant copy already has).
     */
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->rememberToken();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
