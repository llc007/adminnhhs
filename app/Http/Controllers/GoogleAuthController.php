<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class GoogleAuthController extends Controller
{
    /**
     * Redirect to Google for authentication.
     */
    public function redirectToGoogle(): SymfonyRedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle the callback from Google.
     */
    public function handleGoogleCallback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            $email = $googleUser->getEmail();
            $domain = substr(strrchr($email, '@'), 1);

            // Restrict domains
            if (! in_array($domain, ['newheavenhs.cl', 'gmail.com', 'eben-ezer.cl'])) {
                return redirect()->route('login')->with('error', 'Solo se permite el acceso a correos institucional @newheavenhs.cl o Gmail.');
            }

            $school = \App\Models\School::where('domain', $domain)->first();

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    // Guardamos el nombre completo de Google en `nombres`.
                    // El usuario deberá separar apellidos manualmente desde su ficha.
                    'nombres'      => $googleUser->getName(),
                    'apellido_pat' => null,
                    'apellido_mat' => null,
                    'google_id'    => $googleUser->getId(),
                    'avatar'       => $googleUser->getAvatar(),
                    'current_school_id' => $school?->id,
                ]
            );

            // Relacionar usuario al colegio con un rol por defecto si no están enlazados
            if ($school && !$user->schools()->where('school_id', $school->id)->exists()) {
                $user->schools()->attach($school->id, ['roles' => json_encode(['docente'])]); // O el rol por defecto que decidas
            }

            Auth::login($user);

            return redirect()->intended('/dashboard');
        } catch (\Exception $e) {
            return redirect()->route('login')->with('error', 'Hubo un error al intentar iniciar sesión con Google.');
        }
    }
}
