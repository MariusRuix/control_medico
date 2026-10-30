<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\FinancialReportRequest;
use App\Services\Reports\FinancialReport;
use App\Services\Reports\FinancialReportExporter;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialReportController extends Controller
{
    public function index(FinancialReportRequest $request, FinancialReport $report): Response
    {
        $period = $request->period();

        return Inertia::render('reports/Finance', [
            'filters' => [
                'from' => $period->from->toDateString(),
                'to' => $period->to->toDateString(),
                'group' => $period->group,
            ],
            'previous' => [
                'from' => $period->previous()->from->toDateString(),
                'to' => $period->previous()->to->toDateString(),
            ],
            'report' => $report->build($period),
        ]);
    }

    public function export(FinancialReportRequest $request, FinancialReportExporter $exporter): StreamedResponse
    {
        /** @var string $dataset */
        $dataset = $request->validated('dataset') ?? 'payments';

        return $exporter->download($dataset, $request->period());
    }
}
