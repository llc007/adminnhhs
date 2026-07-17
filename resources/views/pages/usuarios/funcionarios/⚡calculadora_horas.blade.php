<?php

use Livewire\Component;
use App\Models\User;
use Flux\Flux;

new class extends Component
{
    public ?int $funcionarioId = null;
    
    // Configuración de días de la semana
    public array $dias = [
        'lunes' => ['nombre' => 'Lunes', 'activo' => true, 'entrada' => '08:30', 'break_inicio' => '13:00', 'break_fin' => '14:00', 'salida' => '17:54'],
        'martes' => ['nombre' => 'Martes', 'activo' => true, 'entrada' => '08:30', 'break_inicio' => '13:00', 'break_fin' => '14:00', 'salida' => '17:54'],
        'miercoles' => ['nombre' => 'Miércoles', 'activo' => true, 'entrada' => '08:30', 'break_inicio' => '13:00', 'break_fin' => '14:00', 'salida' => '17:54'],
        'jueves' => ['nombre' => 'Jueves', 'activo' => true, 'entrada' => '08:30', 'break_inicio' => '13:00', 'break_fin' => '14:00', 'salida' => '17:54'],
        'viernes' => ['nombre' => 'Viernes', 'activo' => true, 'entrada' => '08:30', 'break_inicio' => '13:00', 'break_fin' => '14:00', 'salida' => '17:54'],
        'sabado' => ['nombre' => 'Sábado', 'activo' => false, 'entrada' => '09:00', 'break_inicio' => '13:00', 'break_fin' => '13:00', 'salida' => '13:00'],
        'domingo' => ['nombre' => 'Domingo', 'activo' => false, 'entrada' => '09:00', 'break_inicio' => '13:00', 'break_fin' => '13:00', 'salida' => '13:00'],
    ];

    public int $objetivoHoras = 42;

    #[\Livewire\Attributes\Computed]
    public function funcionarios()
    {
        return User::whereHas('schools', function ($q) {
            $q->where('school_id', auth()->user()->current_school_id);
        })->whereDoesntHave('roles', function ($q) {
            $q->where('roles.team_id', auth()->user()->current_school_id)
              ->where('roles.name', 'estudiante');
        })->orderBy('nombres')->get();
    }

    #[\Livewire\Attributes\Computed]
    public function selectedFuncionario()
    {
        return $this->funcionarioId ? User::find($this->funcionarioId) : null;
    }

    // Cargar horarios predefinidos (Presets)
    public function cargarPreset(string $preset): void
    {
        if ($preset === '5dias_42h') {
            $this->dias = [
                'lunes' => ['nombre' => 'Lunes', 'activo' => true, 'entrada' => '08:30', 'break_inicio' => '13:00', 'break_fin' => '14:00', 'salida' => '17:54'],
                'martes' => ['nombre' => 'Martes', 'activo' => true, 'entrada' => '08:30', 'break_inicio' => '13:00', 'break_fin' => '14:00', 'salida' => '17:54'],
                'miercoles' => ['nombre' => 'Miércoles', 'activo' => true, 'entrada' => '08:30', 'break_inicio' => '13:00', 'break_fin' => '14:00', 'salida' => '17:54'],
                'jueves' => ['nombre' => 'Jueves', 'activo' => true, 'entrada' => '08:30', 'break_inicio' => '13:00', 'break_fin' => '14:00', 'salida' => '17:54'],
                'viernes' => ['nombre' => 'Viernes', 'activo' => true, 'entrada' => '08:30', 'break_inicio' => '13:00', 'break_fin' => '14:00', 'salida' => '17:54'],
                'sabado' => ['nombre' => 'Sábado', 'activo' => false, 'entrada' => '09:00', 'break_inicio' => '13:00', 'break_fin' => '13:00', 'salida' => '13:00'],
                'domingo' => ['nombre' => 'Domingo', 'activo' => false, 'entrada' => '09:00', 'break_inicio' => '13:00', 'break_fin' => '13:00', 'salida' => '13:00'],
            ];
            $this->objetivoHoras = 42;
            Flux::toast('Horario estándar de 5 días (42 horas) cargado.', variant: 'success');
        } elseif ($preset === '5dias_40h') {
            $this->dias = [
                'lunes' => ['nombre' => 'Lunes', 'activo' => true, 'entrada' => '08:30', 'break_inicio' => '13:00', 'break_fin' => '14:00', 'salida' => '17:30'],
                'martes' => ['nombre' => 'Martes', 'activo' => true, 'entrada' => '08:30', 'break_inicio' => '13:00', 'break_fin' => '14:00', 'salida' => '17:30'],
                'miercoles' => ['nombre' => 'Miércoles', 'activo' => true, 'entrada' => '08:30', 'break_inicio' => '13:00', 'break_fin' => '14:00', 'salida' => '17:30'],
                'jueves' => ['nombre' => 'Jueves', 'activo' => true, 'entrada' => '08:30', 'break_inicio' => '13:00', 'break_fin' => '14:00', 'salida' => '17:30'],
                'viernes' => ['nombre' => 'Viernes', 'activo' => true, 'entrada' => '08:30', 'break_inicio' => '13:00', 'break_fin' => '14:00', 'salida' => '17:30'],
                'sabado' => ['nombre' => 'Sábado', 'activo' => false, 'entrada' => '09:00', 'break_inicio' => '13:00', 'break_fin' => '13:00', 'salida' => '13:00'],
                'domingo' => ['nombre' => 'Domingo', 'activo' => false, 'entrada' => '09:00', 'break_inicio' => '13:00', 'break_fin' => '13:00', 'salida' => '13:00'],
            ];
            $this->objetivoHoras = 40;
            Flux::toast('Horario estándar de 5 días (40 horas) cargado.', variant: 'success');
        } elseif ($preset === '6dias_42h') {
            $this->dias = [
                'lunes' => ['nombre' => 'Lunes', 'activo' => true, 'entrada' => '08:30', 'break_inicio' => '13:00', 'break_fin' => '14:00', 'salida' => '16:30'],
                'martes' => ['nombre' => 'Martes', 'activo' => true, 'entrada' => '08:30', 'break_inicio' => '13:00', 'break_fin' => '14:00', 'salida' => '16:30'],
                'miercoles' => ['nombre' => 'Miércoles', 'activo' => true, 'entrada' => '08:30', 'break_inicio' => '13:00', 'break_fin' => '14:00', 'salida' => '16:30'],
                'jueves' => ['nombre' => 'Jueves', 'activo' => true, 'entrada' => '08:30', 'break_inicio' => '13:00', 'break_fin' => '14:00', 'salida' => '16:30'],
                'viernes' => ['nombre' => 'Viernes', 'activo' => true, 'entrada' => '08:30', 'break_inicio' => '13:00', 'break_fin' => '14:00', 'salida' => '16:30'],
                'sabado' => ['nombre' => 'Sábado', 'activo' => true, 'entrada' => '08:30', 'break_inicio' => '12:00', 'break_fin' => '12:00', 'salida' => '15:30'],
                'domingo' => ['nombre' => 'Domingo', 'activo' => false, 'entrada' => '09:00', 'break_inicio' => '13:00', 'break_fin' => '13:00', 'salida' => '13:00'],
            ];
            $this->objetivoHoras = 42;
            Flux::toast('Horario de 6 días (42 horas) cargado.', variant: 'success');
        }
    }

    #[\Livewire\Attributes\Computed]
    public function calculo()
    {
        $diasCalculados = [];
        $totalMinutosSemanales = 0;
        $copiaTexto = "";

        foreach ($this->dias as $key => $dia) {
            if (!$dia['activo']) {
                $diasCalculados[$key] = [
                    'nombre' => $dia['nombre'],
                    'activo' => false,
                    'minutos' => 0,
                    'texto' => 'No laboral',
                    'error' => false,
                ];
                $copiaTexto .= "• {$dia['nombre']}: NO LABORAL\n";
                continue;
            }

            // Convertir a minutos
            $entradaMin = $this->timeToMinutes($dia['entrada']);
            $breakInicioMin = $this->timeToMinutes($dia['break_inicio']);
            $breakFinMin = $this->timeToMinutes($dia['break_fin']);
            $salidaMin = $this->timeToMinutes($dia['salida']);

            // Validaciones lógicas
            $error = null;
            if ($entradaMin === null || $breakInicioMin === null || $breakFinMin === null || $salidaMin === null) {
                $error = 'Formato incorrecto';
            } elseif ($breakInicioMin < $entradaMin) {
                $error = 'Colación antes de entrada';
            } elseif ($breakFinMin < $breakInicioMin) {
                $error = 'Fin colación antes de inicio';
            } elseif ($salidaMin < $breakFinMin) {
                $error = 'Salida antes de colación';
            }

            if ($error) {
                $diasCalculados[$key] = [
                    'nombre' => $dia['nombre'],
                    'activo' => true,
                    'minutos' => 0,
                    'texto' => $error,
                    'error' => true,
                ];
                $copiaTexto .= "• {$dia['nombre']}: ERROR: {$error}\n";
                continue;
            }

            // Horario laboral real (tiempo total menos colación)
            $trabajoMin = ($breakInicioMin - $entradaMin) + ($salidaMin - $breakFinMin);
            $totalMinutosSemanales += $trabajoMin;

            $horas = floor($trabajoMin / 60);
            $minutos = $trabajoMin % 60;
            $colacionMinutos = $breakFinMin - $breakInicioMin;
            $colacionTexto = $colacionMinutos > 0 ? "{$colacionMinutos} min" : "sin colación";

            $diasCalculados[$key] = [
                'nombre' => $dia['nombre'],
                'activo' => true,
                'minutos' => $trabajoMin,
                'texto' => "{$horas}h " . str_pad($minutos, 2, '0', STR_PAD_LEFT) . "m",
                'colacion_minutos' => $colacionMinutos,
                'error' => false,
                'limite_excedido' => $trabajoMin > 600, // 10 horas máximo por día en Chile
            ];

            $copiaTexto .= "• {$dia['nombre']}: {$dia['entrada']} a {$dia['salida']} (Colación: {$colacionTexto}) - Trabajado: {$horas}h " . str_pad($minutos, 2, '0', STR_PAD_LEFT) . "m\n";
        }

        $totalHoras = floor($totalMinutosSemanales / 60);
        $totalMinutos = $totalMinutosSemanales % 60;
        $totalHorasDecimal = round($totalMinutosSemanales / 60, 2);

        $diferencia = $totalHorasDecimal - $this->objetivoHoras;
        
        $copiaTexto .= "\nTOTAL SEMANAL TRABAJADO: {$totalHoras}h " . str_pad($totalMinutos, 2, '0', STR_PAD_LEFT) . "m ({$totalHorasDecimal} hrs)\n";
        $copiaTexto .= "Objetivo legal: {$this->objetivoHoras} hrs | Diferencia: " . ($diferencia >= 0 ? "+" : "") . "{$diferencia} hrs";

        return [
            'dias' => $diasCalculados,
            'total_minutos' => $totalMinutosSemanales,
            'total_texto' => "{$totalHoras}h " . str_pad($totalMinutos, 2, '0', STR_PAD_LEFT) . "m",
            'total_decimal' => $totalHorasDecimal,
            'diferencia' => $diferencia,
            'copia_texto' => $copiaTexto,
        ];
    }

    private function timeToMinutes(string $time): ?int
    {
        if (!preg_match('/^[0-2][0-9]:[0-5][0-9]$/', $time)) {
            return null;
        }

        [$horas, $minutos] = explode(':', $time);
        return ((int) $horas * 60) + (int) $minutos;
    }
};

