<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Title;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

new class extends Component {
    use WithFileUploads;

    public $archivo = null;
    public array $filas = [];
    public bool $previsualizando = false;
    public bool $importado = false;
    public int $totalOk = 0;
    public int $totalError = 0;

    public function updatedArchivo(): void
    {
        $this->validate(['archivo' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);
        $this->parsearCsv();
    }

    private function parsearCsv(): void
    {
        $this->filas = [];
        $path = $this->archivo->getRealPath();

        $contenido = file_get_contents($path);

        if (str_starts_with($contenido, "\xEF\xBB\xBF")) {
            $contenido = substr($contenido, 3);
        } else {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252');
        }

        $primeraLinea = strtok($contenido, "\n");
        $separador = substr_count($primeraLinea, ';') >= substr_count($primeraLinea, ',') ? ';' : ',';

        $lineas = array_filter(explode("\n", $contenido), fn($l) => trim($l) !== '');
        array_shift($lineas); // Quitar encabezados

        $schoolId = auth()->user()->current_school_id;

        foreach ($lineas as $linea) {
            $cols = str_getcsv($linea, $separador);
            if (count($cols) < 5) {
                continue;
            }

            $rutRaw = str_replace(['.', ' ', '-'], '', trim($cols[0] ?? ''));
            $rutNumero = substr($rutRaw, 0, -1);
            $rutDv = strtoupper(substr($rutRaw, -1));

            // Si viene partido en dos columnas (RUT, DV) -> Ajustar índice:
            if (strlen(trim($cols[1] ?? '')) === 1) {
                $rutNumero = preg_replace('/\D/', '', $cols[0] ?? '');
                $rutDv = strtoupper(trim($cols[1] ?? ''));
                $idxOffset = 1;
            } else {
                $idxOffset = 0;
            }

            $nombres = trim($cols[1 + $idxOffset] ?? '');
            $apellidos = trim($cols[2 + $idxOffset] ?? '');
            $correo = strtolower(trim($cols[3 + $idxOffset] ?? ''));
            $rol = strtolower(trim($cols[4 + $idxOffset] ?? 'docente'));

            $partesApellidos = explode(' ', $apellidos, 2);
            $apellidoPat = $partesApellidos[0] ?? '';
            $apellidoMat = $partesApellidos[1] ?? '';

            // Verificar si ya existe en este colegio
            $existe = User::whereHas('schools', function($q) use ($schoolId) {
                $q->where('school_id', $schoolId);
            })->where('email', $correo)->exists();

            $isValid = filter_var($correo, FILTER_VALIDATE_EMAIL) && !empty($nombres);

            $this->filas[] = [
                'nombres' => $nombres,
                'apellido_pat' => $apellidoPat,
                'apellido_mat' => $apellidoMat,
                'rut_numero' => $rutNumero,
                'rut_dv' => $rutDv,
                'email' => $correo,
                'rol' => $rol,
                'estado' => $existe ? 'duplicado' : ($isValid ? 'ok' : 'error'),
            ];
        }

        $this->previsualizando = true;
        $this->totalOk = count(array_filter($this->filas, fn($f) => $f['estado'] === 'ok'));
        $this->totalError = count(array_filter($this->filas, fn($f) => $f['estado'] !== 'ok'));
    }

    public function importar(): void
    {
        $schoolId = auth()->user()->current_school_id;
        $importados = 0;

        DB::transaction(function () use ($schoolId, &$importados) {
            foreach ($this->filas as $fila) {
                if ($fila['estado'] !== 'ok') {
                    continue;
                }

                // El usuario puede ya existir en el sistema global (User), pero no estar en este school
                $user = User::firstOrCreate(
                    ['email' => $fila['email']],
                    [
                        'nombres' => $fila['nombres'],
                        'apellido_pat' => $fila['apellido_pat'],
                        'apellido_mat' => $fila['apellido_mat'],
                        'rut_numero' => $fila['rut_numero'],
                        'rut_dv' => $fila['rut_dv'],
                        'password' => Hash::make(Str::random(16)), // Contraseña al azar
                        'current_school_id' => $schoolId,
                    ]
                );

                // Si se encontró el user, pero le faltan los datos, llenarlos
                if (!$user->wasRecentlyCreated && empty($user->rut_numero)) {
                    $user->update([
                        'nombres' => $fila['nombres'],
                        'apellido_pat' => $fila['apellido_pat'],
                        'rut_numero' => $fila['rut_numero'],
                        'rut_dv' => $fila['rut_dv']
                    ]);
                }

                // Asegurar que quede atado al colegio siempre y actualizar rol
                $roles = [$fila['rol']];
                if ($user->schools()->where('school_id', $schoolId)->exists()) {
                    $user->schools()->updateExistingPivot($schoolId, ['roles' => json_encode($roles)]);
                } else {
                    $user->schools()->attach($schoolId, ['roles' => json_encode($roles)]);
                }

                $importados++;
            }
        });

        $this->importado = true;
        $this->totalOk = $importados;
        $this->reset(['archivo', 'filas', 'previsualizando']);
    }

    public function reiniciar(): void
    {
        $this->reset();
    }
};
?>

<div class="flex flex-col gap-8 max-w-7xl mx-auto w-full">

    {{-- Encabezado --}}
    <div>
        <flux:breadcrumbs class="mb-4">
            <flux:breadcrumbs.item icon="building-library" href="#" />
            <flux:breadcrumbs.item href="{{ route('funcionarios.index') }}">{{ __('Funcionarios') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('Carga Masiva') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="flex items-start justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ __('Importación Masiva de Funcionarios') }}</flux:heading>
                <flux:subheading size="lg">
                    {{ __('Sube un archivo CSV para registrar o actualizar el personal docente y administrativo en lote.') }}
                </flux:subheading>
            </div>
            <flux:button href="{{ route('funcionarios.index') }}" variant="ghost" icon="arrow-left">
                {{ __('Volver') }}
            </flux:button>
        </div>
    </div>

    @if ($importado)
        {{-- Resultado final --}}
        <flux:card class="text-center py-12">
            <flux:icon.check-circle class="size-16 text-[#00376e] mx-auto mb-4" />
            <flux:heading size="xl">{{ __('¡Importación de Empleados Completada!') }}</flux:heading>
            <flux:subheading class="mt-2 text-zinc-600">
                Se agregaron {{ $totalOk }} {{ __('nuevos funcionarios al plantel institucional.') }}
            </flux:subheading>
            <div class="mt-6 flex justify-center gap-4">
                <flux:button wire:click="reiniciar" variant="ghost">{{ __('Nueva importación') }}</flux:button>
                <flux:button href="{{ route('funcionarios.index') }}" variant="primary">{{ __('Ir a Gestión de Personal') }}
                </flux:button>
            </div>
        </flux:card>
    @elseif (!$previsualizando)
        {{-- Upload --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <flux:card class="md:col-span-2">
                <div class="flex items-center gap-3 mb-6">
                    <div class="p-2 bg-gradient-to-r from-blue-500 to-[#00376e] text-white rounded-lg">
                        <flux:icon.document-arrow-up class="size-5" />
                    </div>
                    <flux:heading size="lg">{{ __('Cargar Registro CSV') }}</flux:heading>
                </div>

                <flux:input wire:model="archivo" type="file" accept=".csv"
                    :label="__('Sube la plantilla CSV (separada por comas)')"
                    :description="__('Asegúrate de que la primera línea de tu archivo tenga el nombre de las columnas.')" />
                <flux:error name="archivo" />

                <div wire:loading wire:target="archivo" class="mt-4 flex items-center gap-2 text-zinc-500 text-sm font-bold">
                    <flux:icon.arrow-path class="size-4 animate-spin text-[#00376e]" />
                    {{ __('Procesando las filas... Por favor espera') }}
                </div>
            </flux:card>

            <flux:card class="flex flex-col gap-4 bg-zinc-50 dark:bg-zinc-800/50">
                <flux:heading>{{ __('Columnas esperadas') }}</flux:heading>
                <div class="flex flex-col gap-2 text-sm text-zinc-600 dark:text-zinc-400">
                    @foreach ([['RUT', 'Solo Nros (ej: 12345678)'], ['DV', 'Dígito (ej: K)'], ['Nombres', 'Ej: Juan Pablo'], ['Apellidos', 'Paterno Materno'], ['Correo Institucional', 'Exclusivo @newheavenhs.cl'], ['Rol', 'docente, inspector, admin']] as [$col, $desc])
                        <div class="flex justify-between gap-2 border-b border-zinc-200 dark:border-zinc-700 pb-1">
                            <span class="font-mono font-medium text-zinc-800 dark:text-zinc-200">{{ $col }}</span>
                            <span class="text-xs text-zinc-400 text-right">{{ $desc }}</span>
                        </div>
                    @endforeach
                </div>
                <flux:separator />
                <flux:text class="text-xs">
                    {{ __('Roles admitidos: docente, inspector, asistente, psicosocial, recepcion, directivo, administrador.') }}
                </flux:text>
            </flux:card>
        </div>
    @else
        {{-- Previsualización --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <flux:card class="text-center shadow-sm">
                <div class="text-3xl font-bold text-zinc-800 dark:text-zinc-100">{{ count($filas) }}</div>
                <flux:text class="text-sm mt-1">{{ __('Total de Filas') }}</flux:text>
            </flux:card>
            <flux:card class="text-center border-green-200 dark:border-green-800 bg-green-50/10 shadow-sm">
                <div class="text-3xl font-bold text-green-600">{{ $totalOk }}</div>
                <flux:text class="text-sm mt-1">{{ __('Listos Validaciones OK') }}</flux:text>
            </flux:card>
            <flux:card class="text-center border-red-200 dark:border-red-800 bg-red-50/10 shadow-sm">
                <div class="text-3xl font-bold text-red-500">{{ $totalError }}</div>
                <flux:text class="text-sm mt-1">{{ __('Correos duplicados o Inválidos') }}</flux:text>
            </flux:card>
        </div>

        <flux:card class="shadow-sm">
            <div class="flex flex-col sm:flex-row items-center justify-between mb-4 border-b pb-4 dark:border-zinc-700">
                <div class="flex items-center gap-2 mb-4 sm:mb-0">
                    <div class="p-1.5 bg-yellow-100 text-yellow-600 rounded-md">
                        <flux:icon.eye class="size-4" />
                    </div>
                    <flux:heading size="lg">{{ __('Previsualización de Datos') }}</flux:heading>
                </div>
                
                <div class="flex gap-3">
                    <flux:button wire:click="reiniciar" variant="ghost" icon="arrow-path">
                        {{ __('Cancelar / Subir otro') }}
                    </flux:button>
                    @if ($totalOk > 0)
                        <flux:button wire:click="importar" variant="primary" icon="check" class="bg-[#00376e] text-white"
                            wire:confirm="{{ __('¿Confirmas dar de alta a ' . $totalOk . ' funcionarios?') }}">
                            {{ __('Autorizar Carga de ') }} {{ $totalOk }} {{ __(' Perfiles') }}
                        </flux:button>
                    @endif
                </div>
            </div>

            <div class="overflow-x-auto">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Estado') }}</flux:table.column>
                        <flux:table.column>{{ __('RUT') }}</flux:table.column>
                        <flux:table.column>{{ __('Funcionario') }}</flux:table.column>
                        <flux:table.column>{{ __('Correo') }}</flux:table.column>
                        <flux:table.column>{{ __('Rol') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($filas as $fila)
                            <flux:table.row>
                                <flux:table.cell>
                                    @if ($fila['estado'] === 'ok')
                                        <flux:badge color="green" icon="check-circle">{{ __('OK') }}</flux:badge>
                                    @elseif ($fila['estado'] === 'duplicado')
                                        <flux:badge color="yellow" icon="exclamation-triangle">{{ __('Ya existe el correo') }}
                                        </flux:badge>
                                    @else
                                        <flux:badge color="red" icon="x-circle">{{ __('Campos faltantes') }}</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="font-mono text-xs font-bold">
                                    {{ $fila['rut_numero'] }}-{{ $fila['rut_dv'] }}
                                </flux:table.cell>
                                <flux:table.cell class="font-medium uppercase">{{ $fila['apellido_pat'] }} {{ $fila['apellido_mat'] }}, {{ $fila['nombres'] }}</flux:table.cell>
                                <flux:table.cell>
                                    <span class="text-zinc-500 font-mono text-xs">{{ $fila['email'] }}</span>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge color="blue">{{ $fila['rol'] }}</flux:badge>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        </flux:card>
    @endif
</div>
