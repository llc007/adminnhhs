<?php

namespace Database\Factories;

use App\Models\ArticuloInventario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<ArticuloInventario>
 */
class ArticuloInventarioFactory extends Factory
{
    public function definition(): array
    {
        $tipo = $this->faker->randomElement(['activo', 'consumible']);
        $categoria = $this->faker->randomElement(['TECNOLOGIA', 'MOBILIARIO', 'OFICINA', 'MATERIAL_DIDACTICO']);
        $nombre = $tipo === 'activo'
            ? $this->faker->randomElement(['Notebook HP ProBook', 'Proyector Epson PowerLite', 'Silla Ergonómica', 'Monitor Dell 24"'])
            : $this->faker->randomElement(['Lápices Pasta Azul', 'Resma de Papel Carta', 'Set de Plumones Pizarra', 'Archivador Lomo Ancho']);

        $catCode = substr($categoria, 0, 3);
        $itemName = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $nombre), 0, 3));
        $correlativo = sprintf('%03d', $this->faker->numberBetween(1, 999));

        return [
            'school_id' => DB::table('schools')->first()->id ?? 1,
            'requerimiento_item_id' => null,
            'tipo' => $tipo,
            'codigo_patrimonial' => "{$catCode}-{$itemName}-{$correlativo}",
            'nombre' => $nombre,
            'categoria' => $categoria,
            'marca' => $this->faker->randomElement(['HP', 'Epson', 'Dell', 'Artel', 'Torre']),
            'modelo' => $this->faker->bothify('Model-##??'),
            'numero_serie' => $tipo === 'activo' ? strtoupper($this->faker->bothify('SN-########??')) : null,
            'cantidad' => $tipo === 'activo' ? 1 : $this->faker->numberBetween(10, 500),
            'estado_conservacion' => $this->faker->randomElement(['excelente', 'bueno', 'usado', 'regular', 'malo']),
            'ubicacion' => 'Bodega',
            'responsable_user_id' => null,
            'fecha_ingreso' => now()->toDateString(),
            'observaciones' => $this->faker->sentence(),
        ];
    }
}
