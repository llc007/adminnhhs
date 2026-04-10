<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Primero agregamos las nuevas columnas
            $table->string('nombres')->nullable()->after('id');
            $table->string('apellido_pat')->nullable()->after('nombres');
            $table->string('apellido_mat')->nullable()->after('apellido_pat');
        });

        // Migrar datos existentes: guardar nombre completo en `nombres`.
        // Los apellidos deberán completarse manualmente desde la ficha del funcionario.
        DB::table('users')->get()->each(function ($user) {
            DB::table('users')->where('id', $user->id)->update([
                'nombres'      => $user->name,
                'apellido_pat' => null,
                'apellido_mat' => null,
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
        });

        DB::table('users')->get()->each(function ($user) {
            DB::table('users')->where('id', $user->id)->update([
                'name' => trim("{$user->nombres} {$user->apellido_pat} {$user->apellido_mat}"),
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['nombres', 'apellido_pat', 'apellido_mat']);
        });
    }
};
