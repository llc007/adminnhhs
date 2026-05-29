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
        Schema::create('requerimiento_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requerimiento_id')->constrained('requerimientos')->onDelete('cascade');
            $table->string('descripcion');
            $table->integer('cantidad');
            $table->integer('precio_estimado');
            $table->string('tienda_sugerida')->nullable();
            $table->string('estado')->default('pendiente'); // pendiente, aprobado_rectoria, aprobado_gerencia, rechazado_rectoria, rechazado_gerencia, comprado, entregado
            $table->text('comentario_item')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requerimiento_items');
    }
};
