<?php

use Livewire\Component;

new class extends Component {
    public string $title = 'Bitácora de Entrevista';
    public string $studentName = 'Bastián Rojas';
    public string $parentName = 'Emily Rojas';
    public string $date = '24 de Octubre, 2023';
    public string $reason = 'Revisión de Progreso Académico';
};
?>

<div class="space-y-8">
    <div class="flex items-center gap-2 text-sm">
        <flux:text class="text-zinc-500">Entrevistas</flux:text>
        <flux:icon name="chevron-right" variant="mini" class="text-zinc-400" />
        <flux:text class="text-zinc-500">Bitácora Digital</flux:text>
        <flux:icon name="chevron-right" variant="mini" class="text-zinc-400" />
        <flux:text class="font-bold text-zinc-900 dark:text-zinc-100">Registro #402</flux:text>
    </div>

    <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4">
        <div class="flex flex-col gap-1">
            <flux:heading size="xl" level="1">Registro de Bitácora de Entrevista</flux:heading>
            <flux:subheading>Registre los detalles y las acciones de seguimiento de la reunión con el apoderado.
            </flux:subheading>
        </div>
        <div class="flex gap-3">
            <flux:button variant="ghost">Descartar</flux:button>
            <flux:button variant="primary" icon="document-check" class="shadow-lg shadow-primary/20">
                Guardar Bitácora
            </flux:button>
        </div>
    </div>

    {{-- Info Bar --}}
    <flux:card class="!p-0 overflow-hidden">
        <div
            class="grid grid-cols-1 md:grid-cols-4 divide-y md:divide-y-0 md:divide-x divide-zinc-100 dark:divide-zinc-800">
            <div class="p-5 space-y-1">
                <flux:text size="xs" class="font-bold uppercase tracking-wider text-zinc-400">Estudiante
                </flux:text>
                <flux:text class="font-bold text-zinc-900 dark:text-zinc-100">{{ $studentName }}</flux:text>
            </div>
            <div class="p-5 space-y-1">
                <flux:text size="xs" class="font-bold uppercase tracking-wider text-zinc-400">Apoderado/Tutor
                </flux:text>
                <flux:text class="font-bold text-zinc-900 dark:text-zinc-100">{{ $parentName }}</flux:text>
            </div>
            <div class="p-5 space-y-1">
                <flux:text size="xs" class="font-bold uppercase tracking-wider text-zinc-400">Fecha</flux:text>
                <flux:text class="font-bold text-zinc-900 dark:text-zinc-100">{{ $date }}</flux:text>
            </div>
            <div class="p-5 space-y-1">
                <flux:text size="xs" class="font-bold uppercase tracking-wider text-zinc-400">Motivo</flux:text>
                <flux:text class="font-bold text-zinc-900 dark:text-zinc-100">{{ $reason }}</flux:text>
            </div>
        </div>
    </flux:card>

    {{-- Notes Area --}}
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <flux:heading level="2" size="lg" class="flex items-center gap-2">
                <flux:icon name="pencil-square" variant="mini" class="text-primary" />
                Detalles y Notas de la Entrevista
            </flux:heading>
            <flux:text size="xs" class="text-zinc-400">Último guardado hace 2 mins</flux:text>
        </div>

        <flux:card class="space-y-4">
            {{-- Toolbar placeholder --}}
            <div
                class="flex items-center gap-1 p-1 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg border border-zinc-100 dark:border-zinc-800">
                <flux:button variant="ghost" size="sm" icon="bold" />
                <flux:button variant="ghost" size="sm" icon="italic" />
                <flux:separator vertical class="mx-1 h-5" />
                <flux:button variant="ghost" size="sm" icon="list-bullet" />
                <flux:button variant="ghost" size="sm" icon="list-bullet" />
                <flux:separator vertical class="mx-1 h-5" />
                <flux:button variant="ghost" size="sm" icon="link" />
            </div>

            <flux:textarea placeholder="Comience a escribir los detalles de la entrevista aquí..." rows="12"
                class="border-none shadow-none focus:ring-0 text-base" resize="vertical" />
        </flux:card>
    </div>

    {{-- Follow-up Actions --}}
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <flux:heading level="2" size="lg" class="flex items-center gap-2">
                <flux:icon name="list-bullet" variant="mini" class="text-primary" />
                Acciones de Seguimiento
            </flux:heading>
            <flux:button variant="ghost" size="sm" icon="plus" class="text-primary font-bold">
                Agregar Acción
            </flux:button>
        </div>

        <div class="grid grid-cols-1 gap-3">
            <flux:card class="flex items-center gap-4 p-4 hover:border-primary/30 transition-colors">
                <flux:checkbox />
                <div class="flex-1">
                    <flux:text class="font-bold">Programar sesión complementaria de matemáticas para Bastián</flux:text>
                    <flux:text size="xs" class="text-zinc-400">Vence el 1 de Nov, 2023 • Asignado a: Sr. Figueroa
                    </flux:text>
                </div>
                <flux:button variant="ghost" icon="trash" size="sm" class="text-zinc-400 hover:text-red-500" />
            </flux:card>

            <flux:card class="flex items-center gap-4 p-4 hover:border-primary/30 transition-colors">
                <flux:checkbox />
                <div class="flex-1">
                    <flux:text class="font-bold">Enviar copias digitales del currículo actual a la Sra. Rojas
                    </flux:text>
                    <flux:text size="xs" class="text-zinc-400">Vence mañana • Asignado a: Mí mismo</flux:text>
                </div>
                <flux:button variant="ghost" icon="trash" size="sm" class="text-zinc-400 hover:text-red-500" />
            </flux:card>
        </div>
    </div>

    {{-- Completion Banner --}}
    <flux:card class="bg-primary/5 dark:bg-primary/10 border-primary/20 p-6">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-6">
            <div class="flex items-center gap-4">
                <div
                    class="size-12 bg-primary rounded-full flex items-center justify-center text-white shadow-lg shadow-primary/20">
                    <flux:icon name="check-badge" variant="mini" size="lg" />
                </div>
                <div>
                    <flux:text class="font-bold text-zinc-900 dark:text-zinc-100 text-lg">¿Finalizar registro de
                        bitácora?</flux:text>
                    <flux:text size="sm" class="text-zinc-500">Esto bloqueará el registro para futuras ediciones
                        y notificará a la administración.</flux:text>
                </div>
            </div>
            <div class="flex gap-4 w-full sm:w-auto">
                <flux:button variant="ghost" class="flex-1 sm:flex-none">Guardar como Borrador</flux:button>
                <flux:button variant="primary" class="flex-1 sm:flex-none px-8 shadow-lg shadow-primary/20">Marcar
                    como Completada</flux:button>
            </div>
        </div>
    </flux:card>
</div>
