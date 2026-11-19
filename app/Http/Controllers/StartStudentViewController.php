<?php

namespace App\Http\Controllers;

use App\Support\StudentView;
use Illuminate\Http\RedirectResponse;

class StartStudentViewController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        StudentView::start();

        return redirect()->route('entries.index');
    }
}
