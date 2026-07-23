<?php

namespace App\Http\Middleware;

use App\Models\School;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class SetPermissionsTeamId
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();
            $schoolId = $user->current_school_id;

            if (! $schoolId) {
                $schoolId = $user->schools()->first()?->id ?? School::first()?->id;
                if ($schoolId) {
                    $user->update(['current_school_id' => $schoolId]);
                }
            }

            if ($schoolId) {
                app(PermissionRegistrar::class)->setPermissionsTeamId($schoolId);
            }
        }

        return $next($request);
    }
}
