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
        Schema::create('acta_entrega_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('acta_entrega_id')->constrained('acta_entregas')->onDelete('cascade');
            $table->foreignId('articulo_inventario_id')->constrained('articulo_inventarios')->onDelete('cascade');
            $table->integer('cantidad')->default(1);
            $table->string('numero_serie')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acta_entrega_detalles');
    }
};
