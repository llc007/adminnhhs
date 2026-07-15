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
            ];
        }
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

        // Keep boolean types explicitly when saving
        $school->modulos_publicados = [
            'entrevistas' => filter_var($this->modulos['entrevistas'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'estudiantes' => filter_var($this->modulos['estudiantes'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'adquisiciones' => filter_var($this->modulos['adquisiciones'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'prestamos' => filter_var($this->modulos['prestamos'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'envio_correos' => filter_var($this->modulos['envio_correos'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ];
        $school->save();

        Flux::toast(
            heading: 'Cambios guardados',
            text: 'La visibilidad de los módulos ha sido actualizada correctamente.',
            variant: 'success'
        );
    }
}; ?>

<div class="max-w-7xl mx-auto w-full pb-12 space-y-8">
    <x-header
        :titulo="__('Módulos Publicados')"
        :subtitulo="__('Administra las vistas y accesos de los módulos para docentes y funcionarios.')"
        icono="adjustments-horizontal"
    />

    <div class="bg-surface-container-lowest rounded-xl shadow-sm border border-slate-100 dark:border-zinc-800 overflow-hidden mb-8 bg-white dark:bg-zinc-900">
        <div class="p-8 border-b border-slate-50 dark:border-zinc-800/50 bg-white dark:bg-zinc-900">
            <h3 class="font-headline text-xl font-bold text-primary dark:text-zinc-100">{{ __('Visibilidad de Módulos') }}</h3>
            <p class="text-secondary dark:text-zinc-400 text-sm">{{ __('Selecciona qué módulos y vistas estarán visibles en la barra lateral para los docentes y funcionarios generales.') }}</p>
        </div>
        
        <form wire:submit="save" class="p-8 space-y-8 bg-white dark:bg-zinc-900">
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
                <flux:button type="submit" variant="primary" class="px-6">{{ __('Guardar') }}</flux:button>
            </div>
        </form>
    </div>
</div>
