<?php

namespace App\Http\Middleware;

use App\Models\School;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RestrictGmailUsers
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
            $email = strtolower($user->email);
            $domain = substr(strrchr($email, '@'), 1);

            $isGmail = ($domain === 'gmail.com');
            $isAllowedDomain = School::where('domain', $domain)->exists();

            // Enforce strict gmail block. In non-testing environments, also block all non-institutional domains.
            if ($isGmail || (! $isAllowedDomain && ! app()->environment('testing'))) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->with('error', 'Solo se permite el acceso a correos institucionales.');
            }
        }

        return $next($request);
    }
}
