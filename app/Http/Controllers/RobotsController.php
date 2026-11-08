<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * robots.txt: crawlers may visit the storefront, never the admin, and get the sitemap.
 */
class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $lines = ['User-agent: *', 'Disallow: /*/admin', 'Allow: /', '', 'Sitemap: '.route('store.sitemap')];

        return response(implode("\n", $lines)."\n", 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
