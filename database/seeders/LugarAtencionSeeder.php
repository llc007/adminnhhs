<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\School;
use App\Models\LugarAtencion;

class LugarAtencionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $schools = School::all();
        
        $lugares = [
            'BOX 1',
            'BOX 2',
            'BOX 3',
            'BOX 4',
            'BOX 5',
            'BOX 6',
            'Of. Psicóloga Básica',
            'Of. Psicóloga Media',
            'Sala de Profesores',
            'Inspectoría General',
        ];

        foreach ($schools as $school) {
            foreach ($lugares as $lugar) {
                LugarAtencion::firstOrCreate([
                    'school_id' => $school->id,
                    'nombre' => $lugar
                ]);
            }
        }
    }
}
