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
        Schema::table('articulo_inventarios', function (Blueprint $table) {
            $table->foreignId('categoria_id')->nullable()->constrained('inventario_categorias')->onDelete('set null');
            $table->foreignId('subcategoria_id')->nullable()->constrained('inventario_subcategorias')->onDelete('set null');
            $table->foreignId('ubicacion_id')->nullable()->constrained('inventario_ubicaciones')->onDelete('set null');
        });

        // Migrar datos existentes
        $articulos = DB::table('articulo_inventarios')->get();

        foreach ($articulos as $art) {
            if ($art->categoria) {
                $catId = DB::table('inventario_categorias')
                    ->where('school_id', $art->school_id)
                    ->where('nombre', $art->categoria)
                    ->value('id');

                if (! $catId) {
                    $catId = DB::table('inventario_categorias')->insertGetId([
                        'school_id' => $art->school_id,
                        'nombre' => $art->categoria,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('articulo_inventarios')
                    ->where('id', $art->id)
                    ->update(['categoria_id' => $catId]);
            }

            if ($art->ubicacion) {
                $ubId = DB::table('inventario_ubicaciones')
                    ->where('school_id', $art->school_id)
                    ->where('nombre', $art->ubicacion)
                    ->value('id');

                if (! $ubId) {
                    $ubId = DB::table('inventario_ubicaciones')->insertGetId([
                        'school_id' => $art->school_id,
                        'nombre' => $art->ubicacion,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('articulo_inventarios')
                    ->where('id', $art->id)
                    ->update(['ubicacion_id' => $ubId]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articulo_inventarios', function (Blueprint $table) {
            $table->dropForeign(['categoria_id']);
            $table->dropForeign(['subcategoria_id']);
            $table->dropForeign(['ubicacion_id']);
            $table->dropColumn(['categoria_id', 'subcategoria_id', 'ubicacion_id']);
        });
    }
};
