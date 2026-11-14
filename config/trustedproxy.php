<?php

/**
 * Proxies whose X-Forwarded-* headers are believed (Laravel's TrustProxies middleware reads
 * `trustedproxy.proxies` when nothing else is set). The client address they pass on is what rate
 * limits count by, so trusting anyone would let a buyer dodge a limit with a made-up header.
 *
 * TRUSTED_PROXIES: a comma list of addresses or CIDR ranges (the load balancer or CDN), or `*` to
 * trust the calling address. Unset: trust none.
 */
return [
    'proxies' => filled($proxies = env('TRUSTED_PROXIES')) ? $proxies : null,
];
