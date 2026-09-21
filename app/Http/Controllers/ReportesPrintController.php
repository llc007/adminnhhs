<?php

namespace App\Http\Controllers;

use App\Enums\Modalidad;
use App\Models\CategoriaEntrevista;
use App\Models\Curso;
use App\Models\Entrevista;
use App\Models\User;
use Illuminate\Http\Request;

class ReportesPrintController extends Controller
{
    public function entrevistasProfesor(Request $request)
    {
        $user = auth()->user();
        if (! $user->hasRole(['superadmin', 'administrador', 'directivo', 'gerencia', 'rectoria']) && ! $user->can('ver-reportes-entrevistas')) {
            abort(403, 'No tienes permiso para acceder a este reporte.');
        }

        $school = $user->currentSchool;
        $schoolId = $school?->id;

        $periodo = $request->get('periodo', 'ano_actual');
        $cargo = $request->get('cargo', 'todos');
        $search = $request->get('search', '');

        $now = now('America/Santiago');
        $currentYear = $now->year;

        [$startDate, $endDate, $periodoLabel] = match ($periodo) {
            'mes_actual' => [
                $now->copy()->startOfMonth()->format('Y-m-d'),
                $now->copy()->endOfMonth()->format('Y-m-d'),
                'Mes Actual ('.$now->translatedFormat('F Y').')',
            ],
            'primer_semestre' => [
                "{$currentYear}-01-01",
                "{$currentYear}-06-30",
                "Primer Semestre {$currentYear} (Ene - Jun)",
            ],
            'segundo_semestre' => [
                "{$currentYear}-07-01",
                "{$currentYear}-12-31",
                "Segundo Semestre {$currentYear} (Jul - Dic)",
            ],
            'todo' => [
                null,
                null,
                'Todo el Historial Registrado',
            ],
            default => [
                "{$currentYear}-01-01",
                "{$currentYear}-12-31",
                "Año Escolar {$currentYear}",
            ],
        };

        $dateClosure = function ($q) use ($startDate, $endDate) {
            if ($startDate && $endDate) {
                $q->whereBetween('fecha', [$startDate, $endDate]);
            }
        };

        $today = now('America/Santiago')->toDateString();

        $query = User::query()
            ->whereHas('schools', fn ($q) => $q->where('schools.id', $schoolId))
            ->whereHas('roles', function ($q) use ($schoolId) {
                $q->where('roles.team_id', $schoolId)
                    ->whereIn('roles.name', ['docente', 'directivo', 'psicosocial']);
            })
            ->whereRaw("SUBSTR(email, 1, 1) != '_'")
            ->where('email', 'not like', 'docente1@%')
            ->where('email', 'not like', 'test%')
            ->withCount([
                'entrevistas as total_agendadas' => fn ($q) => $q->where('school_id', $schoolId)->where($dateClosure),
                'entrevistas as total_realizadas' => fn ($q) => $q->where('school_id', $schoolId)->where('estado', 'realizada')->where($dateClosure),
                'entrevistas as total_canceladas' => fn ($q) => $q->where('school_id', $schoolId)->whereIn('estado', ['cancelada', 'ausente'])->where($dateClosure),
                'entrevistas as total_pendientes' => fn ($q) => $q->where('school_id', $schoolId)->whereIn('estado', ['pendiente', 'ingresada', 'abierta'])->where('fecha', '>=', $today)->where($dateClosure),
                'entrevistas as total_abiertas' => fn ($q) => $q->where('school_id', $schoolId)->whereIn('estado', ['pendiente', 'ingresada', 'abierta'])->where('fecha', '<', $today)->where($dateClosure),
            ]);

        if ($cargo !== 'todos' && in_array($cargo, ['docente', 'directivo', 'psicosocial'])) {
            $query->whereHas('roles', function ($q) use ($schoolId, $cargo) {
                $q->where('roles.team_id', $schoolId)
                    ->where('roles.name', $cargo);
            });
        }

        if (trim($search) !== '') {
            $words = array_filter(explode(' ', trim($search)));
            $query->where(function ($q) use ($words) {
                foreach ($words as $word) {
                    $w = trim($word);
                    $q->where(function ($sq) use ($w) {
                        $cleanRut = str_replace(['.', '-'], '', $w);
                        $sq->where('nombres', 'like', "%{$w}%")
                            ->orWhere('apellido_pat', 'like', "%{$w}%")
                            ->orWhere('apellido_mat', 'like', "%{$w}%")
                            ->orWhere('email', 'like', "%{$w}%")
                            ->orWhere('rut_numero', 'like', "%{$cleanRut}%");
                    });
                }
            });
        }

        $funcionarios = $query->orderBy('nombres', 'asc')->orderBy('apellido_pat', 'asc')->get();

        // Totales consolidados del reporte
        $citasQuery = Entrevista::where('school_id', $schoolId);
        if ($startDate && $endDate) {
            $citasQuery->whereBetween('fecha', [$startDate, $endDate]);
        }

        $totales = [
            'funcionarios' => $funcionarios->count(),
            'agendadas' => $funcionarios->sum('total_agendadas'),
            'realizadas' => $funcionarios->sum('total_realizadas'),
            'canceladas' => $funcionarios->sum('total_canceladas'),
            'pendientes' => $funcionarios->sum('total_pendientes'),
            'abiertas' => $funcionarios->sum('total_abiertas'),
        ];
        $totales['tasa_realizacion'] = $totales['agendadas'] > 0
            ? round(($totales['realizadas'] / $totales['agendadas']) * 100)
            : 0;

        return view('pages.reportes.print_entrevistas_profesor', compact(
            'school',
            'user',
            'periodoLabel',
            'cargo',
            'search',
            'funcionarios',
            'totales'
        ));
    }

