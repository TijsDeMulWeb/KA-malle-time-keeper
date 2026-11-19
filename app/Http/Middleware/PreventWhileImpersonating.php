<?php

namespace App\Http\Middleware;

use App\Support\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventWhileImpersonating
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Impersonation::isActive()) {
            abort(403, __('Niet beschikbaar terwijl je de app bekijkt als een andere gebruiker.'));
        }

        return $next($request);
    }
}
