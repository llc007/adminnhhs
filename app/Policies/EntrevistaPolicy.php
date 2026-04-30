<?php

namespace App\Policies;

use App\Models\Entrevista;
use App\Models\User;
use Illuminate\Auth\Access\Response;

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
        // El dueño siempre puede verla
        if ($user->id === $entrevista->user_id) {
            return true;
        }

        // Directivos, administradores y psicosocial pueden verlas todas
        if ($user->hasRole(['administrador', 'directivo', 'superadmin', 'psicosocial', 'recepcion'])) {
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
        // Solo el dueño original (profesor/profesional asignado) puede llenar o editar su bitácora
        if ($user->id === $entrevista->user_id) {
            return true;
        }

        // Un administrador/directivo puede llegar a editarla en casos extremos, pero por defecto dejémoslo solo al dueño
        // Si quieres que directivos editen bitácoras ajenas, descomenta lo siguiente:
        if ($user->hasRole(['administrador', 'superadmin'])) {
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
