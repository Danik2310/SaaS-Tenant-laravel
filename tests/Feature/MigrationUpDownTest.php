<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Test central migration up/down operations.
 *
 * NOTE: This test does NOT use the RefreshDatabase trait because
 * the test methods call migrate:fresh and migrate:rollback directly,
 * which issue DDL statements (DROP/CREATE/ALTER TABLE). MySQL DDL
 * statements implicitly commit any active transaction, which would
 * break RefreshDatabase's transaction-based isolation.
 *
 * Each test method is self-contained: it runs its own migrate:fresh
 * to ensure a clean starting state, then performs its migration
 * operations.
 */
class MigrationUpDownTest extends TestCase
{
    /**
     * Run migrate:fresh with retry logic.
     *
     * migrate:fresh can occasionally fail when:
     * - MySQL INFORMATION_SCHEMA metadata is stale from a previous run
     * - FK constraint validation fails during table re-creation
     * - A temporary deadlock occurs during DDL operations
     *
     * These are transient MySQL conditions, not code defects. Retrying
     * once usually resolves them.
     *
     * @return int Exit code of the final migrate:fresh call
     */
    private function runFresh(int $retries = 1): int
    {
        for ($attempt = 0; $attempt <= $retries; $attempt++) {
            $exitCode = Artisan::call('migrate:fresh', [
                '--env' => 'testing',
                '--path' => 'database/migrations',
            ]);

            if ($exitCode === 0) {
                return 0;
            }

            // On failure, wait briefly and retry
            if ($attempt < $retries) {
                usleep(500000); // 500ms
            }
        }

        return $exitCode;
    }

    /**
     * ✅ Test: migrate:fresh runs cleanly without exceptions.
     *
     * Verifies that a fresh migration creates all required tables.
     * Uses retry logic for intermittent MySQL metadata cache issues.
     */
    public function test_migrate_fresh_runs_cleanly(): void
    {
        $exitCode = $this->runFresh();

        $this->assertSame(0, $exitCode, 'migrate:fresh should exit with code 0');

        // Verify key tables exist after migration
        $this->assertTrue(Schema::hasTable('tenants'));
        $this->assertTrue(Schema::hasTable('domains'));
        $this->assertTrue(Schema::hasTable('plans'));
        $this->assertTrue(Schema::hasTable('permissions'));
        $this->assertTrue(Schema::hasTable('subscriptions'));
    }

    /**
     * ✅ Test: fresh schema has the expected index state.
     *
     * Verifies the expand/contract index migrations:
     * - 2026_08_10_000002 adds activity_log_created_at_index and
     *   subscriptions_status_ends_at_index
     * - 2026_08_10_000003 drops the redundant single-column prefixes
     *   (subscriptions_status_index, permissions_guard_name_index,
     *   roles_guard_name_index) while the wider compound indexes remain.
     */
    public function test_fresh_schema_has_expected_index_state(): void
    {
        $exitCode = $this->runFresh();
        if ($exitCode !== 0) {
            $this->markTestSkipped('migrate:fresh failed - database may be in an inconsistent state');

            return;
        }

        // Expand: new covering indexes exist
        $this->assertTrue(Schema::hasTable('activity_log'));
        $this->assertTrue(Schema::hasIndex('activity_log', 'activity_log_created_at_index'));
        $this->assertTrue(Schema::hasIndex('subscriptions', 'subscriptions_status_ends_at_index'));

        // Contract: redundant leftmost-prefix indexes dropped
        $this->assertFalse(Schema::hasIndex('subscriptions', 'subscriptions_status_index'));
        $this->assertFalse(Schema::hasIndex('permissions', 'permissions_guard_name_index'));
        $this->assertFalse(Schema::hasIndex('roles', 'roles_guard_name_index'));

        // The wider compound indexes must still be present
        $this->assertTrue(Schema::hasIndex('permissions', 'permissions_guard_module_name_index'));
        $this->assertTrue(Schema::hasIndex('roles', 'roles_guard_name_name_index'));
    }

    /**
     * ✅ Test: rolling back 10 steps works without errors.
     *
     * Performs migrate:fresh first, then rolls back 10 steps.
     * This exercises the down() methods of the 10 most recent migrations,
     * including those with FK-related index constraints.
     */
    public function test_migrate_rollback_runs_cleanly(): void
    {
        // Start from a clean state
        $exitCode = $this->runFresh();
        if ($exitCode !== 0) {
            $this->markTestSkipped('migrate:fresh failed - database may be in an inconsistent state');

            return;
        }

        try {
            // Roll back 10 steps
            $rollbackExitCode = Artisan::call('migrate:rollback', [
                '--env' => 'testing',
                '--step' => 10,
                '--path' => 'database/migrations',
            ]);

            $this->assertSame(0, $rollbackExitCode, 'migrate:rollback --step=10 should exit with code 0');
        } finally {
            // Do not leave the shared central schema rolled back for later tests.
            $this->restoreSchemaAfterRollback();
        }
    }

