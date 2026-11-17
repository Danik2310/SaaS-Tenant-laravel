<?php

declare(strict_types=1);

namespace App\Shared\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Tells an open tenant dashboard that a notification has just been written.
 *
 * Broadcast synchronously because the point is a live update; the dispatcher
 * swallows Reverb failures so a stopped socket server never breaks a business
 * action. The payload carries identifiers only — the client re-fetches through
 * an authenticated Inertia request, so no notification text rides the socket.
 */
class NotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $tenantKey,
        public readonly int $notificationId,
        public readonly string $type,
        public readonly string $level,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('tenant-notifications.'.$this->tenantKey);
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    /** @return array<string, int|string> */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->notificationId,
            'type' => $this->type,
            'level' => $this->level,
        ];
    }
}
