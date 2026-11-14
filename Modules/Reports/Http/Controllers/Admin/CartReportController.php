<?php

namespace Modules\Reports\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Reports\Services\CartReport;
use Modules\Reports\Support\ReportFilters;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Store carts: started, ordered, abandoned, reminded and recovered.
 */
class CartReportController extends Controller
{
    public function __construct(private readonly CartReport $report) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Reports/Carts/Index', $this->report->page(ReportFilters::fromRequest($request)));
    }

    public function export(Request $request): StreamedResponse
    {
        return $this->report->export(ReportFilters::fromRequest($request));
    }
}
