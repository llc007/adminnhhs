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
        Schema::create('entrevistas', function (Blueprint $table) {
            $table->id();
            
            // Relaciones
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->comment('Funcionario que agenda/realiza la entrevista');
            
            // Logística de la Cita
            $table->date('fecha');
            $table->time('hora');
            
            // Detalles Operativos
            $table->string('urgencia', 50)->default('normal')->comment('normal, prioritario, urgente');
            $table->string('motivo', 100);
            $table->text('notas_previas')->nullable();
            
            // Estado de la gestión
            $table->string('estado', 50)->default('pendiente')->comment('pendiente, realizada, ausente, cancelada');
            
            $table->timestamps();
            
            // Índices para optimizar búsquedas frecuentes
            $table->index(['school_id', 'fecha']);
            $table->index('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entrevistas');
    }
};
