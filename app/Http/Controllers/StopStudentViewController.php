<?php

namespace App\Http\Controllers;

use App\Support\StudentView;
use Illuminate\Http\RedirectResponse;

class StopStudentViewController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        StudentView::stop();

        return redirect()->back(fallback: route('entries.index'));
    }
}
