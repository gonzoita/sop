<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsClient
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->hasRole('cliente') && ! $user->hasPermissionTo('access-client-portal')) {
            abort(403, 'No tienes permisos para acceder al portal de clientes.');
        }

        return $next($request);
    }
}
