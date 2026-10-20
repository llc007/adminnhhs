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
        Schema::create('evaluacion_docentes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('profesor_id')->constrained('users')->cascadeOnDelete();
            $table->string('tipo_respondente', 20); // 'estudiante' o 'apoderado'
            $table->boolean('con_jefatura')->default(false);
            $table->json('respuestas');
            $table->unsignedSmallInteger('puntaje_obtenido');
            $table->unsignedSmallInteger('puntaje_maximo');
            $table->decimal('promedio', 4, 2);
            $table->foreignId('registrado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'profesor_id', 'tipo_respondente'], 'eval_docente_lookup_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluacion_docentes');
    }
};
