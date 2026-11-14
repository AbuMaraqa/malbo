<?php

namespace Modules\Reports\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Reports\Services\ChannelReport;
use Modules\Reports\Support\ReportFilters;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The store (B2C) against wholesale (B2B), side by side.
 */
class ChannelReportController extends Controller
{
    public function __construct(private readonly ChannelReport $report) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Reports/Channels/Index', $this->report->page(ReportFilters::fromRequest($request)));
    }

    public function export(Request $request): StreamedResponse
    {
        return $this->report->export(ReportFilters::fromRequest($request));
    }
}
