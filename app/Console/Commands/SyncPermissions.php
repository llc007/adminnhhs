<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SyncPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-permissions {--force : Force reset all role permissions to factory defaults}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Spatie roles and permissions for all schools and users without overwriting existing custom settings';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('1. Resetting Spatie permission cache...');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // All system permissions
        $allPermissions = [
            // Entrevistas
            'ver-dashboard-entrevistas',
            'ver-entrevistas-propias',
            'ver-entrevistas-general',
            'ver-bitacoras',
            'crear-entrevistas',
            'cancelar-entrevistas',
            'eliminar-entrevistas',
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

        $this->info('2. Ensuring permissions exist in DB...');
        foreach ($allPermissions as $permName) {
            Permission::findOrCreate($permName, 'web');
        }

        $schools = School::all();
        if ($schools->isEmpty()) {
            $this->error('No schools found in the database!');

            return Command::FAILURE;
        }

        $force = $this->option('force');

        $this->info('3. Syncing role permissions for each school...');
        $rolePermissionsMap = [
            'superadmin' => $allPermissions,
            'administrador' => $allPermissions,
            'directivo' => [
                'ver-dashboard-entrevistas',
                'ver-entrevistas-general',
                'ver-entrevistas-propias',
                'ver-bitacoras',
                'crear-entrevistas',
                'cancelar-entrevistas',
                'ingresar-apoderado',
                'ver-estudiantes',
                'editar-estudiantes',
                'importar-estudiantes',
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

        foreach ($schools as $school) {
            $this->comment("   School: {$school->name} (ID: {$school->id})");
            app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);

            foreach ($rolePermissionsMap as $roleName => $perms) {
                $role = Role::findOrCreate($roleName, 'web');

                // Preserve custom database permissions if role already has permissions, unless --force is passed
                if ($force || $role->permissions()->count() === 0) {
                    $role->syncPermissions($perms);
                }
            }
        }

        $this->info('4. Syncing users current_school_id and roles...');
        $users = User::all();
        $adminEmail = env('ADMIN_EMAIL', 'luislopez@newheavenhs.cl');

        foreach ($users as $user) {
            $schoolId = $user->current_school_id ?? $schools->first()->id;

            if ($user->current_school_id !== $schoolId) {
                $user->update(['current_school_id' => $schoolId]);
            }

            app(PermissionRegistrar::class)->setPermissionsTeamId($schoolId);

            // If user has no roles assigned for this school, assign default role
            $currentRoles = $user->roles()->where('roles.team_id', $schoolId)->pluck('roles.name')->toArray();

            if (empty($currentRoles)) {
                if (strtolower($user->email) === strtolower($adminEmail) || str_contains(strtolower($user->email), 'luislopez')) {
                    $user->syncRolesForSchool($schoolId, ['superadmin', 'administrador', 'docente']);
                } else {
                    $localPart = strstr($user->email, '@', true);
                    $isStudent = $localPart && str_contains($localPart, '.');
                    $user->syncRolesForSchool($schoolId, [$isStudent ? 'estudiante' : 'docente']);
                }
                $currentRoles = $user->roles()->where('roles.team_id', $schoolId)->pluck('roles.name')->toArray();
            }

            $rolesStr = implode(', ', $currentRoles);
            $this->line("   User #{$user->id} ({$user->email}): School={$schoolId}, Roles=[{$rolesStr}]");
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->newLine();
        $this->info('✅ All roles and permissions have been successfully synchronized!');

        return Command::SUCCESS;
    }
}
