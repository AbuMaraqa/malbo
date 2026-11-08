<?php

namespace App\Support;

use Modules\Core\Entities\User;

/**
 * Live notifications through Reverb. Off, without a sound, unless BROADCAST_CONNECTION is `reverb`
 * and the app has a key: with `log` or `null` nothing is broadcast and the browser loads no client.
 */
class Realtime
{
    public static function enabled(): bool
    {
        return config('broadcasting.default') === 'reverb'
            && filled(config('broadcasting.connections.reverb.key'));
    }

    /**
     * What the browser needs to listen for this user's notifications; only public values.
     *
     * @return array{key: string, host: string|null, port: int, tls: bool, channel: string}|null
     */
    public static function client(?User $user): ?array
    {
        if ($user === null || ! self::enabled()) {
            return null;
        }

        $options = config('broadcasting.connections.reverb.options');

        return [
            'key' => config('broadcasting.connections.reverb.key'),
            'host' => $options['host'] ?? null,
            'port' => (int) ($options['port'] ?? 443),
            'tls' => ($options['scheme'] ?? 'https') === 'https',
            'channel' => $user->receivesBroadcastNotificationsOn(),
        ];
    }
}
