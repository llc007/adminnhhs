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
        Schema::table('bitacoras', function (Blueprint $table) {
            $table->string('estado_firma')->default('pendiente')->after('estado_formulario');
            $table->string('firmante_nombre')->nullable()->after('estado_firma');
            $table->string('firmante_rut')->nullable()->after('firmante_nombre');
            $table->string('firmante_email')->nullable()->after('firmante_rut');
            $table->text('firma_svg')->nullable()->after('firmante_email');
            $table->timestamp('firmado_at')->nullable()->after('firma_svg');
            $table->string('firma_token')->nullable()->unique()->after('firmado_at');
            $table->timestamp('firma_token_expires_at')->nullable()->after('firma_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bitacoras', function (Blueprint $table) {
            $table->dropColumn([
                'estado_firma',
                'firmante_nombre',
                'firmante_rut',
                'firmante_email',
                'firma_svg',
                'firmado_at',
                'firma_token',
                'firma_token_expires_at',
            ]);
        });
    }
};
