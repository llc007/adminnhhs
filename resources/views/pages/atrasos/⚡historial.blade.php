<?php

use App\Models\Atraso;
use App\Models\Curso;
use App\Models\Estudiante;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Historial de Atrasos')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $curso_id = '';

    #[Url]
    public string $fecha = '';

    #[Url]
    public string $filtroTemporal = 'dia'; // 'dia', 'semana', 'mes', 'todos'

    #[Url]
    public string $estado = ''; // '', 'injustificado', 'justificado', 'pendiente'

    #[Url]
    public string $jornada = ''; // '', 'manana', 'tarde'

    #[Url]
    public string $ciclo = ''; // '', 'basica', 'media'

    #[Url]
    public string $filtroAtrasosMes = ''; // '', '1', '2', '3', '3+', '4+'

    #[Url]
    public string $sortBy = 'fecha'; // 'fecha', 'atrasos_mes'

    #[Url]
    public string $sortDirection = 'desc'; // 'asc', 'desc'

    // Modal de Historial Detallado del Estudiante
    public bool $modalDetalleEstudiante = false;
    public ?int $estudianteSeleccionadoId = null;
    public ?Estudiante $estudianteSeleccionado = null;

    // Modal para editar fecha, hora, jornada, estado y motivo del atraso
    public bool $modalEdicion = false;
    public ?int $atrasoAEditarId = null;
    public string $editarEstudianteNombre = '';
    public string $editarFecha = '';
    public string $editarHora = '';
    public string $editarJornada = 'manana';
    public string $editarEstado = 'injustificado';
    public string $editarMotivo = '';
    public string $editarObservaciones = '';

    // Modal de confirmación para eliminar
    public bool $modalEliminar = false;
    public ?int $atrasoAEliminarId = null;
    public string $estudianteAEliminarNombre = '';

    // Modal para registrar y justificar nuevo atraso (ingreso posterior)
    public bool $modalNuevoAtraso = false;
    public string $nuevoSearchEstudiante = '';
    public ?int $nuevoEstudianteId = null;
    public ?Estudiante $nuevoEstudiante = null;
    public string $nuevaFecha = '';
    public string $nuevaHora = '';
    public string $nuevaJornada = 'manana';
    public string $nuevoEstado = 'justificado';
    public string $nuevoMotivo = '';
    public string $nuevasObservaciones = '';

    // Sincronización en tiempo real
    public bool $autoRefresh = true;

    public function toggleAutoRefresh(): void
    {
        $this->autoRefresh = ! $this->autoRefresh;
    }

    #[Computed]
    public function esFechaHoy(): bool
    {
        $hoy = now('America/Santiago')->toDateString();

        if ($this->filtroTemporal === 'dia') {
            return empty($this->fecha) || $this->fecha === $hoy;
        }

        if ($this->filtroTemporal === 'semana') {
            $anchor = ! empty($this->fecha) ? Carbon::parse($this->fecha, 'America/Santiago') : now('America/Santiago');
            $startOfWeek = $anchor->copy()->startOfWeek()->toDateString();
            $endOfWeek = $anchor->copy()->endOfWeek()->toDateString();

            return $hoy >= $startOfWeek && $hoy <= $endOfWeek;
        }

        if ($this->filtroTemporal === 'mes') {
            $anchor = ! empty($this->fecha) ? Carbon::parse($this->fecha, 'America/Santiago') : now('America/Santiago');

            return $anchor->format('Y-m') === now('America/Santiago')->format('Y-m');
        }

        // 'todos' incluye hoy
        return true;
    }

    public function mount(): void
    {
        $user = auth()->user();
        if (
            ! $user->hasRole('superadmin') &&
            ! $user->can('ver-atrasos') &&
            ! $user->can('ingresar-atrasos')
        ) {
            abort(403, 'No tienes permiso para acceder al historial de atrasos.');
        }

        if (empty($this->fecha)) {
            $this->fecha = now('America/Santiago')->toDateString();
        }
    }

    public function updating($field): void
    {
        if (in_array($field, ['search', 'curso_id', 'fecha', 'filtroTemporal', 'estado', 'jornada', 'ciclo', 'filtroAtrasosMes', 'sortBy', 'sortDirection'])) {
            $this->resetPage();
        }
    }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = $column === 'atrasos_mes' ? 'desc' : 'asc';
        }
        $this->resetPage();
    }

    public function ordenarPor(string $column): void
    {
        $this->sort($column);
    }

    public function setFiltroTemporal(string $filtro): void
    {
        if ($this->filtroTemporal === $filtro) {
            return;
        }
        $this->filtroTemporal = $filtro;
        if (empty($this->fecha)) {
            $this->fecha = now('America/Santiago')->toDateString();
        }
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'curso_id', 'estado', 'jornada', 'ciclo', 'filtroAtrasosMes']);
        $this->filtroTemporal = 'dia';
        $this->fecha = now('America/Santiago')->toDateString();
        $this->sortBy = 'fecha';
        $this->sortDirection = 'desc';
        $this->resetPage();
    }

    public function getSchoolProperty()
    {
        return auth()->user()->currentSchool;
    }

    public function getAcademicYearProperty()
    {
        return $this->school?->academicYears()->where('is_active', true)->first();
    }

    #[Computed]
    public function cursos()
    {
        if (! $this->school) {
            return collect();
        }

        return Curso::where('school_id', $this->school->id)
            ->when($this->academicYear, fn ($q) => $q->where('academic_year_id', $this->academicYear->id))
            ->orderBy('modalidad')
            ->orderBy('nivel')
            ->orderBy('letra')
            ->get();
    }

    private function getFilteredQuery()
    {
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';

        $subqueryGenerator = function () use ($isSqlite) {
            $sq = Atraso::selectRaw('count(*)')
                ->from('atrasos as a2')
                ->whereColumn('a2.estudiante_id', 'atrasos.estudiante_id')
                ->whereColumn('a2.school_id', 'atrasos.school_id');

            if ($isSqlite) {
                $sq->whereRaw("strftime('%Y-%m', a2.fecha) = strftime('%Y-%m', atrasos.fecha)");
            } else {
                $sq->whereRaw('YEAR(a2.fecha) = YEAR(atrasos.fecha) AND MONTH(a2.fecha) = MONTH(atrasos.fecha)');
            }

            return $sq;
        };

        $query = Atraso::with(['estudiante.curso', 'curso', 'registradoPor'])
            ->select('atrasos.*')
            ->selectSub($subqueryGenerator(), 'atrasos_mes_count')
            ->where('school_id', $this->school?->id);

        if (! empty($this->search)) {
            $words = array_filter(explode(' ', trim($this->search)));
            $cleanRut = preg_replace('/[^0-9kK]/', '', trim($this->search));

            $query->where(function ($q) use ($words, $cleanRut) {
                foreach ($words as $word) {
                    $w = trim($word);
                    if ($w === '') {
                        continue;
                    }
                    $q->whereHas('estudiante', function ($sq) use ($w) {
                        $sq->where('nombres_csv', 'like', "%{$w}%");
                    });
                }
                if (! empty($cleanRut)) {
                    $q->orWhereHas('estudiante', function ($sq) use ($cleanRut) {
                        $sq->where('rut_numero', 'like', "%{$cleanRut}%");
                    });
                }
            });
        }

        if (! empty($this->curso_id)) {
            $query->where(function ($q) {
                $q->where('curso_id', $this->curso_id)
                    ->orWhereHas('estudiante', function ($sq) {
                        $sq->where('curso_id', $this->curso_id);
                    });
            });
        }

        if (! empty($this->estado)) {
            $query->where('estado', $this->estado);
        }

        if (! empty($this->jornada)) {
            $query->where('jornada', $this->jornada);
        }

        if (! empty($this->ciclo)) {
            $query->where(function ($q) {
                $q->whereHas('curso', function ($cq) {
                    $cq->where('modalidad', $this->ciclo);
                })->orWhereHas('estudiante.curso', function ($cq) {
                    $cq->where('modalidad', $this->ciclo);
                });
            });
        }

        if (! empty($this->filtroAtrasosMes)) {
            if ($this->filtroAtrasosMes === '3+') {
                $query->where($subqueryGenerator(), '>=', 3);
            } elseif ($this->filtroAtrasosMes === '4+') {
                $query->where($subqueryGenerator(), '>=', 4);
            } elseif (is_numeric($this->filtroAtrasosMes)) {
                $query->where($subqueryGenerator(), '=', (int) $this->filtroAtrasosMes);
            }
        }

        // Filtro Temporal
        $anchor = ! empty($this->fecha) ? Carbon::parse($this->fecha, 'America/Santiago') : now('America/Santiago');

        if ($this->filtroTemporal === 'dia') {
            $query->whereDate('fecha', $anchor->toDateString());
        } elseif ($this->filtroTemporal === 'semana') {
            $query->whereBetween('fecha', [
                $anchor->copy()->startOfWeek()->toDateString(),
                $anchor->copy()->endOfWeek()->toDateString(),
            ]);
        } elseif ($this->filtroTemporal === 'mes') {
            $query->whereYear('fecha', $anchor->year)->whereMonth('fecha', $anchor->month);
        }

        if ($this->sortBy === 'atrasos_mes') {
            return $query->orderBy('atrasos_mes_count', $this->sortDirection)
                ->orderBy('fecha', 'desc')
                ->orderBy('hora', 'desc');
        }

        return $query->orderBy('fecha', $this->sortDirection)->orderBy('hora', $this->sortDirection);
    }

    /**
     * Ver datos completos y todos los atrasos del estudiante al hacer click en la fila.
     */
    public function verHistorialEstudiante(int $estudianteId): void
    {
        $this->estudianteSeleccionado = Estudiante::with(['curso', 'atrasos' => function ($q) {
            $q->orderBy('fecha', 'desc')->orderBy('hora', 'desc');
        }, 'atrasos.registradoPor'])->where('school_id', $this->school?->id)->find($estudianteId);

        if ($this->estudianteSeleccionado) {
            $this->estudianteSeleccionadoId = $estudianteId;
            $this->modalDetalleEstudiante = true;
        }
    }

    /**
     * Alternar justificación rápida desde la tabla.
     */
    /**
     * Alternar justificación rápida desde la tabla o abrir modal.
     */
    public function toggleJustificado(int $atrasoId): void
    {
        $this->justificarAtraso($atrasoId);
    }

    /**
     * Abrir modal "Detalle del Atraso" con estado 'justificado' preseleccionado.
     */
    public function justificarAtraso(int $atrasoId): void
    {
        $this->abrirModalEdicion($atrasoId, 'justificado');
    }

    public function abrirModalEdicion(int $atrasoId, ?string $estadoPorDefecto = null): void
    {
        $atraso = Atraso::with('estudiante')->where('school_id', $this->school?->id)->find($atrasoId);
        if (! $atraso) {
            return;
        }

        $this->atrasoAEditarId = $atraso->id;
        $this->editarFecha = $atraso->fecha ? Carbon::parse($atraso->fecha)->toDateString() : now('America/Santiago')->toDateString();
        $this->editarHora = $atraso->hora ? Carbon::parse($atraso->hora)->format('H:i') : now('America/Santiago')->format('H:i');
        $this->editarJornada = $atraso->jornada ?? ($this->editarHora >= '13:30' ? 'tarde' : 'manana');
        $this->editarEstado = $estadoPorDefecto ?? $atraso->estado;
        $this->editarMotivo = $atraso->motivo ?? '';
        $this->editarObservaciones = $atraso->observaciones ?? '';
        $this->editarEstudianteNombre = $atraso->estudiante?->nombreCompleto() ?? 'Estudiante';
        $this->modalEdicion = true;
    }

    public function guardarEdicion(): void
    {
        if (! $this->atrasoAEditarId) {
            return;
        }

        $atraso = Atraso::where('school_id', $this->school?->id)->find($this->atrasoAEditarId);
        if ($atraso) {
            $fecha = ! empty($this->editarFecha) ? $this->editarFecha : $atraso->fecha;
            $hora = ! empty($this->editarHora) ? $this->editarHora : $atraso->hora;
            if (strlen($hora) === 5) {
                $hora .= ':00';
            }

            $jornada = in_array($this->editarJornada, ['manana', 'tarde']) ? $this->editarJornada : ($atraso->jornada ?? 'manana');
            $horaOficial = $jornada === 'tarde' ? '13:30:00' : '08:00:00';
            $horaLimite = Carbon::parse($fecha . ' ' . $horaOficial, 'America/Santiago');
            $momentoIngreso = Carbon::parse($fecha . ' ' . $hora, 'America/Santiago');

            $minutosAtraso = 0;
            if ($momentoIngreso->greaterThan($horaLimite)) {
                $minutosAtraso = (int) $horaLimite->diffInMinutes($momentoIngreso);
            }

            $atraso->update([
                'fecha' => $fecha,
                'hora' => $hora,
                'jornada' => $jornada,
                'minutos_atraso' => $minutosAtraso,
                'estado' => $this->editarEstado,
                'motivo' => $this->editarMotivo ?: null,
                'observaciones' => $this->editarObservaciones ?: null,
            ]);
            Flux::toast(heading: 'Atraso Actualizado', text: 'Se guardaron los cambios del atraso.', variant: 'success');
        }

        $this->modalEdicion = false;
        $this->atrasoAEditarId = null;
        $this->editarEstudianteNombre = '';
    }

    public function confirmarEliminacion(int $atrasoId): void
    {
        $atraso = Atraso::where('school_id', $this->school?->id)->find($atrasoId);
        if (! $atraso) {
            return;
        }

        $this->atrasoAEliminarId = $atraso->id;
        $this->estudianteAEliminarNombre = $atraso->estudiante?->nombreCompleto() ?? 'este estudiante';
        $this->modalEliminar = true;
    }

    public function eliminarAtraso(): void
    {
        if (! $this->atrasoAEliminarId) {
            return;
        }

        $atraso = Atraso::where('school_id', $this->school?->id)->find($this->atrasoAEliminarId);
        if ($atraso) {
            $nombre = $atraso->estudiante?->nombreCompleto() ?? 'Estudiante';
            $atraso->delete();
            Flux::toast(heading: 'Registro Anulado', text: "Se eliminó el atraso de {$nombre}.", variant: 'warning');
        }

        $this->modalEliminar = false;
        $this->atrasoAEliminarId = null;
        $this->estudianteAEliminarNombre = '';
    }

    public function abrirModalNuevoAtraso(): void
    {
        $now = now('America/Santiago');
        $this->reset([
            'nuevoSearchEstudiante',
            'nuevoEstudianteId',
            'nuevoEstudiante',
            'nuevoMotivo',
            'nuevasObservaciones',
        ]);
        $this->nuevaFecha = $now->toDateString();
        $this->nuevaHora = $now->format('H:i');
        $this->nuevaJornada = $now->format('H:i:s') >= '13:30:00' ? 'tarde' : 'manana';
        $this->nuevoEstado = 'justificado';
        $this->modalNuevoAtraso = true;
    }

    public function seleccionarNuevoEstudiante(int $id): void
    {
        $estudiante = Estudiante::with('curso')->where('school_id', $this->school?->id)->find($id);
        if ($estudiante) {
            $this->nuevoEstudianteId = $estudiante->id;
            $this->nuevoEstudiante = $estudiante;
            $this->nuevoSearchEstudiante = '';
        }
    }

    public function deseleccionarNuevoEstudiante(): void
    {
        $this->nuevoEstudianteId = null;
        $this->nuevoEstudiante = null;
        $this->nuevoSearchEstudiante = '';
    }

    public function seleccionarPrimerResultadoNuevo(): void
    {
        $primer = $this->resultadosNuevoEstudiante->first();
        if ($primer) {
            $this->seleccionarNuevoEstudiante($primer->id);
        }
    }

    #[Computed]
    public function resultadosNuevoEstudiante()
    {
        $query = trim($this->nuevoSearchEstudiante);
        if (mb_strlen($query) < 2 || ! $this->school) {
            return collect();
        }

        $cleanRut = preg_replace('/[^0-9kK]/', '', $query);
        $words = array_filter(explode(' ', $query));

        return Estudiante::activos()
            ->with(['curso'])
            ->where('school_id', $this->school->id)
            ->where(function ($q) use ($words, $cleanRut) {
                foreach ($words as $word) {
                    $w = trim($word);
                    if ($w === '') {
                        continue;
                    }
                    $q->where(function ($sq) use ($w) {
                        $sq->where('nombres_csv', 'like', "%{$w}%");
                    });
                }
                if (! empty($cleanRut)) {
                    $q->orWhere('rut_numero', 'like', "%{$cleanRut}%");
                }
            })
            ->take(8)
            ->get();
    }

    public function guardarNuevoAtraso(bool $imprimir = false): void
    {
        $user = auth()->user();
        if (! $user->hasRole('superadmin') && ! $user->can('ingresar-atrasos')) {
            Flux::toast(heading: 'Error', text: 'No tienes permiso para registrar atrasos.', variant: 'danger');
            return;
        }

        if (! $this->nuevoEstudianteId) {
            Flux::toast(heading: 'Atención', text: 'Debes seleccionar un estudiante para continuar.', variant: 'warning');
            return;
        }

        $school = $this->school;
        if (! $school) {
            Flux::toast(heading: 'Error', text: 'No tienes un colegio activo seleccionado.', variant: 'danger');
            return;
        }

        $estudiante = Estudiante::where('school_id', $school->id)->find($this->nuevoEstudianteId);
        if (! $estudiante) {
            Flux::toast(heading: 'Error', text: 'Estudiante no encontrado.', variant: 'danger');
            return;
        }

        $fechaRegistro = ! empty($this->nuevaFecha) ? $this->nuevaFecha : now('America/Santiago')->toDateString();

        // Control de duplicados en la misma fecha
        $yaRegistrado = Atraso::where('school_id', $school->id)
            ->where('estudiante_id', $estudiante->id)
            ->whereDate('fecha', $fechaRegistro)
            ->first();

        if ($yaRegistrado) {
            Flux::toast(
                heading: 'Estudiante ya registrado',
                text: "{$estudiante->nombreCompleto()} ya cuenta con un atraso registrado en la fecha seleccionada.",
                variant: 'warning'
            );
            return;
        }

        $horaIngresada = ! empty($this->nuevaHora) ? $this->nuevaHora : now('America/Santiago')->format('H:i');
        if (strlen($horaIngresada) === 5) {
            $horaIngresada .= ':00';
        }

        $jornada = in_array($this->nuevaJornada, ['manana', 'tarde']) ? $this->nuevaJornada : 'manana';
        $horaOficial = $jornada === 'tarde' ? '13:30:00' : '08:00:00';
        $horaLimite = Carbon::parse($fechaRegistro . ' ' . $horaOficial, 'America/Santiago');
        $momentoIngreso = Carbon::parse($fechaRegistro . ' ' . $horaIngresada, 'America/Santiago');

        $minutosAtraso = 0;
        if ($momentoIngreso->greaterThan($horaLimite)) {
            $minutosAtraso = (int) $horaLimite->diffInMinutes($momentoIngreso);
        }

        $nuevoAtraso = Atraso::create([
            'school_id' => $school->id,
            'academic_year_id' => $this->academicYear?->id,
            'estudiante_id' => $estudiante->id,
            'curso_id' => $estudiante->curso_id,
            'registrado_por_user_id' => auth()->id(),
            'fecha' => $fechaRegistro,
            'hora' => $horaIngresada,
            'jornada' => $jornada,
            'minutos_atraso' => $minutosAtraso,
            'estado' => $this->nuevoEstado ?: 'justificado',
            'motivo' => $this->nuevoMotivo ?: null,
            'observaciones' => $this->nuevasObservaciones ?: null,
        ]);

        $this->modalNuevoAtraso = false;
        $this->resetPage();

        $totalMes = $estudiante->atrasosMesActualCount();

        Flux::toast(
            heading: 'Atraso Registrado',
            text: "{$estudiante->nombreCompleto()} registrado como {$this->nuevoEstado} (#{$totalMes} en el mes).",
            variant: 'success'
        );

        if ($imprimir) {
            $this->js("window.open('" . route('atrasos.ticket', $nuevoAtraso->id) . "', '_blank')");
        }
    }

    public function exportarCsv()
    {
        $atrasos = $this->getFilteredQuery()->get();

        $headers = [
            'Content-type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename=historial_atrasos_' . now()->format('Y-m-d_H-i-s') . '.csv',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $columns = ['ID', 'Fecha', 'Hora', 'Jornada', 'Estudiante', 'RUT', 'Curso', 'Minutos Atraso', 'Atrasos Mes', 'Estado', 'Motivo', 'Registrado Por', 'Observaciones'];

        $callback = function () use ($atrasos, $columns) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xef) . chr(0xbb) . chr(0xbf));
            fputcsv($file, $columns, ';');

            foreach ($atrasos as $a) {
                $totalMes = (int) ($a->atrasos_mes_count ?? ($a->estudiante ? $a->estudiante->atrasosMesActualCount() : 1));
                fputcsv($file, [
                    $a->id,
                    $a->fecha ? Carbon::parse($a->fecha)->format('d/m/Y') : '',
                    $a->hora ? Carbon::parse($a->hora)->format('H:i') : '',
                    $a->jornadaLabel(),
                    $a->estudiante?->nombreCompleto() ?? 'N/A',
                    $a->estudiante?->rutCompleto() ?? '',
                    $a->curso?->nombreCompleto() ?? $a->estudiante?->curso?->nombreCompleto() ?? 'N/A',
                    $a->minutos_atraso,
                    $totalMes,
                    ucfirst($a->estado),
                    $a->motivo ?? '',
                    $a->registradoPor ? ($a->registradoPor->nombres . ' ' . $a->registradoPor->apellido_pat) : 'Sistema',
                    $a->observaciones ?? '',
                ], ';');
            }
            fclose($file);
        };

        return response()->streamDownload($callback, 'historial_atrasos_' . now()->format('Y-m-d_H-i-s') . '.csv', $headers);
    }

    public function render()
    {
        $query = $this->getFilteredQuery();
        $atrasos = $query->paginate(20);

        // Métricas de la consulta actual filtrada
        $baseQuery = clone $query;
        $totalFiltrados = (clone $baseQuery)->count();
        $justificadosFiltrados = (clone $baseQuery)->where('estado', 'justificado')->count();
        $injustificadosFiltrados = (clone $baseQuery)->where('estado', 'injustificado')->count();

        // Alumnos únicos atrasados en este periodo
        $alumnosUnicos = (clone $baseQuery)->distinct('estudiante_id')->count('estudiante_id');

        return view('pages.atrasos.⚡historial', [
            'atrasos' => $atrasos,
            'totalFiltrados' => $totalFiltrados,
            'justificadosFiltrados' => $justificadosFiltrados,
            'injustificadosFiltrados' => $injustificadosFiltrados,
            'alumnosUnicos' => $alumnosUnicos,
        ]);
    }
}; ?>

