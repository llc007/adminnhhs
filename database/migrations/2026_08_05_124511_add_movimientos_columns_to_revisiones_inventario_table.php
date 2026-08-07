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
        Schema::table('revisiones_inventario', function (Blueprint $table) {
            $table->unsignedInteger('cantidad_entrada')->nullable()->after('detalle');
            $table->unsignedInteger('cantidad_salida')->nullable()->after('cantidad_entrada');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('revisiones_inventario', function (Blueprint $table) {
            $table->dropColumn(['cantidad_entrada', 'cantidad_salida']);
        });
    }
};
