<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('categorias_entrevista', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Poblar categorías predeterminadas para los colegios existentes
        $schools = DB::table('schools')->pluck('id');
        $defaultCategories = [
            'Rendimiento Académico',
            'Conducta y Convivencia',
            'Asistencia y Puntualidad',
            'Asunto Personal / Familiar',
            'Evaluación Psicopedagógica',
            'Otro',
        ];

        foreach ($schools as $schoolId) {
            foreach ($defaultCategories as $nombre) {
                DB::table('categorias_entrevista')->insert([
                    'school_id' => $schoolId,
                    'nombre' => $nombre,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categorias_entrevista');
    }
};
