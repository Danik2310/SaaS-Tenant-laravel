<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bring the tenant `activity_log` table in line with its central counterpart.
 *
 * The central tree applied both of these fixes but the tenant tree never did:
 *   - 2026_05_09_000003  → subject_id/causer_id become VARCHAR(64) so UUID and
 *                           other non-numeric subjects can be recorded.
 *   - 2026_08_10_000002  → adds the created_at index that ActivityLogController
 *                           relies on for whereDate()/orderBy().
 *
 * Leaving the tenant tree on BIGINT morph ids meant a tenant activity log
 * recording a UUID subject would fail or truncate, and ordering activity by
 * date had no index.
 */
return new class extends Migration
{
    private const TABLE = 'activity_log';

    private const MORPH_COLUMNS = ['subject_id', 'causer_id'];

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        $this->morphColumnsAre('VARCHAR(64)');

        if (
            Schema::hasColumn(self::TABLE, 'created_at')
            && ! Schema::hasIndex(self::TABLE, 'activity_log_created_at_index')
        ) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        if (Schema::hasIndex(self::TABLE, 'activity_log_created_at_index')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropIndex('activity_log_created_at_index');
            });
        }

        // Reverting the column type is lossless: existing numeric ids still fit.
        $this->morphColumnsAre('BIGINT UNSIGNED');
    }

    /**
     * Re-cast the morph id columns to the given SQL type (MySQL only).
     *
     * The type change is what mirrors the central migration; without it the two
     * trees disagree on a schema both ActivityLogger instances write to.
     */
    private function morphColumnsAre(string $type): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::MORPH_COLUMNS as $column) {
            if (Schema::hasColumn(self::TABLE, $column)) {
                DB::statement("ALTER TABLE activity_log MODIFY {$column} {$type} NULL");
            }
        }
    }
};
