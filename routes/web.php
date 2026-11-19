<?php

use App\Http\Controllers\InvitationsController;
use App\Http\Controllers\ReportCsvExportController;
use App\Http\Controllers\ReportPdfExportController;
use App\Http\Controllers\StartImpersonationController;
use App\Http\Controllers\StartStudentViewController;
use App\Http\Controllers\StopImpersonationController;
use App\Http\Controllers\StopStudentViewController;
use App\Http\Middleware\PreventWhileImpersonating;
use App\Models\Category;
use App\Models\Project;
use App\Models\SchoolYear;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::redirect('/', 'uren')->name('home');

Route::middleware(['guest', 'signed'])->group(function () {
    Route::get('uitnodiging/{user}', [InvitationsController::class, 'show'])->name('invitations.show');

    Route::put('uitnodiging/{user}', [InvitationsController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('invitations.update');
});

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

    Route::livewire('beheer/gebruikers', 'pages::admin.users.index')
        ->can('viewAny', User::class)
        ->name('admin.users.index');

    Route::post('beheer/gebruikers/{user}/bekijken-als', StartImpersonationController::class)
        ->can('impersonate', 'user')
        ->middleware(PreventWhileImpersonating::class)
        ->name('admin.users.impersonate');

    Route::delete('bekijken-als', StopImpersonationController::class)->name('impersonation.destroy');

    Route::post('studentweergave', StartStudentViewController::class)
        ->can('viewAny', User::class)
        ->middleware(PreventWhileImpersonating::class)
        ->name('student-view.store');

    Route::delete('studentweergave', StopStudentViewController::class)->name('student-view.destroy');

    Route::livewire('beheer/gebruikers/{user}', 'pages::reports.index')
        ->can('view', 'user')
        ->name('admin.users.show');

    Route::get('beheer/gebruikers/{user}/rapport/export/csv', ReportCsvExportController::class)->name('admin.users.reports.export.csv');
    Route::get('beheer/gebruikers/{user}/rapport/export/pdf', ReportPdfExportController::class)->name('admin.users.reports.export.pdf');

    Route::livewire('beheer/schooljaren', 'pages::admin.school-years.index')
        ->can('viewAny', SchoolYear::class)
        ->name('admin.school-years.index');

    Route::livewire('beheer/schooljaren/{schoolYear}', 'pages::admin.school-years.show')
        ->can('view', 'schoolYear')
        ->name('admin.school-years.show');

    Route::livewire('beheer/klassen/{schoolClass}', 'pages::admin.school-classes.show')
        ->can('view', 'schoolClass')
        ->name('admin.school-classes.show');

    Route::livewire('beheer/categorieen', 'pages::admin.categories.index')
        ->can('viewAny', Category::class)
        ->name('admin.categories.index');
});

require __DIR__.'/settings.php';
