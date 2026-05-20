<?php

namespace App\Services;

use App\Models\Curso;

class StudentMatchService
{
    /**
     * Extrae el curso_id basado en el Org Unit Path de Google Workspace.
     * Ejemplo: "/Estudiantes/1°MA" -> Nivel 1, Media, Letra A.
     */
    public function getCursoIdFromOrgUnitPath(string $orgUnitPath): ?int
    {
        // Formato con letra de modalidad explícita (ej: /Estudiantes/1°MA o 1°BA)
        if (preg_match('/\/Estudiantes\/(\d+)°?([MBmb])([A-Za-z])/', $orgUnitPath, $matches)) {
            $nivel = (int) $matches[1];
            $modalidadCode = strtoupper($matches[2]);
            $letra = strtoupper($matches[3]);

            $modalidad = $modalidadCode === 'M' ? 'media' : 'basica';

            $curso = Curso::where('nivel', $nivel)
                ->where('modalidad', $modalidad)
                ->where('letra', $letra)
                ->first();

            return $curso ? $curso->id : null;
        }

        // Formato sin letra de modalidad (ej: /Estudiantes/1°A), asumimos Básica
        if (preg_match('/\/Estudiantes\/(\d+)°?([A-Za-z])/', $orgUnitPath, $matches)) {
            $nivel = (int) $matches[1];
            $letra = strtoupper($matches[2]);
            
            $curso = Curso::where('nivel', $nivel)
                ->where('modalidad', 'basica')
                ->where('letra', $letra)
                ->first();

            return $curso ? $curso->id : null;
        }

        return null;
    }

    /**
     * Normaliza un string quitando tildes, espacios extra y convirtiendo a mayúsculas.
     */
    public function normalizeString(string $string): string
    {
        $string = mb_strtoupper($string, 'UTF-8');

        $unwanted_array = [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'Ä' => 'A', 'Ë' => 'E', 'Ï' => 'I', 'Ö' => 'O', 'Ü' => 'U',
        ];

        $string = strtr($string, $unwanted_array);

        // Remove extra spaces
        return trim(preg_replace('/\s+/', ' ', $string));
    }

    /**
     * Calcula el porcentaje de similitud entre el nombre del CSV y el de la Base de Datos.
     */
    public function calculateSimilarity(string $csvFirstName, string $csvLastName, string $dbFullName): float
    {
        // En BD está guardado como APELLIDOS NOMBRES
        $csvFullName = $csvLastName.' '.$csvFirstName;

        $normalizedCsv = $this->normalizeString($csvFullName);
        $normalizedDb = $this->normalizeString($dbFullName);

        similar_text($normalizedCsv, $normalizedDb, $percent);

        return $percent;
    }
}
