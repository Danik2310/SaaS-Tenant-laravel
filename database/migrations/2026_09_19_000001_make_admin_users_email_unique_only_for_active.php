<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace the plain unique index on admin_users.email with a partial
     * (functional) unique index so a soft-deleted account no longer blocks
     * reusing its email for a new staff member.
     */
    public function up(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->dropUnique('admin_users_email_unique');
        });

        DB::statement('ALTER TABLE admin_users ADD UNIQUE INDEX admin_users_email_unique ((CASE WHEN deleted_at IS NULL THEN email END))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The partial index we are replacing allowed soft-deleted rows to share
        // an email; a plain unique index does not. Release the email on the
        // soft-deleted duplicates first (a live row always keeps it) so the
        // restore cannot fail with a duplicate-key error.
        $this->releaseDuplicateSoftDeletedEmails();

        DB::statement('ALTER TABLE admin_users DROP INDEX admin_users_email_unique');

        Schema::table('admin_users', function (Blueprint $table) {
            $table->unique('email');
        });
    }

    /**
     * Null the email on soft-deleted rows that share it with another row, so a
     * plain unique index can be restored during rollback. At most one live row
     * per email can exist under the partial index, so live accounts are never
     * touched.
     */
    private function releaseDuplicateSoftDeletedEmails(): void
    {
        $duplicates = DB::table('admin_users')
            ->select('email')
            ->whereNotNull('email')
            ->groupBy('email')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('email');

        foreach ($duplicates as $email) {
            $keeperId = DB::table('admin_users')
                ->where('email', $email)
                ->orderByRaw('deleted_at IS NULL DESC')
                ->orderBy('id')
                ->value('id');

            DB::table('admin_users')
                ->where('email', $email)
                ->where('id', '!=', $keeperId)
                ->update(['email' => null]);
        }
    }
};
