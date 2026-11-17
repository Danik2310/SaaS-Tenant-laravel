<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
 * Every authenticated member of a tenant may listen to its notification
 * channel. The tenant key in the URL is compared with the initialized tenant,
 * so a membership in another workspace cannot subscribe here. The payload
 * carries identifiers only — content is fetched over an authenticated route.
 */
Broadcast::channel('tenant-notifications.{tenantKey}', function ($user, string $tenantKey) {
    return (string) tenant('id') === $tenantKey;
});
