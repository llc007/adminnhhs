<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['nombres', 'apellido_pat', 'apellido_mat', 'email', 'password', 'google_id', 'avatar', 'current_school_id', 'rut_numero', 'rut_dv', 'fecha_nacimiento', 'telefono', 'direccion'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if ($user->nombres) {
                $user->nombres = mb_strtoupper($user->nombres, 'UTF-8');
            }
            if ($user->apellido_pat) {
                $user->apellido_pat = mb_strtoupper($user->apellido_pat, 'UTF-8');
            }
            if ($user->apellido_mat) {
                $user->apellido_mat = mb_strtoupper($user->apellido_mat, 'UTF-8');
            }
            if ($user->direccion) {
                $user->direccion = mb_strtoupper($user->direccion, 'UTF-8');
            }
        });
    }

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
            'ultimo_ingreso_at' => 'datetime',
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
        return $this->belongsToMany(School::class)->withTimestamps();
    }

    /**
     * Get the cursos where this user is the docente jefe.
     */
    public function jefeDeCursos(): HasMany
    {
        return $this->hasMany(Curso::class, 'jefe_id');
    }

    /**
     * Get the entrevistas assigned to this user.
     */
    public function entrevistas(): HasMany
    {
        return $this->hasMany(Entrevista::class, 'user_id');
    }

    /**
     * Get the student record associated with this user.
     */
    public function estudiante(): HasOne
    {
        return $this->hasOne(Estudiante::class);
    }

    /**
     * Get the active roles array for the current school.
     */
    public function getActiveRolesAttribute(): array
    {
        if (! $this->current_school_id) {
            return [];
        }

        return $this->roles()
            ->where('roles.team_id', $this->current_school_id)
            ->pluck('roles.name')
            ->toArray();
    }

    /**
     * Check if user has a specific role or any of an array/collection of roles.
     */
    public function hasRole(string|array|Collection|\Spatie\Permission\Contracts\Role $roles): bool
    {
        $activeRoles = $this->active_roles;

        if ($roles instanceof \Spatie\Permission\Contracts\Role) {
            return in_array($roles->name, $activeRoles);
        }

        if ($roles instanceof Collection) {
            $roles = $roles->pluck('name')->toArray();
        }

        if (is_array($roles)) {
            $flatRoles = [];
            foreach ($roles as $role) {
                if (is_array($role)) {
                    foreach ($role as $r) {
                        $flatRoles[] = $r;
                    }
                } elseif ($role instanceof Collection) {
                    foreach ($role->pluck('name')->toArray() as $r) {
                        $flatRoles[] = $r;
                    }
                } else {
                    $flatRoles[] = $role;
                }
            }
            $roles = $flatRoles;

            $roles = array_map(function ($role) {
                return $role instanceof \Spatie\Permission\Contracts\Role ? $role->name : $role;
            }, $roles);

            return count(array_intersect($roles, $activeRoles)) > 0;
        }

        return in_array($roles, $activeRoles);
    }

    /**
     * Sync roles for a specific school in Spatie model_has_roles and ensure school membership.
     */
    public function syncRolesForSchool(int $schoolId, array $roles): void
    {
        // 1. Sync to Spatie scoped by school team_id (with auto-create if they do not exist)
        app(PermissionRegistrar::class)->setPermissionsTeamId($schoolId);

        foreach ($roles as $roleName) {
            if (! empty($roleName)) {
                Role::findOrCreate($roleName, 'web');
            }
        }

        $this->syncRoles($roles);

        // 2. Ensure user is attached to the school (membership)
        if (! $this->schools()->where('school_id', $schoolId)->exists()) {
            $this->schools()->attach($schoolId);
        }

        // Force relationship refresh
        $this->unsetRelation('roles');
        $this->unsetRelation('schools');
    }
}
