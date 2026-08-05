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
            $table->boolean('es_confidencial')->default(false)->after('motivo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entrevistas', function (Blueprint $table) {
            $table->dropColumn('es_confidencial');
        });
    }
};
