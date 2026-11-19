<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportExportRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class ReportPdfExportController extends Controller
{
    public function __invoke(ReportExportRequest $request): Response
    {
        $report = $request->report();

        $from = $report->period()->from->format('Y-m-d');
        $until = $report->period()->until->subDay()->format('Y-m-d');
        $name = Str::slug($request->subject()->name);

        return Pdf::loadView('reports.pdf', [
            'report' => $report,
            'user' => $request->subject(),
        ])->download("rapport-{$name}-{$from}-{$until}.pdf");
    }
}
