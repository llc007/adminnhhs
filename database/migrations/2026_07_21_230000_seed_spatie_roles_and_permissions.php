<?php

use App\Models\School;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Clear Spatie permission cache
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 2. Ensure all standard permissions exist in DB
        $allPermissions = [
            // Entrevistas
            'ver-entrevistas-propias',
            'ver-entrevistas-general',
            'ver-bitacoras',
            'crear-entrevistas',
            'cancelar-entrevistas',
            'ingresar-apoderado',
            // Estudiantes
            'ver-estudiantes',
            'editar-estudiantes',
            'importar-estudiantes',
            // Adquisiciones
            'crear-requerimientos',
            'aprobar-requerimientos',
            'ver-requerimientos-general',
            // Préstamos TI
            'ver-prestamos-propios',
            'ver-prestamos-general',
            'gestionar-prestamos',
            // Administración
            'gestionar-modulos',
            'gestionar-funcionarios',
            'gestionar-roles-permisos',
        ];

        foreach ($allPermissions as $permName) {
            Permission::findOrCreate($permName, 'web');
        }

        // 3. For each school, set team_id and populate standard roles with default permissions
        $schools = School::all();

        foreach ($schools as $school) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);

            // Define default role permissions
            $rolePermissionsMap = [
                'superadmin' => $allPermissions,
                'administrador' => $allPermissions,
                'directivo' => [
                    'ver-entrevistas-general',
                    'ver-entrevistas-propias',
                    'ver-bitacoras',
                    'crear-entrevistas',
                    'cancelar-entrevistas',
                    'ingresar-apoderado',
                    'ver-estudiantes',
                    'editar-estudiantes',
                    'importar-estudiantes',
                    'crear-requerimientos',
                    'aprobar-requerimientos',
                    'ver-requerimientos-general',
                    'ver-prestamos-propios',
                    'ver-prestamos-general',
                    'gestionar-funcionarios',
                ],
                'docente' => [
                    'ver-entrevistas-propias',
                    'crear-entrevistas',
                    'ver-estudiantes',
                    'ver-prestamos-propios',
                    'crear-requerimientos',
                ],
                'inspector' => [
                    'ingresar-apoderado',
                    'ver-entrevistas-general',
                    'ver-estudiantes',
                    'ver-prestamos-propios',
                ],
                'recepcion' => [
                    'ingresar-apoderado',
                    'ver-prestamos-propios',
                ],
                'psicosocial' => [
                    'ver-entrevistas-propias',
                    'crear-entrevistas',
                    'ver-estudiantes',
                    'ver-prestamos-propios',
                ],
                'asistente' => [
                    'ver-estudiantes',
                    'ver-prestamos-propios',
                ],
                'ti' => [
                    'ver-prestamos-propios',
                    'ver-prestamos-general',
                    'gestionar-prestamos',
                ],
                'solicitante_adquisiciones' => [
                    'crear-requerimientos',
                    'ver-prestamos-propios',
                ],
                'estudiante' => [],
            ];

            foreach ($rolePermissionsMap as $roleName => $perms) {
                $role = Role::findOrCreate($roleName, 'web');
                $role->syncPermissions($perms);
            }
        }

        // 4. Ensure all existing users have roles in model_has_roles
        $users = User::all();
        $adminEmail = env('ADMIN_EMAIL', 'luislopez@newheavenhs.cl');

        foreach ($users as $user) {
            $schoolId = $user->current_school_id ?? $schools->first()?->id;
            if (! $schoolId) {
                continue;
            }

            if (! $user->current_school_id) {
                $user->update(['current_school_id' => $schoolId]);
            }

            app(PermissionRegistrar::class)->setPermissionsTeamId($schoolId);

            // Check if user currently has roles in Spatie for this team
            if ($user->roles()->where('roles.team_id', $schoolId)->count() === 0) {
                if (strtolower($user->email) === strtolower($adminEmail) || str_contains(strtolower($user->email), 'luislopez')) {
                    $user->syncRolesForSchool($schoolId, ['superadmin', 'administrador', 'docente']);
                } else {
                    $localPart = strstr($user->email, '@', true);
                    $isStudent = $localPart && str_contains($localPart, '.');
                    if ($isStudent) {
                        $user->syncRolesForSchool($schoolId, ['estudiante']);
                    } else {
                        $user->syncRolesForSchool($schoolId, ['docente']);
                    }
                }
            }
        }

        // 5. Clear permission cache again
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
