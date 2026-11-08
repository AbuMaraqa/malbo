<?php

use Illuminate\Support\Facades\Broadcast;

/*
| One Reverb app serves every store and user ids repeat between stores, so the channel names the
| store too: user 1 of one store must never hear user 1 of another. /broadcasting/auth runs in the
| `tenant` group, so tenant() is the store of the host the browser is on.
*/
Broadcast::channel('store.{store}.users.{id}', function ($user, string $store, string $id) {
    return tenancy()->initialized
        && (string) tenant()->getTenantKey() === $store
        && (string) $user->getAuthIdentifier() === $id;
});
