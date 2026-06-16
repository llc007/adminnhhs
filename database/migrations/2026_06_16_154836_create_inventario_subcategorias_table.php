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
        Schema::create('inventario_subcategorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');
            $table->foreignId('categoria_id')->constrained('inventario_categorias')->onDelete('cascade');
            $table->string('nombre');
            $table->timestamps();

            $table->unique(['school_id', 'categoria_id', 'nombre'], 'inv_subcat_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventario_subcategorias');
    }
};
