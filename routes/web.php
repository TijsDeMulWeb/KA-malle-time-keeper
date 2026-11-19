<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', 'uren')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::livewire('uren', 'pages::entries.index')->name('entries.index');
    Route::livewire('projecten', 'pages::projects.index')->name('projects.index');
    Route::livewire('rapport', 'pages::reports.index')->name('reports.index');
});

require __DIR__.'/settings.php';
