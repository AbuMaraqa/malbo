<?php

namespace Modules\Store\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Store\Services\Marketing\CatalogFeed;

/**
 * The product catalog for Facebook and Instagram, as XML (RSS 2.0, `g:` fields) or CSV. Public,
 * unless the store set a token: then a request without it gets a 404.
 */
class CatalogFeedController
{
    public function __construct(private readonly CatalogFeed $feed) {}

    public function xml(Request $request): Response
    {
        abort_unless($this->feed->allows($request->query('token')), 404);

        return response($this->feed->xml(), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function csv(Request $request): Response
    {
        abort_unless($this->feed->allows($request->query('token')), 404);

        return response($this->feed->csv(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="meta-catalog.csv"',
        ]);
    }
}
