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

        $permName = 'ver-entrevistas-confidenciales';
        Permission::findOrCreate($permName, 'web');

        $schools = School::all();
        $allowedRoles = ['superadmin', 'psicosocial'];

        foreach ($schools as $school) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);

            foreach ($allowedRoles as $roleName) {
                $role = Role::findOrCreate($roleName, 'web');
                $role->givePermissionTo($permName);
            }
        }

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
