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
        // Solo directivos y administradores pueden ver TODAS las bitácoras por rol
        if ($user->hasRole(['administrador', 'directivo', 'superadmin'])) {
            return true;
        }

        // Si tiene el permiso global para ver todas las bitácoras
        if ($user->can('ver-bitacoras')) {
            return true;
        }

        // El creador puede ver su propia entrevista solo si tiene asignado el permiso correspondiente
        if ($user->can('ver-entrevistas-propias') && $user->id === $entrevista->user_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('crear-entrevistas') || $user->hasRole(['administrador', 'directivo', 'superadmin']);
    }

    /**
     * Determine whether the user can update the model (llenar bitacora).
     */
    public function update(User $user, Entrevista $entrevista): bool
    {
        // Directivos y administradores pueden editar cualquier bitácora por rol
        if ($user->hasRole(['administrador', 'directivo', 'superadmin'])) {
            return true;
        }

        // Solo el creador puede llenar o editar su propia bitácora si tiene el permiso asignado
        if ($user->can('ver-entrevistas-propias') && $user->id === $entrevista->user_id) {
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

    /**
     * Determine whether the user can export interviews.
     */
    public function export(User $user): bool
    {
        // Solo administradores (o superadmin) pueden exportar
        return $user->hasRole(['administrador']);
    }
}
