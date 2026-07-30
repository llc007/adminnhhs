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

        $permName = 'escribir-mensajes-agenda';
        Permission::findOrCreate($permName, 'web');

        $schools = School::all();

        foreach ($schools as $school) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);

            $roles = Role::where('team_id', $school->id)->where('name', '!=', 'superadmin')->get();
            foreach ($roles as $role) {
                if ($role->hasPermissionTo($permName)) {
                    $role->revokePermissionTo($permName);
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
        //
    }
};