@php
    $debeActualizar = $this->autoRefresh && $this->esFechaHoy && ! $modalDetalleEstudiante && ! $modalEdicion && ! $modalEliminar && ! $modalNuevoAtraso;
@endphp

<div 
    class="max-w-7xl mx-auto w-full pb-12 space-y-6"
    @if($debeActualizar) wire:poll.4s.visible @endif
>

    {{-- Page Header --}}
    <x-header 
        titulo="Historial General de Atrasos" 
        subtitulo="Registro histórico, seguimiento de reincidencias por curso y estudiante." 
        icono="clock"
    >
        <div class="flex items-center gap-2">
            {{-- Indicador y Switch de Tiempo Real --}}
            @if($this->esFechaHoy)
                <button 
                    type="button" 
                    wire:click="toggleAutoRefresh"
                    @class([
                        'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold transition-all shadow-xs cursor-pointer border',
                        'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-300 dark:border-emerald-700 hover:bg-emerald-100 dark:hover:bg-emerald-900/60' => $this->autoRefresh,
                        'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400 border-zinc-200 dark:border-zinc-700 hover:bg-zinc-200' => ! $this->autoRefresh,
                    ])
                    title="{{ $this->autoRefresh ? 'Actualización en tiempo real activa (clic para pausar)' : 'Actualización en tiempo real pausada (clic para activar)' }}"
                >
                    @if($this->autoRefresh)
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                        </span>
                        <span>En vivo</span>
                    @else
                        <span class="inline-flex rounded-full h-2 w-2 bg-zinc-400"></span>
                        <span>En vivo pausado</span>
                    @endif
                </button>
            @endif

            <flux:button variant="ghost" icon="x-mark" wire:click="clearFilters">Limpiar filtros</flux:button>
            <flux:button variant="primary" icon="document-arrow-down" wire:click="exportarCsv" class="bg-gradient-to-br from-[#00376e] to-blue-800">
                Exportar CSV
            </flux:button>

            @if(auth()->user()->hasRole('superadmin') || auth()->user()->can('ingresar-atrasos'))
                <flux:button 
                    variant="primary" 
                    icon="plus" 
                    wire:click="abrirModalNuevoAtraso" 
                    class="bg-gradient-to-br from-emerald-600 to-teal-700 hover:from-emerald-700 hover:to-teal-800 text-white font-bold shadow-sm"
                >
                    Registrar y Justificar
                </flux:button>
            @endif
        </div>
    </x-header>

    {{-- Panel de Filtros (Estilo Bento similar a Entrevistas) --}}
    <flux:card class="p-3 sm:p-4 bg-zinc-50 dark:bg-zinc-800/40 shadow-sm border border-zinc-200 dark:border-zinc-700">
        <div class="flex items-center justify-between mb-3 border-b border-zinc-200/60 dark:border-zinc-700/60 pb-2">
            <div class="flex items-center gap-1.5 text-[#00376e] dark:text-blue-400 font-bold">
                <flux:icon.funnel class="size-4" />
                <span class="uppercase tracking-widest text-[11px]">Filtros de Búsqueda</span>
            </div>
            @if(!empty($fecha))
                <span class="text-[11px] font-mono font-bold text-zinc-500">
                    Fecha base: {{ Carbon::parse($fecha)->format('d/m/Y') }}
                </span>
            @endif
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-12 gap-3 items-end">
            {{-- Buscar Estudiante por nombre o RUT --}}
            <flux:field class="lg:col-span-4">
                <flux:label class="text-[11px]">Buscar Estudiante</flux:label>
                <flux:input size="sm" class="!text-xs" wire:model.live.debounce.300ms="search" placeholder="Nombre, apellido o RUT..." />
            </flux:field>

            {{-- Filtrar por Curso --}}
            <flux:field class="lg:col-span-4">
                <flux:label class="text-[11px]">Curso</flux:label>
                <flux:select size="sm" class="!text-xs" wire:model.live="curso_id">
                    <flux:select.option value="">Todos los cursos</flux:select.option>
                    @foreach ($this->cursos as $curso)
                        <flux:select.option value="{{ $curso->id }}">{{ $curso->nombreCompleto() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            {{-- Filtro Temporal: Día, Semana, Mes, Todos --}}
            <flux:field class="lg:col-span-4">
                <flux:label class="text-[11px]">
                    Periodo <span class="text-[10px] text-zinc-400 font-normal">({{ ucfirst($filtroTemporal) }})</span>
                </flux:label>
                <div class="flex gap-0.5 bg-zinc-100 dark:bg-zinc-800 p-0.5 rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <flux:dropdown position="bottom-start" class="flex-1">
                        <button type="button"
                            class="w-full h-full text-[11px] py-1.5 rounded-md font-bold flex items-center justify-center gap-1 {{ $filtroTemporal === 'dia' ? 'bg-[#00376e] text-white shadow-sm' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-200 dark:hover:bg-zinc-700' }} transition-colors"
                            wire:click="setFiltroTemporal('dia')">
                            <flux:icon.calendar class="size-3" /> Día
                        </button>
                        <flux:menu class="p-2 min-w-[280px]">
                            <flux:calendar wire:model.live="fecha" />
                        </flux:menu>
                    </flux:dropdown>

                    <button type="button"
                        class="flex-1 text-[11px] py-1.5 rounded-md font-bold {{ $filtroTemporal === 'semana' ? 'bg-[#00376e] text-white shadow-sm' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-200 dark:hover:bg-zinc-700' }} transition-colors"
                        wire:click="setFiltroTemporal('semana')">
                        Semana
                    </button>

                    <button type="button"
                        class="flex-1 text-[11px] py-1.5 rounded-md font-bold {{ $filtroTemporal === 'mes' ? 'bg-[#00376e] text-white shadow-sm' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-200 dark:hover:bg-zinc-700' }} transition-colors"
                        wire:click="setFiltroTemporal('mes')">
                        Mes
                    </button>

                    <button type="button"
                        class="flex-1 text-[11px] py-1.5 rounded-md font-bold {{ $filtroTemporal === 'todos' ? 'bg-[#00376e] text-white shadow-sm' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-200 dark:hover:bg-zinc-700' }} transition-colors"
                        wire:click="setFiltroTemporal('todos')">
                        Todo
                    </button>
                </div>
            </flux:field>

            {{-- Filtrar por Ciclo (Básica o Media) --}}
            <flux:field class="lg:col-span-4">
                <flux:label class="text-[11px]">Ciclo</flux:label>
                <flux:select size="sm" class="!text-xs" wire:model.live="ciclo">
                    <flux:select.option value="">Todos los ciclos</flux:select.option>
                    <flux:select.option value="basica">Básica (1º a 8º Básico)</flux:select.option>
                    <flux:select.option value="media">Media (1º a 4º Medio)</flux:select.option>
                </flux:select>
            </flux:field>

            {{-- Filtrar por Atrasos Mes --}}
            <flux:field class="lg:col-span-4">
                <flux:label class="text-[11px]">Atrasos Mes</flux:label>
                <flux:select size="sm" class="!text-xs" wire:model.live="filtroAtrasosMes">
                    <flux:select.option value="">Todos</flux:select.option>
                    <flux:select.option value="1">1er atraso</flux:select.option>
                    <flux:select.option value="2">2do atraso</flux:select.option>
                    <flux:select.option value="3">3er atraso</flux:select.option>
                    <flux:select.option value="3+">3 o más (Citación)</flux:select.option>
                    <flux:select.option value="4+">4 o más atrasos</flux:select.option>
                </flux:select>
            </flux:field>

            {{-- Filtrar por Estado --}}
            <flux:field class="lg:col-span-4">
                <flux:label class="text-[11px]">Estado</flux:label>
                <flux:select size="sm" class="!text-xs" wire:model.live="estado">
                    <flux:select.option value="">Todos</flux:select.option>
                    <flux:select.option value="injustificado">Injustificados</flux:select.option>
                    <flux:select.option value="justificado">Justificados</flux:select.option>
                    <flux:select.option value="pendiente">Pendientes</flux:select.option>
                </flux:select>
            </flux:field>
        </div>
    </flux:card>

    {{-- Indicadores Ultra-Compactos del Periodo Seleccionado --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
        <div class="flex items-center justify-between px-3 py-1.5 rounded-lg bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 shadow-2xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Total Atrasos</span>
            <span class="text-sm font-black text-zinc-900 dark:text-zinc-100">{{ $totalFiltrados }}</span>
        </div>

        <div class="flex items-center justify-between px-3 py-1.5 rounded-lg bg-blue-50/60 dark:bg-blue-950/30 border border-blue-200/60 dark:border-blue-900/40 shadow-2xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">Alumnos</span>
            <span class="text-sm font-black text-blue-700 dark:text-blue-300">{{ $alumnosUnicos }}</span>
        </div>

        <div class="flex items-center justify-between px-3 py-1.5 rounded-lg bg-rose-50/60 dark:bg-rose-950/30 border border-rose-200/60 dark:border-rose-900/40 shadow-2xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400">Injustificados</span>
            <span class="text-sm font-black text-rose-700 dark:text-rose-300">{{ $injustificadosFiltrados }}</span>
        </div>

        <div class="flex items-center justify-between px-3 py-1.5 rounded-lg bg-emerald-50/60 dark:bg-emerald-950/30 border border-emerald-200/60 dark:border-emerald-900/40 shadow-2xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Justificados</span>
            <span class="text-sm font-black text-emerald-700 dark:text-emerald-300">{{ $justificadosFiltrados }}</span>
        </div>
    </div>

    {{-- Tabla Principal de Atrasos --}}
    <flux:card class="overflow-hidden shadow-sm p-0 border border-zinc-200 dark:border-zinc-800">
        <div class="px-5 py-2 overflow-x-auto">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column class="w-14 text-[11px]">ID</flux:table.column>
                    <flux:table.column 
                        class="text-[11px]" 
                        sortable 
                        :sorted="$sortBy === 'fecha'" 
                        :direction="$sortDirection"
                        wire:click="sort('fecha')"
                    >
                        Fecha y Hora
                    </flux:table.column>
                    <flux:table.column class="text-[11px]">Estudiante</flux:table.column>
                    <flux:table.column class="text-[11px]">Curso</flux:table.column>
                    <flux:table.column 
                        class="text-[11px]" 
                        align="center"
                        sortable 
                        :sorted="$sortBy === 'atrasos_mes'" 
                        :direction="$sortDirection"
                        wire:click="sort('atrasos_mes')"
                    >
                        Atrasos Mes
                    </flux:table.column>
                    <flux:table.column class="text-[11px]">Minutos</flux:table.column>
                    <flux:table.column class="text-[11px]">Estado / Motivo</flux:table.column>
                    <flux:table.column class="text-[11px]">Registrado Por</flux:table.column>
                    <flux:table.column class="text-right text-[11px]">Acciones</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse($atrasos as $atraso)
                        @php
                            $estudiante = $atraso->estudiante;
                            $atrasosMes = (int) ($atraso->atrasos_mes_count ?? ($estudiante ? $estudiante->atrasosMesActualCount() : 1));
                        @endphp
                        <flux:table.row 
                            class="hover:bg-blue-50/60 dark:hover:bg-blue-950/20 cursor-pointer transition-colors group"
                            wire:click="verHistorialEstudiante({{ $atraso->estudiante_id }})"
                        >
                            {{-- ID --}}
                            <flux:table.cell class="py-2.5">
                                <span class="font-mono text-[11px] font-bold text-zinc-500 dark:text-zinc-400">#{{ $atraso->id }}</span>
                            </flux:table.cell>

                            {{-- Fecha y Hora (sin año) --}}
                            <flux:table.cell class="py-2.5">
                                <div class="flex items-center gap-1.5 text-xs font-semibold text-zinc-900 dark:text-zinc-100 flex-wrap">
                                    <flux:icon.calendar class="size-3.5 text-zinc-400 shrink-0" />
                                    <span>{{ Carbon::parse($atraso->fecha)->format('d/m') }}</span>
                                    <span class="font-mono font-bold text-blue-600 dark:text-blue-400 ml-1">
                                        {{ Carbon::parse($atraso->hora)->format('H:i') }} hrs
                                    </span>
                                    @if($atraso->isTarde())
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-purple-100 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300 border border-purple-200 dark:border-purple-800" title="Jornada Tarde (13:30)">
                                            🌙 Tarde
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-200 dark:border-amber-800" title="Jornada Mañana (08:00)">
                                            ☀️ Mañana
                                        </span>
                                    @endif
                                </div>
                            </flux:table.cell>

                            {{-- Estudiante --}}
                            <flux:table.cell class="py-2.5">
                                <div class="leading-tight">
                                    <span class="text-xs font-black text-zinc-900 dark:text-zinc-100 block group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                                        {{ $estudiante ? $estudiante->nombreCompleto() : 'Estudiante no encontrado' }}
                                    </span>
                                    <span class="text-[10px] text-zinc-400 font-mono">
                                        {{ $estudiante?->rutCompleto() ?? 'Sin RUT' }}
                                    </span>
                                </div>
                            </flux:table.cell>

                            {{-- Curso --}}
                            <flux:table.cell class="py-2.5">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-bold bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200">
                                    {{ $atraso->curso?->nombreAbreviado() ?? $estudiante?->curso?->nombreAbreviado() ?? 'S/C' }}
                                </span>
                            </flux:table.cell>

                            {{-- Columna Atrasos en el Mes con Semáforo --}}
                            <flux:table.cell class="py-2.5 text-center">
                                @if($atrasosMes >= 3)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black bg-rose-100 text-rose-800 dark:bg-rose-950/80 dark:text-rose-300 border border-rose-300 shadow-sm" title="Reincidencia: Citación requerida">
                                        ⚠️ {{ $atrasosMes }} en el mes
                                    </span>
                                @elseif($atrasosMes === 2)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-100 text-amber-800 dark:bg-amber-950/80 dark:text-amber-300 border border-amber-300">
                                        🟡 {{ $atrasosMes }} en el mes
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300 border border-emerald-300">
                                        🟢 1er atraso
                                    </span>
                                @endif
                            </flux:table.cell>

                            {{-- Minutos Atraso --}}
                            <flux:table.cell class="py-2.5">
                                @if($atraso->minutos_atraso > 0)
                                    <span class="font-mono text-xs font-bold text-rose-600 dark:text-rose-400">
                                        +{{ $atraso->minutos_atraso }} min
                                    </span>
                                @else
                                    <span class="text-xs text-zinc-400 font-mono">0 min</span>
                                @endif
                            </flux:table.cell>

                            {{-- Estado y Motivo --}}
                            <flux:table.cell class="py-2.5">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    @if($atraso->estado === 'justificado')
                                        <flux:badge color="emerald" size="xs" icon="check-circle" class="uppercase text-[9px] py-0.5 px-1.5 font-bold">
                                            Justificado
                                        </flux:badge>
                                    @elseif($atraso->estado === 'pendiente')
                                        <flux:badge color="amber" size="xs" icon="clock" class="uppercase text-[9px] py-0.5 px-1.5 font-bold">
                                            Pendiente
                                        </flux:badge>
                                    @else
                                        <flux:badge color="zinc" size="xs" class="uppercase text-[9px] py-0.5 px-1.5 font-bold text-rose-700 bg-rose-50 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-200 dark:border-rose-900/60">
                                            Injustificado
                                        </flux:badge>
                                    @endif

                                    @if($atraso->motivo)
                                        <span class="text-[10px] text-zinc-500 italic max-w-[120px] truncate" title="{{ $atraso->motivo }}">
                                            ({{ $atraso->motivo }})
                                        </span>
                                    @endif
                                </div>
                            </flux:table.cell>

                            {{-- Registrado Por --}}
                            <flux:table.cell class="py-2.5">
                                <span class="text-[11px] text-zinc-600 dark:text-zinc-400 font-medium truncate block max-w-[110px]" title="{{ $atraso->registradoPor?->nombreCompleto() ?? 'Sistema' }}">
                                    {{ $atraso->registradoPor ? ($atraso->registradoPor->nombres . ' ' . $atraso->registradoPor->apellido_pat) : 'Sistema' }}
                                </span>
                            </flux:table.cell>

                            {{-- Acciones (con stopPropagation para no abrir el modal al clickear un botón) --}}
                            <flux:table.cell class="py-2.5 text-right" wire:click.stop>
                                <div class="flex items-center justify-end gap-1">
                                    {{-- Botón justificar / ver justificación --}}
                                    <button 
                                        type="button" 
                                        wire:click.stop="justificarAtraso({{ $atraso->id }})"
                                        @class([
                                            'px-2 py-0.5 rounded text-[10px] font-bold transition-all',
                                            'bg-emerald-600 text-white hover:bg-emerald-700' => $atraso->estado === 'justificado',
                                            'bg-zinc-200 text-zinc-700 hover:bg-zinc-300 dark:bg-zinc-700 dark:text-zinc-300' => $atraso->estado !== 'justificado',
                                        ])
                                        title="{{ $atraso->estado === 'justificado' ? 'Editar justificación' : 'Justificar atraso' }}"
                                    >
                                        {{ $atraso->estado === 'justificado' ? '✓ Justificado' : 'Justificar' }}
                                    </button>

                                    {{-- Botón Editar Atraso (Fecha, Hora, Detalle) --}}
                                    <button 
                                        type="button" 
                                        wire:click.stop="abrirModalEdicion({{ $atraso->id }})"
                                        class="p-1 rounded text-zinc-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors"
                                        title="Editar fecha, hora y detalle del atraso"
                                    >
                                        <flux:icon.pencil-square class="size-3.5" />
                                    </button>

                                    {{-- Pase Térmico --}}
                                    <a 
                                        href="{{ route('atrasos.ticket', $atraso->id) }}" 
                                        target="_blank" 
                                        class="p-1 rounded text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200"
                                        title="Ver ticket de ingreso"
                                    >
                                        <flux:icon.printer class="size-3.5" />
                                    </a>

                                    {{-- Eliminar --}}
                                    <button 
                                        type="button" 
                                        wire:click.stop="confirmarEliminacion({{ $atraso->id }})"
                                        class="p-1 rounded text-zinc-400 hover:text-rose-600 dark:hover:text-rose-400"
                                        title="Anular atraso"
                                    >
                                        <flux:icon.trash class="size-3.5" />
                                    </button>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="9">
                                <div class="py-12 text-center text-zinc-500">
                                    <flux:icon.magnifying-glass class="size-8 mx-auto opacity-40 mb-2" />
                                    <p class="text-xs font-semibold">No se encontraron atrasos registrados con los filtros seleccionados.</p>
                                    <p class="text-[11px] text-zinc-400 mt-1">Prueba cambiando la fecha, curso o periodo de búsqueda.</p>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        <div class="p-3 sm:px-5 bg-zinc-50 dark:bg-zinc-800/20 border-t border-zinc-200 dark:border-zinc-700">
            {{ $atrasos->links(data: ['scrollTo' => false]) }}
        </div>
    </flux:card>

    {{-- MODAL DE HISTORIAL COMPLETO DEL ESTUDIANTE AL HACER CLICK EN LA FILA --}}
    <flux:modal wire:model="modalDetalleEstudiante" class="md:w-[680px]">
        @if($estudianteSeleccionado)
            <div class="space-y-5">
                {{-- Encabezado del Estudiante --}}
                <div class="flex items-start justify-between border-b border-zinc-200/70 dark:border-zinc-800 pb-3">
                    <div class="flex items-center gap-3">
                        <div class="size-12 rounded-2xl bg-blue-100 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 flex items-center justify-center font-black text-lg shadow-inner">
                            {{ $estudianteSeleccionado->initials() }}
                        </div>
                        <div>
                            <flux:heading size="lg" class="font-black text-zinc-900 dark:text-zinc-100 leading-tight">
                                {{ $estudianteSeleccionado->nombreCompleto() }}
                            </flux:heading>
                            <div class="flex items-center gap-2 mt-0.5 text-xs text-zinc-500">
                                <span class="font-bold text-blue-600 dark:text-blue-400">{{ $estudianteSeleccionado->curso?->nombreCompleto() ?? 'Sin Curso' }}</span>
                                <span>•</span>
                                <span class="font-mono">{{ $estudianteSeleccionado->rutCompleto() ?? 'Sin RUT' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Resumen de Reincidencia del Estudiante --}}
                @php
                    $atrasosEstudianteMes = $estudianteSeleccionado->atrasosMesActualCount();
                @endphp
                <div class="grid grid-cols-2 gap-3 text-center">
                    <div class="p-2.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700">
                        <span class="block text-[10px] font-bold uppercase text-zinc-400">Total Histórico</span>
                        <span class="text-base font-black text-zinc-800 dark:text-zinc-200">{{ $estudianteSeleccionado->atrasos->count() }}</span>
                    </div>
                    <div class="p-2.5 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900/50">
                        <span class="block text-[10px] font-bold uppercase text-amber-600 dark:text-amber-400">Mes en Curso</span>
                        <span class="text-base font-black text-amber-700 dark:text-amber-300">{{ $atrasosEstudianteMes }}</span>
                    </div>
                </div>

                {{-- Tabla de Atrasos Anteriores del Estudiante --}}
                <div class="space-y-2">
                    <span class="text-xs font-black uppercase tracking-wider text-zinc-500">
                        Cronograma de Atrasos Registrados
                    </span>

                    <div class="max-h-72 overflow-y-auto rounded-xl border border-zinc-200 dark:border-zinc-800 divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse($estudianteSeleccionado->atrasos as $item)
                            <div class="p-3 flex items-center justify-between gap-3 text-xs hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                                <div class="flex items-center gap-2.5">
                                    <span class="font-bold text-zinc-800 dark:text-zinc-200">
                                        {{ Carbon::parse($item->fecha)->format('d/m/Y') }}
                                    </span>
                                    <span class="font-mono font-bold text-blue-600 dark:text-blue-400">
                                        {{ Carbon::parse($item->hora)->format('H:i') }} hrs
                                    </span>
                                    @if($item->minutos_atraso > 0)
                                        <span class="text-[11px] font-mono text-rose-500">
                                            (+{{ $item->minutos_atraso }}m)
                                        </span>
                                    @endif
                                </div>

                                <div class="flex items-center gap-2">
                                    @if($item->estado === 'justificado')
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                            Justificado {{ $item->motivo ? "({$item->motivo})" : '' }}
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300">
                                            Injustificado
                                        </span>
                                    @endif

                                    <a 
                                        href="{{ route('atrasos.ticket', $item->id) }}" 
                                        target="_blank" 
                                        class="p-1 rounded text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200"
                                        title="Imprimir Pase"
                                    >
                                        <flux:icon.printer class="size-3.5" />
                                    </a>
                                </div>
                            </div>
                        @empty
                            <div class="p-4 text-center text-xs text-zinc-400">
                                No registra atrasos anteriores.
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="flex justify-end pt-2 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button variant="ghost" wire:click="$set('modalDetalleEstudiante', false)">
                        Cerrar
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- Modal Edición de Atraso (Fecha, Hora, Jornada, Estado, Motivo) --}}
    <flux:modal wire:model="modalEdicion" class="md:w-[480px] space-y-5">
        <div>
            <flux:heading size="lg">Editar Atraso</flux:heading>
            <flux:subheading>
                {{ $editarEstudianteNombre ?: 'Actualizar fecha, hora o justificativo del atraso.' }}
            </flux:subheading>
        </div>

        <div class="space-y-4">
            {{-- Fecha, Hora y Jornada --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <flux:field>
                    <flux:label class="text-[11px]">Fecha</flux:label>
                    <flux:input type="date" size="sm" wire:model="editarFecha" />
                </flux:field>

                <flux:field>
                    <flux:label class="text-[11px]">Hora Ingreso</flux:label>
                    <flux:input type="time" size="sm" wire:model="editarHora" />
                </flux:field>

                <flux:field>
                    <flux:label class="text-[11px]">Jornada</flux:label>
                    <flux:select size="sm" wire:model="editarJornada">
                        <flux:select.option value="manana">☀️ Mañana</flux:select.option>
                        <flux:select.option value="tarde">🌙 Tarde</flux:select.option>
                    </flux:select>
                </flux:field>
            </div>

            {{-- Estado y Motivo --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <flux:field class="sm:col-span-1">
                    <flux:label class="text-[11px]">Estado</flux:label>
                    <flux:select size="sm" wire:model="editarEstado">
                        <flux:select.option value="justificado">Justificado</flux:select.option>
                        <flux:select.option value="injustificado">Injustificado</flux:select.option>
                        <flux:select.option value="pendiente">Pendiente</flux:select.option>
                    </flux:select>
                </flux:field>

                <flux:field class="sm:col-span-2">
                    <flux:label class="text-[11px]">Motivo de Justificación</flux:label>
                    <flux:input 
                        size="sm" 
                        wire:model="editarMotivo" 
                        list="motivos-atraso-rapido"
                        placeholder="Ej: Apoderado presente, Pase médico, Locomoción..." 
                    />
                </flux:field>
            </div>

            <flux:field>
                <flux:label class="text-[11px]">Observaciones (Opcional)</flux:label>
                <flux:textarea wire:model="editarObservaciones" placeholder="Detalles adicionales de Inspectoría..." rows="2" />
            </flux:field>
        </div>

        <div class="flex justify-end gap-2 pt-2">
            <flux:button wire:click="$set('modalEdicion', false)">Cancelar</flux:button>
            <flux:button variant="primary" wire:click="guardarEdicion">Guardar Cambios</flux:button>
        </div>
    </flux:modal>

    {{-- Modal Estético para Anular / Eliminar Registro --}}
    <flux:modal wire:model="modalEliminar" class="md:w-96">
        <div class="space-y-5">
            <div class="flex items-start gap-4">
                <div class="size-11 rounded-2xl bg-rose-100 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center shrink-0">
                    <flux:icon.trash class="size-6" />
                </div>
                <div>
                    <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100 font-black">
                        Anular Registro de Atraso
                    </flux:heading>
                    <flux:subheading class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 leading-relaxed">
                        ¿Estás seguro de que deseas anular el atraso de <span class="font-bold text-zinc-800 dark:text-zinc-200">{{ $estudianteAEliminarNombre }}</span>? El registro será eliminado permanentemente.
                    </flux:subheading>
                </div>
            </div>

            <div class="flex justify-end gap-2.5 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button variant="ghost" wire:click="$set('modalEliminar', false)">
                    Cancelar
                </flux:button>
                <flux:button variant="danger" wire:click="eliminarAtraso">
                    Sí, Anular Atraso
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal para Registrar y Justificar Atraso Directamente (Ingreso Posterior) --}}
    <flux:modal wire:model="modalNuevoAtraso" class="md:w-[540px] space-y-5">
        <div class="flex items-center gap-3">
            <div class="size-10 rounded-xl bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                <flux:icon.plus-circle class="size-6" />
            </div>
            <div>
                <flux:heading size="lg">Registrar y Justificar Atraso</flux:heading>
                <flux:subheading>Para estudiantes que ingresan después del registro masivo</flux:subheading>
            </div>
        </div>

        <div class="space-y-4">
            {{-- Selección de Estudiante --}}
            @if($this->nuevoEstudiante)
                <div class="p-3 rounded-xl bg-blue-50/80 dark:bg-blue-950/30 border border-blue-200/80 dark:border-blue-900/50 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="size-9 rounded-lg bg-[#00376e] text-white flex items-center justify-center font-bold text-xs uppercase shrink-0">
                            {{ mb_substr($this->nuevoEstudiante->nombres_csv ?? 'E', 0, 2) }}
                        </div>
                        <div class="leading-tight">
                            <span class="block text-xs font-black text-zinc-900 dark:text-zinc-100">
                                {{ $this->nuevoEstudiante->nombreCompleto() }}
                            </span>
                            <div class="flex items-center gap-2 text-[11px] text-zinc-500 font-mono mt-0.5">
                                <span>{{ $this->nuevoEstudiante->rutCompleto() }}</span>
                                <span>•</span>
                                <span class="font-sans font-semibold text-blue-700 dark:text-blue-300">
                                    {{ $this->nuevoEstudiante->curso?->nombreCompleto() ?? 'Sin curso' }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <button 
                        type="button" 
                        wire:click="deseleccionarNuevoEstudiante" 
                        class="p-1 rounded-lg text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 hover:bg-zinc-200/60 dark:hover:bg-zinc-800 transition-colors"
                        title="Cambiar estudiante"
                    >
                        <flux:icon.x-mark class="size-4" />
                    </button>
                </div>
            @else
                <div class="relative">
                    <flux:field>
                        <flux:label class="text-[11px]">Estudiante <span class="text-rose-500">*</span></flux:label>
                        <flux:input 
                            size="sm"
                            wire:model.live.debounce.250ms="nuevoSearchEstudiante" 
                            wire:keydown.enter.prevent="seleccionarPrimerResultadoNuevo"
                            icon="magnifying-glass"
                            placeholder="Buscar por nombre, apellido o RUT..." 
                            autocomplete="off"
                        />
                    </flux:field>

                    @if(!empty($this->nuevoSearchEstudiante) && mb_strlen(trim($this->nuevoSearchEstudiante)) >= 2)
                        <div class="absolute left-0 right-0 top-full mt-1 z-50 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-lg max-h-56 overflow-y-auto divide-y divide-zinc-100 dark:divide-zinc-800">
                            @forelse($this->resultadosNuevoEstudiante as $res)
                                <button 
                                    type="button" 
                                    wire:click="seleccionarNuevoEstudiante({{ $res->id }})"
                                    class="w-full px-3 py-2 text-left hover:bg-blue-50 dark:hover:bg-blue-950/40 flex items-center justify-between transition-colors text-xs"
                                >
                                    <div>
                                        <span class="font-bold text-zinc-800 dark:text-zinc-200 block">{{ $res->nombreCompleto() }}</span>
                                        <span class="text-[10px] text-zinc-400 font-mono">{{ $res->rutCompleto() }}</span>
                                    </div>
                                    <span class="text-[11px] font-semibold text-blue-600 dark:text-blue-400">
                                        {{ $res->curso?->nombreAbreviado() ?? 'S/C' }}
                                    </span>
                                </button>
                            @empty
                                <div class="p-3 text-center text-xs text-zinc-400">
                                    No se encontraron estudiantes con "{{ $this->nuevoSearchEstudiante }}"
                                </div>
                            @endforelse
                        </div>
                    @endif
                </div>
            @endif

            {{-- Fila con Fecha, Hora y Jornada --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <flux:field>
                    <flux:label class="text-[11px]">Fecha</flux:label>
                    <flux:input type="date" size="sm" wire:model="nuevaFecha" />
                </flux:field>

                <flux:field>
                    <flux:label class="text-[11px]">Hora Ingreso</flux:label>
                    <flux:input type="time" size="sm" wire:model="nuevaHora" />
                </flux:field>

                <flux:field>
                    <flux:label class="text-[11px]">Jornada</flux:label>
                    <flux:select size="sm" wire:model="nuevaJornada">
                        <flux:select.option value="manana">☀️ Mañana (08:00)</flux:select.option>
                        <flux:select.option value="tarde">🌙 Tarde (13:30)</flux:select.option>
                    </flux:select>
                </flux:field>
            </div>

            {{-- Fila con Estado y Motivo --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <flux:field class="sm:col-span-1">
                    <flux:label class="text-[11px]">Estado</flux:label>
                    <flux:select size="sm" wire:model.live="nuevoEstado">
                        <flux:select.option value="justificado">Justificado</flux:select.option>
                        <flux:select.option value="injustificado">Injustificado</flux:select.option>
                        <flux:select.option value="pendiente">Pendiente</flux:select.option>
                    </flux:select>
                </flux:field>

                <flux:field class="sm:col-span-2">
                    <flux:label class="text-[11px]">
                        Motivo @if($nuevoEstado === 'justificado') <span class="text-emerald-600 font-bold">*</span> @endif
                    </flux:label>
                    <flux:input 
                        size="sm" 
                        wire:model="nuevoMotivo" 
                        list="motivos-atraso-rapido"
                        placeholder="Ej: Apoderado presente, Pase médico, Locomoción..." 
                    />
                    <datalist id="motivos-atraso-rapido">
                        <option value="Apoderado presente en portería">
                        <option value="Atención médica / Dental">
                        <option value="Pase de Inspectoría General">
                        <option value="Problemas de locomoción / Transporte">
                        <option value="Trámites personales / familiares">
                        <option value="Certificado médico adjunto">
                        <option value="Autorizado por Dirección">
                    </datalist>
                </flux:field>
            </div>

            {{-- Observaciones --}}
            <flux:field>
                <flux:label class="text-[11px]">Observaciones (Opcional)</flux:label>
                <flux:textarea wire:model="nuevasObservaciones" placeholder="Detalles u observaciones de Inspectoría..." rows="2" />
            </flux:field>
        </div>

        <div class="flex items-center justify-between gap-2 pt-3 border-t border-zinc-100 dark:border-zinc-800">
            <flux:button variant="ghost" wire:click="$set('modalNuevoAtraso', false)">
                Cancelar
            </flux:button>
            <div class="flex items-center gap-2">
                <flux:button 
                    variant="primary" 
                    wire:click="guardarNuevoAtraso(false)"
                    class="bg-[#00376e] hover:bg-blue-800 text-white"
                >
                    Guardar
                </flux:button>
                <flux:button 
                    variant="primary" 
                    icon="printer" 
                    wire:click="guardarNuevoAtraso(true)" 
                    class="bg-gradient-to-br from-emerald-600 to-teal-700 hover:from-emerald-700 hover:to-teal-800 text-white font-bold"
                >
                    Guardar e Imprimir
                </flux:button>
            </div>
        </div>
    </flux:modal>

</div>
