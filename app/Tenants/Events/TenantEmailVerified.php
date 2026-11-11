<?php

declare(strict_types=1);

namespace App\Tenants\Events;

use App\Models\Tenant;
use App\Tenants\Support\TenantVerificationChannel;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Signals an already-open confirmation screen that the owner just verified.
 *
 * Broadcast synchronously (`ShouldBroadcastNow`) because the waiting page is
 * open right now and a queued job would only add latency. The listener wraps
 * the dispatch in a try/catch so a stopped Reverb server cannot break the
 * verification redirect; the page's 5-second poll remains the fallback.
 */
class TenantEmailVerified implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public string $email,
        public string $verifiedAt,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel(TenantVerificationChannel::for($this->tenant, $this->email));
    }

    public function broadcastAs(): string
    {
        return 'tenant.email.verified';
    }

    public function broadcastWith(): array
    {
        return [
            'verified' => true,
            'verified_at' => $this->verifiedAt,
        ];
    }
}
