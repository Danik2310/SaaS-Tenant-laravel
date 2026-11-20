<?php

declare(strict_types=1);

namespace App\Shared\Notifications;

use App\Models\User;
use App\Shared\Constants\NotificationTypes;
use App\Shared\Contracts\NotificationDispatcherInterface;
use Illuminate\Auth\Events\Login;

/**
 * Prompts passwordless tenant users to add a credential when they sign in.
 *
 * Google sign-in is the practical source of passwordless accounts: the handoff
 * calls `Auth::guard('web')->login()`, which fires this event. Hooking `Login`
 * instead of `Authenticated` keeps the prompt to real sign-ins — the JWT guard
 * fires `Authenticated` on every token parse. The same `password:{id}` dedupe
 * key as the nightly sweep keeps the 24-hour cadence consistent.
 */
final class NotifyPasswordlessUserOnLogin
{
    public function __construct(private readonly NotificationDispatcherInterface $dispatcher) {}

    public function handle(Login $event): void
    {
        // JWTGuard reports its internal `tymon.jwt` name for both guards, so the
        // model class — not $event->guard — separates tenant users from admins.
        if (! $event->user instanceof User
            || $event->user->password !== null
            || ! tenancy()->initialized
        ) {
            return;
        }

        $this->dispatcher->dispatch(
            NotificationTypes::PASSWORD_NOT_SET,
            ['body' => 'Add a password so you can sign in without Google.'],
            $event->user->id,
            'password:'.$event->user->id,
        );
    }
}
