<?php

use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

new #[Title('Módulo de Reportes')] class extends Component {
    use WithPagination;

    // Tipo de reporte seleccionado
    #[Url]
    public string $tipoReporte = 'entrevistas_profesor';

    // Filtros interactivos comunes y específicos
    public string $search = '';
    public string $periodo = 'ano_actual';
    public string $cargo = 'todos'; // Para reporte docentes
    public string $modalidad = 'todas'; // Para reporte problemáticas por curso
    public string $sortBy = 'nombres';
    public string $sortDirection = 'asc';

    // Modal para ver el desglose de entrevistas de un profesor
    public bool $modalDetalle = false;
    public ?int $selectedDocenteId = null;

    // Modal para ver el desglose de citas de un curso / problemática
    public bool $modalDetalleCurso = false;
    public ?int $selectedCursoId = null;
    public ?string $selectedCategoriaNombre = null;

    // Filtro para el gráfico dinámico de causas
    public string $graficoCursoId = 'todos';

    public function mount(): void
    {
        $user = auth()->user();
        if (! $user->hasRole(['superadmin', 'administrador', 'directivo', 'gerencia', 'rectoria']) && ! $user->can('ver-reportes-entrevistas')) {
            abort(403, 'No tienes permiso para acceder a la sección de reportes.');
        }

        if ($this->tipoReporte === 'problematicas_curso' && $this->sortBy === 'nombres') {
            $this->sortBy = 'curso';
            $this->sortDirection = 'asc';
        }
    }

    public function updatedTipoReporte(): void
    {
        $this->resetPage();
        $this->search = '';
        $this->graficoCursoId = 'todos';
        if ($this->tipoReporte === 'problematicas_curso') {
            $this->sortBy = 'curso';
            $this->sortDirection = 'asc';
        } else {
            $this->sortBy = 'nombres';
            $this->sortDirection = 'asc';
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPeriodo(): void
    {
        $this->resetPage();
    }

    public function updatedCargo(): void
    {
        $this->resetPage();
    }

    public function updatedModalidad(): void
    {
        $this->resetPage();
    }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    /**
     * Obtiene el rango de fechas [inicio, fin] según el filtro de período
     */
    private function getPeriodoDates(): array
    {
        $now = now('America/Santiago');
        $currentYear = $now->year;

        return match ($this->periodo) {
            'mes_actual' => [
                $now->copy()->startOfMonth()->format('Y-m-d'),
                $now->copy()->endOfMonth()->format('Y-m-d'),
            ],
            'primer_semestre' => [
                "{$currentYear}-01-01",
                "{$currentYear}-06-30",
            ],
            'segundo_semestre' => [
                "{$currentYear}-07-01",
                "{$currentYear}-12-31",
            ],
            'ano_actual' => [
                "{$currentYear}-01-01",
                "{$currentYear}-12-31",
            ],
            default => [null, null], // 'todo'
        };
    }

    /**
     * Categorías activas del colegio para el reporte de problemáticas
     */
    #[Computed]
    public function categorias()
    {
        $schoolId = auth()->user()->current_school_id;
        $cats = \App\Models\CategoriaEntrevista::where('school_id', $schoolId)
            ->where('activo', true)
            ->where('nombre', '!=', 'Otro')
            ->orderBy('id', 'asc')
            ->get();

        if ($cats->isEmpty()) {
            $nombresDefault = [
                'Rendimiento Académico',
                'Conducta y Convivencia',
                'Asistencia y Puntualidad',
                'Asunto Personal / Familiar',
                'Evaluación Psicopedagógica',
                'Situación Médica',
            ];

            return collect($nombresDefault)->map(fn ($n, $i) => (object) ['id' => $i + 1, 'nombre' => $n]);
        }

        return $cats;
    }

    /* =========================================================================
       LÓGICA REPORTE 1: ENTREVISTAS POR PROFESOR
       ========================================================================= */

    public function getBaseQuery()
    {
        $schoolId = auth()->user()->current_school_id;
        [$startDate, $endDate] = $this->getPeriodoDates();

        $dateClosure = function ($q) use ($startDate, $endDate) {
            if ($startDate && $endDate) {
                $q->whereBetween('fecha', [$startDate, $endDate]);
            }
        };

        $today = now('America/Santiago')->toDateString();

        $query = \App\Models\User::query()
            ->whereHas('schools', fn($q) => $q->where('schools.id', $schoolId))
            ->whereHas('roles', function ($q) use ($schoolId) {
                $q->where('roles.team_id', $schoolId)
                    ->whereIn('roles.name', ['docente', 'directivo', 'psicosocial']);
            })
            ->whereRaw("SUBSTR(email, 1, 1) != '_'")
            ->where('email', 'not like', 'docente1@%')
            ->where('email', 'not like', 'test%')
            ->withCount([
                'entrevistas as total_agendadas' => fn($q) => $q->where('school_id', $schoolId)->where($dateClosure),
                'entrevistas as total_realizadas' => fn($q) => $q->where('school_id', $schoolId)->where('estado', 'realizada')->where($dateClosure),
                'entrevistas as total_canceladas' => fn($q) => $q->where('school_id', $schoolId)->whereIn('estado', ['cancelada', 'ausente'])->where($dateClosure),
                'entrevistas as total_pendientes' => fn($q) => $q->where('school_id', $schoolId)->whereIn('estado', ['pendiente', 'ingresada', 'abierta'])->where('fecha', '>=', $today)->where($dateClosure),
                'entrevistas as total_abiertas' => fn($q) => $q->where('school_id', $schoolId)->whereIn('estado', ['pendiente', 'ingresada', 'abierta'])->where('fecha', '<', $today)->where($dateClosure),
            ]);

        if ($this->cargo !== 'todos' && in_array($this->cargo, ['docente', 'directivo', 'psicosocial'])) {
            $query->whereHas('roles', function ($q) use ($schoolId) {
                $q->where('roles.team_id', $schoolId)
                    ->where('roles.name', $this->cargo);
            });
        }

        if (trim($this->search) !== '') {
            $words = array_filter(explode(' ', trim($this->search)));
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

        if (in_array($this->sortBy, ['total_agendadas', 'total_realizadas', 'total_canceladas', 'total_pendientes', 'total_abiertas'])) {
            $query->orderBy($this->sortBy, $this->sortDirection);
        } elseif ($this->sortBy === 'rut_numero') {
            $query->orderBy('rut_numero', $this->sortDirection);
        } else {
            $query->orderBy('nombres', $this->sortDirection)
                ->orderBy('apellido_pat', $this->sortDirection);
        }

        return $query;
    }

    #[Computed]
    public function funcionarios()
    {
        return $this->getBaseQuery()->paginate(50);
    }

    #[Computed]
    public function totalStats(): array
    {
        $schoolId = auth()->user()->current_school_id;
        [$startDate, $endDate] = $this->getPeriodoDates();
        $today = now('America/Santiago')->toDateString();

        $citasQuery = \App\Models\Entrevista::where('school_id', $schoolId)
            ->whereHas('user.roles', function ($q) use ($schoolId) {
                $q->where('roles.team_id', $schoolId)
                    ->whereIn('roles.name', ['docente', 'directivo', 'psicosocial']);
            });

        if ($this->cargo !== 'todos' && in_array($this->cargo, ['docente', 'directivo', 'psicosocial'])) {
            $citasQuery->whereHas('user.roles', function ($q) use ($schoolId) {
                $q->where('roles.team_id', $schoolId)
                    ->where('roles.name', $this->cargo);
            });
        }

        if ($startDate && $endDate) {
            $citasQuery->whereBetween('fecha', [$startDate, $endDate]);
        }

        $agendadas = (clone $citasQuery)->count();
        $realizadas = (clone $citasQuery)->where('estado', 'realizada')->count();
        $canceladas = (clone $citasQuery)->whereIn('estado', ['cancelada', 'ausente'])->count();
        $pendientes = (clone $citasQuery)->whereIn('estado', ['pendiente', 'ingresada', 'abierta'])->where('fecha', '>=', $today)->count();
        $abiertas = (clone $citasQuery)->whereIn('estado', ['pendiente', 'ingresada', 'abierta'])->where('fecha', '<', $today)->count();

        return [
            'agendadas' => $agendadas,
            'realizadas' => $realizadas,
            'canceladas' => $canceladas,
            'pendientes' => $pendientes,
            'abiertas' => $abiertas,
            'tasa_realizacion' => $agendadas > 0 ? round(($realizadas / $agendadas) * 100) : 0,
        ];
    }

    public function verDetalle(int $docenteId): void
    {
        $this->selectedDocenteId = $docenteId;
        $this->modalDetalle = true;
    }

    #[Computed]
    public function selectedDocente()
    {
        if (! $this->selectedDocenteId) {
            return null;
        }

        return \App\Models\User::find($this->selectedDocenteId);
    }

    #[Computed]
    public function detalleEntrevistas()
    {
        if (! $this->selectedDocenteId) {
            return collect();
        }

        $schoolId = auth()->user()->current_school_id;
        [$startDate, $endDate] = $this->getPeriodoDates();

        $query = \App\Models\Entrevista::with(['estudiante.curso', 'bitacora'])
            ->where('school_id', $schoolId)
            ->where('user_id', $this->selectedDocenteId);

        if ($startDate && $endDate) {
            $query->whereBetween('fecha', [$startDate, $endDate]);
        }

        return $query->orderBy('fecha', 'desc')->orderBy('hora', 'desc')->get();
    }

    public function exportarExcel()
    {
        $school = auth()->user()->currentSchool;
        $periodoTexto = match($this->periodo) {
            'mes_actual' => 'Mes_Actual',
            'primer_semestre' => '1er_Semestre',
            'segundo_semestre' => '2do_Semestre',
            'todo' => 'Historico_Completo',
            default => 'Ano_' . now('America/Santiago')->year,
        };

        $filename = 'reporte_entrevistas_por_profesor_' . $periodoTexto . '_' . now('America/Santiago')->format('Y-m-d_His') . '.csv';
        $data = $this->getBaseQuery()->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'Funcionario / Docente',
                'Correo Institucional',
                'RUT',
                'Cargo / Roles',
                'Total Agendadas',
                'Realizadas',
                'Canceladas / Ausentes',
                'Pendientes (Próximas)',
                'Abiertas (Sin Cerrar)',
                'Tasa Realizacion (%)',
            ], ';');

            foreach ($data as $docente) {
                $displayRoles = array_diff($docente->active_roles, ['superadmin', 'externo']);
                $rolesTexto = !empty($displayRoles) ? implode(', ', array_map('ucfirst', $displayRoles)) : 'Docente';
                $tasa = $docente->total_agendadas > 0
                    ? round(($docente->total_realizadas / $docente->total_agendadas) * 100) . '%'
                    : '0%';

                fputcsv($file, [
                    $docente->nombreCompleto(),
                    $docente->email,
                    $docente->rutCompleto() ?? 'No registrado',
                    $rolesTexto,
                    $docente->total_agendadas,
                    $docente->total_realizadas,
                    $docente->total_canceladas,
                    $docente->total_pendientes,
                    $docente->total_abiertas,
                    $tasa,
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /* =========================================================================
       LÓGICA REPORTE 3: MATRIZ DE PROBLEMÁTICAS POR CURSO Y MOTIVO
       ========================================================================= */

    #[Computed]
    public function matrizProblematicas()
    {
        $schoolId = auth()->user()->current_school_id;
        [$startDate, $endDate] = $this->getPeriodoDates();

        $cursosQuery = \App\Models\Curso::with('jefe')
            ->where('school_id', $schoolId);

        if ($this->modalidad !== 'todas') {
            $cursosQuery->where('modalidad', $this->modalidad);
        }

        if (trim($this->search) !== '') {
            $words = array_filter(explode(' ', trim($this->search)));
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

        $entrevistasQuery = \App\Models\Entrevista::select('id', 'school_id', 'estudiante_id', 'motivo', 'fecha')
            ->with('estudiante:id,curso_id')
            ->where('school_id', $schoolId);

        if ($startDate && $endDate) {
            $entrevistasQuery->whereBetween('fecha', [$startDate, $endDate]);
        }

        $entrevistas = $entrevistasQuery->get();
        $categorias = $this->categorias;

        $matriz = [];

        foreach ($cursos as $curso) {
            $citasCurso = $entrevistas->filter(fn ($e) => $e->estudiante && $e->estudiante->curso_id === $curso->id);
            $totalCurso = $citasCurso->count();
            $conteoPorCat = [];

            foreach ($categorias as $cat) {
                $conteoPorCat[$cat->nombre] = 0;
            }
            $conteoPorCat['Otro'] = 0;

            foreach ($citasCurso as $e) {
                $normCat = \App\Models\Entrevista::normalizarCategoria($e->motivo);
                if (isset($conteoPorCat[$normCat])) {
                    $conteoPorCat[$normCat]++;
                } else {
                    $conteoPorCat['Otro']++;
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

        if ($this->sortBy === 'total') {
            $matriz = $this->sortDirection === 'asc'
                ? collect($matriz)->sortBy('total')->values()->all()
                : collect($matriz)->sortByDesc('total')->values()->all();
        } elseif ($this->sortBy === 'causa_dominante') {
            $matriz = $this->sortDirection === 'asc'
                ? collect($matriz)->sortBy(fn ($item) => ($item->total === 0 ? 'ZZZ' : $item->causa_dominante) . '_' . (100 - $item->porcentaje_dominante))->values()->all()
                : collect($matriz)->sortByDesc(fn ($item) => ($item->total === 0 ? 'AAA' : $item->causa_dominante) . '_' . str_pad((string)$item->porcentaje_dominante, 3, '0', STR_PAD_LEFT))->values()->all();
        } elseif ($this->sortBy === 'curso') {
            if ($this->sortDirection === 'desc') {
                $matriz = collect($matriz)->reverse()->values()->all();
            }
        } elseif ($this->sortBy === 'jefe') {
            $matriz = $this->sortDirection === 'asc'
                ? collect($matriz)->sortBy(fn ($item) => $item->curso->jefe ? $item->curso->jefe->nombreCompleto() : 'ZZZ')->values()->all()
                : collect($matriz)->sortByDesc(fn ($item) => $item->curso->jefe ? $item->curso->jefe->nombreCompleto() : '')->values()->all();
        } elseif ($this->categorias->pluck('nombre')->contains($this->sortBy) || $this->sortBy === 'Otro') {
            $catName = $this->sortBy;
            $matriz = $this->sortDirection === 'asc'
                ? collect($matriz)->sortBy(fn ($item) => $item->conteos[$catName] ?? 0)->values()->all()
                : collect($matriz)->sortByDesc(fn ($item) => $item->conteos[$catName] ?? 0)->values()->all();
        }

        return $matriz;
    }

    #[Computed]
    public function statsProblematicas(): array
    {
        $matriz = $this->matrizProblematicas;
        $categorias = $this->categorias;

        $totalesPorCategoria = [];
        foreach ($categorias as $cat) {
            $totalesPorCategoria[$cat->nombre] = 0;
        }
        $totalesPorCategoria['Otro'] = 0;
        $granTotal = 0;

        foreach ($matriz as $fila) {
            $granTotal += $fila->total;
            foreach ($fila->conteos as $catName => $cant) {
                $totalesPorCategoria[$catName] = ($totalesPorCategoria[$catName] ?? 0) + $cant;
            }
        }

        $topCatGlobal = 'Ninguna';
        $topCatGlobalCount = 0;
        foreach ($totalesPorCategoria as $catName => $count) {
            if ($count > $topCatGlobalCount) {
                $topCatGlobalCount = $count;
                $topCatGlobal = $catName;
            }
        }
        $topCatGlobalPct = $granTotal > 0 ? round(($topCatGlobalCount / $granTotal) * 100) : 0;

        $topCurso = collect($matriz)->sortByDesc('total')->first();

        return [
            'granTotal' => $granTotal,
            'totalesPorCategoria' => $totalesPorCategoria,
            'topCatGlobal' => $topCatGlobal,
            'topCatGlobalPct' => $topCatGlobalPct,
            'topCurso' => $topCurso,
        ];
    }

    #[Computed]
    public function cursosList()
    {
        $schoolId = auth()->user()->current_school_id;
        $query = \App\Models\Curso::with('jefe')
            ->where('school_id', $schoolId);

        if ($this->modalidad !== 'todas') {
            $query->where('modalidad', $this->modalidad);
        }

        return $query->orderBy('modalidad', 'asc')
            ->orderBy('nivel', 'asc')
            ->orderBy('letra', 'asc')
            ->get();
    }

    #[Computed]
    public function datosGraficoCausas(): array
    {
        $schoolId = auth()->user()->current_school_id;
        [$startDate, $endDate] = $this->getPeriodoDates();

        $query = \App\Models\Entrevista::select('id', 'school_id', 'estudiante_id', 'motivo', 'fecha')
            ->with('estudiante:id,curso_id')
            ->where('school_id', $schoolId);

        if ($startDate && $endDate) {
            $query->whereBetween('fecha', [$startDate, $endDate]);
        }

        if ($this->graficoCursoId !== 'todos') {
            $cursoId = (int) $this->graficoCursoId;
            $query->whereHas('estudiante', fn ($q) => $q->where('curso_id', $cursoId));
            $cursoModel = \App\Models\Curso::with('jefe')->find($cursoId);
            $cursoTitulo = $cursoModel ? $cursoModel->nombreCompleto() : 'Curso seleccionado';
            $jefeTitulo = $cursoModel?->jefe ? $cursoModel->jefe->nombreCompleto() : null;
        } else {
            if ($this->modalidad !== 'todas') {
                $query->whereHas('estudiante.curso', fn ($q) => $q->where('modalidad', $this->modalidad));
            }
            $cursoTitulo = 'Consolidado General (Todo el Establecimiento)';
            $jefeTitulo = null;
        }

        $entrevistas = $query->get();
        $categorias = $this->categorias;

        $conteoPorCat = [];
        foreach ($categorias as $cat) {
            $conteoPorCat[$cat->nombre] = 0;
        }
        $conteoPorCat['Otro'] = 0;

        foreach ($entrevistas as $e) {
            $norm = \App\Models\Entrevista::normalizarCategoria($e->motivo);
            if (isset($conteoPorCat[$norm])) {
                $conteoPorCat[$norm]++;
            } else {
                $conteoPorCat['Otro']++;
            }
        }

        $labels = [];
        $data = [];
        $percentages = [];
        $colors = [];
        $total = array_sum($conteoPorCat);

        $colorMap = [
            'Rendimiento Académico' => '#2563eb', // Blue
            'Conducta y Convivencia' => '#e11d48', // Rose / Red
            'Asistencia y Puntualidad' => '#d97706', // Amber
            'Asunto Personal / Familiar' => '#7c3aed', // Purple
            'Evaluación Psicopedagógica' => '#0891b2', // Cyan
            'Situación Médica' => '#059669', // Emerald
            'Otro' => '#64748b', // Slate
        ];

        $shortLabels = [
            'Rendimiento Académico' => 'Rendimiento',
            'Conducta y Convivencia' => 'Conducta',
            'Asistencia y Puntualidad' => 'Asistencia',
            'Asunto Personal / Familiar' => 'Familiar',
            'Evaluación Psicopedagógica' => 'Psicoped.',
            'Situación Médica' => 'Médica',
            'Otro' => 'Otro',
        ];

        $ranking = [];

        foreach ($conteoPorCat as $catName => $cant) {
            $labels[] = $shortLabels[$catName] ?? $catName;
            $data[] = $cant;
            $pct = $total > 0 ? round(($cant / $total) * 100) : 0;
            $percentages[] = $pct;
            $colors[] = $colorMap[$catName] ?? '#64748b';

            if ($cant > 0 || $total === 0) {
                $ranking[] = [
                    'nombre' => $catName,
                    'short' => $shortLabels[$catName] ?? $catName,
                    'conteo' => $cant,
                    'porcentaje' => $pct,
                    'color' => $colorMap[$catName] ?? '#64748b',
                ];
            }
        }

        usort($ranking, fn ($a, $b) => $b['conteo'] <=> $a['conteo']);

        return [
            'labels' => $labels,
            'fullLabels' => array_keys($conteoPorCat),
            'data' => $data,
            'percentages' => $percentages,
            'colors' => $colors,
            'total' => $total,
            'cursoTitulo' => $cursoTitulo,
            'jefeTitulo' => $jefeTitulo,
            'ranking' => $ranking,
        ];
    }

    public function verDetalleCurso(int $cursoId, ?string $categoriaNombre = null): void
    {
        $this->selectedCursoId = $cursoId;
        $this->selectedCategoriaNombre = $categoriaNombre;
        $this->modalDetalleCurso = true;
    }

    #[Computed]
    public function selectedCurso()
    {
        if (! $this->selectedCursoId) {
            return null;
        }

        return \App\Models\Curso::with('jefe')->find($this->selectedCursoId);
    }

    #[Computed]
    public function detalleCitasCurso()
    {
        if (! $this->selectedCursoId) {
            return collect();
        }

        $schoolId = auth()->user()->current_school_id;
        [$startDate, $endDate] = $this->getPeriodoDates();

        $query = \App\Models\Entrevista::with(['estudiante', 'user', 'bitacora'])
            ->where('school_id', $schoolId)
            ->whereHas('estudiante', fn ($q) => $q->where('curso_id', $this->selectedCursoId));

        if ($startDate && $endDate) {
            $query->whereBetween('fecha', [$startDate, $endDate]);
        }

        $citas = $query->orderBy('fecha', 'desc')->orderBy('hora', 'desc')->get();

        if ($this->selectedCategoriaNombre) {
            $target = $this->selectedCategoriaNombre;
            $citas = $citas->filter(function ($e) use ($target) {
                return \App\Models\Entrevista::normalizarCategoria($e->motivo) === $target;
            });
        }

        return $citas;
    }

    public function exportarExcelProblematicas()
    {
        $school = auth()->user()->currentSchool;
        $periodoTexto = match($this->periodo) {
            'mes_actual' => 'Mes_Actual',
            'primer_semestre' => '1er_Semestre',
            'segundo_semestre' => '2do_Semestre',
            'todo' => 'Historico_Completo',
            default => 'Ano_' . now('America/Santiago')->year,
        };

        $filename = 'matriz_problematicas_por_curso_' . $periodoTexto . '_' . now('America/Santiago')->format('Y-m-d_His') . '.csv';
        $matriz = $this->matrizProblematicas;
        $categorias = $this->categorias;

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($matriz, $categorias) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF");

            $headerRow = ['Curso', 'Modalidad', 'Profesor(a) Jefe'];
            foreach ($categorias as $cat) {
                $headerRow[] = $cat->nombre;
            }
            $headerRow[] = 'Otro';
            $headerRow[] = 'Total Citaciones';
            $headerRow[] = 'Causa Dominante';
            $headerRow[] = '% Dominante';

            fputcsv($file, $headerRow, ';');

            foreach ($matriz as $fila) {
                $cursoNombre = $fila->curso->nombreCompleto();
                $modalidadStr = $fila->curso->modalidad instanceof \App\Enums\Modalidad ? $fila->curso->modalidad->label() : ucfirst((string)$fila->curso->modalidad);
                $jefeNombre = $fila->curso->jefe ? $fila->curso->jefe->nombreCompleto() : 'Sin asignar';

                $row = [
                    $cursoNombre,
                    $modalidadStr,
                    $jefeNombre,
                ];

                foreach ($categorias as $cat) {
                    $row[] = $fila->conteos[$cat->nombre] ?? 0;
                }
                $row[] = $fila->conteos['Otro'] ?? 0;
                $row[] = $fila->total;
                $row[] = $fila->causa_dominante;
                $row[] = $fila->porcentaje_dominante . '%';

                fputcsv($file, $row, ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}; ?>

<div>
    <div class="flex flex-col gap-8 w-full max-w-7xl mx-auto print:max-w-full">
        <!-- Header con Selector de Reportes y Botones de Exportación -->
        <div>
            <x-header :titulo="__('Módulo de Reportes')" :subtitulo="__(
                'Analítica, estadísticas consolidadas y métricas de desempeño escolar.',
            )" icono="chart-bar">
                <div class="flex items-center gap-2">
                    @if($tipoReporte === 'entrevistas_profesor')
                        <a href="{{ route('reportes.imprimir.profesores', ['periodo' => $periodo, 'cargo' => $cargo, 'search' => $search]) }}" target="_blank">
                            <flux:button variant="ghost" icon="printer">
                                {{ __('Imprimir / PDF') }}
                            </flux:button>
                        </a>
                        <flux:button variant="primary" icon="arrow-down-tray" wire:click="exportarExcel">
                            {{ __('Exportar Excel') }}
                        </flux:button>
                    @else
                        <a href="{{ route('reportes.imprimir.problematicas', ['periodo' => $periodo, 'modalidad' => $modalidad, 'search' => $search, 'sortBy' => $sortBy, 'sortDirection' => $sortDirection]) }}" target="_blank">
                            <flux:button variant="ghost" icon="printer">
                                {{ __('Imprimir / PDF') }}
                            </flux:button>
                        </a>
                        <flux:button variant="primary" icon="arrow-down-tray" wire:click="exportarExcelProblematicas">
                            {{ __('Exportar Excel') }}
                        </flux:button>
                    @endif
                </div>
            </x-header>
        </div>

        <!-- Selector de Tipo de Reporte -->
        <flux:card class="p-4 bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700 print:hidden">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 bg-[#00376e]/10 dark:bg-blue-900/30 rounded-lg text-[#00376e] dark:text-blue-400">
                        <flux:icon.document-chart-bar class="size-6" />
                    </div>
                    <div>
                        <flux:heading size="md" class="text-zinc-900 dark:text-zinc-100">
                            {{ __('Seleccione el Reporte a Consultar') }}
                        </flux:heading>
                        <flux:text class="text-xs text-zinc-500">
                            {{ __('Elija el tipo de análisis institucional que desea visualizar o exportar.') }}
                        </flux:text>
                    </div>
                </div>

                <div class="w-full sm:w-80">
                    <flux:select wire:model.live="tipoReporte">
                        <flux:select.option value="entrevistas_profesor">{{ __('📊 Entrevistas por Profesor') }}</flux:select.option>
                        <flux:select.option value="problematicas_curso">{{ __('🎯 Matriz: Problemáticas por Curso') }}</flux:select.option>
                    </flux:select>
                </div>
            </div>
        </flux:card>

        @if($tipoReporte === 'entrevistas_profesor')
            <!-- FILTROS Y TARJETA PARA: ENTREVISTAS POR PROFESOR -->
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <div class="md:col-span-8">
                    <flux:card class="h-full flex flex-col justify-center">
                        <div class="flex flex-col md:flex-row items-stretch md:items-center gap-4">
                            <flux:input wire:model.live.debounce.300ms="search" placeholder="Buscar por Nombre o RUT..."
                                icon="magnifying-glass" clearable class="flex-1" />

                            <div class="h-12 w-px bg-zinc-200 dark:bg-zinc-700 hidden md:block"></div>

                            <flux:field class="w-full md:w-44">
                                <flux:label class="mb-2 uppercase tracking-widest text-[10px] font-bold text-zinc-500 dark:text-zinc-400">
                                    {{ __('Período') }}
                                </flux:label>
                                <flux:select wire:model.live="periodo">
                                    <flux:select.option value="ano_actual">{{ __('Año Actual') }}</flux:select.option>
                                    <flux:select.option value="mes_actual">{{ __('Mes Actual') }}</flux:select.option>
                                    <flux:select.option value="primer_semestre">{{ __('1er Semestre') }}</flux:select.option>
                                    <flux:select.option value="segundo_semestre">{{ __('2do Semestre') }}</flux:select.option>
                                    <flux:select.option value="todo">{{ __('Todo el Historial') }}</flux:select.option>
                                </flux:select>
                            </flux:field>

                            <div class="h-12 w-px bg-zinc-200 dark:bg-zinc-700 hidden md:block"></div>

                            <flux:field class="w-full md:w-44">
                                <flux:label class="mb-2 uppercase tracking-widest text-[10px] font-bold text-zinc-500 dark:text-zinc-400">
                                    {{ __('Cargo (Rol)') }}
                                </flux:label>
                                <flux:select wire:model.live="cargo">
                                    <flux:select.option value="todos">{{ __('Todos (Docentes/Directivos/Psicosocial)') }}</flux:select.option>
                                    <flux:select.option value="docente">{{ __('Docentes') }}</flux:select.option>
                                    <flux:select.option value="directivo">{{ __('Directivos') }}</flux:select.option>
                                    <flux:select.option value="psicosocial">{{ __('Psicosocial') }}</flux:select.option>
                                </flux:select>
                            </flux:field>
                        </div>
                    </flux:card>
                </div>

                <!-- Tarjeta Métrica Negra -->
                <div class="md:col-span-4">
                    <flux:card class="h-full flex items-center justify-between bg-zinc-900 border-none !text-white dark:bg-zinc-800 shadow-md">
                        <div>
                            <div class="text-[10px] uppercase tracking-widest font-bold opacity-70">
                                {{ __('Total Funcionarios') }}
                            </div>
                            <div class="text-4xl font-bold mt-1 tracking-tight">{{ $this->funcionarios->total() }}</div>
                            <div class="text-xs text-zinc-300 mt-1 font-medium">
                                {{ $this->totalStats['agendadas'] }} entrevistas en total ({{ $this->totalStats['realizadas'] }} realizadas)
                            </div>
                        </div>
                        <div class="p-3 bg-white/10 rounded-xl text-white">
                            <flux:icon.user-group class="size-8" />
                        </div>
                    </flux:card>
                </div>
            </div>

            <!-- Tabla de Datos Profesores -->
            <flux:card>
                <flux:table :paginate="$this->funcionarios">
                    <flux:table.columns>
                        <flux:table.column sortable :sorted="$sortBy === 'nombres'" :direction="$sortDirection"
                            wire:click="sort('nombres')">{{ __('Funcionario') }}</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'total_agendadas'" :direction="$sortDirection"
                            wire:click="sort('total_agendadas')" class="text-center">{{ __('Agendadas') }}</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'total_realizadas'" :direction="$sortDirection"
                            wire:click="sort('total_realizadas')" class="text-center">{{ __('Realizadas') }}</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'total_canceladas'" :direction="$sortDirection"
                            wire:click="sort('total_canceladas')" class="text-center">{{ __('Canceladas / Ausentes') }}</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'total_pendientes'" :direction="$sortDirection"
                            wire:click="sort('total_pendientes')" class="text-center">{{ __('Pendientes') }}</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'total_abiertas'" :direction="$sortDirection"
                            wire:click="sort('total_abiertas')" class="text-center" title="Citas pasadas sin cerrar">{{ __('Abiertas') }}</flux:table.column>
                        <flux:table.column class="text-right print:hidden">{{ __('Acciones') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($this->funcionarios as $funcionario)
                            <flux:table.row :key="$funcionario->id">
                                <flux:table.cell>
                                    <div class="flex items-center gap-3">
                                        <flux:avatar class="print:hidden">
                                            @if ($funcionario->avatar)
                                                <img src="{{ $funcionario->avatar }}" 
                                                     alt="{{ $funcionario->nombres }}" 
                                                     class="rounded-lg size-full object-cover" 
                                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='';"
                                                     referrerpolicy="no-referrer">
                                                <span style="display: none;">{{ $funcionario->initials() }}</span>
                                            @else
                                                <span>{{ $funcionario->initials() }}</span>
                                            @endif
                                        </flux:avatar>
                                        <div>
                                            <div class="text-sm font-bold text-zinc-900 dark:text-zinc-100">
                                                {{ $funcionario->nombreCompleto() }}
                                            </div>
                                            <div class="text-xs text-zinc-500 font-mono">{{ $funcionario->email }}</div>
                                        </div>
                                    </div>
                                </flux:table.cell>

                                {{-- AGENDADAS --}}
                                <flux:table.cell class="text-center font-bold text-zinc-800 dark:text-zinc-200">
                                    <span class="px-2.5 py-1 bg-zinc-100 dark:bg-zinc-800 rounded-md font-mono text-sm">
                                        {{ $funcionario->total_agendadas }}
                                    </span>
                                </flux:table.cell>

                                {{-- REALIZADAS --}}
                                <flux:table.cell class="text-center font-bold">
                                    @if($funcionario->total_realizadas > 0)
                                        <span class="px-2.5 py-1 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/50 rounded-md font-mono text-sm">
                                            {{ $funcionario->total_realizadas }}
                                        </span>
                                    @else
                                        <span class="text-zinc-400 font-mono text-sm">0</span>
                                    @endif
                                </flux:table.cell>

                                {{-- CANCELADAS / AUSENTES --}}
                                <flux:table.cell class="text-center font-bold">
                                    @if($funcionario->total_canceladas > 0)
                                        <span class="px-2.5 py-1 bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800/50 rounded-md font-mono text-sm">
                                            {{ $funcionario->total_canceladas }}
                                        </span>
                                    @else
                                        <span class="text-zinc-400 font-mono text-sm">0</span>
                                    @endif
                                </flux:table.cell>

                                {{-- PENDIENTES (VIGENTES) --}}
                                <flux:table.cell class="text-center font-bold">
                                    @if($funcionario->total_pendientes > 0)
                                        <span class="px-2.5 py-1 bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800/50 rounded-md font-mono text-sm">
                                            {{ $funcionario->total_pendientes }}
                                        </span>
                                    @else
                                        <span class="text-zinc-400 font-mono text-sm">0</span>
                                    @endif
                                </flux:table.cell>

                                {{-- ABIERTAS (SIN CERRAR / VENCIDAS) --}}
                                <flux:table.cell class="text-center font-bold">
                                    @if($funcionario->total_abiertas > 0)
                                        <span class="px-2.5 py-1 bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/50 rounded-md font-mono text-sm font-bold" title="Citas con fecha pasada que aún no se han cerrado">
                                            {{ $funcionario->total_abiertas }}
                                        </span>
                                    @else
                                        <span class="text-zinc-400 font-mono text-sm">0</span>
                                    @endif
                                </flux:table.cell>

                                {{-- ACCIONES --}}
                                <flux:table.cell class="text-right print:hidden">
                                    <flux:button size="xs" variant="ghost" icon="eye" wire:click="verDetalle({{ $funcionario->id }})" title="Ver detalle de citas">
                                        {{ __('Ver Citas') }}
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="8" class="text-center py-10 text-zinc-500">
                                    <div class="flex flex-col items-center justify-center gap-2">
                                        <flux:icon.magnifying-glass class="size-8 text-zinc-400" />
                                        <p class="font-medium text-sm">No se encontraron funcionarios con los filtros seleccionados.</p>
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>

        @else
            <!-- FILTROS Y TARJETAS PARA: MATRIZ DE PROBLEMÁTICAS POR CURSO -->
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <div class="md:col-span-7">
                    <flux:card class="h-full flex flex-col justify-center">
                        <div class="flex flex-col md:flex-row items-stretch md:items-center gap-4">
                            <flux:input wire:model.live.debounce.300ms="search" placeholder="Buscar por Curso o Profesor Jefe..."
                                icon="magnifying-glass" clearable class="flex-1" />

                            <div class="h-12 w-px bg-zinc-200 dark:bg-zinc-700 hidden md:block"></div>

                            <flux:field class="w-full md:w-44">
                                <flux:label class="mb-2 uppercase tracking-widest text-[10px] font-bold text-zinc-500 dark:text-zinc-400">
                                    {{ __('Período') }}
                                </flux:label>
                                <flux:select wire:model.live="periodo">
                                    <flux:select.option value="ano_actual">{{ __('Año Actual') }}</flux:select.option>
                                    <flux:select.option value="mes_actual">{{ __('Mes Actual') }}</flux:select.option>
                                    <flux:select.option value="primer_semestre">{{ __('1er Semestre') }}</flux:select.option>
                                    <flux:select.option value="segundo_semestre">{{ __('2do Semestre') }}</flux:select.option>
                                    <flux:select.option value="todo">{{ __('Todo el Historial') }}</flux:select.option>
                                </flux:select>
                            </flux:field>

                            <div class="h-12 w-px bg-zinc-200 dark:bg-zinc-700 hidden md:block"></div>

                            <flux:field class="w-full md:w-36">
                                <flux:label class="mb-2 uppercase tracking-widest text-[10px] font-bold text-zinc-500 dark:text-zinc-400">
                                    {{ __('Modalidad') }}
                                </flux:label>
                                <flux:select wire:model.live="modalidad">
                                    <flux:select.option value="todas">{{ __('Todas') }}</flux:select.option>
                                    <flux:select.option value="basica">{{ __('Básica') }}</flux:select.option>
                                    <flux:select.option value="media">{{ __('Media') }}</flux:select.option>
                                </flux:select>
                            </flux:field>

                            <div class="h-12 w-px bg-zinc-200 dark:bg-zinc-700 hidden md:block"></div>

                            <flux:field class="w-full md:w-48">
                                <flux:label class="mb-2 uppercase tracking-widest text-[10px] font-bold text-zinc-500 dark:text-zinc-400">
                                    {{ __('Ordenar Por') }}
                                </flux:label>
                                <flux:select wire:model.live="sortBy">
                                    <flux:select.option value="curso">{{ __('Curso (Básica a Media)') }}</flux:select.option>
                                    <flux:select.option value="causa_dominante">{{ __('Causa Dominante') }}</flux:select.option>
                                    <flux:select.option value="total">{{ __('Mayor Total Citas') }}</flux:select.option>
                                </flux:select>
                            </flux:field>
                        </div>
                    </flux:card>
                </div>

                <!-- Tarjeta Diagnóstico Escolar -->
                <div class="md:col-span-5">
                    <flux:card class="h-full flex items-center justify-between bg-zinc-900 border-none !text-white dark:bg-zinc-800 shadow-md p-5">
                        <div class="space-y-1">
                            <div class="text-[10px] uppercase tracking-widest font-bold opacity-70">
                                {{ __('Diagnóstico Global') }}
                            </div>
                            <div class="text-xl font-bold tracking-tight text-amber-400">
                                {{ $this->statsProblematicas['topCatGlobal'] }}
                                @if($this->statsProblematicas['granTotal'] > 0)
                                    <span class="text-sm font-normal text-zinc-300">({{ $this->statsProblematicas['topCatGlobalPct'] }}% de citaciones)</span>
                                @endif
                            </div>
                            <div class="text-xs text-zinc-300">
                                Total: <strong>{{ $this->statsProblematicas['granTotal'] }}</strong> citas registradas en cursos.
                                @if($this->statsProblematicas['topCurso'] && $this->statsProblematicas['topCurso']->total > 0)
                                    • Mayor foco: <strong class="text-white">{{ $this->statsProblematicas['topCurso']->curso->nombreCompleto() }}</strong>
                                @endif
                            </div>
                        </div>
                        <div class="p-3 bg-white/10 rounded-xl text-amber-400 shrink-0">
                            <flux:icon.exclamation-triangle class="size-8" />
                        </div>
                    </flux:card>
                </div>
            </div>

            <!-- Gráfico Dinámico de Causas por Curso (Columnas Verticales Hacia Arriba) -->
            <flux:card class="p-6 relative overflow-hidden">
                <!-- Overlay de Carga (Spinner) -->
                <div wire:loading.flex wire:target="graficoCursoId, periodo, modalidad"
                     class="absolute inset-0 bg-white/80 dark:bg-zinc-900/80 backdrop-blur-[2px] z-30 flex flex-col items-center justify-center transition-all">
                    <div class="p-3.5 bg-white dark:bg-zinc-800 rounded-2xl shadow-xl border border-zinc-200 dark:border-zinc-700 flex items-center gap-3">
                        <svg class="animate-spin size-5 text-[#00376e] dark:text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span class="text-xs font-bold text-zinc-800 dark:text-zinc-200 animate-pulse">
                            {{ __('Actualizando gráfico...') }}
                        </span>
                    </div>
                </div>

                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 border-b border-zinc-200 dark:border-zinc-700 pb-4 mb-6">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <div class="p-1.5 bg-blue-50 dark:bg-blue-950/40 text-[#00376e] dark:text-blue-400 rounded-lg">
                                <flux:icon.chart-bar class="size-5" />
                            </div>
                            <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                                {{ __('Distribución de Problemáticas') }}
                            </flux:heading>
                        </div>
                        <flux:text class="text-xs text-zinc-500">
                            {{ __('Comparativa visual de citaciones por causa para el curso seleccionado o consolidado de todo el establecimiento.') }}
                        </flux:text>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <div class="text-xs bg-zinc-100 dark:bg-zinc-800 px-3 py-2 rounded-lg font-medium text-zinc-700 dark:text-zinc-300 flex items-center gap-1.5 border border-zinc-200 dark:border-zinc-700">
                            <span class="text-zinc-400 font-normal">{{ __('Total Citas:') }}</span>
                            <span class="font-bold font-mono text-zinc-900 dark:text-white">{{ $this->datosGraficoCausas['total'] }}</span>
                        </div>

                        <flux:field class="w-full sm:w-72">
                            <flux:select wire:model.live="graficoCursoId">
                                <flux:select.option value="todos">{{ __('🏫 Todo el Establecimiento (General)') }}</flux:select.option>
                                <optgroup label="Cursos">
                                    @foreach ($this->cursosList as $c)
                                        <flux:select.option value="{{ $c->id }}">
                                            {{ $c->nombreCompleto() }} {{ $c->jefe ? '— ' . $c->jefe->nombreCompleto() : '' }}
                                        </flux:select.option>
                                    @endforeach
                                </optgroup>
                            </flux:select>
                        </flux:field>
                    </div>
                </div>

                <!-- Contenedor del Gráfico de Columnas Verticales -->
                <div class="pt-4 pb-2 px-1 sm:px-4">
                    @php
                        $ranking = $this->datosGraficoCausas['ranking'];
                        $maxCount = !empty($ranking) ? max(array_column($ranking, 'conteo')) : 0;
                        $maxHeightRef = max($maxCount, 1);
                    @endphp

                    <div class="h-64 sm:h-72 flex items-end justify-between gap-2 sm:gap-6 border-b border-zinc-200 dark:border-zinc-700 pb-2 relative">
                        <!-- Líneas guía de fondo -->
                        <div class="absolute inset-0 flex flex-col justify-between pointer-events-none opacity-25 dark:opacity-10">
                            <div class="border-b border-dashed border-zinc-400 w-full"></div>
                            <div class="border-b border-dashed border-zinc-400 w-full"></div>
                            <div class="border-b border-dashed border-zinc-400 w-full"></div>
                            <div class="border-b border-zinc-400 w-full"></div>
                        </div>

                        @forelse ($ranking as $item)
                            @php
                                $proportionalHeight = $maxHeightRef > 0 ? round(($item['conteo'] / $maxHeightRef) * 100) : 0;
                                $minHeightStyle = $item['conteo'] > 0 ? max($proportionalHeight, 10) : 2;
                            @endphp
                            <div class="flex-1 flex flex-col items-center h-full justify-end group relative z-10">
                                <!-- Valor / Badge flotante sobre la barra -->
                                <div class="mb-2 text-center transition-all duration-300 transform group-hover:-translate-y-1">
                                    <span class="inline-block px-2 py-0.5 text-xs sm:text-sm font-bold font-mono rounded-md shadow-xs {{ $item['conteo'] > 0 ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'bg-zinc-200 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-500 text-[10px]' }}">
                                        {{ $item['conteo'] }}
                                    </span>
                                </div>

                                <!-- Columna Vertical animada hacia arriba -->
                                <div class="w-full max-w-[48px] bg-zinc-100 dark:bg-zinc-800/60 rounded-t-lg overflow-hidden flex flex-col justify-end p-0.5 border border-zinc-200/60 dark:border-zinc-700/60 h-full">
                                    <div class="w-full rounded-t-md transition-all duration-700 ease-out shadow-sm group-hover:brightness-110"
                                         style="height: {{ $minHeightStyle }}%; background-color: {{ $item['color'] }};">
                                    </div>
                                </div>

                                <!-- Etiqueta inferior con nombre y porcentaje -->
                                <div class="mt-3 text-center w-full">
                                    <div class="text-[11px] sm:text-xs font-bold text-zinc-800 dark:text-zinc-200 truncate" title="{{ $item['nombre'] }}">
                                        {{ $item['short'] }}
                                    </div>
                                    <div class="text-[10px] sm:text-xs font-semibold text-zinc-500 dark:text-zinc-400 mt-0.5">
                                        {{ $item['porcentaje'] }}%
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="w-full h-full flex items-center justify-center text-sm text-zinc-500">
                                {{ __('No hay citaciones registradas para el período y curso seleccionado.') }}
                            </div>
                        @endforelse
                    </div>

                    <!-- Pie descriptivo -->
                    <div class="mt-4 flex flex-wrap items-center justify-between text-xs text-zinc-500 dark:text-zinc-400 gap-2">
                        <div class="flex items-center gap-1.5">
                            <span class="size-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span>Vista: <strong class="text-zinc-800 dark:text-zinc-200">{{ $this->datosGraficoCausas['cursoTitulo'] }}</strong></span>
                            @if($this->datosGraficoCausas['jefeTitulo'])
                                <span>• Prof. Jefe: <strong class="text-zinc-800 dark:text-zinc-200">{{ $this->datosGraficoCausas['jefeTitulo'] }}</strong></span>
                            @endif
                        </div>
                        <div>
                            <span class="text-[11px] text-zinc-400 italic">{{ __('Barras proporcionales al volumen de citaciones') }}</span>
                        </div>
                    </div>
                </div>
            </flux:card>

            <!-- Matriz de Problemáticas Cruzada (Heatmap) -->
            <flux:card class="overflow-x-auto">
                <div class="min-w-full inline-block align-middle">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40 text-xs font-bold text-zinc-600 dark:text-zinc-300 uppercase tracking-wider">
                                <th class="py-3 px-4 cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors select-none" wire:click="sort('curso')">
                                    <div class="flex items-center gap-1.5">
                                        <span>{{ __('Curso') }}</span>
                                        @if ($sortBy === 'curso')
                                            <flux:icon :name="$sortDirection === 'asc' ? 'chevron-up' : 'chevron-down'" class="size-3.5 text-[#00376e] dark:text-blue-400" />
                                        @endif
                                    </div>
                                </th>
                                <th class="py-3 px-4 cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors select-none" wire:click="sort('jefe')">
                                    <div class="flex items-center gap-1.5">
                                        <span>{{ __('Profesor(a) Jefe') }}</span>
                                        @if ($sortBy === 'jefe')
                                            <flux:icon :name="$sortDirection === 'asc' ? 'chevron-up' : 'chevron-down'" class="size-3.5 text-[#00376e] dark:text-blue-400" />
                                        @endif
                                    </div>
                                </th>
                                @foreach ($this->categorias as $cat)
                                    <th class="py-3 px-3 text-center cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors select-none" wire:click="sort('{{ $cat->nombre }}')" title="Ordenar por {{ $cat->nombre }}">
                                        <div class="flex items-center justify-center gap-1">
                                            @php
                                                $short = match($cat->nombre) {
                                                    'Rendimiento Académico' => 'Rendimiento',
                                                    'Conducta y Convivencia' => 'Conducta',
                                                    'Asistencia y Puntualidad' => 'Asistencia',
                                                    'Asunto Personal / Familiar' => 'Familiar',
                                                    'Evaluación Psicopedagógica' => 'Psicoped.',
                                                    default => mb_substr($cat->nombre, 0, 10),
                                                };
                                            @endphp
                                            <span>{{ $short }}</span>
                                            @if ($sortBy === $cat->nombre)
                                                <flux:icon :name="$sortDirection === 'asc' ? 'chevron-up' : 'chevron-down'" class="size-3 text-[#00376e] dark:text-blue-400" />
                                            @endif
                                        </div>
                                    </th>
                                @endforeach
                                <th class="py-3 px-3 text-center cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors select-none" wire:click="sort('Otro')">
                                    <div class="flex items-center justify-center gap-1">
                                        <span>{{ __('Otro') }}</span>
                                        @if ($sortBy === 'Otro')
                                            <flux:icon :name="$sortDirection === 'asc' ? 'chevron-up' : 'chevron-down'" class="size-3 text-[#00376e] dark:text-blue-400" />
                                        @endif
                                    </div>
                                </th>
                                <th class="py-3 px-4 text-center font-extrabold text-zinc-900 dark:text-zinc-100 cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors select-none" wire:click="sort('total')">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <span>{{ __('Total') }}</span>
                                        @if ($sortBy === 'total')
                                            <flux:icon :name="$sortDirection === 'asc' ? 'chevron-up' : 'chevron-down'" class="size-3.5 text-[#00376e] dark:text-blue-400" />
                                        @endif
                                    </div>
                                </th>
                                <th class="py-3 px-4 text-center cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors select-none" wire:click="sort('causa_dominante')">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <span>{{ __('Causa Dominante') }}</span>
                                        @if ($sortBy === 'causa_dominante')
                                            <flux:icon :name="$sortDirection === 'asc' ? 'chevron-up' : 'chevron-down'" class="size-3.5 text-[#00376e] dark:text-blue-400" />
                                        @endif
                                    </div>
                                </th>
                                <th class="py-3 px-4 text-right">{{ __('Acción') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700/60 text-sm">
                            @forelse ($this->matrizProblematicas as $fila)
                                <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/30 transition-colors">
                                    <td class="py-3 px-4 font-bold text-zinc-900 dark:text-zinc-100 font-mono tracking-tight" title="{{ $fila->curso->nombreCompleto() }}">
                                        {{ $fila->curso->nombreAbreviado() }}
                                    </td>
                                    <td class="py-3 px-4 text-xs text-zinc-600 dark:text-zinc-400">
                                        {{ $fila->curso->jefe ? $fila->curso->jefe->nombreCompleto() : 'Sin asignar' }}
                                    </td>
                                    @foreach ($this->categorias as $cat)
                                        @php
                                            $cant = $fila->conteos[$cat->nombre] ?? 0;
                                        @endphp
                                        <td class="py-3 px-3 text-center">
                                            @if($cant > 0)
                                                <button type="button" 
                                                        wire:click="verDetalleCurso({{ $fila->curso->id }}, '{{ $cat->nombre }}')"
                                                        class="inline-flex items-center justify-center size-7 rounded-lg font-mono text-xs font-bold transition-all hover:scale-110 cursor-pointer
                                                            @if($cant >= 6) bg-rose-100 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-300 dark:border-rose-700
                                                            @elseif($cant >= 3) bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-300 dark:border-amber-700
                                                            @else bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 border border-zinc-300 dark:border-zinc-700 @endif"
                                                        title="Ver {{ $cant }} citas por {{ $cat->nombre }}">
                                                    {{ $cant }}
                                                </button>
                                            @else
                                                <span class="text-zinc-300 dark:text-zinc-600 font-mono text-xs">-</span>
                                            @endif
                                        </td>
                                    @endforeach

                                    {{-- Columna Otro --}}
                                    @php
                                        $cantOtro = $fila->conteos['Otro'] ?? 0;
                                    @endphp
                                    <td class="py-3 px-3 text-center">
                                        @if($cantOtro > 0)
                                            <button type="button" 
                                                    wire:click="verDetalleCurso({{ $fila->curso->id }}, 'Otro')"
                                                    class="inline-flex items-center justify-center size-7 rounded-lg font-mono text-xs font-bold transition-all hover:scale-110 cursor-pointer
                                                        @if($cantOtro >= 6) bg-rose-100 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-300 dark:border-rose-700
                                                        @elseif($cantOtro >= 3) bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-300 dark:border-amber-700
                                                        @else bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 border border-zinc-300 dark:border-zinc-700 @endif">
                                                {{ $cantOtro }}
                                            </button>
                                        @else
                                            <span class="text-zinc-300 dark:text-zinc-600 font-mono text-xs">-</span>
                                        @endif
                                    </td>

                                    {{-- Total Citas Curso --}}
                                    <td class="py-3 px-4 text-center font-extrabold text-zinc-900 dark:text-zinc-100 font-mono">
                                        <span class="px-2.5 py-1 bg-zinc-100 dark:bg-zinc-800 rounded-md">
                                            {{ $fila->total }}
                                        </span>
                                    </td>

                                    {{-- Causa Dominante --}}
                                    <td class="py-3 px-4 text-center">
                                        @if($fila->total > 0)
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold
                                                @if(str_contains(mb_strtolower($fila->causa_dominante), 'conducta')) bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300 border border-rose-200
                                                @elseif(str_contains(mb_strtolower($fila->causa_dominante), 'rendimiento')) bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300 border border-blue-200
                                                @elseif(str_contains(mb_strtolower($fila->causa_dominante), 'asistencia')) bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200
                                                @else bg-purple-50 text-purple-700 dark:bg-purple-950/40 dark:text-purple-300 border border-purple-200 @endif">
                                                {{ $fila->causa_dominante }} ({{ $fila->porcentaje_dominante }}%)
                                            </span>
                                        @else
                                            <span class="text-xs text-zinc-400">Sin citaciones</span>
                                        @endif
                                    </td>

                                    {{-- Acción Ver Citas del Curso --}}
                                    <td class="py-3 px-4 text-right">
                                        <flux:button size="xs" variant="ghost" icon="eye" wire:click="verDetalleCurso({{ $fila->curso->id }})" title="Ver todas las citas de este curso">
                                            {{ __('Ver Citas') }}
                                        </flux:button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($this->categorias) + 6 }}" class="text-center py-10 text-zinc-500">
                                        <flux:icon.magnifying-glass class="size-8 mx-auto text-zinc-400 mb-2" />
                                        <p class="font-medium text-sm">No se encontraron cursos con los filtros seleccionados.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="border-t-2 border-zinc-300 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-800/80 font-bold text-zinc-900 dark:text-zinc-100 text-xs">
                            <tr>
                                <td colspan="2" class="py-3 px-4 text-right uppercase">
                                    {{ __('Totales Consolidados:') }}
                                </td>
                                @foreach ($this->categorias as $cat)
                                    <td class="py-3 px-3 text-center font-mono">
                                        {{ $this->statsProblematicas['totalesPorCategoria'][$cat->nombre] ?? 0 }}
                                    </td>
                                @endforeach
                                <td class="py-3 px-3 text-center font-mono">
                                    {{ $this->statsProblematicas['totalesPorCategoria']['Otro'] ?? 0 }}
                                </td>
                                <td class="py-3 px-4 text-center font-mono font-extrabold text-sm text-[#00376e] dark:text-blue-400">
                                    {{ $this->statsProblematicas['granTotal'] }}
                                </td>
                                <td colspan="2" class="py-3 px-4 text-center text-xs font-semibold text-zinc-600 dark:text-zinc-400">
                                    Top: {{ $this->statsProblematicas['topCatGlobal'] }} ({{ $this->statsProblematicas['topCatGlobalPct'] }}%)
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </flux:card>
        @endif

        <!-- Modal 1: Detalle de Citas del Profesor -->
        <flux:modal wire:model="modalDetalle" class="min-w-[90vw] md:min-w-[48rem] max-h-[85vh] overflow-y-auto">
            <div class="space-y-6">
                <div class="flex items-start justify-between border-b border-zinc-200 dark:border-zinc-700 pb-4">
                    <div>
                        <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                            <flux:icon.calendar-days class="size-5 text-[#00376e] dark:text-blue-400" />
                            Detalle de Entrevistas: {{ $this->selectedDocente?->nombreCompleto() }}
                        </flux:heading>
                        <flux:text class="text-xs text-zinc-500 mt-1">
                            {{ $this->selectedDocente?->email }} • {{ $this->selectedDocente?->rutCompleto() ?? 'Sin RUT' }}
                        </flux:text>
                    </div>
                </div>

                <div class="space-y-3">
                    @forelse ($this->detalleEntrevistas as $cita)
                        <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 flex flex-col md:flex-row md:items-center justify-between gap-4">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold uppercase tracking-wider text-zinc-500">
                                        {{ \Carbon\Carbon::parse($cita->fecha)->translatedFormat('d M, Y') }} — {{ \Carbon\Carbon::parse($cita->hora)->format('H:i') }} hrs
                                    </span>
                                    
                                    {{-- Badge de Estado --}}
                                    @php
                                        $todayStr = now('America/Santiago')->toDateString();
                                        $esPasada = $cita->fecha < $todayStr;
                                    @endphp
                                    @if($cita->estado === 'realizada')
                                        <flux:badge size="sm" color="emerald">Realizada</flux:badge>
                                    @elseif($cita->estado === 'cancelada')
                                        <flux:badge size="sm" color="red">Cancelada</flux:badge>
                                    @elseif($cita->estado === 'ausente')
                                        <flux:badge size="sm" color="red">Ausente</flux:badge>
                                    @elseif(in_array($cita->estado, ['pendiente', 'ingresada', 'abierta']) && $esPasada)
                                        <flux:badge size="sm" color="amber" class="font-bold">⚠️ Abierta (Sin Cerrar)</flux:badge>
                                    @elseif($cita->estado === 'ingresada')
                                        <flux:badge size="sm" color="sky">En Recinto</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="sky">Pendiente</flux:badge>
                                    @endif
                                </div>

                                <p class="text-sm font-bold text-zinc-900 dark:text-zinc-100">
                                    Estudiante: {{ $cita->estudiante?->nombreCompleto() ?? 'No registrado' }}
                                    <span class="text-xs font-normal text-zinc-500">({{ $cita->estudiante?->curso?->nombreCompleto() ?? 'Sin curso' }})</span>
                                </p>
                                
                                <p class="text-xs text-zinc-600 dark:text-zinc-400">
                                    <strong>Apoderado:</strong> {{ $cita->estudiante?->apoderado_nombres ? $cita->estudiante->apoderado_nombres . ' ' . $cita->estudiante->apoderado_apellido_pat : 'Sin nombre' }}
                                    • <strong>Motivo:</strong> {{ $cita->motivo }}
                                </p>
                            </div>

                            <div class="flex items-center gap-2 self-end md:self-center shrink-0">
                                <flux:button size="xs" variant="primary" href="{{ route('entrevistas.bitacora', $cita->id) }}" wire:navigate icon="document-text">
                                    {{ __('Ver Bitácora') }}
                                </flux:button>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-zinc-500">
                            <flux:icon.calendar class="size-8 mx-auto text-zinc-400 mb-2" />
                            <p class="text-sm font-medium">Este docente no registra entrevistas en el período seleccionado.</p>
                        </div>
                    @endforelse
                </div>

                <div class="flex justify-end pt-2 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cerrar') }}</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        </flux:modal>

        <!-- Modal 2: Detalle de Citas del Curso / Problemática -->
        <flux:modal wire:model="modalDetalleCurso" class="min-w-[90vw] md:min-w-[50rem] max-h-[85vh] overflow-y-auto">
            <div class="space-y-6">
                <div class="flex items-start justify-between border-b border-zinc-200 dark:border-zinc-700 pb-4">
                    <div>
                        <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                            <flux:icon.academic-cap class="size-5 text-[#00376e] dark:text-blue-400" />
                            Citas del Curso: {{ $this->selectedCurso?->nombreCompleto() }}
                        </flux:heading>
                        <flux:text class="text-xs text-zinc-500 mt-1">
                            Profesor(a) Jefe: <strong>{{ $this->selectedCurso?->jefe ? $this->selectedCurso->jefe->nombreCompleto() : 'Sin asignar' }}</strong>
                            @if($selectedCategoriaNombre)
                                • Filtro Problemática: <span class="font-bold text-amber-600 dark:text-amber-400">{{ $selectedCategoriaNombre }}</span>
                            @else
                                • Mostrando <strong>todas las problemáticas</strong>
                            @endif
                        </flux:text>
                    </div>
                </div>

                <div class="space-y-3">
                    @forelse ($this->detalleCitasCurso as $cita)
                        <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 flex flex-col md:flex-row md:items-center justify-between gap-4">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold uppercase tracking-wider text-zinc-500">
                                        {{ \Carbon\Carbon::parse($cita->fecha)->translatedFormat('d M, Y') }} — {{ \Carbon\Carbon::parse($cita->hora)->format('H:i') }} hrs
                                    </span>
                                    
                                    @if($cita->estado === 'realizada')
                                        <flux:badge size="sm" color="emerald">Realizada</flux:badge>
                                    @elseif($cita->estado === 'cancelada')
                                        <flux:badge size="sm" color="red">Cancelada</flux:badge>
                                    @elseif($cita->estado === 'ingresada')
                                        <flux:badge size="sm" color="amber">En Recinto</flux:badge>
                                    @elseif($cita->estado === 'abierta')
                                        <flux:badge size="sm" color="sky">Abierta</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="zinc">{{ ucfirst($cita->estado) }}</flux:badge>
                                    @endif

                                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/50">
                                        {{ $cita->motivo }}
                                    </span>
                                </div>

                                <p class="text-sm font-bold text-zinc-900 dark:text-zinc-100">
                                    Estudiante: {{ $cita->estudiante?->nombreCompleto() ?? 'No registrado' }}
                                </p>
                                
                                <p class="text-xs text-zinc-600 dark:text-zinc-400">
                                    <strong>Apoderado:</strong> {{ $cita->estudiante?->apoderado_nombres ? $cita->estudiante->apoderado_nombres . ' ' . $cita->estudiante->apoderado_apellido_pat : 'Sin nombre' }}
                                    • <strong>Citado por:</strong> {{ $cita->user ? $cita->user->nombreCompleto() : 'Docente no asignado' }}
                                </p>
                            </div>

                            <div class="flex items-center gap-2 self-end md:self-center shrink-0">
                                <flux:button size="xs" variant="primary" href="{{ route('entrevistas.bitacora', $cita->id) }}" wire:navigate icon="document-text">
                                    {{ __('Ver Bitácora') }}
                                </flux:button>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-zinc-500">
                            <flux:icon.calendar class="size-8 mx-auto text-zinc-400 mb-2" />
                            <p class="text-sm font-medium">No se registran entrevistas para este curso en la categoría seleccionada.</p>
                        </div>
                    @endforelse
                </div>

                <div class="flex justify-end pt-2 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cerrar') }}</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        </flux:modal>
    </div>
</div>
