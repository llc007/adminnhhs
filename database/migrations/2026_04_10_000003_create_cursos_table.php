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
        Schema::create('cursos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->enum('modalidad', ['basica', 'media'])->comment('Educación básica (1°-8°) o media (1°-4°)');
            $table->unsignedTinyInteger('nivel')->comment('1 a 8 para básica, 1 a 4 para media');
            $table->string('letra', 5)->comment('Ej: A, B, C');
            $table->string('nombre_fc')->nullable()->unique()
                ->comment('Nombre en FullCollege para mapeo CSV. Ej: "1 Medio A (110)"');
            $table->foreignId('jefe_id')->nullable()->constrained('users')->nullOnDelete()->comment('Docente jefe del curso');
            $table->timestamps();

            $table->unique(['school_id', 'academic_year_id', 'modalidad', 'nivel', 'letra'], 'cursos_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cursos');
    }
};
