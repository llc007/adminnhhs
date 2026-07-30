<?php

namespace App\Http\Middleware;

use App\Models\School;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenanceMode
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        $schoolId = $user->current_school_id;
        if (! $schoolId) {
            $schoolId = $user->schools()->first()?->id ?? School::first()?->id;
        }

        if ($schoolId) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($schoolId);
        }

        // Superadmin and Administrador can bypass maintenance mode to manage the platform
        if ($user->hasRole(['superadmin', 'administrador'])) {
            return $next($request);
        }

        $school = $schoolId ? School::find($schoolId) : null;
        $modulos = $school?->modulos_publicados ?? [];

        $modoMantenimiento = filter_var($modulos['modo_mantenimiento'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($modoMantenimiento) {
            // Allow logout so users aren't trapped
            if ($request->routeIs('logout')) {
                return $next($request);
            }

            $mensaje = $modulos['mensaje_mantenimiento'] ?? 'El sistema se encuentra en mantenimiento programado. Volveremos en breve.';

            return response()->view('errors.maintenance', [
                'mensaje' => $mensaje,
                'school' => $school,
            ], 503);
        }

        return $next($request);
    }
}
