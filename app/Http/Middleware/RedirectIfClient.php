<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfClient
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->hasRole('cliente')) {
            // Si un usuario cliente accede al dashboard principal, redirigir a su portal
            if ($request->is('dashboard')) {
                return redirect()->route('portal.runs.index');
            }
        }

        return $next($request);
    }
}
