<?php

use App\Models\School;
use Illuminate\Database\Migrations\Migration;
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

        $schools = School::all();

        foreach ($schools as $school) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);

            // Crear el rol 'rectoria'
            $roleRectoria = Role::findOrCreate('rectoria', 'web');
            $roleRectoria->syncPermissions([
                'ver-dashboard-gerencia',
                'ver-reportes-entrevistas',
                'ver-entrevistas-general',
                'ver-entrevistas-propias',
                'ver-bitacoras',
                'ver-estudiantes',
                'crear-requerimientos',
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $schools = School::all();
        foreach ($schools as $school) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);
            $role = Role::where('name', 'rectoria')->where('team_id', $school->id)->first();
            if ($role) {
                $role->delete();
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
