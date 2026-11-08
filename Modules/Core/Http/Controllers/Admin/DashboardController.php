<?php

namespace Modules\Core\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Services\DashboardService;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboardService) {}

    public function __invoke(Request $request): Response
    {
        return Inertia::render('Core/Home/Index', $this->dashboardService->widgets($request->user()));
    }
}
