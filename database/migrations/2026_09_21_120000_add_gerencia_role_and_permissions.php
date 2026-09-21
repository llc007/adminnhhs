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

        // 1. Crear nuevo permiso 'ver-dashboard-gerencia'
        $permGerencia = Permission::firstOrCreate([
            'name' => 'ver-dashboard-gerencia',
            'guard_name' => 'web',
        ]);

        $schools = School::all();

        foreach ($schools as $school) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);

            // Crear el rol 'gerencia'
            $roleGerencia = Role::findOrCreate('gerencia', 'web');
            $roleGerencia->syncPermissions([
                'ver-dashboard-gerencia',
                'ver-reportes-entrevistas',
                'ver-entrevistas-general',
                'ver-estudiantes',
            ]);

            // Asignar también permiso a superadmin, administrador y directivo
            foreach (['superadmin', 'administrador', 'directivo'] as $adminRoleName) {
                $r = Role::where('name', $adminRoleName)->where('team_id', $school->id)->first();
                if ($r && ! $r->hasPermissionTo('ver-dashboard-gerencia')) {
                    $r->givePermissionTo($permGerencia);
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

        $perm = Permission::where('name', 'ver-dashboard-gerencia')->first();
        if ($perm) {
            $perm->delete();
        }

        Role::where('name', 'gerencia')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
