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
            // Se registra opcionalmente la hora a la que realmente llegó la persona
            $table->time('hora_llegada')->nullable()->after('hora');
            
            // Lugar, Box o Sala (Ej: "Box 1", "Sala de Profesores")
            $table->string('lugar')->nullable()->after('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entrevistas', function (Blueprint $table) {
            $table->dropColumn(['hora_llegada', 'lugar']);
        });
    }
};
