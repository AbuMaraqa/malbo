<?php

namespace Modules\Reports\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Reports\Services\MerchantReport;
use Modules\Reports\Support\ReportFilters;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Wholesale merchants: orders, revenue, average order, last order, balance.
 */
class MerchantReportController extends Controller
{
    public function __construct(private readonly MerchantReport $report) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Reports/Merchants/Index', $this->report->page(ReportFilters::fromRequest($request)));
    }

    public function export(Request $request): StreamedResponse
    {
        return $this->report->export(ReportFilters::fromRequest($request));
    }
}
