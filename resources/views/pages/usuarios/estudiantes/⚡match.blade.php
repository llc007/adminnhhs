<?php

use App\Models\Estudiante;
use App\Services\StudentMatchService;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public $csvFile;

    public $matches = [];

    public $procesado = false;

    public function updatedCsvFile()
    {
        $this->procesarCsv();
    }

    public function procesarCsv()
    {
        $this->validate([
            'csvFile' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
        ]);

        $path = $this->csvFile->getRealPath();
        $file = fopen($path, 'r');

        // Leer los encabezados
        $headers = fgetcsv($file);

        // Mapear posiciones de columnas por nombre
        $colIndex = [];
        foreach ($headers as $index => $header) {
            $colIndex[trim($header)] = $index;
        }

        // Verificar columnas requeridas
        $firstCol = $colIndex['First Name [Required]'] ?? ($colIndex['First Name'] ?? null);
        $lastCol = $colIndex['Last Name [Required]'] ?? ($colIndex['Last Name'] ?? null);
        $emailCol = $colIndex['Email Address [Required]'] ?? ($colIndex['Email Address'] ?? null);
        $orgCol = $colIndex['Org Unit Path [Required]'] ?? ($colIndex['Org Unit Path'] ?? null);

        if ($firstCol === null || $lastCol === null || $emailCol === null || $orgCol === null) {
            Flux::toast('El archivo CSV no tiene el formato esperado de Google Workspace.', variant: 'danger');

            return;
        }

        $service = new StudentMatchService;
        $resultados = [];

        // Para evitar múltiples consultas a BD, cargamos todos los estudiantes que AÚN NO tienen correo
        $estudiantesBd = Estudiante::with('curso')->whereNull('email')->get();
        // Obtenemos los correos ya vinculados para ignorarlos en el CSV
        $correosVinculados = Estudiante::whereNotNull('email')->pluck('email')->toArray();

        while (($row = fgetcsv($file)) !== false) {
            // Validar que la fila tenga datos
            if (! isset($row[$firstCol]) || ! isset($row[$lastCol]) || ! isset($row[$emailCol])) {
                continue;
            }

            $firstName = $row[$firstCol];
            $lastName = $row[$lastCol];
            $email = trim($row[$emailCol]);
            $orgPath = trim($row[$orgCol] ?? '');

            if (empty($firstName) || empty($lastName) || empty($email)) {
                continue;
            }

            // Si el correo ya está vinculado en la base de datos, lo saltamos
            if (in_array($email, $correosVinculados)) {
                continue;
            }

            $cursoIdCsv = $service->getCursoIdFromOrgUnitPath($orgPath);

            $bestMatch = null;
            $highestSimilarity = 0;

            if ($cursoIdCsv) {
                // Filtramos estudiantes solo del mismo curso
                $estudiantesCurso = $estudiantesBd->where('curso_id', $cursoIdCsv);

                foreach ($estudiantesCurso as $estudiante) {
                    $similarity = $service->calculateSimilarity($firstName, $lastName, $estudiante->nombres_csv);

                    if ($similarity > $highestSimilarity) {
                        $highestSimilarity = $similarity;
                        $bestMatch = $estudiante;
                    }
                }
            } else {
                // Si no se detectó el curso, comparamos con toda la base
                foreach ($estudiantesBd as $estudiante) {
                    $similarity = $service->calculateSimilarity($firstName, $lastName, $estudiante->nombres_csv);

                    if ($similarity > $highestSimilarity) {
                        $highestSimilarity = $similarity;
                        $bestMatch = $estudiante;
                    }
                }
            }

            // Determinar estado basado en similitud
            $estado = 'No Encontrado';
            $color = 'zinc';

            if ($highestSimilarity >= 95) {
                $estado = 'Match Seguro';
                $color = 'emerald';
            } elseif ($highestSimilarity >= 50) {
                $estado = 'Sugerencia';
                $color = 'amber';
            } else {
                $estado = 'No Encontrado'; // Too low
                $color = 'red';
            }

            $resultados[] = [
                'csv_first_name' => $firstName,
                'csv_last_name' => $lastName,
                'email' => $email,
                'org_path' => $orgPath,
                'estudiante_id' => $bestMatch ? $bestMatch->id : null,
                'estudiante_nombre' => $bestMatch ? $bestMatch->nombres_csv : 'Ninguno',
                'estudiante_curso' => $bestMatch && $bestMatch->curso ? $bestMatch->curso->nombreCompleto() : '-',
                'similitud' => round($highestSimilarity, 2),
                'estado' => $estado,
                'color' => $color,
            ];
        }

        fclose($file);

        $this->matches = collect($resultados)->sortByDesc('similitud')->values()->toArray();
        $this->procesado = true;
    }

    public function vincularCuentasSeguras()
    {
        $count = 0;

        DB::transaction(function () use (&$count) {
            foreach ($this->matches as $match) {
                if ($match['estado'] === 'Match Seguro' && $match['estudiante_id']) {
                    $estudiante = Estudiante::find($match['estudiante_id']);
                    if ($estudiante) {
                        $estudiante->update([
                            'email' => $match['email'],
                        ]);
                        $count++;
                    }
                }
            }
        });

        $this->procesado = false;
        $this->matches = [];
        $this->csvFile = null;

        Flux::toast("Se han vinculado {$count} cuentas de forma exitosa.", variant: 'success');
    }

    public function vincularManual($estudianteId, $email)
    {
        $estudiante = Estudiante::find($estudianteId);
        if ($estudiante) {
            $estudiante->update([
                'email' => $email,
            ]);

            // Remover de la lista
            $this->matches = array_filter($this->matches, function ($match) use ($email) {
                return $match['email'] !== $email;
            });

            Flux::toast("Vinculación de correo {$email} realizada.", variant: 'success');
        }
    }
};
?>

