<?php

use App\Models\Entrevista;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Entrevista::where('estado', 'ingresada')
            ->where('mensaje_recepcion', 'like', '%[SALIDA]%')
            ->update(['estado' => 'abierta']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Entrevista::where('estado', 'abierta')
            ->where('mensaje_recepcion', 'like', '%[SALIDA]%')
            ->update(['estado' => 'ingresada']);
    }
};
