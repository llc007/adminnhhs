<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Title;
use App\Models\Estudiante;
use App\Models\Curso;
use Flux\Flux;
use Illuminate\Support\Facades\DB;

new #[Title('Sincronización de Correos')] class extends Component {
    use WithFileUploads;

    public int $paso = 1;

    // Archivos (FullCollege admite múltiples archivos XLSX/CSV: Básica + Media)
    public array $archivosFullCollege = [];
    public $archivoGoogle = null;

    // Rutas temporales almacenadas
    public array $fcPaths = [];
    public array $fcFileNames = [];
    public int $fcTotalFilas = 0;

    public string $googlePath = '';
    public string $googleFileName = '';
    public int $googleTotalFilas = 0;

    // Datos de Previsualización (Solo 5 filas)
    public array $fcHeaders = [];
    public array $fcPreviewRows = [];
    public array $fcColumnasActivas = [];
    public string $fcRutCol = '';

    public array $googleHeaders = [];
    public array $googlePreviewRows = [];
    public array $googleColumnasActivas = [];
    public string $googleRutCol = '';

    // Cursos disponibles en el colegio
    public array $listaCursos = [];

    // Estadísticas detalladas de diagnóstico
    public int $fcRutsUnicosCount = 0;
    public int $fcDuplicadosOEncabezadosCount = 0;
    public int $nuevosCreadosAutoCount = 0;

    // Resultados del Cruce
    public array $resultados = [
        'sin_correo' => [],
        'retirados' => [],
        'coincidentes' => [],
    ];

    public array $correosForm = []; // Inputs manuales de correo
    public array $cursosForm = [];  // Inputs manuales de curso

    // Selección interactiva de retirados
    public array $retiradosSeleccionados = [];
    public bool $marcarTodosRetiradosCheck = true;

    public function mount(): void
    {
        $schoolId = auth()->user()->current_school_id;
        $query = Curso::query();
        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }

        $this->listaCursos = $query->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'nombre' => $c->nombreCompleto(),
                'nombre_fc' => $c->nombre_fc,
            ])
            ->sortBy('nombre')
            ->values()
            ->toArray();
    }

    public function updatedArchivosFullCollege(): void
    {
        $this->validate([
            'archivosFullCollege.*' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:20480'],
        ]);

        $this->fcHeaders = [];
        $this->fcPreviewRows = [];
        $this->fcPaths = [];
        $this->fcFileNames = [];
        $this->fcTotalFilas = 0;

        foreach ($this->archivosFullCollege as $archivo) {
            $ext = strtolower($archivo->getClientOriginalExtension());
            $path = $archivo->getRealPath();
            
            $data = $this->parsearArchivo($path, $ext, 10);

            if (empty($data['headers'])) {
                continue;
            }

            if (empty($this->fcHeaders)) {
                $this->fcHeaders = $data['headers'];
            }

            if (count($this->fcPreviewRows) < 5) {
                $this->fcPreviewRows = array_merge($this->fcPreviewRows, array_slice($data['rows'], 0, 5 - count($this->fcPreviewRows)));
            }

            $this->fcPaths[] = [
                'path' => $path,
                'ext' => $ext,
                'name' => $archivo->getClientOriginalName(),
            ];
            $this->fcFileNames[] = $archivo->getClientOriginalName();
            $this->fcTotalFilas += $data['total_rows'];
        }

        $this->fcColumnasActivas = array_fill_keys(array_keys($this->fcHeaders), true);
        $this->fcRutCol = (string) $this->autoDetectarColumnaRut($this->fcHeaders);

        Flux::toast(count($this->fcFileNames) . ' archivo(s) de FullCollege cargados (' . $this->fcTotalFilas . ' filas).', variant: 'success');
    }

    public function updatedArchivoGoogle(): void
    {
        $this->validate([
            'archivoGoogle' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:20480'],
        ]);

        $ext = strtolower($this->archivoGoogle->getClientOriginalExtension());
        $path = $this->archivoGoogle->getRealPath();
        
        $data = $this->parsearArchivo($path, $ext, 5);

        $this->googleHeaders = $data['headers'];
        $this->googlePreviewRows = $data['rows'];
        $this->googleColumnasActivas = array_fill_keys(array_keys($data['headers']), true);
        $this->googleFileName = $this->archivoGoogle->getClientOriginalName();
        $this->googlePath = $path;
        $this->googleTotalFilas = $data['total_rows'];

        $this->googleRutCol = (string) $this->autoDetectarColumnaRut($data['headers']);

        Flux::toast('Archivo Admin Google cargado correctamente (' . $this->googleTotalFilas . ' filas).', variant: 'success');
    }

    public function updatedMarcarTodosRetiradosCheck(bool $value): void
    {
        foreach ($this->resultados['retirados'] as $item) {
            if (!empty($item['rut_numero'])) {
                $this->retiradosSeleccionados[$item['rut_numero']] = $value;
            }
        }
    }

    private function parsearArchivo(string $path, string $extension, ?int $limitRows = null): array
    {
        if ($extension === 'xlsx') {
            return $this->parsearXlsx($path, $limitRows);
        }

        return $this->parsearCsv($path, $limitRows);
    }

    private function parsearXlsx(string $path, ?int $limitRows = null): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return ['headers' => [], 'rows' => [], 'total_rows' => 0];
        }

        $sharedStrings = [];
        $ssContent = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssContent !== false) {
            $xml = @simplexml_load_string($ssContent);
            if ($xml && isset($xml->si)) {
                foreach ($xml->si as $val) {
                    if (isset($val->t)) {
                        $sharedStrings[] = (string) $val->t;
                    } elseif (isset($val->r)) {
                        $t = '';
                        foreach ($val->r as $r) {
                            $t .= (string) $r->t;
                        }
                        $sharedStrings[] = $t;
                    } else {
                        $sharedStrings[] = '';
                    }
                }
            }
        }

        $sheetContent = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetContent === false) {
            $sheetContent = $zip->getFromName('xl/worksheets/sheet.xml');
        }
        $zip->close();

        if ($sheetContent === false) {
            return ['headers' => [], 'rows' => [], 'total_rows' => 0];
        }

        $xml = @simplexml_load_string($sheetContent);
        if (!$xml || !isset($xml->sheetData->row)) {
            return ['headers' => [], 'rows' => [], 'total_rows' => 0];
        }

        $rowsRaw = [];
        foreach ($xml->sheetData->row as $r) {
            $row = [];
            foreach ($r->c as $c) {
                $t = (string) $c['t'];
                $v = (string) $c->v;
                if ($t === 's' && isset($sharedStrings[(int) $v])) {
                    $val = $sharedStrings[(int) $v];
                } else {
                    $val = $v;
                }

                $rCoord = (string) $c['r'];
                preg_match('/([A-Z]+)(\d+)/', $rCoord, $matches);
                $colLetters = $matches[1] ?? 'A';
                
                $colIndex = 0;
                for ($i = 0; $i < strlen($colLetters); $i++) {
                    $colIndex = $colIndex * 26 + (ord($colLetters[$i]) - 64);
                }
                $colIndex = max(0, $colIndex - 1);
                $row[$colIndex] = trim($val);
            }

            if (!empty($row)) {
                ksort($row);
                $maxCol = max(array_keys($row));
                for ($i = 0; $i < $maxCol; $i++) {
                    if (!isset($row[$i])) $row[$i] = '';
                }
                ksort($row);
                $rowsRaw[] = array_values($row);
            }
        }

        if (empty($rowsRaw)) {
            return ['headers' => [], 'rows' => [], 'total_rows' => 0];
        }

        $headers = array_shift($rowsRaw) ?? [];
        $headers = array_map(fn($h) => trim(str_replace(['"', "'"], '', $h)), $headers);

        $totalRows = count($rowsRaw);
        if ($limitRows !== null) {
            $rowsRaw = array_slice($rowsRaw, 0, $limitRows);
        }

        return [
            'headers' => $headers,
            'rows' => $rowsRaw,
            'total_rows' => $totalRows,
        ];
    }

    private function parsearCsv(string $path, ?int $limitRows = null): array
    {
        $contenido = file_get_contents($path);

        if (str_starts_with($contenido, "\xEF\xBB\xBF")) {
            $contenido = substr($contenido, 3);
        } else {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252');
        }

        $lineas = array_filter(explode("\n", str_replace("\r", "", $contenido)), fn($l) => trim($l) !== '');
        if (empty($lineas)) {
            return ['headers' => [], 'rows' => [], 'total_rows' => 0];
        }

        $primeraLinea = reset($lineas);
        $separador = ';';
        if (substr_count($primeraLinea, ',') > substr_count($primeraLinea, ';')) {
            $separador = ',';
        } elseif (substr_count($primeraLinea, "\t") > substr_count($primeraLinea, ';')) {
            $separador = "\t";
        }

        $rowsRaw = array_map(fn($linea) => str_getcsv($linea, $separador), $lineas);
        $headers = array_shift($rowsRaw) ?? [];
        $headers = array_map(fn($h) => trim(str_replace(['"', "'"], '', $h)), $headers);

        $totalRows = count($rowsRaw);
        if ($limitRows !== null) {
            $rowsRaw = array_slice($rowsRaw, 0, $limitRows);
        }

        return [
            'headers' => $headers,
            'rows' => $rowsRaw,
            'total_rows' => $totalRows,
        ];
    }

    private function autoDetectarColumnaRut(array $headers): string|int
    {
        foreach ($headers as $index => $header) {
            $hLower = mb_strtolower($header, 'UTF-8');
            if (str_contains($hLower, 'rut') || str_contains($hLower, 'run') || str_contains($hLower, 'user name') || str_contains($hLower, 'usuario')) {
                return $index;
            }
        }
        return 0;
    }

    private function normalizarTexto(?string $texto): string
    {
        if ($texto === null || trim($texto) === '') {
            return '';
        }

        if (class_exists('Normalizer')) {
            $texto = \Normalizer::normalize($texto, \Normalizer::NFC);
        }

        $texto = preg_replace('/[\x{00A0}\x{202F}\x{FEFF}\t]+/u', ' ', $texto);
        $texto = preg_replace('/\s+/', ' ', $texto);

        $buscar     = ['Á', 'É', 'Í', 'Ó', 'Ú', 'Ü', 'Ñ', 'á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'];
        $reemplazar = ['A', 'E', 'I', 'O', 'U', 'U', 'N', 'A', 'E', 'I', 'O', 'U', 'U', 'N'];

        return strtoupper(trim(str_replace($buscar, $reemplazar, $texto)));
    }

    public function irAlPaso(int $pasoDestino): void
    {
        if ($pasoDestino === 2) {
            if (empty($this->fcHeaders) || empty($this->googleHeaders)) {
                Flux::toast('Debes subir los archivos de FullCollege y Admin Google para continuar.', variant: 'warning');
                return;
            }
        }

        if ($pasoDestino === 3) {
            $this->ejecutarCruce();
        }

        $this->paso = $pasoDestino;
    }

    public function procesarRutFullCollege(string $rutRaw): array
    {
        $limpio = preg_replace('/[.\s]/', '', trim($rutRaw));
        if (empty($limpio)) {
            return ['numero' => '', 'dv' => ''];
        }

        if (str_contains($limpio, '-')) {
            $partes = explode('-', $limpio, 2);
            return [
                'numero' => preg_replace('/\D/', '', $partes[0]),
                'dv' => strtoupper(trim($partes[1])),
            ];
        }

        $len = strlen($limpio);
        if ($len <= 1) {
            return ['numero' => $limpio, 'dv' => ''];
        }

        $numero = substr($limpio, 0, -1);
        $dv = substr($limpio, -1);

        return [
            'numero' => preg_replace('/\D/', '', $numero),
            'dv' => strtoupper(trim($dv)),
        ];
    }

    public function procesarRutGoogle(string $rutRaw): string
    {
        $limpio = preg_replace('/[.\s-]/', '', trim($rutRaw));
        if (str_contains($limpio, '@')) {
            $limpio = explode('@', $limpio)[0];
        }
        return preg_replace('/\D/', '', $limpio);
    }

    private function mapearEncabezados(array $headers): array
    {
        $mapa = [];
        foreach ($headers as $index => $header) {
            $key = mb_strtolower(trim(str_replace(['"', "'"], '', $header)), 'UTF-8');
            $key = str_replace(' ', '_', $key);
            $mapa[$key] = $index;
        }
        return $mapa;
    }

    public function ejecutarCruce(): void
    {
        $indexFcRut = (int) $this->fcRutCol;
        $indexGoogleRut = (int) $this->googleRutCol;
        $schoolId = auth()->user()->current_school_id;

        $cursosBdMap = Curso::where('school_id', $schoolId ?? 1)->get();
        $mapaFc = $this->mapearEncabezados($this->fcHeaders);

        $colDvFc = $mapaFc['dv'] ?? null;
        $colPaterno = $mapaFc['paterno'] ?? null;
        $colMaterno = $mapaFc['materno'] ?? null;
        $colNombres = $mapaFc['nombres'] ?? $mapaFc['nombre'] ?? null;
        $colCursoFc = $mapaFc['curso'] ?? null;

        $colRutApod = $mapaFc['rut_apoderado'] ?? null;
        $colDvApod = $mapaFc['dv_apoderado'] ?? null;
        $colPatApod = $mapaFc['paterno_apoderado'] ?? null;
        $colMatApod = $mapaFc['materno_apoderado'] ?? null;
        $colNomApod = $mapaFc['nombre_apoderado'] ?? $mapaFc['nombres_apoderado'] ?? null;
        $colDirApod = $mapaFc['direccion_apoderado'] ?? null;
        $colComApod = $mapaFc['comuna_apoderado'] ?? null;
        $colTelApod = $mapaFc['telefono_apoderado'] ?? null;
        $colEmailApod = $mapaFc['email_apoderado'] ?? null;

        $allFcRows = [];
        foreach ($this->fcPaths as $fcItem) {
            if (!empty($fcItem['path']) && file_exists($fcItem['path'])) {
                $data = $this->parsearArchivo($fcItem['path'], $fcItem['ext'], null);
                $allFcRows = array_merge($allFcRows, $data['rows']);
            }
        }

        $allGoogleRows = [];
        if (!empty($this->googlePath) && file_exists($this->googlePath)) {
            $ext = strtolower(pathinfo($this->googleFileName, PATHINFO_EXTENSION));
            $data = $this->parsearArchivo($this->googlePath, $ext, null);
            $allGoogleRows = $data['rows'];
        }

        $fcData = [];
        $filasDescartadas = 0;

        foreach ($allFcRows as $row) {
            $rawRut = $row[$indexFcRut] ?? '';
            if ($colDvFc !== null && isset($row[$colDvFc]) && trim($row[$colDvFc]) !== '') {
                $rutNum = preg_replace('/\D/', '', $rawRut);
                $rutDv = strtoupper(trim($row[$colDvFc]));
            } else {
                $rutParsed = $this->procesarRutFullCollege($rawRut);
                $rutNum = $rutParsed['numero'];
                $rutDv = $rutParsed['dv'];
            }

            if (empty($rutNum)) {
                $filasDescartadas++;
                continue;
            }

            if ($colPaterno !== null || $colNombres !== null) {
                $patEst = $colPaterno !== null ? trim($row[$colPaterno] ?? '') : '';
                $matEst = $colMaterno !== null ? trim($row[$colMaterno] ?? '') : '';
                $nomEst = $colNombres !== null ? trim($row[$colNombres] ?? '') : '';
                $nombreCompleto = trim(preg_replace('/\s+/', ' ', "{$patEst} {$matEst} {$nomEst}"));
            } else {
                $nombreCompleto = trim($row[$indexFcRut] ?? '');
            }

            $cursoRaw = $colCursoFc !== null ? trim($row[$colCursoFc] ?? '') : '';
            $autoCursoId = null;
            if (!empty($cursoRaw)) {
                $normCurso = $this->normalizarTexto($cursoRaw);
                foreach ($cursosBdMap as $c) {
                    if (!empty($c->nombre_fc) && $this->normalizarTexto($c->nombre_fc) === $normCurso) {
                        $autoCursoId = $c->id;
                        break;
                    }
                    if ($this->normalizarTexto($c->nombreCompleto()) === $normCurso) {
                        $autoCursoId = $c->id;
                        break;
                    }
                }
            }

            $nomApod = $colNomApod !== null ? trim($row[$colNomApod] ?? '') : '';
            $patApod = $colPatApod !== null ? trim($row[$colPatApod] ?? '') : '';
            $matApod = $colMatApod !== null ? trim($row[$colMatApod] ?? '') : '';
            $rutApodNum = $colRutApod !== null ? preg_replace('/\D/', '', $row[$colRutApod] ?? '') : '';
            $rutApodDv = $colDvApod !== null ? strtoupper(trim($row[$colDvApod] ?? '')) : '';
            $emailApod = $colEmailApod !== null ? trim($row[$colEmailApod] ?? '') : '';
            $telApod = $colTelApod !== null ? trim($row[$colTelApod] ?? '') : '';
            $dirApod = $colDirApod !== null ? trim($row[$colDirApod] ?? '') : '';
            $comApod = $colComApod !== null ? trim($row[$colComApod] ?? '') : '';
            $domApod = trim(implode(', ', array_filter([$dirApod, $comApod])));

            $columnasClean = [];
            foreach ($this->fcHeaders as $colIdx => $colName) {
                if (!empty($this->fcColumnasActivas[$colIdx]) && isset($row[$colIdx]) && trim($row[$colIdx]) !== '') {
                    $columnasClean[] = $colName . ': ' . trim($row[$colIdx]);
                }
            }

            $fcData[$rutNum] = [
                'rut_numero' => $rutNum,
                'rut_dv' => $rutDv,
                'nombre_completo' => mb_strtoupper($nombreCompleto, 'UTF-8'),
                'curso_raw' => $cursoRaw,
                'auto_curso_id' => $autoCursoId,
                'apoderado_nombres' => mb_strtoupper($nomApod, 'UTF-8'),
                'apoderado_apellido_pat' => mb_strtoupper($patApod, 'UTF-8'),
                'apoderado_apellido_mat' => mb_strtoupper($matApod, 'UTF-8'),
                'apoderado_rut_numero' => $rutApodNum ?: null,
                'apoderado_rut_dv' => $rutApodDv ?: null,
                'apoderado_email' => $emailApod ?: null,
                'apoderado_telefono' => $telApod ?: null,
                'apoderado_domicilio' => mb_strtoupper($domApod, 'UTF-8') ?: null,
                'columnas_str' => implode(' | ', $columnasClean),
                'raw_row' => $row,
            ];
        }

        $this->fcRutsUnicosCount = count($fcData);
        $this->fcDuplicadosOEncabezadosCount = $filasDescartadas + (count($allFcRows) - count($fcData) - $filasDescartadas);

        $googleData = [];
        foreach ($allGoogleRows as $row) {
            $rawRut = $row[$indexGoogleRut] ?? '';
            $rutNum = $this->procesarRutGoogle($rawRut);

            if (empty($rutNum)) continue;

            $emailFound = '';
            foreach ($row as $val) {
                if (filter_var(trim($val), FILTER_VALIDATE_EMAIL)) {
                    $emailFound = trim($val);
                    break;
                }
            }

            $googleData[$rutNum] = [
                'rut_numero' => $rutNum,
                'email' => $emailFound,
            ];
        }

        $queryBd = Estudiante::with('curso');
        if ($schoolId) {
            $queryBd->where('school_id', $schoolId);
        }
        $estudiantesBd = $queryBd->get()->keyBy('rut_numero');

        $sinCorreo = [];
        $coincidentes = [];
        $retirados = [];
        $this->retiradosSeleccionados = [];
        $this->nuevosCreadosAutoCount = 0;

        foreach ($fcData as $rutNum => $item) {
            $estBd = $estudiantesBd->get($rutNum);

            if (!isset($googleData[$rutNum])) {
                $nombreComp = $estBd ? $estBd->nombreCompleto() : $item['nombre_completo'];
                $cursoNombre = $estBd && $estBd->curso ? $estBd->curso->nombreCompleto() : null;

                $sinCorreo[] = [
                    'rut_numero' => $rutNum,
                    'rut_dv' => $item['rut_dv'],
                    'rut_completo' => $rutNum . '-' . $item['rut_dv'],
                    'nombre' => $nombreComp,
                    'datos_fc' => $item['columnas_str'],
                    'curso_actual' => $cursoNombre,
                    'curso_id' => $estBd?->curso_id ?? $item['auto_curso_id'],
                    'estudiante_id' => $estBd?->id,
                    'email_bd' => $estBd?->email,
                    'existe_en_bd' => $estBd !== null,
                    'estado_bd' => $estBd?->estado ?? 'nuevo',
                    'fc_data' => $item,
                ];
                $this->correosForm[$rutNum] = $estBd?->email ?? '';
                $this->cursosForm[$rutNum] = $estBd?->curso_id ?? $item['auto_curso_id'] ?? '';
            } else {
                // Si el estudiante no existía en la BD del sistema pero está en FullCollege y tiene correo Google: CREARLO
                if (!$estBd) {
                    $estBd = Estudiante::create([
                        'school_id' => $schoolId ?? 1,
                        'rut_numero' => $rutNum,
                        'rut_dv' => $item['rut_dv'],
                        'nombres_csv' => $item['nombre_completo'],
                        'email' => $googleData[$rutNum]['email'],
                        'curso_id' => $item['auto_curso_id'],
                        'estado' => 'activo',

                        'apoderado_nombres' => $item['apoderado_nombres'] ?? null,
                        'apoderado_apellido_pat' => $item['apoderado_apellido_pat'] ?? null,
                        'apoderado_apellido_mat' => $item['apoderado_apellido_mat'] ?? null,
                        'apoderado_rut_numero' => $item['apoderado_rut_numero'] ?? null,
                        'apoderado_rut_dv' => $item['apoderado_rut_dv'] ?? null,
                        'apoderado_email' => $item['apoderado_email'] ?? null,
                        'apoderado_telefono' => $item['apoderado_telefono'] ?? null,
                        'apoderado_domicilio' => $item['apoderado_domicilio'] ?? null,
                    ]);
                    $this->nuevosCreadosAutoCount++;
                } else {
                    $updates = [];
                    if (empty($estBd->email) && !empty($googleData[$rutNum]['email'])) {
                        $updates['email'] = $googleData[$rutNum]['email'];
                    }
                    if ($estBd->estado === 'retirado') {
                        $updates['estado'] = 'activo';
                        $updates['fecha_retiro'] = null;
                    }
                    if (!empty($item['apoderado_nombres'])) {
                        $updates['apoderado_nombres'] = $item['apoderado_nombres'];
                        $updates['apoderado_apellido_pat'] = $item['apoderado_apellido_pat'];
                        $updates['apoderado_apellido_mat'] = $item['apoderado_apellido_mat'];
                        $updates['apoderado_rut_numero'] = $item['apoderado_rut_numero'];
                        $updates['apoderado_rut_dv'] = $item['apoderado_rut_dv'];
                        $updates['apoderado_email'] = $item['apoderado_email'];
                        $updates['apoderado_telefono'] = $item['apoderado_telefono'];
                        $updates['apoderado_domicilio'] = $item['apoderado_domicilio'];
                    }
                    if (!empty($updates)) {
                        $estBd->update($updates);
                    }
                }

                $nombreComp = $estBd->nombreCompleto();
                $cursoNombre = $estBd->curso ? $estBd->curso->nombreCompleto() : 'Sin Curso';

                $coincidentes[] = [
                    'rut_numero' => $rutNum,
                    'rut_dv' => $item['rut_dv'],
                    'rut_completo' => $rutNum . '-' . $item['rut_dv'],
                    'nombre' => $nombreComp,
                    'curso' => $cursoNombre,
                    'email_google' => $googleData[$rutNum]['email'],
                    'estudiante_id' => $estBd->id,
                    'email_bd' => $estBd->email,
                    'existe_en_bd' => true,
                ];
            }
        }

        $retiradosRuts = [];

        foreach ($estudiantesBd as $rutNum => $estBd) {
            if (!isset($fcData[$rutNum])) {
                $googleItem = $googleData[$rutNum] ?? null;
                $retirados[] = [
                    'rut_numero' => $rutNum,
                    'email_google' => $googleItem['email'] ?? $estBd->email ?? 'Sin correo',
                    'nombre' => $estBd->nombreCompleto(),
                    'curso' => $estBd->curso ? $estBd->curso->nombreCompleto() : '-',
                    'estudiante_id' => $estBd->id,
                    'estado_bd' => $estBd->estado ?? 'activo',
                    'origen' => 'Registrado en BD del Sistema',
                ];
                $retiradosRuts[$rutNum] = true;
                $this->retiradosSeleccionados[$rutNum] = ($estBd->estado !== 'retirado');
            }
        }

        foreach ($googleData as $rutNum => $item) {
            if (!isset($fcData[$rutNum]) && !isset($retiradosRuts[$rutNum])) {
                $retirados[] = [
                    'rut_numero' => $rutNum,
                    'email_google' => $item['email'],
                    'nombre' => 'Sin registro en BD',
                    'curso' => '-',
                    'estudiante_id' => null,
                    'estado_bd' => 'sin_bd',
                    'origen' => 'Google Admin',
                ];
                $this->retiradosSeleccionados[$rutNum] = false;
            }
        }

        $this->resultados = [
            'sin_correo' => $sinCorreo,
            'retirados' => $retirados,
            'coincidentes' => $coincidentes,
        ];

        Flux::toast('Cruce completado. Se procesaron ' . $this->fcRutsUnicosCount . ' estudiantes únicos de FullCollege.', variant: 'success');
    }

    public function guardarCorreoIndividual(string $rutNumero): void
    {
        $nuevoEmail = trim($this->correosForm[$rutNumero] ?? '');
        $nuevoCursoId = $this->cursosForm[$rutNumero] ?? null;

        $estudiante = Estudiante::where('rut_numero', $rutNumero)->first();

        $itemFc = null;
        foreach ($this->resultados['sin_correo'] as $item) {
            if ($item['rut_numero'] === $rutNumero) {
                $itemFc = $item;
                break;
            }
        }

        $fcData = $itemFc['fc_data'] ?? [];

        if (!$estudiante && $itemFc) {
            $schoolId = auth()->user()->current_school_id ?? 1;
            $estudiante = Estudiante::create([
                'school_id' => $schoolId,
                'rut_numero' => $rutNumero,
                'rut_dv' => $itemFc['rut_dv'],
                'nombres_csv' => $itemFc['nombre'],
                'email' => filter_var($nuevoEmail, FILTER_VALIDATE_EMAIL) ? $nuevoEmail : null,
                'curso_id' => !empty($nuevoCursoId) ? (int) $nuevoCursoId : null,
                'estado' => 'activo',

                'apoderado_nombres' => $fcData['apoderado_nombres'] ?? null,
                'apoderado_apellido_pat' => $fcData['apoderado_apellido_pat'] ?? null,
                'apoderado_apellido_mat' => $fcData['apoderado_apellido_mat'] ?? null,
                'apoderado_rut_numero' => $fcData['apoderado_rut_numero'] ?? null,
                'apoderado_rut_dv' => $fcData['apoderado_rut_dv'] ?? null,
                'apoderado_email' => $fcData['apoderado_email'] ?? null,
                'apoderado_telefono' => $fcData['apoderado_telefono'] ?? null,
                'apoderado_domicilio' => $fcData['apoderado_domicilio'] ?? null,
            ]);
            Flux::toast("Estudiante {$itemFc['nombre']} creado en el sistema exitosamente.", variant: 'success');
        } else if ($estudiante) {
            $updates = ['estado' => 'activo', 'fecha_retiro' => null];
            if (!empty($nuevoEmail) && filter_var($nuevoEmail, FILTER_VALIDATE_EMAIL)) {
                $updates['email'] = $nuevoEmail;
            }
            if (!empty($nuevoCursoId)) {
                $updates['curso_id'] = (int) $nuevoCursoId;
            }
            if (!empty($fcData['apoderado_nombres'])) {
                $updates['apoderado_nombres'] = $fcData['apoderado_nombres'];
                $updates['apoderado_apellido_pat'] = $fcData['apoderado_apellido_pat'];
                $updates['apoderado_apellido_mat'] = $fcData['apoderado_apellido_mat'];
                $updates['apoderado_rut_numero'] = $fcData['apoderado_rut_numero'];
                $updates['apoderado_rut_dv'] = $fcData['apoderado_rut_dv'];
                $updates['apoderado_email'] = $fcData['apoderado_email'];
                $updates['apoderado_telefono'] = $fcData['apoderado_telefono'];
                $updates['apoderado_domicilio'] = $fcData['apoderado_domicilio'];
            }
            $estudiante->update($updates);
            Flux::toast("Estudiante RUT {$rutNumero} actualizado exitosamente.", variant: 'success');
        }

        $this->resultados['sin_correo'] = array_filter(
            $this->resultados['sin_correo'],
            fn($item) => $item['rut_numero'] !== $rutNumero
        );
    }

    public function guardarTodosLosCorreos(): void
    {
        $guardados = 0;
        foreach ($this->resultados['sin_correo'] as $item) {
            $rutNum = $item['rut_numero'];
            $email = trim($this->correosForm[$rutNum] ?? '');
            $cursoId = $this->cursosForm[$rutNum] ?? null;
            $fcData = $item['fc_data'] ?? [];

            $estudiante = Estudiante::where('rut_numero', $rutNum)->first();

            if (!$estudiante) {
                $schoolId = auth()->user()->current_school_id ?? 1;
                Estudiante::create([
                    'school_id' => $schoolId,
                    'rut_numero' => $rutNum,
                    'rut_dv' => $item['rut_dv'],
                    'nombres_csv' => $item['nombre'],
                    'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
                    'curso_id' => !empty($cursoId) ? (int) $cursoId : null,
                    'estado' => 'activo',

                    'apoderado_nombres' => $fcData['apoderado_nombres'] ?? null,
                    'apoderado_apellido_pat' => $fcData['apoderado_apellido_pat'] ?? null,
                    'apoderado_apellido_mat' => $fcData['apoderado_apellido_mat'] ?? null,
                    'apoderado_rut_numero' => $fcData['apoderado_rut_numero'] ?? null,
                    'apoderado_rut_dv' => $fcData['apoderado_rut_dv'] ?? null,
                    'apoderado_email' => $fcData['apoderado_email'] ?? null,
                    'apoderado_telefono' => $fcData['apoderado_telefono'] ?? null,
                    'apoderado_domicilio' => $fcData['apoderado_domicilio'] ?? null,
                ]);
                $guardados++;
            } else {
                $updates = ['estado' => 'activo', 'fecha_retiro' => null];
                if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $updates['email'] = $email;
                }
                if (!empty($cursoId)) {
                    $updates['curso_id'] = (int) $cursoId;
                }
                if (!empty($fcData['apoderado_nombres'])) {
                    $updates['apoderado_nombres'] = $fcData['apoderado_nombres'];
                    $updates['apoderado_apellido_pat'] = $fcData['apoderado_apellido_pat'];
                    $updates['apoderado_apellido_mat'] = $fcData['apoderado_apellido_mat'];
                    $updates['apoderado_rut_numero'] = $fcData['apoderado_rut_numero'];
                    $updates['apoderado_rut_dv'] = $fcData['apoderado_rut_dv'];
                    $updates['apoderado_email'] = $fcData['apoderado_email'];
                    $updates['apoderado_telefono'] = $fcData['apoderado_telefono'];
                    $updates['apoderado_domicilio'] = $fcData['apoderado_domicilio'];
                }
                $estudiante->update($updates);
                $guardados++;
            }
        }

        if ($guardados > 0) {
            Flux::toast("Se procesaron {$guardados} estudiantes en el sistema.", variant: 'success');
            $this->ejecutarCruce();
        } else {
            Flux::toast("No hay estudiantes para procesar.", variant: 'warning');
        }
    }

    public function marcarComoRetirado(string $rutNumero): void
    {
        $estudiante = Estudiante::where('rut_numero', $rutNumero)->first();
        if ($estudiante) {
            $estudiante->update([
                'estado' => 'retirado',
                'fecha_retiro' => now(),
            ]);
            Flux::toast("Estudiante {$estudiante->nombreCompleto()} marcado como RETIRADO en el sistema. Sus entrevistas e historial se conservan intactos.", variant: 'warning');
        } else {
            Flux::toast("El RUT {$rutNumero} no está registrado en la base de datos.", variant: 'info');
        }

        foreach ($this->resultados['retirados'] as &$item) {
            if ($item['rut_numero'] === $rutNumero) {
                $item['estado_bd'] = 'retirado';
            }
        }
    }

    public function marcarTodosComoRetirados(): void
    {
        $retiradosCount = 0;
        foreach ($this->resultados['retirados'] as $item) {
            $rutNum = $item['rut_numero'];
            $estaSeleccionado = !empty($this->retiradosSeleccionados[$rutNum]);

            if ($estaSeleccionado && !empty($item['estudiante_id'])) {
                $estudiante = Estudiante::find($item['estudiante_id']);
                if ($estudiante && $estudiante->estado !== 'retirado') {
                    $estudiante->update([
                        'estado' => 'retirado',
                        'fecha_retiro' => now(),
                    ]);
                    $retiradosCount++;
                }
            }
        }

        if ($retiradosCount > 0) {
            Flux::toast("Se marcaron {$retiradosCount} estudiante(s) seleccionado(s) como RETIRADOS en la base de datos sin borrar su historial.", variant: 'success');
            $this->ejecutarCruce();
        } else {
            Flux::toast("No hay estudiantes seleccionados pendientes por retirar.", variant: 'info');
        }
    }
};
?>

