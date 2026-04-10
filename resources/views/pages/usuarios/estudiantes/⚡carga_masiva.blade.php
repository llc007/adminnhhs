<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Title;
use App\Models\Curso;
use App\Models\Estudiante;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

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

    private function normalizarTexto(?string $texto): string
    {
        if ($texto === null || trim($texto) === '') {
            return '';
        }

        // Normalizar a NFC: Excel UTF-8 puede exportar tildes en forma NFD
        if (class_exists('Normalizer')) {
            $texto = \Normalizer::normalize($texto, \Normalizer::NFC);
        }

        // Normalizar espacios: reemplazar non-breaking space (U+00A0), tabs y
        // múltiples espacios por un solo espacio regular
        $texto = preg_replace('/[\x{00A0}\x{202F}\x{FEFF}\t]+/u', ' ', $texto);
        $texto = preg_replace('/\s+/', ' ', $texto);

        // Reemplazar caracteres españoles por su equivalente ASCII
        $buscar     = ['Á', 'É', 'Í', 'Ó', 'Ú', 'Ü', 'Ñ', 'á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'];
        $reemplazar = ['A', 'E', 'I', 'O', 'U', 'U', 'N', 'A', 'E', 'I', 'O', 'U', 'U', 'N'];

        return strtoupper(trim(str_replace($buscar, $reemplazar, $texto)));
    }

    private function parsearRut(string $rut): array
    {
        // Eliminar puntos y espacios, dejar solo número-dv
        $rut = preg_replace('/[.\s]/', '', trim($rut));

        if (str_contains($rut, '-')) {
            [$numero, $dv] = explode('-', $rut, 2);
        } else {
            // Sin guión: last char es DV
            $numero = substr($rut, 0, -1);
            $dv = substr($rut, -1);
        }

        return [
            'rut_numero' => preg_replace('/\D/', '', $numero),
            'rut_dv' => strtoupper(trim($dv)),
        ];
    }

    private function parsearCsv(): void
    {
        $this->filas = [];
        $path = $this->archivo->getRealPath();

        // FullCollege exporta en Windows-1252 sin BOM.
        // mb_detect_encoding() no es confiable para estos archivos → usamos BOM.
        $contenido = file_get_contents($path);

        if (str_starts_with($contenido, "\xEF\xBB\xBF")) {
            // Tiene BOM UTF-8 → ya está en UTF-8, solo quitamos el BOM
            $contenido = substr($contenido, 3);
        } else {
            // Sin BOM → asumir Windows-1252 (estándar en software chileno)
            $contenido = mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252');
        }

        // Detectar separador desde la primera línea
        $primeraLinea = strtok($contenido, "\n");
        $separador = substr_count($primeraLinea, ';') >= substr_count($primeraLinea, ',') ? ';' : ',';

        // Parsear línea por línea desde el string ya convertido
        $lineas = array_filter(explode("\n", $contenido), fn($l) => trim($l) !== '');
        array_shift($lineas); // Quitar encabezados

        $schoolId = auth()->user()->current_school_id;

        // Cargar todos los cursos del colegio y pre-normalizar su nombre_fc
        $cursos = Curso::where('school_id', $schoolId)
            ->whereNotNull('nombre_fc')
            ->get()
            ->mapWithKeys(fn($c) => [$this->normalizarTexto($c->nombre_fc) => $c]);

        foreach ($lineas as $linea) {
            $cols = str_getcsv($linea, $separador);
            if (count($cols) < 3) {
                continue;
            }

            $rutRaw = $cols[1] ?? '';
            $rut = $this->parsearRut($rutRaw);
            $cursoRaw = $this->normalizarTexto($cols[2] ?? '');

            // Buscar en el mapa pre-normalizado (ambos lados usan la misma función)
            $curso = $cursos->get($cursoRaw);

            $nombreCompleto = $this->normalizarTexto($cols[0] ?? '');

            // Verificar si ya existe en BD
            $existe = Estudiante::where('school_id', $schoolId)->where('rut_numero', $rut['rut_numero'])->exists();

            $this->filas[] = [
                'nombre_completo' => $nombreCompleto,
                'rut_numero' => $rut['rut_numero'],
                'rut_dv' => $rut['rut_dv'],
                'curso_raw' => $cursoRaw,
                'curso_id' => $curso?->id,
                'curso_nombre' => $curso?->nombreCompleto(),
                'apoderado' => $this->normalizarTexto($cols[3] ?? ''),
                'apoderado_telefono' => $this->normalizarTexto($cols[4] ?? ''),
                'apoderado_email' => strtoupper(trim($cols[5] ?? '')),
                'apoderado_domicilio' => $this->normalizarTexto($cols[6] ?? ''),
                'estado' => $existe ? 'duplicado' : ($curso ? 'ok' : 'sin_curso'),
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

                Estudiante::create([
                    'school_id' => $schoolId,
                    'curso_id' => $fila['curso_id'],
                    'nombres_csv' => $fila['nombre_completo'],
                    'rut_numero' => $fila['rut_numero'],
                    'rut_dv' => $fila['rut_dv'],
                    'apoderado_nombres' => $fila['apoderado'],
                    'apoderado_telefono' => $fila['apoderado_telefono'],
                    'apoderado_email' => $fila['apoderado_email'],
                    'apoderado_domicilio' => $fila['apoderado_domicilio'],
                ]);

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
            <flux:breadcrumbs.item href="{{ route('estudiantes.index') }}">{{ __('Estudiantes') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('Carga Masiva') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="flex items-start justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ __('Importación Masiva de Estudiantes') }}</flux:heading>
                <flux:subheading size="lg">
                    {{ __('Sube el CSV exportado desde FullCollege para registrar estudiantes en lote.') }}
                </flux:subheading>
            </div>
            <flux:button href="{{ route('estudiantes.index') }}" variant="ghost" icon="arrow-left">
                {{ __('Volver') }}
            </flux:button>
        </div>
    </div>

    @if ($importado)
        {{-- Resultado final --}}
        <flux:card class="text-center py-12">
            <flux:icon.check-circle class="size-16 text-green-500 mx-auto mb-4" />
            <flux:heading size="xl">{{ __('¡Importación completada!') }}</flux:heading>
            <flux:subheading class="mt-2">
                {{ $totalOk }} {{ __('estudiantes importados correctamente.') }}
            </flux:subheading>
            <div class="mt-6 flex justify-center gap-4">
                <flux:button wire:click="reiniciar" variant="ghost">{{ __('Nueva importación') }}</flux:button>
                <flux:button href="{{ route('estudiantes.index') }}" variant="primary">{{ __('Ver estudiantes') }}
                </flux:button>
            </div>
        </flux:card>
    @elseif (!$previsualizando)
        {{-- Upload --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <flux:card class="lg:col-span-2">
                <div class="flex items-center gap-3 mb-6">
                    <div class="p-2 bg-zinc-100 dark:bg-zinc-800 rounded-lg">
                        <flux:icon.arrow-up-tray class="size-5 text-zinc-600 dark:text-zinc-300" />
                    </div>
                    <flux:heading size="lg">{{ __('Seleccionar archivo CSV') }}</flux:heading>
                </div>

                <flux:input wire:model="archivo" type="file" accept=".csv,.txt"
                    :label="__('Archivo CSV de FullCollege')"
                    :description="__('Formato: separado por punto y coma o coma. Máx. 5 MB.')" />
                <flux:error name="archivo" />

                <div wire:loading wire:target="archivo" class="mt-4 flex items-center gap-2 text-zinc-500 text-sm">
                    <flux:icon.arrow-path class="size-4 animate-spin" />
                    {{ __('Procesando archivo…') }}
                </div>
            </flux:card>

            <flux:card class="flex flex-col gap-4 bg-zinc-50 dark:bg-zinc-800/50">
                <flux:heading>{{ __('Columnas esperadas') }}</flux:heading>
                <div class="flex flex-col gap-2 text-sm text-zinc-600 dark:text-zinc-400">
                    @foreach ([['Nombres y Apellidos', '→ nombre estudiante'], ['R.U.T', '→ ej: 21258654-5'], ['Curso', '→ nombre en FullCollege'], ['Apoderado', '→ nombre apoderado'], ['Telefono Apoderado', '→ número'], ['Email Apoderado', '→ correo'], ['Domicilio Apoderado', '→ dirección']] as [$col, $desc])
                        <div class="flex justify-between gap-2">
                            <span
                                class="font-mono font-medium text-zinc-800 dark:text-zinc-200">{{ $col }}</span>
                            <span class="text-xs text-zinc-400">{{ $desc }}</span>
                        </div>
                    @endforeach
                </div>
                <flux:separator />
                <flux:text class="text-xs">
                    {{ __('Los cursos deben tener configurado el campo "Nombre FC" para que se puedan mapear correctamente.') }}
                </flux:text>
            </flux:card>
        </div>
    @else
        {{-- Previsualización --}}
        <div class="grid grid-cols-3 gap-4">
            <flux:card class="text-center">
                <div class="text-3xl font-bold text-zinc-800 dark:text-zinc-100">{{ count($filas) }}</div>
                <flux:text class="text-sm mt-1">{{ __('Filas encontradas') }}</flux:text>
            </flux:card>
            <flux:card class="text-center border-green-200 dark:border-green-800">
                <div class="text-3xl font-bold text-green-600">{{ $totalOk }}</div>
                <flux:text class="text-sm mt-1">{{ __('Listas para importar') }}</flux:text>
            </flux:card>
            <flux:card class="text-center border-red-200 dark:border-red-800">
                <div class="text-3xl font-bold text-red-500">{{ $totalError }}</div>
                <flux:text class="text-sm mt-1">{{ __('Con advertencias') }}</flux:text>
            </flux:card>
        </div>

        <flux:card>
            <div class="flex items-center justify-between mb-4">
                <flux:heading size="lg">{{ __('Previsualización') }}</flux:heading>
                <div class="flex gap-3">
                    <flux:button wire:click="reiniciar" variant="ghost" icon="x-mark">
                        {{ __('Cancelar') }}
                    </flux:button>
                    @if ($totalOk > 0)
                        <flux:button wire:click="importar" variant="primary" icon="check"
                            wire:confirm="{{ __('¿Confirmas la importación de ' . $totalOk . ' estudiantes?') }}">
                            {{ __('Importar') }} {{ $totalOk }} {{ __('registros') }}
                        </flux:button>
                    @endif
                </div>
            </div>

            <div class="overflow-x-auto">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Estado') }}</flux:table.column>
                        <flux:table.column>{{ __('Nombre Completo') }}</flux:table.column>
                        <flux:table.column>{{ __('RUT') }}</flux:table.column>
                        <flux:table.column>{{ __('Curso CSV') }}</flux:table.column>
                        <flux:table.column>{{ __('Curso Mapeado') }}</flux:table.column>
                        <flux:table.column>{{ __('Apoderado') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($filas as $fila)
                            <flux:table.row>
                                <flux:table.cell>
                                    @if ($fila['estado'] === 'ok')
                                        <flux:badge color="green" icon="check-circle">{{ __('OK') }}</flux:badge>
                                    @elseif ($fila['estado'] === 'duplicado')
                                        <flux:badge color="yellow" icon="exclamation-triangle">{{ __('Ya existe') }}
                                        </flux:badge>
                                    @else
                                        <flux:badge color="red" icon="x-circle">{{ __('Sin curso') }}</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="font-medium">{{ $fila['nombre_completo'] }}</flux:table.cell>
                                <flux:table.cell class="font-mono text-sm">
                                    {{ $fila['rut_numero'] }}-{{ $fila['rut_dv'] }}
                                </flux:table.cell>
                                <flux:table.cell class="text-xs text-zinc-500">{{ $fila['curso_raw'] }}
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($fila['curso_nombre'])
                                        <flux:badge color="blue">{{ $fila['curso_nombre'] }}</flux:badge>
                                    @else
                                        <span class="text-red-400 text-xs">{{ __('Sin mapeo') }}</span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="text-sm text-zinc-600 dark:text-zinc-400">
                                    {{ $fila['apoderado'] }}
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        </flux:card>
    @endif
</div>
