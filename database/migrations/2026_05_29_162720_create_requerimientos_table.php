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
        Schema::create('requerimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');
            $table->text('justificacion');
            $table->string('estado')->default('pendiente_rectoria'); // pendiente_rectoria, pendiente_gerencia, aprobado, aprobado_parcialmente, rechazado, en_adquisicion, recibido, entregado
            $table->text('comentarios_rectoria')->nullable();
            $table->text('comentarios_gerencia')->nullable();
            $table->timestamp('firma_rectoria_at')->nullable();
            $table->timestamp('firma_gerencia_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requerimientos');
    }
};
