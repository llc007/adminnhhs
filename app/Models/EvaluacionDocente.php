<?php

namespace App\Models;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Database\Factories\EvaluacionDocenteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'school_id',
    'profesor_id',
    'tipo_respondente',
    'con_jefatura',
    'respuestas',
    'puntaje_obtenido',
    'puntaje_maximo',
    'promedio',
    'registrado_por_id',
])]
class EvaluacionDocente extends Model
{
    /** @use HasFactory<EvaluacionDocenteFactory> */
    use HasFactory;

    /**
     * Escala de puntaje por alternativa.
     *
     * @var array<int, string>
     */
    public const ALTERNATIVAS = [
        3 => 'Siempre',
        2 => 'Casi siempre',
        1 => 'A veces',
        0 => 'Por lograr',
    ];

    public const META_ESTUDIANTES = 20;

    public const META_APODERADOS = 2;

    /**
     * Preguntas para la encuesta de Estudiantes (10 preguntas).
     *
     * @var array<int, string>
     */
    public const PREGUNTAS_ESTUDIANTE = [
        1 => 'El/la profesor/a entrega notas y pruebas corregidas o trabajos en plazos de acuerdo al Reglamento de Evaluación digitándolas en plataforma Fullcollege (7 días hábiles).',
        2 => 'El/la profesor/a mantiene un trato respetuoso con los estudiantes.',
        3 => 'El/la profesor/a realiza los devocionales en la primera hora del día.',
        4 => 'El/la profesor/a trata el contenido de la clase con rigurosidad conceptual y es comprensible para los/as estudiantes.',
        5 => 'El/la profesor/a entrega retroalimentación para que los/as estudiantes comprendan qué necesitan mejorar, y sepan sus errores después de una evaluación.',
        6 => 'El/la profesor/a realiza repasos, reforzamientos o retroalimentación, usando herramientas como resúmenes, guías o cuestionarios, etc. En contenidos más descendidos.',
        7 => 'El/la profesor/a da a conocer el objetivo de la clase o informa el propósito de la clase y los aprendizajes a lograr. (lo escribe en pizarra, lo anuncia verbalmente y/o lo incluye en presentación, etc).',
        8 => 'El/la profesor/a controla sin problemas la disciplina del grupo curso, considerando las situaciones emergentes y corrige a los alumnos cuando cometen una falta (conductuales, presentación personal, relaciones interpersonales, etc).',
        9 => 'El/la profesor/a utiliza diversos recursos para enseñar y evaluar, de acuerdo a su especialidad (guías, actividades en libro y/o cuaderno, flashcards, maquetas, pósters, audios, recortes, juegos, representaciones, videos, exposiciones, plumones de pizarra de diversos colores, canvas, powerpoint, etc.)',
        10 => 'El/la profesor/a realiza la lectura silenciosa al inicio del segundo bloque.',
    ];

    /**
     * Preguntas para la encuesta de Apoderados (6 comunes + 1 adicional para profesores con jefatura).
     *
     * @var array<int, string>
     */
    public const PREGUNTAS_APODERADO = [
        1 => 'El/la docente respeta el calendario de evaluaciones del I y II semestre establecido en la asignatura que imparte, informando de manera formal por las plataformas utilizadas (Full College, Classroom y/o correo electrónico institucional) en caso de haber cambios.',
        2 => 'El/la docente digita oportunamente las notas a la plataforma full college luego de una evaluación sumativa dentro de un plazo de 7 días según reglamento de evaluación.',
        3 => 'El/la docente mantiene un trato de respeto y cordialidad hacia apoderados.',
        4 => 'El/la docente responde los correos enviados por el apoderado de manera clara y oportuna.',
        5 => 'Estoy satisfecho/a como apoderado respecto a la comunicación por parte del/la docente en cuanto a la información dada por las diversas plataformas sobre calificaciones, evaluaciones, actividades de la asignatura, conducta, asistencia, etc.',
        6 => 'El/la docente utiliza diversos materiales pedagógicos para la enseñanza, refuerzo y evaluación de su asignatura tales como guías, actividades en libro y/o cuaderno, flashcards, maquetas, pósters, recortes, juegos, representaciones, exposiciones, presentaciones, resúmenes, plumones de colores, etc.',
        7 => 'El/la docente colabora directamente con el delegado pastoral para promover actividades en el grupo curso y hacer que estas resulten de la mejor manera.',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'con_jefatura' => 'boolean',
            'respuestas' => 'array',
            'puntaje_obtenido' => 'integer',
            'puntaje_maximo' => 'integer',
            'promedio' => 'float',
        ];
    }

    /**
     * Retorna el listado de preguntas según el tipo de respondente y si tiene jefatura.
     *
     * @return array<int, string>
     */
    public static function preguntasPara(string $tipoRespondente, bool $conJefatura = false): array
    {
        if ($tipoRespondente === 'apoderado') {
            $preguntas = self::PREGUNTAS_APODERADO;
            if (! $conJefatura) {
                unset($preguntas[7]);
            }

            return $preguntas;
        }

        return self::PREGUNTAS_ESTUDIANTE;
    }

    /**
     * Genera un código QR en formato SVG para una URL dada.
     */
    public static function generarQrSvg(string $url, int $size = 200): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 1),
            new SvgImageBackEnd
        );

        $writer = new Writer($renderer);

        return $writer->writeString($url);
    }

    /**
     * Determina la clasificación y porcentaje según el puntaje obtenido y el puntaje máximo.
     *
     * - Destacado: 90% o superior
     * - Competente: 80% a 89.9%
     * - Básico: 70% a 79.9%
     * - Por lograr: menos de 70%
     *
     * @return array{nombre: string, porcentaje: float, color: string, badge_class: string}|null
     */
    public static function clasificacionPorcentual(?float $puntajeObtenido, int $puntajeMaximo): ?array
    {
        if ($puntajeObtenido === null || $puntajeMaximo <= 0) {
            return null;
        }

        $porcentaje = round(($puntajeObtenido / $puntajeMaximo) * 100, 1);

        if ($porcentaje >= 90.0) {
            $nombre = 'Destacado';
            $color = 'emerald';
            $badgeClass = 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800';
        } elseif ($porcentaje >= 80.0) {
            $nombre = 'Competente';
            $color = 'blue';
            $badgeClass = 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300 border border-blue-200 dark:border-blue-800';
        } elseif ($porcentaje >= 70.0) {
            $nombre = 'Básico';
            $color = 'amber';
            $badgeClass = 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 border border-amber-200 dark:border-amber-800';
        } else {
            $nombre = 'Por lograr';
            $color = 'red';
            $badgeClass = 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300 border border-red-200 dark:border-red-800';
        }

        return [
            'nombre' => $nombre,
            'porcentaje' => $porcentaje,
            'color' => $color,
            'badge_class' => $badgeClass,
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function profesor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profesor_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_id');
    }
}
