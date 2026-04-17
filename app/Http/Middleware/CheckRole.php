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
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
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

        // Student explicitly trying to access system routes
        // If the user does not have ANY of the allowed roles, throw 403 or redirect
        if (! $request->user()->hasRole($roles)) {
            abort(403, 'No tienes los permisos necesarios para acceder a esta área. Si eres un funcionario, solicita acceso a administración.');
        }

        return $next($request);
    }
}
