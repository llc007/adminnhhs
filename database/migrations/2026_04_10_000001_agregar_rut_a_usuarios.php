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
        Schema::table('users', function (Blueprint $table) {
            $table->string('rut_numero', 9)->nullable()->after('name')->comment('RUT sin dígito verificador. Ej: 12345678');
            $table->string('rut_dv', 1)->nullable()->after('rut_numero')->comment('Dígito verificador del RUT. Ej: K o 9');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['rut_numero', 'rut_dv']);
        });
    }
};
