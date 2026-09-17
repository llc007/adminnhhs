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
        Schema::create('atrasos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            $table->foreignId('curso_id')->nullable()->constrained('cursos')->nullOnDelete();
            $table->foreignId('registrado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('fecha');
            $table->time('hora');
            $table->unsignedInteger('minutos_atraso')->default(0);
            $table->string('estado', 30)->default('injustificado'); // injustificado, justificado, pendiente
            $table->string('motivo', 100)->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'fecha']);
            $table->index(['estudiante_id', 'fecha']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('atrasos');
    }
};
