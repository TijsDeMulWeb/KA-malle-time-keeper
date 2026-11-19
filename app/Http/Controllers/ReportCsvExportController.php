<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportExportRequest;
use App\Models\TimeEntry;
use App\Support\Duration;
use Illuminate\Support\Str;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportCsvExportController extends Controller
{
    public function __invoke(ReportExportRequest $request): StreamedResponse
    {
        $report = $request->report();

        $rows = $report->entries()->map(fn (TimeEntry $entry) => [
            'Datum' => $entry->localStartedAt()->format('Y-m-d'),
            'Van' => $entry->localStartedAt()->format('H:i'),
            'Tot' => $entry->localEndedAt()?->format('H:i') ?? '',
            'Duur' => Duration::clock($entry->durationInMinutes()),
            'Minuten' => $entry->durationInMinutes(),
            'Project' => $entry->project->name,
            'Klant' => $entry->project->client ?? '',
            'Categorie' => $entry->category?->name ?? '',
            'Omschrijving' => $entry->description,
        ]);

        $from = $report->period()->from->format('Y-m-d');
        $until = $report->period()->until->subDay()->format('Y-m-d');
        $name = Str::slug($request->subject()->name);

        return response()->streamDownload(function () use ($rows) {
            SimpleExcelWriter::create('php://output', 'csv')
                ->addRows($rows->all())
                ->close();
        }, "uren-{$name}-{$from}-{$until}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
