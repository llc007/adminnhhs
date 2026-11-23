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
        Schema::create('evaluacion_directivo_asignaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('departamento', 40);
            $table->string('etiqueta', 80)->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'user_id', 'departamento'], 'eval_dir_asig_unique');
        });

        Schema::create('evaluacion_directivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('evaluado_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('departamento', 40);
            $table->json('respuestas');
            $table->unsignedSmallInteger('puntaje_obtenido');
            $table->unsignedSmallInteger('puntaje_maximo');
            $table->decimal('promedio', 4, 2);
            $table->foreignId('registrado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'departamento', 'evaluado_id'], 'eval_directivo_lookup_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluacion_directivos');
        Schema::dropIfExists('evaluacion_directivo_asignaciones');
    }
};
