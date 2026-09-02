<?php

namespace App\Http\Controllers;

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
        if (! $user->hasRole(['superadmin', 'administrador', 'directivo']) && ! $user->can('ver-reportes-entrevistas')) {
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
            ->whereDoesntHave('roles', function ($q) use ($schoolId) {
                $q->where('roles.team_id', $schoolId)
                    ->where('roles.name', 'estudiante');
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

        if ($cargo !== 'todos') {
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

        // Categorías activas
        $categorias = CategoriaEntrevista::where('school_id', $schoolId)
            ->where('activo', true)
            ->orderBy('nombre', 'asc')
            ->get();

        if ($categorias->isEmpty()) {
            $nombresDefault = ['Rendimiento Académico', 'Conducta y Convivencia', 'Asistencia y Puntualidad', 'Asunto Personal / Familiar', 'Evaluación Psicopedagógica', 'Otro'];
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
}
