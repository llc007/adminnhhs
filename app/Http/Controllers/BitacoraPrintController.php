<?php

namespace App\Http\Controllers;

use App\Models\Entrevista;
use Illuminate\Http\Request;

class BitacoraPrintController extends Controller
{
    /**
     * Muestra la vista imprimible y descargable en PDF de la bitácora de una entrevista.
     */
    public function imprimir(Request $request, Entrevista $entrevista)
    {
        // Validación de permisos según EntrevistaPolicy (view)
        if (! $request->user()->can('view', $entrevista)) {
            abort(403, 'No tienes permiso para ver o imprimir esta bitácora.');
        }

        $entrevista->load([
            'school',
            'estudiante.curso',
            'user',
            'bitacora.updatedByUser',
            'recepcionistaIngreso',
            'recepcionistaSalida',
        ]);

        $bitacora = $entrevista->bitacora;
        $school = $entrevista->school ?? $request->user()->currentSchool;
        $estudiante = $entrevista->estudiante;
        $docente = $entrevista->user;

        return view('pages.entrevistas.print_bitacora', [
            'entrevista' => $entrevista,
            'bitacora' => $bitacora,
            'school' => $school,
            'estudiante' => $estudiante,
            'docente' => $docente,
        ]);
    }
}
