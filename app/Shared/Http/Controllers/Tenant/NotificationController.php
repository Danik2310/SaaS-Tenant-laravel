<?php

declare(strict_types=1);

namespace App\Shared\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Shared\Support\NotificationCache;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The tenant's notification inbox. Every query is scoped to the authenticated
 * user — notification ids are never trusted as an ownership proof.
 */
class NotificationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Tenant/Notifications/Index', [
            'notifications' => Notification::query()
                ->where('user_id', auth()->id())
                ->latest()
                ->paginate(15),
        ]);
    }

    public function markRead(Notification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === auth()->id(), 403);

        $notification->markRead();
        NotificationCache::forget(auth()->id());

        return back();
    }

    public function markAllRead(): RedirectResponse
    {
        Notification::query()
            ->where('user_id', auth()->id())
            ->unread()
            ->update(['read_at' => now()]);

        NotificationCache::forget(auth()->id());

        return back();
    }
}
