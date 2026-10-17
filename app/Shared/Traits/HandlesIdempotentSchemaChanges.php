<?php

declare(strict_types=1);

namespace App\Shared\Traits;

use Illuminate\Database\QueryException;
use Throwable;

/**
 * Lets a schema migration skip an operation that is already satisfied
 * (index/constraint already exists, or already dropped) while still
 * surfacing unexpected failures.
 *
 * The previous `catch (Throwable)` idiom swallowed *every* error — including
 * genuine ones such as a foreign-key violation caused by orphan rows — and
 * reported the migration as successful even when the schema change never
 * applied. Guard with this instead so real problems propagate:
 *
 *   try {
 *       Schema::table('t', fn (Blueprint $b) => $b->index('x'));
 *   } catch (Throwable $e) {
 *       if (! $this->isBenignSchemaError($e)) {
 *           throw $e;
 *       }
 *   }
 */
trait HandlesIdempotentSchemaChanges
{
    /**
     * Driver error numbers that mean the schema is already in the target state.
     *
     * MySQL only — other drivers fall through to the message check below.
     * Deliberately excludes data errors such as 1062 (duplicate entry) and
     * 121 (duplicate key on write): those must never be silently ignored.
     */
    private const BENIGN_DRIVER_ERRORS = [
        1050, // table already exists
        1060, // duplicate column name
        1061, // duplicate key name
        1091, // can't DROP; check that column/key exists
        1167, // storage engine doesn't have this index
        1826, // duplicate foreign key constraint name
    ];

    protected function isBenignSchemaError(Throwable $exception): bool
    {
        if ($exception instanceof QueryException) {
            $driverCode = $exception->errorInfo[1] ?? null;

            if ($driverCode !== null && in_array((int) $driverCode, self::BENIGN_DRIVER_ERRORS, true)) {
                return true;
            }
        }

        $message = strtolower($exception->getMessage());

        foreach ([
            'already exists',
            'duplicate key name',
            'duplicate foreign key',
            'duplicate column name',
            'cannot drop',
            'check that column/key exists',
            'no such table',
            'no such index',
        ] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }
}
