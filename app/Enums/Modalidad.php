<?php

namespace App\Enums;

enum Modalidad: string
{
    case Basica = 'basica';
    case Media  = 'media';

    /**
     * Human-readable label for the modalidad.
     */
    public function label(): string
    {
        return match ($this) {
            self::Basica => 'Básico',
            self::Media  => 'Medio',
        };
    }

    /**
     * Valid nivel range for each modalidad.
     *
     * @return int[]
     */
    public function nivelesValidos(): array
    {
        return match ($this) {
            self::Basica => range(1, 8),
            self::Media  => range(1, 4),
        };
    }

    /**
     * Build the display name for a given nivel and letra.
     * Example: "3° Básico B", "2° Medio A"
     */
    public function displayCurso(int $nivel, string $letra): string
    {
        return "{$nivel}° {$this->label()} {$letra}";
    }
}
