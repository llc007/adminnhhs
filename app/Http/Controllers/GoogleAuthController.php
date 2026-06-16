<?php

namespace App\Http\Controllers;

use App\Models\Estudiante;
use App\Models\School;
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
        return Socialite::driver('google')
            ->with(['prompt' => 'select_account'])
            ->redirect();
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

            $school = School::where('domain', $domain)->first();

            // Restrict domains dynamically (strictly institutional, registered in schools table)
            if (! $school) {
                return redirect()->route('login')->with('error', 'Solo se permite el acceso a correos institucionales.');
            }

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    // Guardamos el nombre completo de Google en `nombres`.
                    // El usuario deberá separar apellidos manualmente desde su ficha.
                    'nombres' => $googleUser->getName(),
                    'apellido_pat' => null,
                    'apellido_mat' => null,
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'current_school_id' => $school?->id,
                ]
            );

            // Relacionar usuario al colegio con un rol por defecto si no están enlazados
            // Los correos de estudiantes tienen un punto en la parte local.
            $localPart = strstr($email, '@', true);
            $isStudent = $localPart && str_contains($localPart, '.');
            $roleToAssign = $isStudent ? 'estudiante' : 'externo';

            $schoolUser = $user->schools()->where('school_id', $school->id)->first();
            if (! $schoolUser) {
                $user->schools()->attach($school->id, ['roles' => json_encode([$roleToAssign])]);
            } elseif ($isStudent) {
                // Si ya existe pero tiene solo 'externo', actualizamos a 'estudiante'
                $currentRoles = json_decode($schoolUser->pivot->roles, true) ?: [];
                if (empty($currentRoles) || (count($currentRoles) === 1 && $currentRoles[0] === 'externo')) {
                    $user->schools()->updateExistingPivot($school->id, ['roles' => json_encode(['estudiante'])]);
                }
            }

            // Si es estudiante, lo vinculamos con su ficha de estudiante si existe una con el mismo correo
            if ($isStudent) {
                $estudiante = Estudiante::where('email', $email)
                    ->where('school_id', $school->id)
                    ->first();
                if ($estudiante) {
                    if (! $estudiante->user_id) {
                        $estudiante->update([
                            'user_id' => $user->id,
                            'vinculado_en' => now(),
                        ]);
                    }
                }
            }

            Auth::login($user);

            return redirect()->intended('/dashboard');
        } catch (\Exception $e) {
            return redirect()->route('login')->with('error', 'Error real de Hostinger: '.$e->getMessage());
        }
    }
}
