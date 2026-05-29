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
        Schema::create('acta_entregas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requerimiento_id')->nullable()->constrained('requerimientos')->onDelete('set null');
            $table->foreignId('recibe_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('entrega_user_id')->constrained('users')->onDelete('cascade');
            $table->date('fecha_entrega');
            $table->timestamp('firmado_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acta_entregas');
    }
};
