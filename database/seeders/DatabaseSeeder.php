<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * Orden: School → Cursos (requiere school + academic_year)
     */
    public function run(): void
    {
        $this->call([
            SchoolSeeder::class,  // Crea school, academic year, terms y asocia user 1
            CursosSeeder::class,  // Requiere school + academic_year existentes
        ]);
    }
}
