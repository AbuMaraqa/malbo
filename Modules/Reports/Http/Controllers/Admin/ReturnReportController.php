<?php

namespace Modules\Reports\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Reports\Services\ReturnReport;
use Modules\Reports\Support\ReportFilters;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Return and exchange rates, overall, by product or category, and their reasons.
 */
class ReturnReportController extends Controller
{
    public function __construct(private readonly ReturnReport $report) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Reports/Returns/Index', $this->report->page(ReportFilters::fromRequest($request)));
    }

    public function export(Request $request): StreamedResponse
    {
        return $this->report->export(ReportFilters::fromRequest($request));
    }
}
