<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdateLastActivity
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            // Update last activity if it is null or updated more than 3 hours (180 minutes) ago
            if (! $user->ultimo_ingreso_at || $user->ultimo_ingreso_at->diffInMinutes(now()) >= 180) {
                $user->ultimo_ingreso_at = now();
                $user->save();
            }
        }

        return $next($request);
    }
}