    public function problematicasCurso(Request $request)
    {
        $user = auth()->user();
        if (! $user->hasRole(['superadmin', 'administrador', 'directivo']) && ! $user->can('ver-reportes-entrevistas')) {
            abort(403, 'No tienes permiso para acceder a este reporte.');
        }

        $school = $user->currentSchool;
        $schoolId = $school?->id;

        $periodo = $request->get('periodo', 'ano_actual');
        $modalidad = $request->get('modalidad', 'todas');
        $search = $request->get('search', '');

        $now = now('America/Santiago');
        $currentYear = $now->year;

        [$startDate, $endDate, $periodoLabel] = match ($periodo) {
            'mes_actual' => [
                $now->copy()->startOfMonth()->format('Y-m-d'),
                $now->copy()->endOfMonth()->format('Y-m-d'),
                'Mes Actual ('.$now->translatedFormat('F Y').')',
            ],
            'primer_semestre' => [
                "{$currentYear}-01-01",
                "{$currentYear}-06-30",
                "Primer Semestre {$currentYear} (Ene - Jun)",
            ],
            'segundo_semestre' => [
                "{$currentYear}-07-01",
                "{$currentYear}-12-31",
                "Segundo Semestre {$currentYear} (Jul - Dic)",
            ],
            'todo' => [
                null,
                null,
                'Todo el Historial Registrado',
            ],
            default => [
                "{$currentYear}-01-01",
                "{$currentYear}-12-31",
                "Año Escolar {$currentYear}",
            ],
        };

        // Categorías activas (excluyendo 'Otro' para no duplicar la columna fija final)
        $categorias = CategoriaEntrevista::where('school_id', $schoolId)
            ->where('activo', true)
            ->where('nombre', '!=', 'Otro')
            ->orderBy('id', 'asc')
            ->get();

        if ($categorias->isEmpty()) {
            $nombresDefault = [
                'Rendimiento Académico',
                'Conducta y Convivencia',
                'Asistencia y Puntualidad',
                'Asunto Personal / Familiar',
                'Evaluación Psicopedagógica',
                'Situación Médica',
            ];
            $categorias = collect($nombresDefault)->map(fn ($n, $i) => (object) ['id' => $i + 1, 'nombre' => $n]);
        }

        // Cursos del colegio
        $cursosQuery = Curso::with('jefe')
            ->where('school_id', $schoolId);

        if ($modalidad !== 'todas') {
            $cursosQuery->where('modalidad', $modalidad);
        }

        if (trim($search) !== '') {
            $words = array_filter(explode(' ', trim($search)));
            $cursosQuery->where(function ($q) use ($words) {
                foreach ($words as $word) {
                    $w = trim($word);
                    $q->where(function ($sq) use ($w) {
                        $sq->where('letra', 'like', "%{$w}%")
                            ->orWhere('nivel', 'like', "%{$w}%")
                            ->orWhere('modalidad', 'like', "%{$w}%")
                            ->orWhereHas('jefe', function ($jq) use ($w) {
                                $jq->where('nombres', 'like', "%{$w}%")
                                    ->orWhere('apellido_pat', 'like', "%{$w}%");
                            });
                    });
                }
            });
        }

        $cursos = $cursosQuery->orderBy('modalidad', 'asc')
            ->orderBy('nivel', 'asc')
            ->orderBy('letra', 'asc')
            ->get();

        // Entrevistas en el rango
        $entrevistasQuery = Entrevista::with('estudiante')
            ->where('school_id', $schoolId);

        if ($startDate && $endDate) {
            $entrevistasQuery->whereBetween('fecha', [$startDate, $endDate]);
        }

        $entrevistas = $entrevistasQuery->get();

        // Armar matriz por curso
        $matriz = [];
        $totalesPorCategoria = [];
        foreach ($categorias as $cat) {
            $totalesPorCategoria[$cat->nombre] = 0;
        }
        $totalesPorCategoria['Otro'] = 0;
        $granTotal = 0;

        foreach ($cursos as $curso) {
            $citasCurso = $entrevistas->filter(fn ($e) => $e->estudiante && $e->estudiante->curso_id === $curso->id);
            $totalCurso = $citasCurso->count();
            $granTotal += $totalCurso;
            $conteoPorCat = [];

            foreach ($categorias as $cat) {
                $conteoPorCat[$cat->nombre] = 0;
            }
            $conteoPorCat['Otro'] = 0;

            foreach ($citasCurso as $e) {
                $normCat = Entrevista::normalizarCategoria($e->motivo);
                if (isset($conteoPorCat[$normCat])) {
                    $conteoPorCat[$normCat]++;
                    $totalesPorCategoria[$normCat] = ($totalesPorCategoria[$normCat] ?? 0) + 1;
                } else {
                    $conteoPorCat['Otro']++;
                    $totalesPorCategoria['Otro'] = ($totalesPorCategoria['Otro'] ?? 0) + 1;
                }
            }

            $maxCount = 0;
            $dominantCat = 'Sin citaciones';
            $dominantPct = 0;

            foreach ($conteoPorCat as $catName => $cant) {
                if ($cant > $maxCount) {
                    $maxCount = $cant;
                    $dominantCat = $catName;
                }
            }

            if ($totalCurso > 0 && $maxCount > 0) {
                $dominantPct = round(($maxCount / $totalCurso) * 100);
            }

            $matriz[] = (object) [
                'curso' => $curso,
                'conteos' => $conteoPorCat,
                'total' => $totalCurso,
                'causa_dominante' => $totalCurso > 0 ? $dominantCat : 'Sin citaciones',
                'porcentaje_dominante' => $dominantPct,
            ];
        }

        $sortBy = $request->get('sortBy', 'curso');
        $sortDirection = $request->get('sortDirection', 'asc');

        if ($sortBy === 'total') {
            $matriz = $sortDirection === 'asc'
                ? collect($matriz)->sortBy('total')->values()->all()
                : collect($matriz)->sortByDesc('total')->values()->all();
        } elseif ($sortBy === 'causa_dominante') {
            $matriz = $sortDirection === 'asc'
                ? collect($matriz)->sortBy(fn ($item) => ($item->total === 0 ? 'ZZZ' : $item->causa_dominante).'_'.(100 - $item->porcentaje_dominante))->values()->all()
                : collect($matriz)->sortByDesc(fn ($item) => ($item->total === 0 ? 'AAA' : $item->causa_dominante).'_'.str_pad((string) $item->porcentaje_dominante, 3, '0', STR_PAD_LEFT))->values()->all();
        } elseif ($sortBy === 'curso' && $sortDirection === 'desc') {
            $matriz = collect($matriz)->reverse()->values()->all();
        }

        // Determinar la problemática dominante global
        $topCatGlobal = 'Ninguna';
        $topCatGlobalCount = 0;
        foreach ($totalesPorCategoria as $catName => $count) {
            if ($count > $topCatGlobalCount) {
                $topCatGlobalCount = $count;
                $topCatGlobal = $catName;
            }
        }
        $topCatGlobalPct = $granTotal > 0 ? round(($topCatGlobalCount / $granTotal) * 100) : 0;

        // Curso con más citas
        $topCurso = collect($matriz)->sortByDesc('total')->first();

        return view('pages.reportes.print_problematicas_curso', compact(
            'school',
            'user',
            'periodoLabel',
            'modalidad',
            'search',
            'categorias',
            'matriz',
            'totalesPorCategoria',
            'granTotal',
            'topCatGlobal',
            'topCatGlobalPct',
            'topCurso'
        ));
    }

