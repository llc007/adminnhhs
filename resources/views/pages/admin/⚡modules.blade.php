<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Flux\Flux;

new #[Title('Módulos Publicados')] class extends Component {
    public array $modulos = [];

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        if (! Auth::user()->hasRole(['administrador', 'directivo', 'superadmin'])) {
            abort(403);
        }

        $school = Auth::user()->currentSchool;
        
        if ($school) {
            $this->modulos = $school->modulos_publicados;
        } else {
            $this->modulos = [
                'entrevistas' => true,
                'estudiantes' => true,
                'adquisiciones' => true,
                'prestamos' => true,
                'envio_correos' => true,
                'modo_mantenimiento' => false,
                'mensaje_mantenimiento' => 'El sistema se encuentra en mantenimiento programado. Volveremos en breve.',
            ];
        }

        // Default values if keys don't exist yet
        $this->modulos['modo_mantenimiento'] = filter_var($this->modulos['modo_mantenimiento'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $this->modulos['mensaje_mantenimiento'] = $this->modulos['mensaje_mantenimiento'] ?? 'El sistema se encuentra en mantenimiento programado. Volveremos en breve.';
    }

    /**
     * Save the modules configuration.
     */
    public function save(): void
    {
        if (! Auth::user()->hasRole(['administrador', 'directivo', 'superadmin'])) {
            abort(403);
        }

        $school = Auth::user()->currentSchool;

        if (!$school) {
            Flux::toast(
                heading: 'Error',
                text: 'No tienes un colegio activo seleccionado.',
                variant: 'danger'
            );
            return;
        }

        $isMaintenanceMode = filter_var($this->modulos['modo_mantenimiento'] ?? false, FILTER_VALIDATE_BOOLEAN);

        // Keep boolean types explicitly when saving
        $school->modulos_publicados = [
            'entrevistas' => filter_var($this->modulos['entrevistas'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'estudiantes' => filter_var($this->modulos['estudiantes'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'adquisiciones' => filter_var($this->modulos['adquisiciones'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'prestamos' => filter_var($this->modulos['prestamos'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'envio_correos' => filter_var($this->modulos['envio_correos'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'modo_mantenimiento' => $isMaintenanceMode,
            'mensaje_mantenimiento' => trim($this->modulos['mensaje_mantenimiento'] ?? 'El sistema se encuentra en mantenimiento programado. Volveremos en breve.'),
        ];
        $school->save();

        Flux::toast(
            heading: 'Cambios guardados',
            text: $isMaintenanceMode 
                ? 'El Modo Mantenimiento ha sido ACTIVADO. Solo Administradores podrán ingresar.'
                : 'La configuración de módulos ha sido actualizada correctamente.',
            variant: $isMaintenanceMode ? 'warning' : 'success'
        );
    }
}; ?>

<div class="max-w-7xl mx-auto w-full pb-12 space-y-8">
    <x-header
        :titulo="__('Módulos Publicados')"
        :subtitulo="__('Administra las vistas y accesos de los módulos para docentes y funcionarios.')"
        icono="adjustments-horizontal"
    />

    <form wire:submit="save" class="space-y-8">
        {{-- Mantenimiento del Sistema --}}
        <div class="rounded-xl shadow-sm border overflow-hidden transition-all duration-300 {{ ($modulos['modo_mantenimiento'] ?? false) ? 'bg-amber-500/10 border-amber-400 dark:border-amber-700' : 'bg-white dark:bg-zinc-900 border-slate-100 dark:border-zinc-800' }}">
            <div class="p-6 border-b border-zinc-200/50 dark:border-zinc-800/50 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="size-10 rounded-xl flex items-center justify-center {{ ($modulos['modo_mantenimiento'] ?? false) ? 'bg-amber-500 text-white animate-bounce' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400' }}">
                        <flux:icon.wrench-screwdriver class="size-5" />
                    </div>
                    <div>
                        <h3 class="font-headline text-lg font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                            {{ __('Modo Mantenimiento del Sistema') }}
                            @if($modulos['modo_mantenimiento'] ?? false)
                                <span class="px-2 py-0.5 text-[10px] font-extrabold uppercase bg-amber-500 text-white rounded-full tracking-wider animate-pulse">
                                    ¡ACTIVADO!
                                </span>
                            @endif
                        </h3>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('Bloquea el acceso temporal a todos los usuarios generales (docentes, apoderados, etc.) mostrando un aviso interactivo.') }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3 bg-white dark:bg-zinc-800 p-3 rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <flux:switch wire:model.live="modulos.modo_mantenimiento" />
                    <span class="text-xs font-bold {{ ($modulos['modo_mantenimiento'] ?? false) ? 'text-amber-600 dark:text-amber-400' : 'text-zinc-600 dark:text-zinc-400' }}">
                        {{ ($modulos['modo_mantenimiento'] ?? false) ? __('MANTENIMIENTO ACTIVO') : __('Mantenimiento Desactivado') }}
                    </span>
                </div>
            </div>

            <div class="p-6 space-y-4">
                <flux:field>
                    <flux:label class="font-bold text-xs uppercase tracking-wider text-zinc-500">{{ __('Mensaje de Mantenimiento para los Usuarios') }}</flux:label>
                    <flux:input 
                        wire:model="modulos.mensaje_mantenimiento" 
                        placeholder="Ej: El sistema se encuentra en mantenimiento programado. Volveremos en breve." 
                    />
                    <flux:description class="text-xs">Este mensaje se desplegará en pantalla completa cuando los usuarios intenten ingresar a la plataforma.</flux:description>
                </flux:field>

                <div class="p-3 rounded-xl bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800 text-xs text-blue-900 dark:text-blue-200 flex items-start gap-2">
                    <flux:icon.information-circle class="size-4 shrink-0 text-blue-600 dark:text-blue-400 mt-0.5" />
                    <div>
                        <span class="font-bold block mb-0.5">Acceso Exclusivo para Administradores:</span>
                        Mientras el Modo Mantenimiento esté activo, únicamente el Superadministrador y Administrador podrán continuar navegando para realizar ajustes o pruebas.
                    </div>
                </div>
            </div>
        </div>

        {{-- Visibilidad de Módulos Generales --}}
        <div class="bg-surface-container-lowest rounded-xl shadow-sm border border-slate-100 dark:border-zinc-800 overflow-hidden bg-white dark:bg-zinc-900">
            <div class="p-8 border-b border-slate-50 dark:border-zinc-800/50 bg-white dark:bg-zinc-900">
                <h3 class="font-headline text-xl font-bold text-primary dark:text-zinc-100">{{ __('Visibilidad de Módulos') }}</h3>
                <p class="text-secondary dark:text-zinc-400 text-sm">{{ __('Selecciona qué módulos y vistas estarán visibles en la barra lateral para los docentes y funcionarios generales.') }}</p>
            </div>
            
            <div class="p-8 space-y-8 bg-white dark:bg-zinc-900">
                <div class="space-y-6">
                    <flux:field variant="inline">
                        <flux:switch wire:model="modulos.entrevistas" />
                        <div>
                            <flux:label class="font-bold">{{ __('Entrevistas') }}</flux:label>
                            <flux:description>{{ __('Permite agendar y ver el historial de entrevistas de apoderados.') }}</flux:description>
                        </div>
                    </flux:field>

                    <flux:separator variant="subtle" />

                    <flux:field variant="inline">
                        <flux:switch wire:model="modulos.estudiantes" />
                        <div>
                            <flux:label class="font-bold">{{ __('Gestión Académica (Estudiantes)') }}</flux:label>
                            <flux:description>{{ __('Permite ver la lista y fichas de los estudiantes.') }}</flux:description>
                        </div>
                    </flux:field>

                    <flux:separator variant="subtle" />

                    <flux:field variant="inline">
                        <flux:switch wire:model="modulos.adquisiciones" />
                        <div>
                            <flux:label class="font-bold">{{ __('Adquisiciones') }}</flux:label>
                            <flux:description>{{ __('Permite solicitar adquisiciones e insumos.') }}</flux:description>
                        </div>
                    </flux:field>

                    <flux:separator variant="subtle" />

                    <flux:field variant="inline">
                        <flux:switch wire:model="modulos.prestamos" />
                        <div>
                            <flux:label class="font-bold">{{ __('Préstamos de Informática') }}</flux:label>
                            <flux:description>{{ __('Permite a los funcionarios visualizar sus préstamos activos e históricos.') }}</flux:description>
                        </div>
                    </flux:field>

                    <flux:separator variant="subtle" />

                    <flux:field variant="inline">
                        <flux:switch wire:model="modulos.envio_correos" />
                        <div>
                            <flux:label class="font-bold">{{ __('Envío de Correos Electrónicos') }}</flux:label>
                            <flux:description>{{ __('Habilita o deshabilita el envío de notificaciones automáticas por correo para este colegio.') }}</flux:description>
                        </div>
                    </flux:field>
                </div>

                <div class="flex justify-end gap-2 border-t border-zinc-100 dark:border-zinc-800 pt-6">
                    <flux:button type="submit" variant="primary" class="px-6 bg-[#00376e] hover:bg-blue-800 text-white font-bold">{{ __('Guardar Cambios') }}</flux:button>
                </div>
            </div>
        </div>
    </form>
</div>
