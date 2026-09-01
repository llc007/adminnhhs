<?php

namespace App\Http\Controllers;

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

        $query = User::query()
            ->whereHas('schools', fn ($q) => $q->where('schools.id', $schoolId))
            ->whereDoesntHave('roles', function ($q) use ($schoolId) {
                $q->where('roles.team_id', $schoolId)
                    ->where('roles.name', 'estudiante');
            })
            ->withCount([
                'entrevistas as total_agendadas' => fn ($q) => $q->where('school_id', $schoolId)->where($dateClosure),
                'entrevistas as total_realizadas' => fn ($q) => $q->where('school_id', $schoolId)->where('estado', 'realizada')->where($dateClosure),
                'entrevistas as total_canceladas' => fn ($q) => $q->where('school_id', $schoolId)->where('estado', 'cancelada')->where($dateClosure),
                'entrevistas as total_abiertas' => fn ($q) => $q->where('school_id', $schoolId)->whereIn('estado', ['abierta', 'ingresada', 'pendiente'])->where($dateClosure),
                'entrevistas as total_ausentes' => fn ($q) => $q->where('school_id', $schoolId)->where('estado', 'ausente')->where($dateClosure),
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
            'abiertas' => $funcionarios->sum('total_abiertas'),
            'ausentes' => $funcionarios->sum('total_ausentes'),
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
}
