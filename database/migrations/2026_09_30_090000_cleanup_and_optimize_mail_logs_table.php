<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('mail_logs')) {
            Schema::table('mail_logs', function (Blueprint $table) {
                $table->longText('body')->nullable()->change();
            });

            // 1. Eliminar logs de correos con más de 2 meses de antigüedad
            DB::table('mail_logs')
                ->where('created_at', '<=', now()->subMonths(2))
                ->delete();

            // 2. Vaciar el campo body de todos los correos restantes para liberar el 95%+ del peso
            DB::table('mail_logs')
                ->whereNotNull('body')
                ->update(['body' => null]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No es reversible
    }
};
