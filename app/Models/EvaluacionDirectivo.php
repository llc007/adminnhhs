<?php

namespace App\Models;

use Database\Factories\EvaluacionDirectivoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'school_id',
    'evaluado_id',
    'departamento',
    'respuestas',
    'puntaje_obtenido',
    'puntaje_maximo',
    'promedio',
    'registrado_por_id',
])]
class EvaluacionDirectivo extends Model
{
    /** @use HasFactory<EvaluacionDirectivoFactory> */
    use HasFactory;

    /**
     * Escala de puntaje por alternativa para evaluación de Directivos / Departamentos.
     *
     * @var array<int, string>
     */
    public const ALTERNATIVAS = [
        3 => 'Destacado',
        2 => 'Competente',
        1 => 'Básico',
        0 => 'Por lograr',
    ];

    /**
     * Catálogo de los 8 departamentos a evaluar por los docentes.
     *
     * @var array<string, array{nombre: string, descripcion: string}>
     */
    public const DEPARTAMENTOS = [
        'capellania' => [
            'nombre' => 'Capellanía',
            'descripcion' => 'Evaluación del departamento de Capellanía (9 preguntas)',
        ],
        'coordinador_convivencia' => [
            'nombre' => 'Coordinador Convivencia Escolar',
            'descripcion' => 'Evaluación de Coordinación de Convivencia Escolar (10 preguntas)',
        ],
        'encargados_convivencia' => [
            'nombre' => 'Encargados Convivencia Escolar',
            'descripcion' => 'Evaluación de Encargados de Convivencia Escolar (12 preguntas)',
        ],
        'evaluadores' => [
            'nombre' => 'Evaluadores',
            'descripcion' => 'Evaluación del equipo de Evaluadores (11 preguntas)',
        ],
        'orientacion' => [
            'nombre' => 'Orientadoras',
            'descripcion' => 'Evaluación del departamento de Orientación (14 preguntas)',
        ],
        'psicologas' => [
            'nombre' => 'Psicólogas',
            'descripcion' => 'Evaluación del equipo de Psicólogas (11 preguntas)',
        ],
        'psicopedagogas' => [
            'nombre' => 'Psicopedagogas',
            'descripcion' => 'Evaluación del equipo de Psicopedagogas (12 preguntas)',
        ],
        'utp' => [
            'nombre' => 'UTP',
            'descripcion' => 'Evaluación de Unidad Técnico Pedagógica (10 preguntas)',
        ],
    ];

