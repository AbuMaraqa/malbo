<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * robots.txt per host. A store lets crawlers in, keeps them out of its admin and points them
 * at its sitemap; the platform host keeps the rules public/robots.txt had.
 */
class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $lines = tenancy()->initialized
            ? ['User-agent: *', 'Disallow: /*/admin', 'Allow: /', '', 'Sitemap: '.route('store.sitemap')]
            : ['User-agent: *', 'Disallow:'];

        return response(implode("\n", $lines)."\n", 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
