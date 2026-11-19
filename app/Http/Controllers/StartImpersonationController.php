<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StartImpersonationController extends Controller
{
    public function __invoke(Request $request, User $user): RedirectResponse
    {
        Impersonation::start($request->user(), $user);

        return redirect()->route('entries.index');
    }
}
