<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['nombres', 'apellido_pat', 'apellido_mat', 'email', 'password', 'google_id', 'avatar', 'current_school_id', 'rut_numero', 'rut_dv'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's full name.
     * If apellidos are set, combines all parts. Otherwise returns nombres as-is.
     */
    public function nombreCompleto(): string
    {
        return trim(implode(' ', array_filter([
            $this->nombres,
            $this->apellido_pat,
            $this->apellido_mat,
        ])));
    }

    /**
     * Get the user's initials from nombres and apellido_pat.
     */
    public function initials(): string
    {
        $partes = array_filter([$this->nombres, $this->apellido_pat]);

        return Str::of(implode(' ', $partes))
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    /**
     * Get the full RUT with verification digit.
     * Returns format: 12.345.678-K
     */
    public function rutCompleto(): ?string
    {
        if (! $this->rut_numero) {
            return null;
        }

        $numero = number_format((int) $this->rut_numero, 0, ',', '.');

        return $numero.'-'.($this->rut_dv ?? '');
    }

    /**
     * Get the currently active school session for the user.
     */
    public function currentSchool(): BelongsTo
    {
        return $this->belongsTo(School::class, 'current_school_id');
    }

    /**
     * Get all schools the user is associated with.
     */
    public function schools(): BelongsToMany
    {
        return $this->belongsToMany(School::class)->withPivot('roles')->withTimestamps();
    }

    /**
     * Get the cursos where this user is the docente jefe.
     */
    public function jefeDeCursos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Curso::class, 'jefe_id');
    }

    /**
     * Get the entrevistas assigned to this user.
     */
    public function entrevistas(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Entrevista::class, 'user_id');
    }

    /**
     * Get the active roles array for the current school.
     */
    public function getActiveRolesAttribute(): array
    {
        if (! $this->current_school_id) {
            return [];
        }

        $school = $this->schools->where('id', $this->current_school_id)->first();
        
        if (! $school || ! $school->pivot->roles) {
            return [];
        }

        $roles = json_decode($school->pivot->roles, true);
        return is_array($roles) ? $roles : [];
    }

    /**
     * Check if user has a specific role or any of an array of roles.
     */
    public function hasRole(string|array $roles): bool
    {
        $activeRoles = $this->active_roles;

        if (is_array($roles)) {
            return count(array_intersect($roles, $activeRoles)) > 0;
        }

        return in_array($roles, $activeRoles);
    }
}
