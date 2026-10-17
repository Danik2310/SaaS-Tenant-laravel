<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use ReflectionMethod;
use Tests\TestCase;

/**
 * `47badcf` releases duplicate soft-deleted admin emails before the partial
 * unique index on admin_users.email is rolled back to a plain unique index —
 * otherwise `down()` dies with a duplicate-key error.
 */
class AdminEmailRollbackReleaseTest extends TestCase
{
    public function test_release_nulls_email_on_soft_deleted_duplicate_but_never_on_the_live_row(): void
    {
        $keeper = AdminUser::factory()->create(['email' => 'shared@acme.test']);

        $duplicate = AdminUser::factory()->create(['email' => 'other@acme.test']);
        $duplicate->delete();
        $duplicate->forceFill(['email' => 'shared@acme.test'])->save();

        $this->assertSame(
            2,
            AdminUser::withTrashed()->where('email', 'shared@acme.test')->count(),
            'setup: a live row and a soft-deleted row must share the email'
        );

        $this->releaseDuplicateSoftDeletedEmails();

        $this->assertSame('shared@acme.test', AdminUser::query()->whereKey($keeper->id)->value('email'));
        $this->assertNull(
            AdminUser::withTrashed()->whereKey($duplicate->id)->value('email'),
            'the soft-deleted duplicate must give up the email so the plain unique index can be restored'
        );
    }

    public function test_release_leaves_a_single_soft_deleted_row_untouched(): void
    {
        $sole = AdminUser::factory()->create(['email' => 'solo@acme.test']);
        $sole->delete();

        $this->releaseDuplicateSoftDeletedEmails();

        $this->assertSame('solo@acme.test', AdminUser::withTrashed()->whereKey($sole->id)->value('email'));
    }

    public function test_release_keeps_the_live_row_even_when_it_sorts_last_by_id(): void
    {
        $deletedFirst = AdminUser::factory()->create(['email' => 'pair@acme.test']);
        $deletedFirst->delete();

        $live = AdminUser::factory()->create(['email' => 'pair@acme.test']);
        $this->assertGreaterThan($deletedFirst->id, $live->id, 'setup: the live row must sort after the trashed one');

        $this->releaseDuplicateSoftDeletedEmails();

        $this->assertSame('pair@acme.test', AdminUser::query()->whereKey($live->id)->value('email'));
        $this->assertNull(AdminUser::withTrashed()->whereKey($deletedFirst->id)->value('email'));
    }

    private function releaseDuplicateSoftDeletedEmails(): void
    {
        $migration = include base_path(
            'database/migrations/2026_09_19_000001_make_admin_users_email_unique_only_for_active.php'
        );

        $method = new ReflectionMethod($migration, 'releaseDuplicateSoftDeletedEmails');
        $method->setAccessible(true);
        $method->invoke($migration);
    }
}