    /**
     * Preguntas y alternativas personalizadas de "No aplica" (valores negativos: -1, -2) por departamento.
     *
     * @var array<string, array<int, array{texto: string, no_aplica?: array<int, string>}>>
     */
    public const CUESTIONARIOS = [
        'capellania' => [
            1 => [
                'texto' => 'Los capellanes mantienen un trato respetuoso con el cuerpo docente.',
            ],
            2 => [
                'texto' => 'Los capellanes tienen la habilidad para motivar al equipo docente y fomentar un ambiente colaborativo.',
            ],
            3 => [
                'texto' => 'Los capellanes me informan como profesor jefe cuando atienden a un estudiante de mi jefatura por razones personales y/o espirituales.',
                'no_aplica' => [
                    -1 => 'No tengo jefatura. No aplica.',
                    -2 => 'No han atendido a algún estudiante de mi jefatura.',
                ],
            ],
            4 => [
                'texto' => 'Los capellanes son un ejemplo en la práctica de valores como la responsabilidad, respeto y sello cristiano según nuestro proyecto educativo.',
            ],
            5 => [
                'texto' => 'Los capellanes transmiten información, objetivos y directrices sobre actividades, devocionales, semana de la biblia, etc. de manera clara y eficaz a todos los docentes de manera formal (correo o en consejo de profesores).',
            ],
            6 => [
                'texto' => 'Los capellanes responden dudas o consultas formales dentro de un plazo prudente (48hrs).',
            ],
            7 => [
                'texto' => 'Los capellanes dan el tiempo necesario para cumplir con las actividades solicitadas durante el año.',
            ],
            8 => [
                'texto' => 'Los capellanes colaboran con la preparación de devocionales de patio entregando pautas, participando de ensayos, brindando sugerencias o colaborando con los números artísticos en el caso de no contar con ello, etc.',
                'no_aplica' => [
                    -1 => 'No tengo jefatura por lo que no he requerido de su ayuda en este aspecto.',
                ],
            ],
            9 => [
                'texto' => 'La comunicación entre capellanes y delegadas pastorales son de utilidad y facilitan la labor del profesor jefe en cuanto al cumplimiento de actividades programadas por el departamento de capellanía.',
                'no_aplica' => [
                    -1 => 'No tengo jefatura por lo que no he requerido de su ayuda en este aspecto.',
                ],
            ],
        ],
        'coordinador_convivencia' => [
            1 => [
                'texto' => 'Planificación y gestión de la convivencia escolar: planifica, coordina y supervisa acciones que favorecen la convivencia escolar, de acuerdo con las necesidades y objetivos institucionales.',
            ],
            2 => [
                'texto' => 'Prevención de conflictos y promoción del buen trato: Implementa estrategias preventivas que promueven el respeto, el buen trato y la resolución pacífica de conflictos.',
            ],
            3 => [
                'texto' => 'Organización y ejecución de talleres formativos: Organiza y desarrolla talleres y actividades formativas pertinentes a las necesidades de la comunidad educativa.',
                'no_aplica' => [
                    -1 => 'No he necesitado que tome decisiones sobre situaciones con estudiantes.',
                ],
            ],
            4 => [
                'texto' => 'Gestión y abordaje de conflictos: Aborda oportunamente las situaciones de conflicto, promoviendo soluciones objetivas, respetuosas y pertinentes.',
            ],
            5 => [
                'texto' => 'Aplicación de protocolos institucionales: Coordina la aplicación de los protocolos institucionales, resguardando los procedimientos, la confidencialidad y los derechos de los involucrados.',
            ],
            6 => [
                'texto' => 'Orientación y acompañamiento docente: Orienta y acompaña oportunamente a los docentes frente a situaciones de convivencia escolar, facilitando la búsqueda de soluciones.',
            ],
            7 => [
                'texto' => 'Comunicación efectiva: Mantiene una comunicación clara, respetuosa y oportuna con estudiantes, docentes y apoderados.',
            ],
            8 => [
                'texto' => 'Registro y seguimiento de casos: Mantiene registros y realiza seguimiento de los casos, verificando el cumplimiento de los acuerdos y medidas establecidas.',
            ],
            9 => [
                'texto' => 'Trabajo colaborativo y articulación institucional: Coordina acciones con los distintos equipos institucionales para abordar las necesidades de convivencia escolar.',
            ],
            10 => [
                'texto' => 'Evaluación y mejora continua: Evalúa las acciones implementadas y propone mejoras que contribuyen al fortalecimiento de la convivencia y el bienestar escolar.',
            ],
        ],
        'encargados_convivencia' => [
            1 => [
                'texto' => 'El encargado de convivencia Escolar mantiene un trato respetuoso con el cuerpo docente.',
            ],
            2 => [
                'texto' => 'El encargado de convivencia Escolar tiene la habilidad para motivar al equipo docente y fomentar un ambiente colaborativo.',
            ],
            3 => [
                'texto' => 'El encargado de convivencia toma decisiones pertinentes en situaciones que involucren a los estudiantes, de acuerdo con lo establecido en el Reglamento Interno y en el Manual de Convivencia Escolar.',
                'no_aplica' => [
                    -1 => 'No he necesitado que tome decisiones sobre situaciones con estudiantes.',
                ],
            ],
            4 => [
                'texto' => 'El encargado de convivencia Escolar participa en reuniones de nivel o ciclo para analizar casos de convivencia y proponer acciones conjuntas.',
            ],
            5 => [
                'texto' => 'El encargado de convivencia Escolar brinda orientación sobre protocolos de actuación frente a situaciones de acoso, maltrato o agresiones.',
            ],
            6 => [
                'texto' => 'El encargado de convivencia es un ejemplo en la práctica de valores como la responsabilidad y respeto según nuestro proyecto educativo.',
            ],
            7 => [
                'texto' => 'El encargado de convivencia Escolar transmite información formal sobre casos de suspensión o medidas formales.',
            ],
            8 => [
                'texto' => 'El encargado de convivencia Escolar realiza talleres para aplicar en aula que fortalezcan la convivencia, el respeto y la inclusión.',
            ],
            9 => [
                'texto' => 'El encargado de convivencia Escolar entrega información sobre casos particulares que se encuentran en convivencia para el conocimiento de los docentes y mantenerlos visualizados.',
            ],
            10 => [
                'texto' => 'El encargado de convivencia Escolar responde consultas formales (correos o en consejos) sobre su área en tiempo prudente.',
            ],
            11 => [
                'texto' => 'El encargado de convivencia Escolar realiza seguimiento a los alumnos derivados a convivencia escolar a través de solicitud de informes y/o conversaciones informales.',
                'no_aplica' => [
                    -1 => 'No he necesitado el seguimiento de estudiantes.',
                ],
            ],
            12 => [
                'texto' => 'El encargado de Convivencia Escolar apoya a los profesores en la comunicación con los apoderados sobre temas de convivencia, cuando se le solicita su colaboración.',
                'no_aplica' => [
                    -1 => 'No he necesitado o requerido de su ayuda.',
                ],
            ],
        ],
        'evaluadores' => [
            1 => [
                'texto' => 'El/la directivo/a mantiene un trato respetuoso con el cuerpo docente.',
            ],
            2 => [
                'texto' => 'El/la directivo/a tiene la habilidad para motivar al equipo docente y fomentar un ambiente colaborativo.',
            ],
            3 => [
                'texto' => 'El/la directivo/a entrega el calendario de evaluaciones de cada semestre con tiempo prudente para su completación.',
            ],
            4 => [
                'texto' => 'El/la directivo/a realiza sugerencias para mejorar el desempeño docente mediante acompañamiento en aula.',
                'no_aplica' => [
                    -1 => 'No he recibido acompañamiento al aula.',
                ],
            ],
            5 => [
                'texto' => 'El/la directivo/a es capaz de hacer un plan evaluativo en conjunto con el/la docente en casos especiales (alumnos que no asisten más a clases regulares y/o licencias prolongadas).',
            ],
            6 => [
                'texto' => 'El/la directivo/a es un ejemplo en la práctica de valores como la responsabilidad y respeto según nuestro proyecto educativo.',
            ],
            7 => [
                'texto' => 'El/la directivo/a transmite información, objetivos y directrices de manera clara y eficaz a todos los docentes de manera formal (correo o reunión de consejo).',
            ],
            8 => [
                'texto' => 'El/la directivo/a se reúne periódicamente y dirige reuniones técnicas del quehacer pedagógico enfocadas en evaluación.',
            ],
            9 => [
                'texto' => 'El/la directivo/a promueve evaluaciones formativas como herramienta para mejorar la enseñanza.',
            ],
            10 => [
                'texto' => 'El/la directivo/a responde en tiempo prudente al visado de evaluaciones enviadas por docentes y/o jefes de departamento (según lo estipulado con jefes de depto, las pruebas tienen un tiempo de visado de 1 semana).',
            ],
            11 => [
                'texto' => 'El/la directivo/a provee datos y/o reportes que ayuden al docente a ajustar sus prácticas pedagógicas.',
            ],
        ],
        'orientacion' => [
            1 => [
                'texto' => 'El/la directivo/a mantiene un trato respetuoso con el cuerpo docente.',
            ],
            2 => [
                'texto' => 'El/la directivo/a tiene la habilidad para motivar al equipo docente y fomentar un ambiente colaborativo.',
            ],
            3 => [
                'texto' => 'El/la directivo/a solicita informes en tiempo prudente (al menos 48hrs).',
            ],
            4 => [
                'texto' => 'El/la directivo/a organiza instancias de autocuidado y contención emocional para profesores.',
            ],
            5 => [
                'texto' => 'El/la directivo/a entrega una nómina clara de estudiantes NEE y avisa actualizaciones de estas de forma precisa.',
            ],
            6 => [
                'texto' => 'El/la directivo/a es un ejemplo en la práctica de valores como la responsabilidad y respeto según nuestro proyecto educativo.',
            ],
            7 => [
                'texto' => 'El/la directivo/a transmite información a través del correo institucional o en los consejos de profesores respecto a metas, objetivos y directrices claras, tales como lineamientos de SENDA, instrucciones para completar informes, metas institucionales como el no uso del teléfono, entre otros.',
            ],
            8 => [
                'texto' => 'El/la directivo/a se reúne periódicamente y dirige reuniones técnicas del quehacer pedagógico dirigidas a su área, ejemplo: protocolos de vulneración de derechos, abusos, etc.',
            ],
            9 => [
                'texto' => 'El/la directivo/a entrega información sobre casos particulares para el conocimiento de los docentes para mantener visualización de estudiantes.',
            ],
            10 => [
                'texto' => 'El/la directivo/a responde consultas formales (correos o en consejos) sobre su área en tiempo prudente (48hrs).',
            ],
            11 => [
                'texto' => 'El/la directivo/a apoya y acompaña al docente en el manejo de casos complejos que afecten el clima del aula (con casos de tribunales o NEE).',
                'no_aplica' => [
                    -1 => 'No he necesitado la presencia de orientadoras en aula, entrevistas, etc.',
                ],
            ],
            12 => [
                'texto' => 'El/la directivo/a apoya a los profesores en la comunicación con apoderados sobre temas de orientación, cuando se le requiera o solicite.',
                'no_aplica' => [
                    -1 => 'No he necesitado o requerido de su ayuda.',
                ],
            ],
            13 => [
                'texto' => 'El/la directivo/a gestiona entrevistas y trabajo colaborativo entre los/as docentes para la toma de decisiones asertivas respecto a casos relevantes en el área de orientación.',
                'no_aplica' => [
                    -1 => 'No he necesitado la gestión de orientadoras frente a casos relevantes.',
                ],
            ],
            14 => [
                'texto' => 'El/la directivo/a articula y guía el apoyo entre profesor y otros profesionales (psicólogos, convivencia escolar y psicopedagogas) para informar casos relevantes y gestionar su ayuda.',
                'no_aplica' => [
                    -1 => 'No he necesitado la gestión de orientación.',
                ],
            ],
        ],
        'psicologas' => [
            1 => [
                'texto' => 'La especialista mantiene un trato respetuoso con el cuerpo docente.',
            ],
            2 => [
                'texto' => 'El/la directivo/a tiene la habilidad para motivar al equipo docente y fomentar un ambiente colaborativo.',
            ],
            3 => [
                'texto' => 'El/la directivo/a solicita informes en tiempo prudente (al menos 48hrs).',
                'no_aplica' => [
                    -1 => 'No me han solicitado informes.',
                ],
            ],
            4 => [
                'texto' => 'La especialista organiza talleres para aplicar en aula que promuevan la salud mental, autoestima, empatía y habilidades socioemocionales en los estudiantes.',
            ],
            5 => [
                'texto' => 'La especialista entrega información sobre casos particulares para el conocimiento de los docentes para mantener visualización de estudiantes.',
            ],
            6 => [
                'texto' => 'La especialista es un ejemplo en la práctica de valores como la responsabilidad y respeto según nuestro proyecto educativo.',
            ],
            7 => [
                'texto' => 'La especialista entrega orientación sobre cómo actuar frente a estudiantes que presentan señales de riesgo emocional.',
            ],
            8 => [
                'texto' => 'La especialista atiende estudiantes que presenten dificultades emocionales mediante sesiones de apoyo psicológico breve e informa al docente de ello.',
            ],
            9 => [
                'texto' => 'La especialista acompaña procesos de reintegración escolar tras ausencias prolongadas, crisis familiares o problemas de salud mental (seguimiento de casos).',
                'no_aplica' => [
                    -1 => 'No se ha requerido de dicho acompañamiento.',
                ],
            ],
            10 => [
                'texto' => 'El/la directivo/a responde consultas formales (correos o en consejos) sobre su área en tiempo prudente (48hrs).',
            ],
            11 => [
                'texto' => 'La especialista contribuye en la actualización de protocolos de actuación frente a crisis emocionales, autolesiones o conductuales de riesgo.',
            ],
        ],
        'psicopedagogas' => [
            1 => [
                'texto' => 'La profesional mantiene un trato respetuoso con el cuerpo docente.',
            ],
            2 => [
                'texto' => 'La profesional entrega a los profesores información con orientaciones pedagógicas concretas sobre los estudiantes con los que trabaja.',
            ],
            3 => [
                'texto' => 'La profesional sugiere estrategias diferenciadas o adaptaciones curriculares que los docentes puedan implementar en el aula.',
            ],
            4 => [
                'texto' => 'La profesional se reúne con los profesores para analizar y diseñar planes de apoyo individual o grupales de los estudiantes NEE que lo requieran.',
                'no_aplica' => [
                    -1 => 'No he necesitado ni requerido de esa ayuda.',
                ],
            ],
            5 => [
                'texto' => 'La profesional entrega una nómina clara de estudiantes NEE y avisa actualizaciones de estas de forma precisa.',
            ],
            6 => [
                'texto' => 'La profesional es un ejemplo en la práctica de valores como la responsabilidad y respeto según nuestro proyecto educativo.',
            ],
            7 => [
                'texto' => 'La profesional realiza seguimiento a los estudiantes en intervención y retroalimentan periódicamente al profesor de sus avances o retrocesos.',
            ],
            8 => [
                'texto' => 'La profesional participa en reuniones técnico psicopedagógicas aportando desde la mirada pedagógica.',
            ],
            9 => [
                'texto' => 'La profesional entrega información sobre casos particulares para el conocimiento de los docentes para mantener visualización de estudiantes.',
            ],
            10 => [
                'texto' => 'La profesional realiza contención de las desregulaciones de los alumnos NEE cuando se le solicita.',
                'no_aplica' => [
                    -1 => 'No he requerido de su ayuda.',
                ],
            ],
            11 => [
                'texto' => 'La profesional apoya y acompaña al docente en el aula en el manejo de casos complejos que afecten el clima del aula (NEE).',
                'no_aplica' => [
                    -1 => 'No he necesitado la presencia de las profesionales.',
                ],
            ],
            12 => [
                'texto' => 'La profesional entrega, dentro de un plazo de dos semanas, las evaluaciones que los profesores solicitan aplicar a los estudiantes NEE como segunda oportunidad.',
                'no_aplica' => [
                    -1 => 'No he necesitado que la profesional tome una prueba como 2da oportunidad.',
                ],
            ],
        ],
        'utp' => [
            1 => [
                'texto' => 'El/la directivo/a mantiene un trato respetuoso con el cuerpo docente.',
            ],
            2 => [
                'texto' => 'El/la directivo/a tiene la habilidad para motivar al equipo docente y fomentar un ambiente colaborativo.',
            ],
            3 => [
                'texto' => 'El/la directivo/a resuelve conflictos y toma decisiones pertinentes respecto a situaciones emergentes de índole educativo (cierre de año, alumno con diferente modalidad de evaluación, entre otros).',
                'no_aplica' => [
                    -1 => 'No he necesitado que el/la directivo resuelva conflictos ni tome decisiones respecto a situaciones emergentes.',
                ],
            ],
            4 => [
                'texto' => 'El/la directivo/a está disponible para acompañar e inducir a los docentes que requieren apoyo en su labor.',
                'no_aplica' => [
                    -1 => 'No he necesitado que el/la directivo me apoye en labores.',
                ],
            ],
            5 => [
                'texto' => 'El/la directivo/a muestra disposición y habilidades para intervenir y mediar en situaciones de conflicto dentro del equipo pedagógico.',
                'no_aplica' => [
                    -1 => 'No he necesitado que el/la directivo resuelva conflictos ni tome decisiones respecto a situaciones emergentes.',
                ],
            ],
            6 => [
                'texto' => 'El/la directivo/a es un ejemplo en la práctica de valores como la responsabilidad y respeto según nuestro proyecto educativo.',
            ],
            7 => [
                'texto' => 'El/la directivo/a transmite información, objetivos y directrices de manera clara y eficaz a todos los docentes de manera formal (correo y/o consejo de profesores) y a tiempo.',
            ],
            8 => [
                'texto' => 'El/la directivo/a se reúne periódicamente y dirige reuniones técnicas del quehacer pedagógico.',
            ],
            9 => [
                'texto' => 'El/la directivo/a responde dudas o consultas formales dentro de un plazo prudente (48hrs).',
            ],
            10 => [
                'texto' => 'El/la directivo/a fomenta el trabajo colaborativo mediante comunidades de aprendizaje docente, al organizar reuniones periódicas de comunidades de aprendizaje donde los docentes comparten experiencias, estrategias y reflexionan sobre su práctica pedagógica.',
            ],
        ],
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'respuestas' => 'array',
            'puntaje_obtenido' => 'integer',
            'puntaje_maximo' => 'integer',
            'promedio' => 'float',
        ];
    }

    /**
     * Retorna las preguntas configuradas para un departamento dado.
     *
     * @return array<int, array{texto: string, no_aplica?: array<int, string>}>
     */
    public static function preguntasDe(string $departamento): array
    {
        return self::CUESTIONARIOS[$departamento] ?? [];
    }

    /**
     * Retorna las opciones de "No aplica" para una pregunta específica de un departamento.
     *
     * @return array<int, string>
     */
    public static function opcionesNoAplica(string $departamento, int $numeroPregunta): array
    {
        return self::CUESTIONARIOS[$departamento][$numeroPregunta]['no_aplica'] ?? [];
    }

    public static function nombreDepartamento(string $departamento): string
    {
        return self::DEPARTAMENTOS[$departamento]['nombre'] ?? ucfirst($departamento);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function evaluado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluado_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_id');
    }
}
