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
        Schema::create('bitacoras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entrevista_id')->unique()->constrained()->cascadeOnDelete();
            
            // Textos largos
            $table->text('resumen')->nullable();
            $table->text('observaciones')->nullable();
            
            // Arreglos JSON
            $table->json('acuerdos')->nullable();       // Ej: ["Reforzar matematicas", "Control semana proxima"]
            $table->json('adjuntos_drive')->nullable(); // Ej: [{"nombre": "Prueba", "url": "https://..."}]
            
            // Logística del llenado
            $table->string('estado_formulario')->default('borrador'); // borrador, finalizado
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bitacoras');
    }
};
