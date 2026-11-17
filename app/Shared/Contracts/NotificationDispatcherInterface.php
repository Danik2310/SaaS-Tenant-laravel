<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

/**
 * Entry point for writing in-app notifications.
 *
 * Consumers (observers, commands, controllers) depend on this contract so the
 * notification subsystem can be replaced without a concrete cross-module
 * import.
 */
interface NotificationDispatcherInterface
{
    /**
     * @param  array<string, mixed>  $payload
     * @return int Number of notification rows created.
     */
    public function dispatch(
        string $type,
        array $payload = [],
        ?int $userId = null,
        ?string $dedupeKey = null,
    ): int;
}
