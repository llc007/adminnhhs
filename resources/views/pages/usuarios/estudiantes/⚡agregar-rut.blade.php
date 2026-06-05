<?php

use App\Models\Curso;
use App\Models\Estudiante;
use App\Services\StudentMatchService;
use Flux\Flux;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public $googleCsv;

    public $fullcollegeCsv;

    public $matches = [];

    public $procesado = false;

    public $googleHeaders = [];

    public $unmappedFcCourses = [];

    public $mapeosPendientes = [];

    #[Computed]
    public function getTodosLosCursosProperty()
    {
        return Curso::orderBy('modalidad')->orderBy('nivel')->orderBy('letra')->get();
    }

    public function updatedMapeosPendientes($cursoId, $encodedRawName)
    {
        if (! $cursoId) {
            return;
        }

        $rawName = base64_decode($encodedRawName);
        $curso = Curso::find($cursoId);

        if ($curso) {
            $curso->update(['nombre_fc' => $rawName]);
            Flux::toast('Curso mapeado correctamente a '.$curso->nombreCompleto(), variant: 'success');
            $this->procesar();
        }
    }

    public function procesar()
    {
        $this->unmappedFcCourses = [];

        $this->validate([
            'googleCsv' => 'required|file|mimes:csv,txt|max:10240',
            'fullcollegeCsv' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $fcPath = $this->fullcollegeCsv->getRealPath();
        $fcFile = fopen($fcPath, 'r');

        // Detectar si es punto y coma o coma
        $firstLine = fgets($fcFile);
        $delimiter = strpos($firstLine, ';') !== false ? ';' : ',';
        rewind($fcFile);

        $fcHeaders = fgetcsv($fcFile, 0, $delimiter);

        $fcColIndex = [];
        foreach ($fcHeaders as $index => $header) {
            // Limpiar posible BOM de UTF-8 que suele colocar Excel
            $cleanHeader = preg_replace('/^\xEF\xBB\xBF/', '', trim($header));
            $fcColIndex[$cleanHeader] = $index;
        }

        $fcNameCol = $fcColIndex['Nombres y Apeliidos'] ?? ($fcColIndex['Nombres y Apellidos'] ?? null);
        $fcRutCol = $fcColIndex['R.U.T'] ?? null;
        $fcCursoCol = $fcColIndex['Curso'] ?? null;

        if ($fcNameCol === null || $fcRutCol === null || $fcCursoCol === null) {
            Flux::toast('El archivo Fullcollege no tiene las columnas esperadas. Detectadas: '.implode(', ', array_keys($fcColIndex)), variant: 'danger');

            return;
        }

        $fcStudents = [];
        while (($row = fgetcsv($fcFile, 0, $delimiter)) !== false) {
            if (! isset($row[$fcNameCol])) {
                continue;
            }

            $rutFull = trim($row[$fcRutCol]);
            $rutSinDv = explode('-', $rutFull)[0];
            $rutSinDv = str_replace('.', '', $rutSinDv);

            $curso = $this->normalizeFullcollegeCurso($row[$fcCursoCol] ?? '');

            if (str_starts_with($curso, 'UNKNOWN_FC_')) {
                $rawName = substr($curso, 11);
                $this->unmappedFcCourses[$rawName] = true;
            }

            $fullName = $this->normalizeString($row[$fcNameCol] ?? '');

            $fcStudents[$curso][] = [
                'rut_full' => $rutFull,
                'rut_sin_dv' => $rutSinDv,
                'full_name' => $fullName,
                'original_name' => trim($row[$fcNameCol]),
                'matched' => false,
            ];
        }
        fclose($fcFile);

        $googlePath = $this->googleCsv->getRealPath();
        $googleFile = fopen($googlePath, 'r');
        $this->googleHeaders = fgetcsv($googleFile);

        $gColIndex = [];
        foreach ($this->googleHeaders as $index => $header) {
            $gColIndex[trim($header)] = $index;
        }

        $gFirstCol = $gColIndex['First Name [Required]'] ?? ($gColIndex['First Name'] ?? null);
        $gLastCol = $gColIndex['Last Name [Required]'] ?? ($gColIndex['Last Name'] ?? null);
        $gEmailCol = $gColIndex['Email Address [Required]'] ?? ($gColIndex['Email Address'] ?? null);
        $gOrgCol = $gColIndex['Org Unit Path [Required]'] ?? ($gColIndex['Org Unit Path'] ?? null);
        $gEmpIdCol = $gColIndex['Employee ID'] ?? null;

        // Si Employee ID no existe, lo agregamos a los headers
        if ($gEmpIdCol === null) {
            $this->googleHeaders[] = 'Employee ID';
            $gEmpIdCol = count($this->googleHeaders) - 1;
        }

        if ($gFirstCol === null || $gLastCol === null || $gEmailCol === null || $gOrgCol === null) {
            Flux::toast('El archivo Google no tiene las columnas esperadas.', variant: 'danger');

            return;
        }

        $this->matches = [];

        while (($row = fgetcsv($googleFile)) !== false) {
            if (! isset($row[$gFirstCol])) {
                continue;
            }

            // Ajustar el largo de la fila al de los headers si es que agregamos Employee ID
            while (count($row) < count($this->googleHeaders)) {
                $row[] = '';
            }

            $firstName = $this->normalizeString($row[$gFirstCol]);
            $lastName = $this->normalizeString($row[$gLastCol]);
            $email = trim($row[$gEmailCol]);
            $orgPath = trim($row[$gOrgCol] ?? '');

            $curso = $this->normalizeGoogleCurso($orgPath);

            $bestMatch = null;
            $highestSimilarity = 0;

            if (isset($fcStudents[$curso])) {
                foreach ($fcStudents[$curso] as $fcIndex => $fcStudent) {
                    if ($fcStudent['matched']) {
                        continue;
                    }

                    $googleCombined = $lastName.' '.$firstName;

                    similar_text($googleCombined, $fcStudent['full_name'], $percent);

                    if ($percent > $highestSimilarity) {
                        $highestSimilarity = $percent;
                        $bestMatch = $fcIndex;
                    }
                }
            }

            if ($bestMatch !== null && $highestSimilarity >= 70) {
                $fcStudents[$curso][$bestMatch]['matched'] = true;

                // Set Employee ID
                $row[$gEmpIdCol] = $fcStudents[$curso][$bestMatch]['rut_sin_dv'];

                $this->matches[] = [
                    'google_first_name' => trim($row[$gFirstCol]),
                    'google_last_name' => trim($row[$gLastCol]),
                    'email' => $email,
                    'fc_name' => $fcStudents[$curso][$bestMatch]['original_name'],
                    'rut_full' => $fcStudents[$curso][$bestMatch]['rut_full'],
                    'rut_sin_dv' => $fcStudents[$curso][$bestMatch]['rut_sin_dv'],
                    'similitud' => round($highestSimilarity, 2),
                    'curso' => $curso,
                    'status' => 'success',
                    'row' => $row,
                ];
            } else {
                $this->matches[] = [
                    'google_first_name' => trim($row[$gFirstCol]),
                    'google_last_name' => trim($row[$gLastCol]),
                    'email' => $email,
                    'fc_name' => '-',
                    'rut_full' => '-',
                    'rut_sin_dv' => '',
                    'similitud' => round($highestSimilarity, 2),
                    'curso' => $curso,
                    'status' => 'error',
                    'row' => $row,
                ];
            }
        }
        fclose($googleFile);

        $this->procesado = true;
        Flux::toast('Análisis completado.', variant: 'success');
    }

    public function aceptarYDescargar()
    {
        if (empty($this->matches)) {
            return;
        }

        $csvData = implode(',', $this->googleHeaders)."\n";

        foreach ($this->matches as $match) {
            // Guardar en la base de datos si fue un match exitoso
            if ($match['status'] === 'success') {
                Estudiante::where('rut_numero', $match['rut_sin_dv'])->update([
                    'email' => $match['email'],
                ]);
            }

            // Preparar fila CSV
            $rowLine = array_map(function ($field) {
                // Escapar comillas dobles y encerrar en comillas si hay comas
                if (strpos($field, ',') !== false || strpos($field, '"') !== false) {
                    return '"'.str_replace('"', '""', $field).'"';
                }

                return $field;
            }, $match['row']);

            $csvData .= implode(',', $rowLine)."\n";
        }

        $fileName = 'Google_Users_Updated_'.now()->format('Ymd_His').'.csv';

        Flux::toast('Se actualizaron los correos en la base de datos y se generó el archivo.', variant: 'success');

        return response()->streamDownload(function () use ($csvData) {
            echo $csvData;
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function normalizeGoogleCurso($path)
    {
        $service = new StudentMatchService;
        $id = $service->getCursoIdFromOrgUnitPath($path);

        if ($id) {
            return (string) $id;
        }

        return 'UNKNOWN_GOOGLE_'.$path;
    }

    private function normalizeFullcollegeCurso($cursoFc)
    {
        $curso = Curso::where('nombre_fc', trim($cursoFc))->first();
        if ($curso) {
            return (string) $curso->id;
        }

        // Si no lo encuentra por nombre_fc, intentamos parsearlo manualmente
        $cursoFcUpper = mb_strtoupper((string) $cursoFc, 'UTF-8');
        if (preg_match('/(\d+)\s*(MEDIO|BASICO|BÁSICO)\s*([A-Z])/', $cursoFcUpper, $matches)) {
            $nivel = (int) $matches[1];
            $modalidad = ($matches[2] === 'MEDIO') ? 'media' : 'basica';
            $letra = $matches[3];

            $cursoObj = Curso::where('nivel', $nivel)
                ->where('modalidad', $modalidad)
                ->where('letra', $letra)
                ->first();

            if ($cursoObj) {
                return (string) $cursoObj->id;
            }
        }

        return 'UNKNOWN_FC_'.$cursoFc;
    }

    private function normalizeString($str)
    {
        $str = mb_strtoupper((string) $str, 'UTF-8');
        $unwanted_array = ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N', 'Ä' => 'A', 'Ë' => 'E', 'Ï' => 'I', 'Ö' => 'O', 'Ü' => 'U'];
        $str = strtr($str, $unwanted_array);
        $str = preg_replace('/[^A-Z\s]/', '', $str); // Remover puntuación si la hubiera
        $str = preg_replace('/\s+/', ' ', $str);

        return trim($str);
    }
};
?>

<div class="max-w-7xl mx-auto w-full pb-12 space-y-8">
    <x-header 
        :titulo="__('Enlazar RUTs a Google Workspace')"
        :subtitulo="__('Cruce de datos entre el CSV de Google Workspace y el de Fullcollege para asignar el RUT (Employee ID).')"
        icono="arrows-right-left"
    >
        <flux:button href="{{ route('estudiantes.index') }}" variant="ghost" icon="arrow-left">
            {{ __('Volver a Estudiantes') }}
        </flux:button>
    </x-header>

    <div class="flex flex-col gap-6">
        <flux:card class="shadow-sm border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40">
            <div class="mb-6">
                <flux:heading size="lg">Subir Archivos CSV</flux:heading>
                <flux:text class="mt-2 text-sm">Sube ambos archivos para cruzar la información.</flux:text>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Google CSV -->
                <flux:field>
                    <flux:label class="mb-2 font-bold text-[#00376e] dark:text-blue-400">CSV Google Workspace</flux:label>
                    <div
                        class="relative group cursor-pointer border-2 border-dashed border-zinc-300 dark:border-zinc-600 rounded-xl p-6 hover:bg-zinc-100 dark:hover:bg-zinc-700/50 transition-colors text-center">
                        <input type="file" wire:model.live="googleCsv" accept=".csv"
                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" />
                        <flux:icon.document-arrow-up
                            class="size-8 mx-auto text-zinc-400 mb-2 group-hover:text-[#00376e] dark:group-hover:text-blue-400 transition-colors" />

                        <div wire:loading wire:target="googleCsv" class="text-sm font-medium text-blue-600">
                            Cargando Google CSV...
                        </div>
                        <div wire:loading.remove wire:target="googleCsv">
                            @if ($googleCsv)
                                <p class="text-sm font-medium text-[#00376e] dark:text-blue-400">
                                    {{ $googleCsv->getClientOriginalName() }}</p>
                            @else
                                <p class="text-sm font-medium text-zinc-600 dark:text-zinc-300">Selecciona el CSV de Google</p>
                            @endif
                        </div>
                    </div>
                    <flux:error name="googleCsv" />
                </flux:field>

                <!-- Fullcollege CSV -->
                <flux:field>
                    <flux:label class="mb-2 font-bold text-[#00376e] dark:text-blue-400">CSV Fullcollege</flux:label>
                    <div
                        class="relative group cursor-pointer border-2 border-dashed border-zinc-300 dark:border-zinc-600 rounded-xl p-6 hover:bg-zinc-100 dark:hover:bg-zinc-700/50 transition-colors text-center">
                        <input type="file" wire:model.live="fullcollegeCsv" accept=".csv"
                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" />
                        <flux:icon.document-arrow-up
                            class="size-8 mx-auto text-zinc-400 mb-2 group-hover:text-[#00376e] dark:group-hover:text-blue-400 transition-colors" />

                        <div wire:loading wire:target="fullcollegeCsv" class="text-sm font-medium text-blue-600">
                            Cargando Fullcollege CSV...
                        </div>
                        <div wire:loading.remove wire:target="fullcollegeCsv">
                            @if ($fullcollegeCsv)
                                <p class="text-sm font-medium text-[#00376e] dark:text-blue-400">
                                    {{ $fullcollegeCsv->getClientOriginalName() }}</p>
                            @else
                                <p class="text-sm font-medium text-zinc-600 dark:text-zinc-300">Selecciona el CSV de Fullcollege</p>
                            @endif
                        </div>
                    </div>
                    <flux:error name="fullcollegeCsv" />
                </flux:field>
            </div>

            @if($googleCsv && $fullcollegeCsv && !$procesado)
                <div class="mt-6 flex justify-end">
                    <flux:button wire:click="procesar" variant="primary" icon="play">
                        Procesar y Analizar Cruce
                    </flux:button>
                </div>
            @endif

            @if ($procesado)
                @if (!empty($unmappedFcCourses))
                    <div class="mt-6 border-t border-zinc-200 dark:border-zinc-700 pt-6">
                        <div class="bg-orange-50 dark:bg-orange-900/30 p-5 rounded-lg border border-orange-200 dark:border-orange-800">
                            <div class="flex items-start gap-3 mb-4">
                                <flux:icon.exclamation-triangle class="size-5 text-orange-600 dark:text-orange-400 shrink-0 mt-0.5" />
                                <div class="text-sm text-orange-800 dark:text-orange-200">
                                    <p class="font-bold text-base mb-1">Cursos de Fullcollege No Reconocidos</p>
                                    <p>Algunos estudiantes vienen con nombres de cursos que no coinciden con nuestra base de datos. Selecciona a qué curso corresponde cada uno; la base de datos se actualizará y los alumnos se enlazarán solos.</p>
                                </div>
                            </div>
                            
                            <div class="space-y-3 mt-4">
                                @foreach ($unmappedFcCourses as $rawName => $dummy)
                                    <div class="flex items-center gap-4 bg-white dark:bg-zinc-800 p-3 rounded border border-orange-100 dark:border-zinc-700">
                                        <div class="font-bold text-sm text-zinc-700 dark:text-zinc-300 w-1/3 break-words">{{ $rawName }}</div>
                                        <div class="w-2/3">
                                            <flux:select wire:model.live="mapeosPendientes.{{ base64_encode($rawName) }}" placeholder="Seleccionar Curso correcto...">
                                                <flux:select.option value="" disabled selected>Selecciona el curso</flux:select.option>
                                                @foreach($this->todosLosCursos as $curso)
                                                    <flux:select.option value="{{ $curso->id }}">{{ $curso->nombreCompleto() }}</flux:select.option>
                                                @endforeach
                                            </flux:select>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                <div class="mt-6 border-t border-zinc-200 dark:border-zinc-700 pt-6">
                    <div class="bg-blue-50 dark:bg-blue-900/30 p-4 rounded-lg flex items-start gap-3">
                        <flux:icon.information-circle class="size-5 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5" />
                        <div class="text-sm text-blue-800 dark:text-blue-200">
                            <p class="font-bold mb-1">Análisis Finalizado</p>
                            <p>Revisa la tabla de coincidencias. Si estás de acuerdo, presiona "Aceptar y Descargar" para actualizar la BD del sistema y obtener el nuevo CSV para subir a Google Workspace.</p>
                        </div>
                    </div>
                    <flux:button wire:click="aceptarYDescargar" variant="primary" class="w-full mt-4"
                        icon="arrow-down-tray" :disabled="!empty($unmappedFcCourses)">
                        Aceptar Cambios y Descargar CSV Google
                    </flux:button>
                </div>
            @endif
        </flux:card>

        @if($procesado)
            <flux:card class="overflow-hidden shadow-sm">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Google Workspace</flux:table.column>
                        <flux:table.column>Match Fullcollege</flux:table.column>
                        <flux:table.column>RUT Asignado</flux:table.column>
                        <flux:table.column>Estado / Similitud</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($matches as $match)
                            <flux:table.row>
                                <!-- Google -->
                                <flux:table.cell>
                                    <p class="text-xs font-bold text-zinc-900 dark:text-zinc-100 uppercase">
                                        {{ $match['google_last_name'] }} {{ $match['google_first_name'] }}</p>
                                    <p class="text-[10px] text-blue-600 dark:text-blue-400 mt-0.5 font-medium">
                                        {{ $match['email'] }}</p>
                                    <p class="text-[10px] text-zinc-500 mt-1">Curso Normalizado: {{ $match['curso'] }}</p>
                                </flux:table.cell>

                                <!-- Fullcollege -->
                                <flux:table.cell>
                                    @if ($match['status'] === 'success')
                                        <p class="text-xs font-bold text-zinc-900 dark:text-zinc-100 uppercase">
                                            {{ $match['fc_name'] }}</p>
                                    @else
                                        <p class="text-xs italic text-zinc-500">Ningún estudiante cercano encontrado</p>
                                    @endif
                                </flux:table.cell>

                                <!-- RUT -->
                                <flux:table.cell>
                                    @if ($match['status'] === 'success')
                                        <flux:badge color="blue">{{ $match['rut_full'] }}</flux:badge>
                                        <p class="text-[10px] text-zinc-500 mt-1">Employee ID: <span class="font-bold text-zinc-700 dark:text-zinc-300">{{ $match['rut_sin_dv'] }}</span></p>
                                    @else
                                        <span class="text-zinc-400">-</span>
                                    @endif
                                </flux:table.cell>

                                <!-- Estado y Similitud -->
                                <flux:table.cell>
                                    <div class="flex flex-col gap-2">
                                        <flux:badge color="{{ $match['status'] === 'success' ? 'emerald' : 'red' }}" size="sm" class="w-fit">
                                            {{ $match['status'] === 'success' ? 'Encontrado' : 'No Encontrado' }}
                                        </flux:badge>
                                        <div class="flex items-center gap-2">
                                            <div class="w-20 bg-zinc-200 dark:bg-zinc-700 rounded-full h-1.5">
                                                <div class="bg-{{ $match['status'] === 'success' ? 'emerald' : 'red' }}-500 h-1.5 rounded-full"
                                                    style="width: {{ min(100, $match['similitud']) }}%"></div>
                                            </div>
                                            <span
                                                class="text-[10px] font-bold text-zinc-500">{{ $match['similitud'] }}%</span>
                                        </div>
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        @endif
    </div>
</div>