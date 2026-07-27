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
        Schema::table('entrevistas', function (Blueprint $table) {
            $table->string('confirmacion_token')->nullable()->unique()->after('estado');
            $table->string('estado_asistencia')->default('pendiente')->after('confirmacion_token');
            $table->timestamp('confirmado_at')->nullable()->after('estado_asistencia');
            $table->string('confirmado_desde_email')->nullable()->after('confirmado_at');
            $table->text('motivo_rechazo_asistencia')->nullable()->after('confirmado_desde_email');
            $table->string('correo_citacion_enviado')->nullable()->after('motivo_rechazo_asistencia');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entrevistas', function (Blueprint $table) {
            $table->dropColumn([
                'confirmacion_token',
                'estado_asistencia',
                'confirmado_at',
                'confirmado_desde_email',
                'motivo_rechazo_asistencia',
                'correo_citacion_enviado',
            ]);
        });
    }
};
