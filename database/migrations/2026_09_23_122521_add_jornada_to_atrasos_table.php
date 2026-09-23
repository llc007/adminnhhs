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
        Schema::table('atrasos', function (Blueprint $table) {
            $table->string('jornada', 20)->default('manana')->after('minutos_atraso');
            $table->index(['school_id', 'fecha', 'jornada']);
        });

        DB::table('atrasos')
            ->whereTime('hora', '>=', '13:00:00')
            ->update(['jornada' => 'tarde']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('atrasos', function (Blueprint $table) {
            $table->dropIndex(['school_id', 'fecha', 'jornada']);
            $table->dropColumn('jornada');
        });
    }
};
