<?php

namespace App\Http\Controllers;

use App\Support\Impersonation;
use Illuminate\Http\RedirectResponse;

class StopImpersonationController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        $impersonator = Impersonation::stop();

        if (! $impersonator) {
            return redirect()->route('login');
        }

        return redirect()->route('admin.users.index');
    }
}
