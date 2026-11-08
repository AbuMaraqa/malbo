<?php

use Illuminate\Support\Facades\Broadcast;

// A user's own notifications.
Broadcast::channel('users.{id}', function ($user, string $id) {
    return (string) $user->getAuthIdentifier() === $id;
});
