<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * `/{locale}` on every host: the storefront home on a store host, the platform landing page on a
 * central host.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request): mixed
    {
        if (tenancy()->initialized) {
            return app()->call(\Modules\Store\Http\Controllers\Storefront\HomeController::class);
        }

        return Inertia::render('Platform/Landing', ['signUpUrl' => url('/'.app()->getLocale().'/register-store')]);
    }
}
