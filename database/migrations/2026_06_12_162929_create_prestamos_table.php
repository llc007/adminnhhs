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
        Schema::create('prestamos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('articulo_inventario_id')->nullable()->constrained('articulo_inventarios')->onDelete('set null');
            $table->string('nombre_articulo');
            $table->string('marca')->nullable();
            $table->string('modelo')->nullable();
            $table->string('numero_serie')->nullable();
            $table->integer('cantidad')->default(1);
            $table->date('fecha_prestamo');
            $table->date('fecha_devolucion_estimada');
            $table->date('fecha_devolucion_real')->nullable();
            $table->string('estado')->default('prestado'); // prestado, devuelto, vencido
            $table->text('observaciones')->nullable();
            $table->foreignId('creado_por_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('recibido_por_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prestamos');
    }
};
