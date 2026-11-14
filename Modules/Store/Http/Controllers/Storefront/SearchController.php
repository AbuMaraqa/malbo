<?php

namespace Modules\Store\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Store\Services\StorefrontSearch;

/**
 * The header search box's suggestions, as JSON. Public and throttled (`store-search`).
 */
class SearchController extends Controller
{
    public function __construct(private readonly StorefrontSearch $search) {}

    public function suggest(Request $request): JsonResponse
    {
        return response()->json($this->search->suggest($request->query('q')));
    }
}
