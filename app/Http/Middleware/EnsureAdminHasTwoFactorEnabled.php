<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminHasTwoFactorEnabled
{
    /**
     * Handle an incoming request.
     * Enforce two-factor authentication for users with the admin role.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->hasRole('admin')) {
            if (! $user->hasEnabledTwoFactorAuthentication()) {
                // Permitir rutas de perfil, 2FA setup y logout
                if ($request->routeIs('profile.show') ||
                    $request->is('user/two-factor-authentication*') ||
                    $request->is('user/confirmed-two-factor-authentication*') ||
                    $request->is('logout')
                ) {
                    return $next($request);
                }

                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'La autenticación de dos factores (2FA) es obligatoria para el rol Administrador.',
                        'requires_two_factor' => true,
                    ], 403);
                }

                return redirect()->route('profile.show')->with('warning', 'La autenticación de dos factores (2FA) es obligatoria para los administradores. Por favor, actívala para acceder al panel de administración.');
            }
        }

        return $next($request);
    }
}