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
        Schema::create('entrevista_compartida', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entrevista_id')->constrained('entrevistas')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('granted_by_user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['entrevista_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entrevista_compartida');
    }
};
