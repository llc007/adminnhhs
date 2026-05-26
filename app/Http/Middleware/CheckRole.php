<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (! $request->user()) {
            return redirect('login');
        }

        // If no roles passed, just ensure they are logged in
        if (empty($roles)) {
            return $next($request);
        }

        // If the user has ONLY the default 'externo' role (newly registered and unauthorized),
        // redirect them to the access request page rather than showing a static 403 error.
        if ($request->user()->hasRole(['externo']) && ! $request->user()->hasRole(['docente', 'inspector', 'administrador', 'directivo', 'superadmin', 'asistente', 'psicosocial', 'recepcion'])) {
            return redirect()->route('sin-permiso');
        }

        // If the user does not have ANY of the allowed roles, check for landing redirects or throw 403
        if (! $request->user()->hasRole($roles)) {
            // If they are attempting to visit the dashboard but are an inspector or receptionist,
            // redirect them to their respective functional home route instead of throwing a 403.
            if ($request->routeIs('dashboard') || $request->routeIs('entrevistas.dashboard')) {
                if ($request->user()->hasRole(['inspector', 'recepcion'])) {
                    return redirect()->route('entrevistas.recepcion');
                }
                if ($request->user()->hasRole(['docente', 'asistente', 'psicosocial'])) {
                    return redirect()->route('entrevistas.agenda');
                }
            }

            abort(403, 'No tienes los permisos necesarios para acceder a esta área. Si eres un funcionario, solicita acceso a administración.');
        }

        return $next($request);
    }
}
