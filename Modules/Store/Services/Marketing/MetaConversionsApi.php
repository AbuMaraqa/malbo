<?php

namespace Modules\Store\Services\Marketing;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Store\Settings\MarketingSettings;
use Throwable;

/**
 * Posts a request's server events to Meta's Conversions API in one call. Called after the response
 * (MetaEvents defers it): a slow or failing Meta never holds up or breaks a buyer's page, and a PHP
 * worker is held at most about 10 seconds: 2 s to connect, 4 s in all, one retry on a network error
 * or a 5xx. Every failure is logged, never thrown.
 */
class MetaConversionsApi
{
    /**
     * @param  list<array<string, mixed>>  $events  the entries of `data`
     */
    public function send(array $events): void
    {
        $settings = app(MarketingSettings::class);

        if (! $settings->capiReady()) {
            return;
        }

        try {
            $response = Http::acceptJson()
                ->connectTimeout(2)
                ->timeout(4)
                ->retry(1, 200, fn (Throwable $e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException && $e->response->serverError()), throw: false)
                ->post(self::url((string) $settings->meta_pixel_id), array_filter([
                    'data' => array_values($events),
                    'test_event_code' => filled($settings->meta_test_event_code) ? $settings->meta_test_event_code : null,
                    'access_token' => $settings->meta_capi_token,
                ], fn ($value) => $value !== null));

            if ($response->failed()) {
                Log::warning('Meta Conversions API refused an event', [
                    'status' => $response->status(),
                    'events' => array_column($events, 'event_name'),
                    'error' => $response->json('error.message'),
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('Meta Conversions API unreachable: '.$e->getMessage(), ['events' => array_column($events, 'event_name')]);
        }
    }

    public static function url(string $pixelId): string
    {
        return 'https://graph.facebook.com/v'.ltrim((string) config('store.meta.graph_version'), 'v').'/'.rawurlencode($pixelId).'/events';
    }
}
