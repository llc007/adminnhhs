<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estudiantes', function (Blueprint $table) {
            // Nombre completo del estudiante tal como viene del CSV (pre-vinculación)
            // Una vez que se vincule con su cuenta Google, el nombre queda en users
            $table->string('nombres_csv')->nullable()->after('rut_dv')
                ->comment('Nombre completo desde CSV, antes de vincular cuenta Google');
        });
    }

    public function down(): void
    {
        Schema::table('estudiantes', function (Blueprint $table) {
            $table->dropColumn('nombres_csv');
        });
    }
};
