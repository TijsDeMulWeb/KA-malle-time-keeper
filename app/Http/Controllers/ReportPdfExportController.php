<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportExportRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class ReportPdfExportController extends Controller
{
    public function __invoke(ReportExportRequest $request): Response
    {
        $report = $request->report();

        $from = $report->period()->from->format('Y-m-d');
        $until = $report->period()->until->subDay()->format('Y-m-d');

        return Pdf::loadView('reports.pdf', [
            'report' => $report,
            'user' => $request->user(),
        ])->download("rapport-{$from}-{$until}.pdf");
    }
}