<div class="max-w-7xl mx-auto w-full pb-12 space-y-8">
    <!-- Page Header -->
    <x-header 
        :titulo="__('Vinculación de Cuentas Google Workspace')"
        :subtitulo="__('Carga el archivo CSV de usuarios descargado de Google Workspace para enlazar los correos institucionales a los estudiantes de nuestra plataforma.')"
        icono="envelope"
    >
        <flux:button href="{{ route('estudiantes.index') }}" variant="ghost" icon="arrow-left">
            {{ __('Volver a Estudiantes') }}
        </flux:button>
    </x-header>

    <div class="flex flex-col gap-6">
        <!-- Zona de Carga -->
        <flux:card
            class="shadow-sm border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40">
            <div class="mb-6">
                <flux:heading size="lg" class="flex items-center gap-2">
                    <flux:icon.arrow-up-tray class="size-5 text-[#00376e] dark:text-blue-400" />
                    Subir CSV
                </flux:heading>
                <flux:text class="mt-2 text-sm">Asegúrate de que el archivo CSV mantenga las columnas "First Name",
                    "Last Name", "Email Address" y "Org Unit Path".</flux:text>
            </div>

            <div class="space-y-4">
                <flux:field>
                    <div
                        class="relative group cursor-pointer border-2 border-dashed border-zinc-300 dark:border-zinc-600 rounded-xl p-8 hover:bg-zinc-100 dark:hover:bg-zinc-700/50 transition-colors text-center">
                        <input type="file" wire:model.live="csvFile" accept=".csv"
                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" />
                        <flux:icon.document-arrow-up
                            class="size-8 mx-auto text-zinc-400 mb-3 group-hover:text-[#00376e] dark:group-hover:text-blue-400 transition-colors" />

                        <div wire:loading wire:target="csvFile" class="text-sm font-medium text-blue-600">
                            Cargando archivo y procesando coincidencias...
                        </div>
                        <div wire:loading.remove wire:target="csvFile">
                            @if ($csvFile)
                                <p class="text-sm font-medium text-[#00376e] dark:text-blue-400">
                                    {{ $csvFile->getClientOriginalName() }}</p>
                            @else
                                <p class="text-sm font-medium text-zinc-600 dark:text-zinc-300">Haz clic o arrastra tu
                                    CSV aquí</p>
                            @endif
                        </div>
                    </div>
                    <flux:error name="csvFile" />
                </flux:field>
            </div>

            @if ($procesado)
                <div class="mt-6 border-t border-zinc-200 dark:border-zinc-700 pt-6">
                    <div class="bg-blue-50 dark:bg-blue-900/30 p-4 rounded-lg flex items-start gap-3">
                        <flux:icon.information-circle class="size-5 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5" />
                        <div class="text-sm text-blue-800 dark:text-blue-200">
                            <p class="font-bold mb-1">Carga Finalizada</p>
                            <p>Revisa la tabla de coincidencias. Puedes realizar vinculaciones manuales individuales o
                                vincular todos los matches seguros con un solo clic.</p>
                        </div>
                    </div>
                    <flux:button wire:click="vincularCuentasSeguras" variant="primary" class="w-full mt-4"
                        icon="check-badge">
                        Vincular Matches Seguros
                    </flux:button>
                </div>
            @endif
        </flux:card>

        <!-- Tabla de Matches -->
        <flux:card class="overflow-hidden shadow-sm">
            @if (empty($matches))
                <div class="py-24 text-center">
                    <flux:icon.table-cells class="size-12 mx-auto text-zinc-300 dark:text-zinc-700 mb-4" />
                    <flux:heading size="lg" class="text-zinc-500">Sin Datos para Mostrar</flux:heading>
                    <flux:text class="mt-2 text-zinc-400 max-w-sm mx-auto">Sube un archivo CSV de Google Workspace para
                        visualizar el cruce de datos con el sistema.</flux:text>
                </div>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Datos CSV</flux:table.column>
                        <flux:table.column>Match Sistema</flux:table.column>
                        <flux:table.column>Estado / Similitud</flux:table.column>
                        <flux:table.column class="text-right">Acción</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($matches as $match)
                            <flux:table.row>
                                <!-- Datos CSV -->
                                <flux:table.cell>
                                    <p class="text-xs font-bold text-zinc-900 dark:text-zinc-100 uppercase">
                                        {{ $match['csv_last_name'] }} {{ $match['csv_first_name'] }}</p>
                                    <p class="text-[10px] text-blue-600 dark:text-blue-400 mt-0.5 font-medium">
                                        {{ $match['email'] }}</p>
                                    <p class="text-[10px] text-zinc-500 mt-1">{{ $match['org_path'] }}</p>
                                </flux:table.cell>

                                <!-- Match en BD -->
                                <flux:table.cell>
                                    @if ($match['estudiante_id'])
                                        <p class="text-xs font-bold text-zinc-900 dark:text-zinc-100 uppercase">
                                            {{ $match['estudiante_nombre'] }}</p>
                                        <p class="text-[10px] text-zinc-500 mt-1 font-medium">Curso:
                                            <span class="text-blue-600 dark:text-blue-400">{{ $match['estudiante_curso'] }}</span>
                                        </p>
                                    @else
                                        <p class="text-xs italic text-zinc-500">Ningún estudiante cercano encontrado</p>
                                    @endif
                                </flux:table.cell>

                                <!-- Estado y Similitud -->
                                <flux:table.cell>
                                    <div class="flex flex-col gap-2">
                                        <flux:badge color="{{ $match['color'] }}" size="sm" class="w-fit">
                                            {{ $match['estado'] }}</flux:badge>
                                        <div class="flex items-center gap-2">
                                            <div class="w-20 bg-zinc-200 dark:bg-zinc-700 rounded-full h-1.5">
                                                <div class="bg-{{ $match['color'] }}-500 h-1.5 rounded-full"
                                                    style="width: {{ $match['similitud'] }}%"></div>
                                            </div>
                                            <span
                                                class="text-[10px] font-bold text-zinc-500">{{ $match['similitud'] }}%</span>
                                        </div>
                                    </div>
                                </flux:table.cell>

                                <!-- Acciones -->
                                <flux:table.cell class="text-right whitespace-nowrap">
                                    @if ($match['estudiante_id'] && $match['estado'] !== 'No Encontrado')
                                        <flux:button size="sm" variant="filled" class="bg-blue-100 text-blue-700 hover:bg-blue-200 dark:bg-blue-900/40 dark:text-blue-300 dark:hover:bg-blue-800/50"
                                            wire:click="vincularManual({{ $match['estudiante_id'] }}, '{{ $match['email'] }}')">
                                            Vincular Manual
                                        </flux:button>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>
    </div>
</div>
