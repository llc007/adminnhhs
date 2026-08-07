<?php

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

        // 1. Crear el nuevo permiso 'ver-recepcion'
        $permission = Permission::firstOrCreate([
            'name' => 'ver-recepcion',
            'guard_name' => 'web',
        ]);

        // 2. Asignar 'ver-recepcion' a roles globales o por escuela que tengan 'ingresar-apoderado'
        $roles = Role::whereIn('name', ['superadmin', 'administrador', 'directivo', 'inspector', 'recepcion'])->get();
        foreach ($roles as $role) {
            $role->givePermissionTo($permission);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::where('name', 'ver-recepcion')->first();
        if ($permission) {
            $permission->delete();
        }
    }
};