    public function resumenEjecutivo(Request $request)
    {
        $user = auth()->user();
        if (! $user->hasRole(['superadmin', 'administrador', 'directivo', 'gerencia', 'rectoria']) && ! $user->can('ver-dashboard-gerencia')) {
            abort(403, 'No tienes permiso para acceder a este reporte.');
        }

        $school = $user->currentSchool;
        $schoolId = $school?->id;

        $periodo = $request->get('periodo', 'ano_actual');
        $ciclo = $request->get('ciclo', 'todos');

        $now = now('America/Santiago');
        $currentYear = $now->year;

        [$startDate, $endDate, $periodoLabel] = match ($periodo) {
            'mes_actual' => [
                $now->copy()->startOfMonth()->format('Y-m-d'),
                $now->copy()->endOfMonth()->format('Y-m-d'),
                'Mes Actual ('.$now->translatedFormat('F Y').')',
            ],
            'primer_semestre' => [
                "{$currentYear}-01-01",
                "{$currentYear}-06-30",
                "Primer Semestre {$currentYear} (Ene - Jun)",
            ],
            'segundo_semestre' => [
                "{$currentYear}-07-01",
                "{$currentYear}-12-31",
                "Segundo Semestre {$currentYear} (Jul - Dic)",
            ],
            'todo' => [
                null,
                null,
                'Todo el Historial Registrado',
            ],
            default => [
                "{$currentYear}-01-01",
                "{$currentYear}-12-31",
                "Año Escolar {$currentYear}",
            ],
        };

        // Query base
        $query = Entrevista::with(['estudiante.curso', 'user'])
            ->where('school_id', $schoolId);

        if ($startDate && $endDate) {
            $query->whereBetween('fecha', [$startDate, $endDate]);
        }

        if ($ciclo !== 'todos') {
            $query->whereHas('estudiante.curso', fn ($q) => $q->where('modalidad', $ciclo));
        }

        $entrevistas = $query->get();

        $totalAgendadas = $entrevistas->count();
        $realizadas = $entrevistas->where('estado', 'realizada')->count();
        $ausentes = $entrevistas->where('estado', 'ausente')->count();
        $canceladas = $entrevistas->where('estado', 'cancelada')->count();
        $noConcretadas = $ausentes + $canceladas;
        $pendientes = $entrevistas->whereIn('estado', ['pendiente', 'ingresada', 'abierta'])->where('fecha', '>=', $now->toDateString())->count();
        $abiertas = $entrevistas->whereIn('estado', ['pendiente', 'ingresada', 'abierta'])->where('fecha', '<', $now->toDateString())->count();

        $tasaConcrecion = $totalAgendadas > 0 ? round(($realizadas / $totalAgendadas) * 100, 1) : 0;
        $tasaInasistencia = $totalAgendadas > 0 ? round(($noConcretadas / $totalAgendadas) * 100, 1) : 0;

        // Docentes
        $docentesColegio = User::where('current_school_id', $schoolId)
            ->whereHas('roles', fn ($q) => $q->where('name', 'docente')->where('roles.team_id', $schoolId))
            ->count();
        $docentesActivos = $entrevistas->where('estado', 'realizada')->pluck('user_id')->filter()->unique()->count();
        $coberturaDocente = $docentesColegio > 0 ? round(($docentesActivos / $docentesColegio) * 100, 1) : 0;

        // Radiografía de Problemáticas
        $conteoProblematicas = [
            'Rendimiento Académico' => 0,
            'Conducta y Convivencia' => 0,
            'Asistencia y Puntualidad' => 0,
            'Asunto Personal / Familiar' => 0,
            'Evaluación Psicopedagógica' => 0,
            'Situación Médica' => 0,
            'Otro' => 0,
        ];

        foreach ($entrevistas as $e) {
            $cat = Entrevista::normalizarCategoria($e->motivo);
            if (isset($conteoProblematicas[$cat])) {
                $conteoProblematicas[$cat]++;
            } else {
                $conteoProblematicas['Otro']++;
            }
        }

        // Causa Dominante Global
        arsort($conteoProblematicas);
        $topProblematica = key($conteoProblematicas);
        $topProblematicaCant = current($conteoProblematicas);
        $topProblematicaPct = $totalAgendadas > 0 ? round(($topProblematicaCant / $totalAgendadas) * 100, 1) : 0;

        // Comparativa Ciclos
        $citasBasica = $entrevistas->filter(fn ($e) => $e->estudiante?->curso?->modalidad === Modalidad::Basica || (is_string($e->estudiante?->curso?->modalidad) && $e->estudiante?->curso?->modalidad === 'basica'));
        $citasMedia = $entrevistas->filter(fn ($e) => $e->estudiante?->curso?->modalidad === Modalidad::Media || (is_string($e->estudiante?->curso?->modalidad) && $e->estudiante?->curso?->modalidad === 'media'));

        $totalBasica = $citasBasica->count();
        $realizadasBasica = $citasBasica->where('estado', 'realizada')->count();
        $tasaBasica = $totalBasica > 0 ? round(($realizadasBasica / $totalBasica) * 100, 1) : 0;

        $totalMedia = $citasMedia->count();
        $realizadasMedia = $citasMedia->where('estado', 'realizada')->count();
        $tasaMedia = $totalMedia > 0 ? round(($realizadasMedia / $totalMedia) * 100, 1) : 0;

        // Top 5 Cursos
        $cursosAgrupados = $entrevistas->groupBy(fn ($e) => $e->estudiante?->curso_id)->filter(fn ($group, $cursoId) => ! empty($cursoId));
        $topCursos = [];
        foreach ($cursosAgrupados as $cursoId => $citas) {
            $curso = $citas->first()->estudiante?->curso;
            if (! $curso) {
                continue;
            }

            $mots = [];
            foreach ($citas as $c) {
                $norm = Entrevista::normalizarCategoria($c->motivo);
                $mots[$norm] = ($mots[$norm] ?? 0) + 1;
            }
            arsort($mots);

            $topCursos[] = (object) [
                'curso' => $curso,
                'total' => $citas->count(),
                'realizadas' => $citas->where('estado', 'realizada')->count(),
                'causa_dominante' => ! empty($mots) ? key($mots) : 'General',
            ];
        }
        $topCursos = collect($topCursos)->sortByDesc('total')->take(5)->values();

        // Top 5 Docentes
        $docentesAgrupados = $entrevistas->groupBy('user_id')->filter(fn ($group, $uid) => ! empty($uid));
        $topDocentes = [];
        foreach ($docentesAgrupados as $uid => $citas) {
            $prof = $citas->first()->user;
            if (! $prof) {
                continue;
            }

            $topDocentes[] = (object) [
                'user' => $prof,
                'total' => $citas->count(),
                'realizadas' => $citas->where('estado', 'realizada')->count(),
            ];
        }
        $topDocentes = collect($topDocentes)->sortByDesc('realizadas')->take(5)->values();

        return view('pages.gerencia.print_resumen_ejecutivo', compact(
            'school',
            'user',
            'periodoLabel',
            'ciclo',
            'totalAgendadas',
            'realizadas',
            'ausentes',
            'canceladas',
            'pendientes',
            'abiertas',
            'tasaConcrecion',
            'tasaInasistencia',
            'docentesColegio',
            'docentesActivos',
            'coberturaDocente',
            'conteoProblematicas',
            'topProblematica',
            'topProblematicaCant',
            'topProblematicaPct',
            'totalBasica',
            'realizadasBasica',
            'tasaBasica',
            'totalMedia',
            'realizadasMedia',
            'tasaMedia',
            'topCursos',
            'topDocentes'
        ));
    }
}
