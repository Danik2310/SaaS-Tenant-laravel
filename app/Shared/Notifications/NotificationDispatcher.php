<?php

declare(strict_types=1);

namespace App\Shared\Notifications;

use App\Models\Notification;
use App\Shared\Constants\NotificationTypes;
use App\Shared\Contracts\NotificationDispatcherInterface;
use App\Shared\Events\NotificationCreated;
use App\Shared\Support\NotificationCache;
use Illuminate\Support\Facades\Log;

/**
 * Single write path for every in-app notification.
 *
 * Flow:
 *  1. Read the type definition (level, title, recipient permissions).
 *  2. Resolve recipients, applying the dedupe window per user.
 *  3. Insert one row per recipient, invalidate their unread cache and broadcast.
 *
 * A dead Reverb server is logged and ignored: notification delivery must never
 * fail the stock movement or order that triggered it.
 */
final class NotificationDispatcher implements NotificationDispatcherInterface
{
    private const DEDUPE_WINDOW_HOURS = 24;

    public function __construct(private readonly RecipientResolver $recipients) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return int Number of notification rows created.
     */
    public function dispatch(
        string $type,
        array $payload = [],
        ?int $userId = null,
        ?string $dedupeKey = null,
    ): int {
        $definition = NotificationTypes::definition($type);
        $created = 0;

        foreach ($this->recipients->resolve($type, $userId) as $recipient) {
            if ($dedupeKey !== null && $this->alreadyNotified($recipient->id, $type, $dedupeKey)) {
                continue;
            }

            $notification = Notification::create([
                'user_id' => $recipient->id,
                'type' => $type,
                'level' => $definition['level'],
                'title' => $payload['title'] ?? $definition['title'],
                'body' => $payload['body'] ?? null,
                'data' => $payload['data'] ?? [],
                'dedupe_key' => $dedupeKey,
            ]);

            $created++;
            NotificationCache::forget($recipient->id);
            $this->broadcast($notification);
        }

        return $created;
    }

    private function alreadyNotified(int $userId, string $type, string $dedupeKey): bool
    {
        return Notification::query()
            ->where('user_id', $userId)
            ->where('type', $type)
            ->where('dedupe_key', $dedupeKey)
            ->where('created_at', '>=', now()->subHours(self::DEDUPE_WINDOW_HOURS))
            ->exists();
    }

    private function broadcast(Notification $notification): void
    {
        $tenantKey = (string) tenant('id');

        if ($tenantKey === '') {
            return;
        }

        try {
            NotificationCreated::dispatch(
                $tenantKey,
                $notification->id,
                $notification->type,
                $notification->level,
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to broadcast notification', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
