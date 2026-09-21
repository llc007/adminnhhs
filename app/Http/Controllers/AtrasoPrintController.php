<?php

namespace App\Http\Controllers;

use App\Models\Atraso;
use Illuminate\Http\Request;

class AtrasoPrintController extends Controller
{
    /**
     * Render the thermal ticket view for a given atraso.
     */
    public function ticket(Request $request, Atraso $atraso)
    {
        $user = auth()->user();
        if (! $user->hasRole('superadmin') && ! $user->can('ingresar-atrasos')) {
            abort(403, 'No tienes permiso para imprimir este ticket.');
        }

        $atraso->load(['estudiante', 'curso', 'school', 'registradoPor']);

        $totalMes = $atraso->estudiante ? $atraso->estudiante->atrasosMesActualCount() : 1;

        return view('print.atraso_ticket', [
            'atraso' => $atraso,
            'totalMes' => $totalMes,
        ]);
    }
}
