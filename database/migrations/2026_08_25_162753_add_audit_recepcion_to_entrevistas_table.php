<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('entrevistas', function (Blueprint $table) {
            $table->foreignId('recepcionista_ingreso_id')->nullable()->after('hora_llegada')->constrained('users')->nullOnDelete();
            $table->foreignId('recepcionista_salida_id')->nullable()->after('recepcionista_ingreso_id')->constrained('users')->nullOnDelete();
            $table->time('hora_salida')->nullable()->after('recepcionista_salida_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entrevistas', function (Blueprint $table) {
            $table->dropForeign(['recepcionista_ingreso_id']);
            $table->dropForeign(['recepcionista_salida_id']);
            $table->dropColumn(['recepcionista_ingreso_id', 'recepcionista_salida_id', 'hora_salida']);
        });
    }
};
