<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Shared\Traits\HandlesIdempotentSchemaChanges;
use Illuminate\Database\QueryException;
use PDOException;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class HandlesIdempotentSchemaChangesTest extends TestCase
{
    private object $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new class
        {
            use HandlesIdempotentSchemaChanges;

            public function isBenign(Throwable $exception): bool
            {
                return $this->isBenignSchemaError($exception);
            }
        };
    }

    public function test_driver_code_for_missing_column_is_benign(): void
    {
        $exception = $this->queryException(1091, "Can't DROP 'is_main_admin'; check that column/key exists");

        $this->assertTrue($this->subject->isBenign($exception));
    }

    public function test_driver_code_for_existing_table_is_benign(): void
    {
        $this->assertTrue($this->subject->isBenign($this->queryException(1050, 'Table \'plans\' already exists')));
    }

    public function test_driver_code_for_duplicate_key_name_is_benign(): void
    {
        $this->assertTrue($this->subject->isBenign($this->queryException(1061, 'Duplicate key name \'plans_slug_unique\'')));
    }

    public function test_duplicate_entry_is_never_swallowed(): void
    {
        $exception = $this->queryException(
            1062,
            "Duplicate entry 'jane@acme.test' for key 'tenants.tenants_email_unique'"
        );

        $this->assertFalse($this->subject->isBenign($exception));
    }

    public function test_foreign_key_violation_is_never_swallowed(): void
    {
        $exception = $this->queryException(
            1452,
            'Cannot add or update a child row: a foreign key constraint fails'
        );

        $this->assertFalse($this->subject->isBenign($exception));
    }

    public function test_driver_code_falls_back_to_the_message_when_error_info_is_absent(): void
    {
        $exception = $this->queryException(null, 'SQLSTATE[42S01]: Base table or view already exists');

        $this->assertTrue($this->subject->isBenign($exception));
    }

    public function test_unrelated_connection_failure_is_not_benign(): void
    {
        $this->assertFalse(
            $this->subject->isBenign(new RuntimeException('SQLSTATE[HY000] [2002] Connection refused'))
        );
    }

    public function test_message_only_exception_with_benign_text_is_benign(): void
    {
        $this->assertTrue(
            $this->subject->isBenign(new RuntimeException('no such table: main.users'))
        );
    }

    private function queryException(?int $driverCode, string $message): QueryException
    {
        $previous = new PDOException($message);

        if ($driverCode !== null) {
            $previous->errorInfo = ['HY000', $driverCode, $message];
        }

        return new QueryException(
            'mysql',
            'alter table `admin_users` add index `plans_slug_unique` (`slug`)',
            [],
            $previous
        );
    }
}
