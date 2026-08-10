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
        Schema::create('ti_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->string('frecuencia', 20)->default('diaria'); // diaria, semanal, semestral, anual, unica
            $table->string('prioridad', 20)->default('media'); // baja, media, alta, critica
            $table->string('categoria', 50)->nullable(); // Servidores, Redes, Equipos/Salas, Soporte, Mantenimiento, Respaldos, Licencias
            $table->string('estado', 20)->default('pendiente'); // pendiente, en_progreso, completada, omitida
            $table->date('fecha_programada')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->timestamp('fecha_completada')->nullable();
            $table->foreignId('asignado_a')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('creado_por')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('ti_tasks')->nullOnDelete();
            $table->text('notas_cierre')->nullable();
            $table->boolean('es_recurrente')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ti_tasks');
    }
};
