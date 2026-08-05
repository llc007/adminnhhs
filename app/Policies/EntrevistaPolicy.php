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
        // 1. Superadmin siempre tiene acceso ilimitado
        if ($user->hasRole('superadmin')) {
            return true;
        }

        // 2. Creador de la entrevista siempre puede verla
        if ($user->id === $entrevista->user_id) {
            return true;
        }

        // 3. Usuario con acceso explícitamente compartido
        if ($entrevista->accesosCompartidos()->where('user_id', $user->id)->exists()) {
            return true;
        }

        // 4. Si la entrevista es CONFIDENCIAL / PRIVADA (Equipo Psicosocial)
        if ($entrevista->es_confidencial) {
            return $user->can('ver-entrevistas-confidenciales') || $user->hasRole('psicosocial');
        }

        // 5. Entrevistas normales según permisos globales
        if ($user->can('ver-bitacoras')) {
            return true;
        }

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
        return $user->can('crear-entrevistas') || $user->hasRole(['superadmin', 'psicosocial']);
    }

    /**
     * Determine whether the user can update the model (llenar o editar bitácora).
     * Solo el creador de la cita y superadmin tienen permiso de edición.
     */
    public function update(User $user, Entrevista $entrevista): bool
    {
        if ($user->hasRole('superadmin')) {
            return true;
        }

        return $user->id === $entrevista->user_id;
    }

    /**
     * Determine whether the user can share access to the entrevista.
     */
    public function share(User $user, Entrevista $entrevista): bool
    {
        if ($user->hasRole('superadmin')) {
            return true;
        }

        if ($user->id === $entrevista->user_id) {
            return true;
        }

        if ($entrevista->es_confidencial) {
            return $user->can('ver-entrevistas-confidenciales') || $user->hasRole('psicosocial');
        }

        return $user->can('ver-bitacoras');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Entrevista $entrevista): bool
    {
        return $user->hasRole(['superadmin', 'administrador']) || $user->can('eliminar-entrevistas');
    }

    /**
     * Determine whether the user can export interviews.
     */
    public function export(User $user): bool
    {
        return $user->hasRole('superadmin') || $user->can('ver-entrevistas-general');
    }
}