?>

<div class="max-w-7xl mx-auto w-full pb-12 space-y-8">
    <x-header
        :titulo="__('Calculadora de Jornada Laboral (Ley 42 Horas)')"
        :subtitulo="__('Planifica, ajusta y cuadra los horarios del equipo de trabajo según los límites legales.')"
        icono="clock"
    />

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        {{-- Tabla de Configuración de Horas --}}
        <div class="lg:col-span-9 space-y-6">
            <flux:card>
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <flux:heading size="lg">{{ __('Horario de la Jornada Semanal') }}</flux:heading>
                        <flux:subheading size="sm">{{ __('Define las horas de entrada, colación y salida por cada día.') }}</flux:subheading>
                    </div>
                </div>

                <div class="space-y-4">
                    {{-- Encabezado de filas --}}
                    <div class="hidden md:grid grid-cols-12 gap-2 lg:gap-3 pb-2 border-b border-zinc-200 dark:border-zinc-700 text-xs font-bold text-zinc-500 uppercase tracking-wider">
                        <div class="col-span-2">{{ __('Día') }}</div>
                        <div class="col-span-2">{{ __('Entrada') }}</div>
                        <div class="col-span-2">{{ __('Inicio Colación') }}</div>
                        <div class="col-span-2">{{ __('Fin Colación') }}</div>
                        <div class="col-span-2">{{ __('Salida') }}</div>
                        <div class="col-span-2 text-right">{{ __('Subtotal') }}</div>
                    </div>

                    @foreach($dias as $key => $dia)
                        @php
                            $calcDia = $this->calculo['dias'][$key];
                            $estaActivo = $dias[$key]['activo'];
                        @endphp
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-2 lg:gap-3 items-center p-3 rounded-lg transition-colors {{ $estaActivo ? 'bg-zinc-50/50 dark:bg-zinc-800/20' : 'bg-zinc-100/30 dark:bg-zinc-800/5 opacity-60' }}">
                            
                            {{-- Checkbox e Identificador del Día --}}
                            <div class="col-span-12 md:col-span-2 flex items-center gap-3">
                                <flux:checkbox wire:model.live="dias.{{ $key }}.activo" />
                                <span class="font-bold text-zinc-800 dark:text-zinc-200 text-sm">{{ $dia['nombre'] }}</span>
                            </div>

                            @if($estaActivo)
                                {{-- Controles de Hora --}}
                                <div class="col-span-6 md:col-span-2">
                                    <flux:field>
                                        <flux:label class="md:hidden text-[10px] uppercase font-bold text-zinc-400 mb-1">{{ __('Entrada') }}</flux:label>
                                        <flux:time-picker type="input" time-format="24-hour" interval="15" min="07:00" max="20:00" wire:model.live="dias.{{ $key }}.entrada" size="sm" />
                                    </flux:field>
                                </div>
                                <div class="col-span-6 md:col-span-2">
                                    <flux:field>
                                        <flux:label class="md:hidden text-[10px] uppercase font-bold text-zinc-400 mb-1">{{ __('Inicio Colación') }}</flux:label>
                                        <flux:time-picker type="input" time-format="24-hour" interval="15" min="07:00" max="20:00" wire:model.live="dias.{{ $key }}.break_inicio" size="sm" />
                                    </flux:field>
                                </div>
                                <div class="col-span-6 md:col-span-2">
                                    <flux:field>
                                        <flux:label class="md:hidden text-[10px] uppercase font-bold text-zinc-400 mb-1">{{ __('Fin Colación') }}</flux:label>
                                        <flux:time-picker type="input" time-format="24-hour" interval="15" min="07:00" max="20:00" wire:model.live="dias.{{ $key }}.break_fin" size="sm" />
                                    </flux:field>
                                </div>
                                <div class="col-span-6 md:col-span-2">
                                    <flux:field>
                                        <flux:label class="md:hidden text-[10px] uppercase font-bold text-zinc-400 mb-1">{{ __('Salida') }}</flux:label>
                                        <flux:time-picker type="input" time-format="24-hour" interval="15" min="07:00" max="20:00" wire:model.live="dias.{{ $key }}.salida" size="sm" />
                                    </flux:field>
                                </div>

                                {{-- Subtotal del Día --}}
                                <div class="col-span-12 md:col-span-2 text-right flex md:block items-center justify-between mt-2 md:mt-0">
                                    <span class="md:hidden text-xs font-bold text-zinc-500">{{ __('Trabajado:') }}</span>
                                    <div>
                                        @if($calcDia['error'])
                                            <flux:badge color="red" size="sm">{{ $calcDia['texto'] }}</flux:badge>
                                        @else
                                            <div class="flex flex-col items-end gap-1">
                                                <span class="font-mono font-bold text-zinc-800 dark:text-zinc-100 text-sm">
                                                    {{ $calcDia['texto'] }}
                                                </span>
                                                @if($calcDia['limite_excedido'])
                                                    <flux:badge color="red" size="sm" class="text-[9px] uppercase tracking-wider font-extrabold">
                                                        {{ __('Excede 10h') }}
                                                    </flux:badge>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @else
                                <div class="col-span-10 text-zinc-400 dark:text-zinc-500 italic text-sm text-center md:text-left py-1">
                                    {{ __('Día no laboral para este funcionario') }}
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </flux:card>

            {{-- Alerta informativa de legislación en Chile --}}
            <flux:card class="bg-[#00376e]/5 border-[#00376e]/20 dark:bg-blue-950/10 dark:border-blue-900/20">
                <div class="flex gap-4">
                    <div class="text-[#00376e] dark:text-blue-400">
                        <flux:icon.exclamation-circle class="size-6" />
                    </div>
                    <div>
                        <flux:heading size="md" class="text-[#00376e] dark:text-blue-300">{{ __('Normativa de Jornada Laboral (Chile)') }}</flux:heading>
                        <flux:text class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                            {{ __('La nueva ley reduce la jornada semanal ordinaria de trabajo a 42 horas en este tramo del año (con límite final en 40 horas). Adicionalmente, el código del trabajo dictamina que la jornada ordinaria no puede exceder las 10 horas diarias de trabajo efectivo, y el tiempo mínimo legal de colación debe ser de al menos 30 minutos (los cuales no son imputables a la jornada de trabajo).') }}
                        </flux:text>
                    </div>
                </div>
            </flux:card>
        </div>

        {{-- Panel Lateral de Totales y Ajustes --}}
        <div class="lg:col-span-3 space-y-6">
            {{-- Tarjeta de Asignación / Selección --}}
            <flux:card>
                <flux:heading size="md" class="mb-4">{{ __('Funcionario (Opcional)') }}</flux:heading>
                <flux:field>
                    <flux:label>{{ __('Selecciona un funcionario para asociar el horario') }}</flux:label>
                    <flux:select wire:model.live="funcionarioId" placeholder="Seleccionar...">
                        <flux:select.option value="">{{ __('--- Ninguno (Solo simular) ---') }}</flux:select.option>
                        @foreach($this->funcionarios as $func)
                            <flux:select.option value="{{ $func->id }}">{{ $func->nombres }} {{ $func->apellido_pat }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>
            </flux:card>

            {{-- Tarjeta de Presets Rápidos --}}
            <flux:card>
                <flux:heading size="md" class="mb-4">{{ __('Configuraciones Rápidas') }}</flux:heading>
                <div class="flex flex-col gap-2">
                    <flux:button variant="subtle" size="sm" icon="calendar" wire:click="cargarPreset('5dias_42h')">
                        {{ __('5 días a la semana (42 hrs)') }}
                    </flux:button>
                    <flux:button variant="subtle" size="sm" icon="calendar" wire:click="cargarPreset('6dias_42h')">
                        {{ __('6 días a la semana (42 hrs)') }}
                    </flux:button>
                    <flux:button variant="subtle" size="sm" icon="clock" wire:click="cargarPreset('5dias_40h')">
                        {{ __('Estándar 40 horas') }}
                    </flux:button>
                </div>
            </flux:card>

            {{-- Tarjeta del Resumen de Cálculo --}}
            @php
                $resumen = $this->calculo;
                $diferencia = $resumen['diferencia'];
                $totalDecimal = $resumen['total_decimal'];
            @endphp
            <flux:card class="relative overflow-hidden">
                <div class="space-y-6">
                    <div class="text-center">
                        <span class="text-xs font-bold uppercase tracking-widest text-zinc-400">{{ __('Jornada Semanal Total') }}</span>
                        
                        @if($this->selectedFuncionario)
                            <p class="text-sm font-bold text-[#00376e] dark:text-blue-400 mt-1 truncate">
                                {{ $this->selectedFuncionario->nombres }} {{ $this->selectedFuncionario->apellido_pat }}
                            </p>
                        @endif

                        <div class="text-5xl font-black mt-2 font-mono text-[#00376e] dark:text-blue-400">
                            {{ $resumen['total_texto'] }}
                        </div>
                        <div class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                            {{ $totalDecimal }} {{ __('horas decimales') }}
                        </div>
                    </div>

                    {{-- Caja del Objetivo --}}
                    <div class="border-t border-b border-zinc-200 dark:border-zinc-700 py-4 flex items-center justify-between text-sm">
                        <div class="flex flex-col">
                            <span class="font-bold text-zinc-700 dark:text-zinc-300">{{ __('Límite / Objetivo') }}</span>
                            <span class="text-xs text-zinc-400">{{ __('Establecido por Ley') }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:input type="number" wire:model.live="objetivoHoras" class="w-16 text-center font-mono font-bold" min="1" max="60" />
                            <span class="font-bold text-zinc-600 dark:text-zinc-400">hrs</span>
                        </div>
                    </div>

                    {{-- Estado de la diferencia --}}
                    <div class="flex flex-col items-center gap-2">
                        @if($diferencia === 0.0)
                            <flux:badge color="green" class="w-full text-center py-2 text-xs uppercase tracking-wider font-extrabold flex justify-center gap-2">
                                <flux:icon.check-circle class="size-4" />
                                {{ __('Cuadra Perfectamente') }}
                            </flux:badge>
                            <p class="text-xs text-center text-zinc-500">
                                {{ __('El horario simulado cumple con el límite legal seleccionado.') }}
                            </p>
                        @elseif($diferencia > 0)
                            <flux:badge color="red" class="w-full text-center py-2 text-xs uppercase tracking-wider font-extrabold flex justify-center gap-2">
                                <flux:icon.exclamation-triangle class="size-4" />
                                {{ __('Exceso de ') }} {{ $diferencia }} {{ __(' hrs') }}
                            </flux:badge>
                            <p class="text-xs text-center text-red-500 font-semibold">
                                {{ __('¡Alerta! Se están trabajando horas de más sobre el límite ordinario.') }}
                            </p>
                        @else
                            <flux:badge color="blue" class="w-full text-center py-2 text-xs uppercase tracking-wider font-extrabold flex justify-center gap-2">
                                <flux:icon.information-circle class="size-4" />
                                {{ __('Faltan ') }} {{ abs($diferencia) }} {{ __(' hrs') }}
                            </flux:badge>
                            <p class="text-xs text-center text-zinc-500">
                                {{ __('El total está por debajo del límite de horas configurado.') }}
                            </p>
                        @endif
                    </div>
                </div>
            </flux:card>

            {{-- Caja de Copiar Resumen en Texto --}}
            <flux:card x-data="{ 
                text: @entangled('calculo.copia_texto'),
                copied: false,
                copyToClipboard() {
                    navigator.clipboard.writeText(this.text);
                    this.copied = true;
                    setTimeout(() => this.copied = false, 2000);
                }
            }">
                <div class="flex items-center justify-between mb-3">
                    <flux:heading size="sm">{{ __('Resumen para Compartir') }}</flux:heading>
                    <flux:button variant="ghost" size="sm" icon="document-duplicate" class="text-blue-600" x-on:click="copyToClipboard">
                        <span x-text="copied ? '¡Copiado!' : 'Copiar'"></span>
                    </flux:button>
                </div>
                <textarea 
                    class="w-full h-40 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg p-2 font-mono text-xs text-zinc-600 dark:text-zinc-300 select-all" 
                    readonly
                    x-text="text"
                ></textarea>
            </flux:card>
        </div>
    </div>
</div>