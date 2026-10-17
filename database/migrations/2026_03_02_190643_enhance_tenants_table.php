<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // NOTE: `email` is stored as plaintext PII. If GDPR compliance or data minimization is required,
        // consider hashing or encrypting this column, or moving it to a separate encrypted store.
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'name')) {
                $table->string('name')->nullable()->after('id');
            }
            if (! Schema::hasColumn('tenants', 'email')) {
                $table->string('email')->nullable()->after('name');
                $table->index('email');
            }
            // Only add status if it doesn't exist (migration may have been updated)
            if (! Schema::hasColumn('tenants', 'status') && ! Schema::hasColumn('tenants', 'is_active')) {
                $table->enum('status', ['Active', 'Suspended'])->default('Active')->after('email');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The plain email index may already be gone — e.g. when this runs as
        // part of a full rollback after 2026_10_17_000003 replaced it with a
        // partial unique index. Guard the drop so the rollback does not abort.
        if (Schema::hasIndex('tenants', 'tenants_email_index')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropIndex('tenants_email_index');
            });
        }

        $columns = [];
        foreach (['name', 'email', 'status'] as $col) {
            if (Schema::hasColumn('tenants', $col)) {
                $columns[] = $col;
            }
        }

        if ($columns !== []) {
            Schema::table('tenants', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
