<?php

namespace Modules\Reports\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Reports\Services\ConversionReport;
use Modules\Reports\Support\ReportFilters;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Cart-to-order conversion and average order value per channel.
 */
class ConversionReportController extends Controller
{
    public function __construct(private readonly ConversionReport $report) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Reports/Conversion/Index', $this->report->page(ReportFilters::fromRequest($request)));
    }

    public function export(Request $request): StreamedResponse
    {
        return $this->report->export(ReportFilters::fromRequest($request));
    }
}
