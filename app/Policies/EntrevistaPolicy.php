<?php

namespace App\Policies;

use App\Models\Entrevista;
use App\Models\User;

class EntrevistaPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Entrevista $entrevista): bool
    {
        // El creador siempre puede ver su propia entrevista (incluyendo inspectoria)
        if ($user->id === $entrevista->user_id) {
            return true;
        }

        // Solo directivos y administradores pueden ver TODAS las bitácoras
        if ($user->hasRole(['administrador', 'directivo', 'superadmin'])) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['docente', 'asistente', 'psicosocial', 'directivo', 'administrador', 'superadmin', 'inspector']);
    }

    /**
     * Determine whether the user can update the model (llenar bitacora).
     */
    public function update(User $user, Entrevista $entrevista): bool
    {
        // Solo el creador puede llenar o editar su propia bitácora
        if ($user->id === $entrevista->user_id) {
            return true;
        }

        // Directivos y administradores pueden editar cualquier bitácora
        if ($user->hasRole(['administrador', 'directivo', 'superadmin'])) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Entrevista $entrevista): bool
    {
        // Solo superadmin o administrador pueden borrar una entrevista (y sus bitácoras)
        return $user->hasRole(['superadmin', 'administrador']);
    }
}