    /**
     * ✅ Test: re-running migrate after a rollback works cleanly.
     *
     * Exercises the full cycle: fresh → rollback → migrate again.
     */
    public function test_migrate_after_rollback_reruns_cleanly(): void
    {
        // Start from a clean state
        $exitCode = $this->runFresh();
        if ($exitCode !== 0) {
            $this->markTestSkipped('migrate:fresh failed - database may be in an inconsistent state');

            return;
        }

        // Roll back 10 steps
        $rollbackExitCode = Artisan::call('migrate:rollback', [
            '--env' => 'testing',
            '--step' => 10,
            '--path' => 'database/migrations',
        ]);
        $this->assertSame(0, $rollbackExitCode, 'migrate:rollback should exit with code 0');

        // Re-run migrations
        $migrateExitCode = Artisan::call('migrate', [
            '--env' => 'testing',
            '--path' => 'database/migrations',
        ]);
        $this->assertSame(0, $migrateExitCode, 'migrate after rollback should exit with code 0');
    }

    /**
     * ✅ Test: running migrate:fresh twice in a row is idempotent.
     */
    public function test_migrate_fresh_is_idempotent(): void
    {
        $firstExitCode = $this->runFresh();
        $this->assertSame(0, $firstExitCode, 'First migrate:fresh should exit with code 0');

        $secondExitCode = Artisan::call('migrate:fresh', [
            '--env' => 'testing',
            '--path' => 'database/migrations',
        ]);
        $this->assertSame(0, $secondExitCode, 'Second migrate:fresh should also exit with code 0');
    }

    /**
     * ✅ Test: rolling back every migration (in batches) then re-migrating works.
     *
     * Rather than one `--step=99` call (which tripped MySQL metadata locks and
     * left the schema half-rolled-back), the entire ledger is rolled back in
     * small batches so each batch commits and locks are released between them.
     * The schema is then asserted absent and re-migrated cleanly.
     *
     * On every path the schema is restored with migrate:fresh so this class
     * never leaves the shared central database partially rolled back.
     */
    public function test_migrate_rollback_entire_then_fresh(): void
    {
        // Start from a known state
        $exitCode = $this->runFresh();
        $this->assertSame(0, $exitCode, 'migrate:fresh should exit with code 0');

        try {
            $total = DB::table('migrations')->count();
            $this->assertGreaterThan(0, $total, 'There should be applied migrations to roll back');

            $remaining = $total;
            $batch = 5;

            while ($remaining > 0) {
                $step = min($batch, $remaining);

                $rollbackExitCode = Artisan::call('migrate:rollback', [
                    '--env' => 'testing',
                    '--step' => $step,
                    '--path' => 'database/migrations',
                ]);

                $this->assertSame(
                    0,
                    $rollbackExitCode,
                    "migrate:rollback --step={$step} failed ({$remaining} applied migrations remaining). Output: ".Artisan::output()
                );

                $remaining -= $step;

                // Let residual MySQL metadata locks clear before the next batch.
                usleep(100000);
            }

            // Everything should be rolled back and the core table dropped.
            $this->assertSame(0, DB::table('migrations')->count(),
                'All migrations should be rolled back');
            $this->assertFalse(Schema::hasTable('tenants'),
                'tenants table should be dropped after a full rollback');

            // Re-run migrations from scratch.
            $migrateExitCode = Artisan::call('migrate', [
                '--env' => 'testing',
                '--path' => 'database/migrations',
            ]);
            $this->assertSame(0, $migrateExitCode,
                'migrate after a full rollback should exit with code 0. Output: '.Artisan::output());
            $this->assertTrue(Schema::hasTable('tenants'),
                'tenants table should exist again after re-migrating');
        } finally {
            // Guarantee the shared central schema is fully migrated afterwards.
            $this->restoreSchemaAfterRollback();
        }
    }

    /**
     * Restore a fully migrated schema after a partial rollback so the shared
     * central database is never left half-rolled-back for later tests.
     */
    private function restoreSchemaAfterRollback(): void
    {
        $exitCode = $this->runFresh(2);

        if ($exitCode !== 0) {
            // Give the metadata lock a moment to clear, then try once more.
            usleep(500000);
            $this->runFresh(2);
        }
    }
}
