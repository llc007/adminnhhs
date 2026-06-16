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
        Schema::create('revisiones_inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('articulo_inventario_id')->constrained('articulo_inventarios')->onDelete('cascade');
            $table->date('fecha');
            $table->text('detalle');
            $table->string('realizado_por');
            $table->date('fecha_proxima_revision')->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('revisiones_inventario');
    }
};
