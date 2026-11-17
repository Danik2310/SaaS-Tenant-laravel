<?php

declare(strict_types=1);

namespace App\Shared\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateNotificationSettingsRequest;
use App\Models\Setting;
use App\Shared\Support\NotificationSettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Tenant-wide alert thresholds for the three v1 notification types. */
class NotificationSettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Tenant/Notifications/Settings', [
            'settings' => NotificationSettings::current(),
        ]);
    }

    public function update(UpdateNotificationSettingsRequest $request): RedirectResponse
    {
        foreach ($request->validated() as $key => $value) {
            Setting::updateOrCreate(
                ['key' => NotificationSettings::keyFor($key)],
                ['value' => $value],
            );
        }

        return back()->with('success', 'Notification settings saved.');
    }
}
