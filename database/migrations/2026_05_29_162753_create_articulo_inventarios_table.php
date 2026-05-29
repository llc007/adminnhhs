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
        Schema::create('articulo_inventarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');
            $table->foreignId('requerimiento_item_id')->nullable()->constrained('requerimiento_items')->onDelete('set null');
            $table->string('tipo')->default('activo'); // activo, consumible
            $table->string('codigo_patrimonial')->unique();
            $table->string('nombre');
            $table->string('categoria');
            $table->string('marca')->nullable();
            $table->string('modelo')->nullable();
            $table->string('numero_serie')->nullable();
            $table->integer('cantidad')->default(1);
            $table->string('estado_conservacion')->default('excelente'); // excelente, bueno, usado, regular, malo
            $table->string('ubicacion')->default('Bodega');
            $table->foreignId('responsable_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->date('fecha_ingreso');
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articulo_inventarios');
    }
};
