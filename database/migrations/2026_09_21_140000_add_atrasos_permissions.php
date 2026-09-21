<?php

use App\Models\School;
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
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 1. Crear permisos de Atrasos
        $permissions = [
            'ingresar-atrasos',
            'ver-atrasos',
            'ver-mis-atrasos',
        ];

        foreach ($permissions as $permName) {
            Permission::firstOrCreate([
                'name' => $permName,
                'guard_name' => 'web',
            ]);
        }

        $schools = School::all();

        foreach ($schools as $school) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);

            // Asignar ingresar-atrasos y ver-atrasos a roles de administración/inspección
            foreach (['superadmin', 'administrador', 'directivo', 'inspector'] as $roleName) {
                $role = Role::where('name', $roleName)->where('team_id', $school->id)->first();
                if ($role) {
                    $role->givePermissionTo(['ingresar-atrasos', 'ver-atrasos']);
                }
            }

            // Recepción puede ingresar atrasos
            $roleRecepcion = Role::where('name', 'recepcion')->where('team_id', $school->id)->first();
            if ($roleRecepcion) {
                $roleRecepcion->givePermissionTo('ingresar-atrasos');
            }

            // Gerencia y Rectoría pueden ver atrasos
            foreach (['gerencia', 'rectoria'] as $roleName) {
                $role = Role::where('name', $roleName)->where('team_id', $school->id)->first();
                if ($role) {
                    $role->givePermissionTo('ver-atrasos');
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'ingresar-atrasos',
            'ver-atrasos',
            'ver-mis-atrasos',
        ];

        foreach ($permissions as $permName) {
            $perm = Permission::where('name', $permName)->first();
            if ($perm) {
                $perm->delete();
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
