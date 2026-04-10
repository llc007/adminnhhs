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
        Schema::create('estudiantes', function (Blueprint $table) {
            $table->id();
            // user_id es nullable: el estudiante existe en la BD antes de tener cuenta Google
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('curso_id')->nullable()->constrained('cursos')->nullOnDelete();

            // RUT del estudiante: es el puente de vinculación entre nómina y cuenta Google
            $table->string('rut_numero', 9)->nullable();
            $table->string('rut_dv', 1)->nullable();

            // Datos académicos
            $table->date('fecha_nacimiento')->nullable();
            $table->string('genero', 20)->nullable()->comment('masculino, femenino, otro');

            // Datos del apoderado (se completan manualmente en la ficha)
            $table->string('apoderado_nombres')->nullable();
            $table->string('apoderado_apellido_pat')->nullable();
            $table->string('apoderado_apellido_mat')->nullable();
            $table->string('apoderado_rut_numero', 9)->nullable();
            $table->string('apoderado_rut_dv', 1)->nullable();
            $table->string('apoderado_email')->nullable();
            $table->string('apoderado_telefono', 20)->nullable();
            $table->string('apoderado_parentesco', 50)->nullable()->comment('Ej: Padre, Madre, Abuelo/a, Tío/a');

            // Timestamp de vinculación con cuenta Google
            $table->timestamp('vinculado_en')->nullable();

            $table->timestamps();

            // RUT único por colegio: evita duplicados en la importación masiva
            $table->unique(['school_id', 'rut_numero'], 'estudiantes_rut_school_unique');
            // Un usuario solo puede ser estudiante una vez por sistema
            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estudiantes');
    }
};
