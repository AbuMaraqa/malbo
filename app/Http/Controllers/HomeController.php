<?php

namespace App\Http\Controllers;

/**
 * `/{locale}`: the storefront home. Resolved for each request, so the storefront controller and
 * the layout it memoizes are never reused by a later request (Laravel keeps a route's controller).
 */
class HomeController extends Controller
{
    public function __invoke(): mixed
    {
        return app()->call(\Modules\Store\Http\Controllers\Storefront\HomeController::class);
    }
}
