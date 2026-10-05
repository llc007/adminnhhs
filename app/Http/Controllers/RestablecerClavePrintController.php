<?php

namespace App\Http\Controllers;

use App\Models\Estudiante;
use App\Models\User;
use Illuminate\Http\Request;

class RestablecerClavePrintController extends Controller
{
    /**
     * Render the print view for credential reset slip (A4/Carta or POS80 thermal receipt).
     */
    public function imprimir(Request $request)
    {
        $user = auth()->user();
        if (! $user->hasRole('superadmin') && ! $user->can('ver-estudiantes') && ! $user->can('gestionar-funcionarios')) {
            abort(403, 'No tienes permiso para imprimir comprobantes de credenciales.');
        }

        $tipo = $request->query('tipo', 'estudiante');
        $id = $request->query('id');
        $format = $request->query('format', 'a4'); // 'a4' or 'pos80'

        $persona = null;
        $nombreCompleto = '';
        $rutCompleto = '';
        $cursoCargo = '';
        $apoderadoNombre = '';

        if ($tipo === 'funcionario') {
            $persona = User::findOrFail($id);
            $nombreCompleto = $persona->nombreCompleto();
            $rutCompleto = $persona->rutCompleto() ?? 'N/A';
            $cursoCargo = 'Funcionario / Docente';
        } else {
            $persona = Estudiante::with(['curso', 'user', 'school'])->findOrFail($id);
            $nombreCompleto = $persona->nombreCompleto();
            $rutCompleto = $persona->rutCompleto() ?? 'N/A';
            $cursoCargo = $persona->curso ? $persona->curso->nombreCompleto() : 'Sin Curso';
            $apoderadoNombre = $persona->apoderadoNombreCompleto();
        }

        $school = $user->currentSchool ?? ($persona->school ?? null);

        $datos = [
            'format' => $format,
            'tipo' => $tipo,
            'persona' => $persona,
            'school' => $school,
            'nombreCompleto' => $nombreCompleto,
            'rutCompleto' => $rutCompleto,
            'cursoCargo' => $cursoCargo,
            'apoderadoNombre' => $apoderadoNombre,
            'incluirGoogle' => $request->boolean('incluir_google', true),
            'googleEmail' => $request->query('google_email', ''),
            'googlePassword' => $request->query('google_password', ''),
            'incluirFullcollege' => $request->boolean('incluir_fullcollege', false),
            'fullcollegeUser' => $request->query('fullcollege_user', ''),
            'fullcollegePassword' => $request->query('fullcollege_password', '12345'),
            'contactoEmail' => $user->email ?? 'contacto@colegio.cl',
        ];

        $viewName = $format === 'pos80' ? 'print.restablecer_clave_pos80' : 'print.restablecer_clave_a4';

        return view($viewName, $datos);
    }
}