<div class="max-w-7xl mx-auto w-full pb-16 space-y-8">
    <!-- Header -->
    <x-header 
        :titulo="__('Sincronización de Correos (FullCollege vs. Admin Google)')"
        :subtitulo="__('Herramienta interactiva para limpiar datos, extraer RUT/DV, detectar alumnos sin correo y sincronizarlos directamente con la plataforma.')"
        icono="arrow-path-rounded-square"
    >
        <flux:button href="{{ route('estudiantes.index') }}" variant="ghost" icon="arrow-left">
            {{ __('Volver a Estudiantes') }}
        </flux:button>
    </x-header>

    <!-- Indicador de Pasos Wizard -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="p-4 rounded-xl border {{ $paso === 1 ? 'border-blue-500 bg-blue-50/50 dark:bg-blue-900/20' : 'border-zinc-200 dark:border-zinc-700' }} transition-all">
            <div class="flex items-center gap-3">
                <span class="size-8 rounded-full flex items-center justify-center font-bold text-sm {{ $paso === 1 ? 'bg-blue-600 text-white' : 'bg-zinc-200 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300' }}">1</span>
                <div>
                    <p class="font-bold text-sm text-zinc-900 dark:text-zinc-100">Subir Archivos</p>
                    <p class="text-xs text-zinc-500">FullCollege (Básica + Media) y Google</p>
                </div>
            </div>
        </div>

        <div class="p-4 rounded-xl border {{ $paso === 2 ? 'border-blue-500 bg-blue-50/50 dark:bg-blue-900/20' : 'border-zinc-200 dark:border-zinc-700' }} transition-all">
            <div class="flex items-center gap-3">
                <span class="size-8 rounded-full flex items-center justify-center font-bold text-sm {{ $paso === 2 ? 'bg-blue-600 text-white' : 'bg-zinc-200 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300' }}">2</span>
                <div>
                    <p class="font-bold text-sm text-zinc-900 dark:text-zinc-100">Previsualizar y Limpiar</p>
                    <p class="text-xs text-zinc-500">Seleccionar columnas y verificar RUT/DV</p>
                </div>
            </div>
        </div>

        <div class="p-4 rounded-xl border {{ $paso === 3 ? 'border-blue-500 bg-blue-50/50 dark:bg-blue-900/20' : 'border-zinc-200 dark:border-zinc-700' }} transition-all">
            <div class="flex items-center gap-3">
                <span class="size-8 rounded-full flex items-center justify-center font-bold text-sm {{ $paso === 3 ? 'bg-blue-600 text-white' : 'bg-zinc-200 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300' }}">3</span>
                <div>
                    <p class="font-bold text-sm text-zinc-900 dark:text-zinc-100">Resultados del Cruce</p>
                    <p class="text-xs text-zinc-500">Alumnos a crear, retirados y guardar</p>
                </div>
            </div>
        </div>
    </div>

    <!-- PASO 1: Subir Archivos -->
    @if ($paso === 1)
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- File 1: FullCollege (Admite Múltiples XLSX / CSV) -->
            <flux:card class="border border-zinc-200 dark:border-zinc-700">
                <div class="mb-4">
                    <flux:badge color="blue" class="mb-2">Fuente de Verdad (Soporta .xlsx y .csv)</flux:badge>
                    <flux:heading size="lg">1. Archivos FullCollege (Básica + Media)</flux:heading>
                    <flux:text class="text-xs text-zinc-500">Puedes seleccionar 1 o varios archivos Excel (.xlsx) o CSV simultáneamente.</flux:text>
                </div>

                <div class="space-y-4">
                    <div class="relative border-2 border-dashed border-zinc-300 dark:border-zinc-600 rounded-xl p-6 text-center hover:border-blue-500 transition-colors cursor-pointer">
                        <input type="file" wire:model.live="archivosFullCollege" multiple accept=".xlsx,.csv,.txt" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" />
                        <flux:icon.document-arrow-up class="size-10 mx-auto text-zinc-400 mb-2" />
                        
                        <div wire:loading wire:target="archivosFullCollege" class="text-xs font-semibold text-blue-600">
                            Procesando archivos de FullCollege (.xlsx / .csv)...
                        </div>
                        <div wire:loading.remove wire:target="archivosFullCollege">
                            @if (!empty($fcFileNames))
                                <div class="space-y-1">
                                    @foreach ($fcFileNames as $fName)
                                        <p class="text-xs font-semibold text-blue-600 dark:text-blue-400">✓ {{ $fName }}</p>
                                    @endforeach
                                    <p class="text-xs text-zinc-500 font-bold mt-2">Total combinados: {{ $fcTotalFilas }} estudiantes</p>
                                </div>
                            @else
                                <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Haz clic o arrastra aquí tus planillas .xlsx o .csv</p>
                                <p class="text-[11px] text-zinc-400 mt-1">Puedes seleccionar Básica y Media juntos</p>
                            @endif
                        </div>
                    </div>
                    <flux:error name="archivosFullCollege" />
                    <flux:error name="archivosFullCollege.*" />
                </div>
            </flux:card>

            <!-- File 2: Admin Google -->
            <flux:card class="border border-zinc-200 dark:border-zinc-700">
                <div class="mb-4">
                    <flux:badge color="indigo" class="mb-2">Cuentas Existentes (Soporta .xlsx y .csv)</flux:badge>
                    <flux:heading size="lg">2. Archivo Admin Google</flux:heading>
                    <flux:text class="text-xs text-zinc-500">Exportación de usuarios descargada del panel de Google Workspace Admin.</flux:text>
                </div>

                <div class="space-y-4">
                    <div class="relative border-2 border-dashed border-zinc-300 dark:border-zinc-600 rounded-xl p-6 text-center hover:border-indigo-500 transition-colors cursor-pointer">
                        <input type="file" wire:model.live="archivoGoogle" accept=".xlsx,.csv,.txt" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" />
                        <flux:icon.document-arrow-up class="size-10 mx-auto text-zinc-400 mb-2" />

                        <div wire:loading wire:target="archivoGoogle" class="text-xs font-semibold text-indigo-600">
                            Procesando archivo Admin Google...
                        </div>
                        <div wire:loading.remove wire:target="archivoGoogle">
                            @if ($archivoGoogle)
                                <p class="text-sm font-semibold text-indigo-600 dark:text-indigo-400">✓ {{ $googleFileName }}</p>
                                <p class="text-xs text-zinc-500 mt-1">{{ $googleTotalFilas }} filas detectadas</p>
                            @else
                                <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Haz clic o arrastra tu archivo CSV o XLSX de Google aquí</p>
                                <p class="text-[11px] text-zinc-400 mt-1">Soporta .csv y .xlsx</p>
                            @endif
                        </div>
                    </div>
                    <flux:error name="archivoGoogle" />
                </div>
            </flux:card>
        </div>

        <div class="flex justify-end pt-4">
            <flux:button 
                wire:click="irAlPaso(2)" 
                variant="primary" 
                icon-trailing="arrow-right"
                :disabled="empty($archivosFullCollege) || !$archivoGoogle"
            >
                Continuar a Limpieza y Selección de RUT
            </flux:button>
        </div>
    @endif

    <!-- PASO 2: Limpieza y Configuración de Columnas -->
    @if ($paso === 2)
        <div class="space-y-8">
            <!-- Configuración FullCollege -->
            <flux:card class="border border-blue-200 dark:border-blue-900/50">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
                    <div>
                        <flux:badge color="blue">FullCollege ({{ implode(', ', $fcFileNames) }})</flux:badge>
                        <flux:heading size="lg" class="mt-1">Vista Previa y Columna de RUT</flux:heading>
                        <flux:text class="text-xs text-zinc-500">Selecciona qué columnas mantener y cuál contiene los RUTs de los estudiantes.</flux:text>
                    </div>

                    <div class="w-full md:w-72">
                        <flux:select label="Columna con el RUT" wire:model.live="fcRutCol">
                            @foreach ($fcHeaders as $idx => $header)
                                <option value="{{ $idx }}">{{ $idx + 1 }}. {{ $header }}</option>
                            @endforeach
                        </flux:select>
                    </div>
                </div>

                <!-- Tabla Previsualización 5 Filas FC -->
                <div class="overflow-x-auto border border-zinc-200 dark:border-zinc-700 rounded-lg">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-zinc-100 dark:bg-zinc-800 border-b border-zinc-200 dark:border-zinc-700">
                            <tr>
                                @foreach ($fcHeaders as $idx => $header)
                                    <th class="p-3">
                                        <div class="flex items-center gap-2">
                                            <input type="checkbox" wire:model.live="fcColumnasActivas.{{ $idx }}" class="rounded text-blue-600" />
                                            <span class="font-bold text-zinc-900 dark:text-zinc-100">{{ $header }}</span>
                                            @if ((string) $idx === (string) $fcRutCol)
                                                <span class="bg-blue-100 text-blue-800 text-[10px] px-1.5 py-0.5 rounded font-mono font-bold">RUT</span>
                                            @endif
                                        </div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach ($fcPreviewRows as $row)
                                <tr>
                                    @foreach ($fcHeaders as $idx => $header)
                                        <td class="p-3 {{ empty($fcColumnasActivas[$idx]) ? 'opacity-30 line-through' : '' }}">
                                            @if ((string) $idx === (string) $fcRutCol)
                                                @php $parsed = $this->procesarRutFullCollege($row[$idx] ?? ''); @endphp
                                                <span class="font-mono font-bold text-blue-600">{{ $parsed['numero'] }}</span>
                                                @if (!empty($parsed['dv']))
                                                    <span class="font-mono text-zinc-500 bg-zinc-100 px-1 rounded">- {{ $parsed['dv'] }}</span>
                                                @endif
                                            @else
                                                {{ $row[$idx] ?? '-' }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </flux:card>

            <!-- Configuración Admin Google -->
            <flux:card class="border border-indigo-200 dark:border-indigo-900/50">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
                    <div>
                        <flux:badge color="indigo">Admin Google ({{ $googleFileName }})</flux:badge>
                        <flux:heading size="lg" class="mt-1">Vista Previa y Columna de RUT</flux:heading>
                        <flux:text class="text-xs text-zinc-500">Selecciona qué columnas mantener y cuál contiene el número de RUT del estudiante.</flux:text>
                    </div>

                    <div class="w-full md:w-72">
                        <flux:select label="Columna con el RUT" wire:model.live="googleRutCol">
                            @foreach ($googleHeaders as $idx => $header)
                                <option value="{{ $idx }}">{{ $idx + 1 }}. {{ $header }}</option>
                            @endforeach
                        </flux:select>
                    </div>
                </div>

                <!-- Tabla Previsualización 5 Filas Google -->
                <div class="overflow-x-auto border border-zinc-200 dark:border-zinc-700 rounded-lg">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-zinc-100 dark:bg-zinc-800 border-b border-zinc-200 dark:border-zinc-700">
                            <tr>
                                @foreach ($googleHeaders as $idx => $header)
                                    <th class="p-3">
                                        <div class="flex items-center gap-2">
                                            <input type="checkbox" wire:model.live="googleColumnasActivas.{{ $idx }}" class="rounded text-indigo-600" />
                                            <span class="font-bold text-zinc-900 dark:text-zinc-100">{{ $header }}</span>
                                            @if ((string) $idx === (string) $googleRutCol)
                                                <span class="bg-indigo-100 text-indigo-800 text-[10px] px-1.5 py-0.5 rounded font-mono font-bold">RUT</span>
                                            @endif
                                        </div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach ($googlePreviewRows as $row)
                                <tr>
                                    @foreach ($googleHeaders as $idx => $header)
                                        <td class="p-3 {{ empty($googleColumnasActivas[$idx]) ? 'opacity-30 line-through' : '' }}">
                                            @if ((string) $idx === (string) $googleRutCol)
                                                <span class="font-mono font-bold text-indigo-600">{{ $this->procesarRutGoogle($row[$idx] ?? '') }}</span>
                                            @else
                                                {{ $row[$idx] ?? '-' }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </flux:card>

            <div class="flex justify-between items-center pt-4">
                <flux:button wire:click="irAlPaso(1)" variant="ghost" icon="arrow-left">
                    Volver a Carga de Archivos
                </flux:button>

                <flux:button wire:click="irAlPaso(3)" variant="primary" icon-trailing="arrow-path">
                    Ejecutar Cruce y Comparación
                </flux:button>
            </div>
        </div>
    @endif

    <!-- PASO 3: Resultados del Cruce -->
    @if ($paso === 3)
        <div class="space-y-6">
            <!-- Banner Diagnóstico de Filas -->
            <flux:card class="bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800">
                <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                    <div>
                        <flux:heading class="text-blue-900 dark:text-blue-200">📊 Diagnóstico de Filas de FullCollege</flux:heading>
                        <p class="text-xs text-blue-700 dark:text-blue-300 mt-1">
                            Planilla FullCollege: <b>{{ $fcTotalFilas }} filas totales</b>. 
                            RUTs Únicos reales de estudiantes: <b>{{ $fcRutsUnicosCount }}</b>. 
                            @if($fcDuplicadosOEncabezadosCount > 0)
                                (Se filtraron <b>{{ $fcDuplicadosOEncabezadosCount }} filas</b> entre encabezados secundarios, filas vacías o RUTs duplicados).
                            @endif
                            @if($nuevosCreadosAutoCount > 0)
                                <span class="block mt-0.5 font-bold text-emerald-600 dark:text-emerald-400">⚡ Se crearon automáticamente {{ $nuevosCreadosAutoCount }} estudiantes en la BD que tenían correo en Google Admin.</span>
                            @endif
                        </p>
                    </div>
                </div>
            </flux:card>

            <!-- Métricas del Cruce -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <flux:card class="border-l-4 border-l-amber-500">
                    <p class="text-xs font-semibold text-amber-600 dark:text-amber-400 uppercase">Requieren Crear Correo / Alta</p>
                    <p class="text-3xl font-extrabold text-zinc-900 dark:text-zinc-100 mt-1">{{ count($resultados['sin_correo']) }}</p>
                    <p class="text-xs text-zinc-500 mt-1">Estudiantes en FullCollege sin cuenta en Google Admin</p>
                </flux:card>

                <flux:card class="border-l-4 border-l-rose-500">
                    <p class="text-xs font-semibold text-rose-600 dark:text-rose-400 uppercase">Estudiantes Retirados</p>
                    <p class="text-3xl font-extrabold text-zinc-900 dark:text-zinc-100 mt-1">{{ count($resultados['retirados']) }}</p>
                    <p class="text-xs text-zinc-500 mt-1">Cuentas en BD o Google que no figuran en FullCollege</p>
                </flux:card>

                <flux:card class="border-l-4 border-l-emerald-500">
                    <p class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 uppercase">Estudiantes Coincidentes</p>
                    <p class="text-3xl font-extrabold text-zinc-900 dark:text-zinc-100 mt-1">{{ count($resultados['coincidentes']) }}</p>
                    <p class="text-xs text-zinc-500 mt-1">Alumnos activos en BD con correo Google vinculado</p>
                </flux:card>
            </div>

            <!-- Tabla 1: Estudiantes que Requieren Crear Correo / Alta en Sistema -->
            <flux:card class="border border-amber-200 dark:border-amber-900/50">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:badge color="amber">Acción Requerida</flux:badge>
                            <flux:heading size="lg">Estudiantes sin Correo Institucional / Pendientes de Alta ({{ count($resultados['sin_correo']) }})</flux:heading>
                        </div>
                        <flux:text class="text-xs text-zinc-500 mt-1">
                            Estudiantes en FullCollege que no están vinculados a Google. El sistema concatena su nombre y el de su apoderado automáticamente.
                        </flux:text>
                    </div>

                    @if (!empty($resultados['sin_correo']))
                        <flux:button wire:click="guardarTodosLosCorreos" variant="primary" icon="check-circle" size="sm">
                            Procesar / Crear Todos los Ingresados
                        </flux:button>
                    @endif
                </div>

                @if (empty($resultados['sin_correo']))
                    <div class="p-8 text-center text-zinc-500">
                        <flux:icon.check-circle class="size-8 mx-auto text-emerald-500 mb-2" />
                        <p class="font-semibold text-sm">¡Todos los estudiantes de FullCollege tienen correo asignado!</p>
                    </div>
                @else
                    <div class="overflow-x-auto border border-zinc-200 dark:border-zinc-700 rounded-lg">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-zinc-100 dark:bg-zinc-800 border-b border-zinc-200 dark:border-zinc-700">
                                <tr>
                                    <th class="p-3">RUT / DV</th>
                                    <th class="p-3">Estado en Sistema</th>
                                    <th class="p-3">Nombre Estudiante Concatenado</th>
                                    <th class="p-3">Asignar Curso</th>
                                    <th class="p-3">Asignar Nuevo Correo</th>
                                    <th class="p-3 text-right">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @foreach ($resultados['sin_correo'] as $item)
                                    <tr>
                                        <!-- RUT -->
                                        <td class="p-3 font-mono font-bold text-zinc-900 dark:text-zinc-100 whitespace-nowrap">
                                            {{ $item['rut_completo'] }}
                                        </td>

                                        <!-- Estado en Sistema -->
                                        <td class="p-3 whitespace-nowrap">
                                            @if ($item['existe_en_bd'])
                                                <span class="bg-blue-100 text-blue-800 text-[10px] px-2 py-1 rounded-full font-semibold">Registrado en BD</span>
                                            @else
                                                <span class="bg-amber-100 text-amber-800 text-[10px] px-2 py-1 rounded-full font-semibold">⚡ Nuevo (No en BD)</span>
                                            @endif
                                        </td>

                                        <!-- Nombre Concatenado y Apoderado -->
                                        <td class="p-3 max-w-md">
                                            <p class="font-bold text-zinc-900 dark:text-zinc-100 uppercase text-xs">
                                                {{ $item['nombre'] }}
                                            </p>
                                            @if (!empty($item['fc_data']['apoderado_nombres']) || !empty($item['fc_data']['apoderado_apellido_pat']))
                                                <p class="text-[11px] text-zinc-500 mt-1">
                                                    <span class="font-semibold text-zinc-700 dark:text-zinc-300">Apoderado:</span> 
                                                    {{ trim($item['fc_data']['apoderado_nombres'] . ' ' . $item['fc_data']['apoderado_apellido_pat'] . ' ' . $item['fc_data']['apoderado_apellido_mat']) }}
                                                    @if(!empty($item['fc_data']['apoderado_telefono']))
                                                        ({{ $item['fc_data']['apoderado_telefono'] }})
                                                    @endif
                                                </p>
                                            @endif
                                            <p class="text-[10px] text-zinc-400 mt-0.5 font-mono truncate">
                                                {{ $item['datos_fc'] }}
                                            </p>
                                        </td>

                                        <!-- Selector de Curso -->
                                        <td class="p-3 min-w-[180px]">
                                            <select 
                                                wire:model.defer="cursosForm.{{ $item['rut_numero'] }}"
                                                class="w-full px-2.5 py-1.5 text-xs rounded-md border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 focus:ring-2 focus:ring-blue-500"
                                            >
                                                <option value="">-- Seleccionar Curso --</option>
                                                @foreach ($listaCursos as $c)
                                                    <option value="{{ $c['id'] }}">{{ $c['nombre'] }}</option>
                                                @endforeach
                                            </select>
                                            @if ($item['curso_actual'])
                                                <p class="text-[10px] text-emerald-600 dark:text-emerald-400 mt-1 font-medium">✓ En BD: {{ $item['curso_actual'] }}</p>
                                            @elseif (!empty($item['fc_data']['curso_raw']))
                                                <p class="text-[10px] text-blue-600 dark:text-blue-400 mt-1 font-medium">FullCollege: {{ $item['fc_data']['curso_raw'] }}</p>
                                            @else
                                                <p class="text-[10px] text-amber-600 dark:text-amber-400 mt-1 font-medium">⚠️ Sin curso asignado</p>
                                            @endif
                                        </td>

                                        <!-- Input Correo -->
                                        <td class="p-3 min-w-[200px]">
                                            <input 
                                                type="email" 
                                                wire:model.defer="correosForm.{{ $item['rut_numero'] }}" 
                                                placeholder="ejemplo@estudiantes.cl"
                                                class="w-full px-2.5 py-1.5 text-xs rounded-md border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 focus:ring-2 focus:ring-blue-500"
                                            />
                                        </td>

                                        <!-- Acción Guardar / Crear -->
                                        <td class="p-3 text-right whitespace-nowrap">
                                            <flux:button 
                                                wire:click="guardarCorreoIndividual('{{ $item['rut_numero'] }}')" 
                                                size="sm" 
                                                variant="filled" 
                                                class="bg-blue-100 text-blue-700 hover:bg-blue-200 dark:bg-blue-900/40 dark:text-blue-300"
                                            >
                                                {{ $item['existe_en_bd'] ? 'Guardar' : 'Crear en BD' }}
                                            </flux:button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </flux:card>

            <!-- Tabla 2: Estudiantes Retirados / Desvinculados con Checkbox de Selección -->
            <flux:card class="border border-rose-200 dark:border-rose-900/50">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:badge color="rose">Retirados / Desvinculados</flux:badge>
                            <flux:heading size="lg">Estudiantes en BD / Google Admin no presentes en FullCollege ({{ count($resultados['retirados']) }})</flux:heading>
                        </div>
                        <flux:text class="text-xs text-zinc-500 mt-1">
                            Selecciona los estudiantes que deseas marcar como <b>RETIRADOS</b>. Puedes desmarcar cuentas de prueba o excepciones para que permanezcan activas.
                        </flux:text>
                    </div>

                    @if (!empty($resultados['retirados']))
                        @php
                            $cantSeleccionados = count(array_filter($retiradosSeleccionados));
                        @endphp
                        <flux:button 
                            wire:click="marcarTodosComoRetirados" 
                            wire:confirm="¿Estás seguro de marcar como RETIRADOS a los {{ $cantSeleccionados }} estudiantes seleccionados? Su historial se conservará intacto."
                            variant="danger" 
                            size="sm"
                            icon="user-minus"
                            :disabled="$cantSeleccionados === 0"
                        >
                            Marcar Seleccionados como Retirados ({{ $cantSeleccionados }})
                        </flux:button>
                    @endif
                </div>

                @if (empty($resultados['retirados']))
                    <div class="p-6 text-center text-zinc-500">
                        <p class="text-xs">No hay estudiantes en BD ni cuentas sobrantes en Google Admin fuera de FullCollege.</p>
                    </div>
                @else
                    <div class="overflow-x-auto border border-zinc-200 dark:border-zinc-700 rounded-lg">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-zinc-100 dark:bg-zinc-800 border-b border-zinc-200 dark:border-zinc-700">
                                <tr>
                                    <th class="p-3 w-10">
                                        <input 
                                            type="checkbox" 
                                            wire:model.live="marcarTodosRetiradosCheck" 
                                            class="rounded text-rose-600 focus:ring-rose-500 cursor-pointer"
                                            title="Seleccionar / Deseleccionar todos"
                                        />
                                    </th>
                                    <th class="p-3">RUT</th>
                                    <th class="p-3">Origen / Detección</th>
                                    <th class="p-3">Correo Registrar o Google</th>
                                    <th class="p-3">Nombre en Sistema</th>
                                    <th class="p-3">Último Curso</th>
                                    <th class="p-3">Estado BD</th>
                                    <th class="p-3 text-right">Acción Individual</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @foreach ($resultados['retirados'] as $item)
                                    @php
                                        $rutNum = $item['rut_numero'];
                                        $isChecked = !empty($retiradosSeleccionados[$rutNum]);
                                    @endphp
                                    <tr class="transition-colors {{ $isChecked ? 'bg-rose-50/40 dark:bg-rose-900/20' : 'opacity-60' }}">
                                        <td class="p-3 w-10">
                                            <input 
                                                type="checkbox" 
                                                wire:model.live="retiradosSeleccionados.{{ $rutNum }}" 
                                                class="rounded text-rose-600 focus:ring-rose-500 cursor-pointer"
                                            />
                                        </td>
                                        <td class="p-3 font-mono font-bold text-zinc-700 dark:text-zinc-300">
                                            {{ $rutNum }}
                                        </td>
                                        <td class="p-3 whitespace-nowrap">
                                            <span class="bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 text-[10px] px-2 py-0.5 rounded font-medium border border-zinc-200 dark:border-zinc-700">
                                                {{ $item['origen'] }}
                                            </span>
                                        </td>
                                        <td class="p-3 font-medium text-rose-600 dark:text-rose-400">
                                            {{ $item['email_google'] ?: 'Sin correo' }}
                                        </td>
                                        <td class="p-3 uppercase text-zinc-700 dark:text-zinc-300 font-semibold">
                                            {{ $item['nombre'] }}
                                        </td>
                                        <td class="p-3 text-zinc-500">
                                            {{ $item['curso'] }}
                                        </td>
                                        <td class="p-3 whitespace-nowrap">
                                            @if ($item['estado_bd'] === 'retirado')
                                                <span class="bg-zinc-200 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300 text-[10px] px-2 py-1 rounded-full font-semibold">Ya Retirado</span>
                                            @elseif ($item['estado_bd'] === 'activo')
                                                <span class="bg-rose-100 text-rose-800 text-[10px] px-2 py-1 rounded-full font-semibold">⚠️ Activo en BD</span>
                                            @else
                                                <span class="bg-zinc-100 text-zinc-500 text-[10px] px-2 py-1 rounded-full">Sin registro</span>
                                            @endif
                                        </td>
                                        <td class="p-3 text-right whitespace-nowrap">
                                            @if ($item['estudiante_id'] && $item['estado_bd'] !== 'retirado')
                                                <flux:button 
                                                    wire:click="marcarComoRetirado('{{ $rutNum }}')"
                                                    wire:confirm="¿Deseas marcar a {{ $item['nombre'] }} como RETIRADO en el sistema? Su historial se conservará intacto."
                                                    size="sm" 
                                                    variant="ghost" 
                                                    class="text-rose-600 hover:bg-rose-100 dark:hover:bg-rose-900/40 font-bold"
                                                >
                                                    Marcar Retirado
                                                </flux:button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </flux:card>

            <div class="flex justify-between items-center pt-4">
                <flux:button wire:click="irAlPaso(2)" variant="ghost" icon="arrow-left">
                    Volver a Limpieza de Columnas
                </flux:button>
            </div>
        </div>
    @endif
</div>
