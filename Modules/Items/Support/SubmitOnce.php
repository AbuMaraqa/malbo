<?php

namespace Modules\Items\Support;

use Closure;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Forms whose rows have no natural unique value (an order, an offer without a code): the page gets a
 * one-time token and the first request carrying it wins, so a double click creates one record.
 * A failed attempt gives the token back, so the user can fix the problem and send the form again.
 */
final class SubmitOnce
{
    private const SECONDS = 86400;

    /**
     * Runs $action unless this token was already used.
     *
     * @return bool whether it ran
     */
    public static function run(string $scope, string $token, Closure $action): bool
    {
        $key = "submit-once:{$scope}:{$token}";

        if (! Cache::add($key, true, self::SECONDS)) {
            return false;
        }

        try {
            $action();
        } catch (Throwable $e) {
            Cache::forget($key);

            throw $e;
        }

        return true;
    }
}
