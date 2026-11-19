<?php

use App\Http\Controllers\ReportCsvExportController;
use App\Http\Controllers\ReportPdfExportController;
use App\Models\Project;
use App\Models\TimeEntry;
use Illuminate\Support\Facades\Route;

Route::redirect('/', 'uren')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::livewire('uren', 'pages::entries.index')
        ->can('viewAny', TimeEntry::class)
        ->name('entries.index');

    Route::livewire('projecten', 'pages::projects.index')
        ->can('viewAny', Project::class)
        ->name('projects.index');

    Route::livewire('rapport', 'pages::reports.index')
        ->can('viewAny', TimeEntry::class)
        ->name('reports.index');

    Route::get('rapport/export/csv', ReportCsvExportController::class)->name('reports.export.csv');
    Route::get('rapport/export/pdf', ReportPdfExportController::class)->name('reports.export.pdf');
});

require __DIR__.'/settings.php';
