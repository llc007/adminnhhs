<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use App\Models\MailLog;

new #[Title('Auditoría de Correos')] class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filtroStatus = 'todos';
    public bool $envioCorreosHabilitado = true;
    
    // Modal de Detalle
    public bool $modalDetalle = false;
    public ?int $selectedLogId = null;

    public function mount(): void
    {
        $school = auth()->user()->currentSchool;
        if ($school) {
            $this->envioCorreosHabilitado = (bool) ($school->modulos_publicados['envio_correos'] ?? true);
        }
    }

    public function updatedEnvioCorreosHabilitado(bool $value): void
    {
        $school = auth()->user()->currentSchool;
        if ($school) {
            $modulos = $school->modulos_publicados;
            $modulos['envio_correos'] = $value;
            $school->modulos_publicados = $modulos;
            $school->save();

            Flux::toast(
                heading: $value ? __('Correos Habilitados') : __('Correos Deshabilitados'),
                text: $value 
                    ? __('El envío de correos y notificaciones automáticas ha sido activado.') 
                    : __('El envío de correos ha sido desactivado temporalmente.'),
                variant: $value ? 'success' : 'warning'
            );
        }
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedFiltroStatus()
    {
        $this->resetPage();
    }

    public function verDetalle(int $id): void
    {
        $this->selectedLogId = $id;
        $this->modalDetalle = true;
    }

    #[\Livewire\Attributes\Computed]
    public function selectedLog()
    {
        return $this->selectedLogId ? MailLog::find($this->selectedLogId) : null;
    }

    #[\Livewire\Attributes\Computed]
    public function mailLogs()
    {
        return MailLog::query()
            ->when(trim($this->search) !== '', function ($query) {
                $search = trim($this->search);
                $query->where(function ($q) use ($search) {
                    $q->where('to', 'like', "%{$search}%")
                      ->orWhere('subject', 'like', "%{$search}%")
                      ->orWhere('mail_id', 'like', "%{$search}%");
                });
            })
            ->when($this->filtroStatus !== 'todos', function ($query) {
                $query->where('status', $this->filtroStatus);
            })
            ->orderBy('sent_at', 'desc')
            ->paginate(15);
    }
};
?>

<div class="max-w-7xl mx-auto w-full pb-12 space-y-8">
    <x-header
        :titulo="__('Auditoría de Correos Enviados')"
        :subtitulo="__('Monitorea y diagnostica los correos electrónicos emitidos por la plataforma, incluyendo rebotes y fallos.')"
        icono="envelope"
    />

    {{-- Filtros y Buscador --}}
    <flux:card>
        <div class="flex flex-col md:flex-row gap-4 items-end justify-between w-full">
            <flux:field class="flex-1 w-full">
                <flux:label>{{ __('Buscar Correo') }}</flux:label>
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Buscar por destinatario, asunto o Message-ID...')" />
            </flux:field>

            <div class="h-10 w-px bg-zinc-200 dark:bg-zinc-700 hidden md:block"></div>

            <flux:field class="shrink-0 w-full md:w-auto">
                <div class="flex items-center gap-3 h-10">
                    <flux:switch wire:model.live="envioCorreosHabilitado" />
                    <div>
                        <flux:label class="font-bold cursor-pointer" wire:click="$toggle('envioCorreosHabilitado')">
                            {{ __('Envío de Correos') }}
                        </flux:label>
                        <flux:description class="text-xs">
                            {{ __('Activar/Desactivar notificaciones globales') }}
                        </flux:description>
                    </div>
                </div>
            </flux:field>

            <div class="h-10 w-px bg-zinc-200 dark:bg-zinc-700 hidden md:block"></div>

            <flux:field class="w-full md:w-64">
                <flux:label>{{ __('Filtrar por Estado') }}</flux:label>
                <flux:select wire:model.live="filtroStatus">
                    <flux:select.option value="todos">{{ __('Todos los estados') }}</flux:select.option>
                    <flux:select.option value="sent">{{ __('Enviado (Sent)') }}</flux:select.option>
                    <flux:select.option value="delivered">{{ __('Entregado (Delivered)') }}</flux:select.option>
                    <flux:select.option value="bounced">{{ __('Rebotado (Bounced)') }}</flux:select.option>
                    <flux:select.option value="failed">{{ __('Fallido (Failed)') }}</flux:select.option>
                </flux:select>
            </flux:field>
        </div>
    </flux:card>

    {{-- Listado de Correos --}}
    <flux:card>
        <flux:table :paginate="$this->mailLogs">
            <flux:table.columns>
                <flux:table.column>{{ __('Estado') }}</flux:table.column>
                <flux:table.column>{{ __('Destinatario') }}</flux:table.column>
                <flux:table.column>{{ __('Asunto') }}</flux:table.column>
                <flux:table.column>{{ __('Message-ID') }}</flux:table.column>
                <flux:table.column>{{ __('Fecha Envío') }}</flux:table.column>
                <flux:table.column class="text-right"></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($this->mailLogs as $log)
                    <flux:table.row :key="$log->id">
                        <flux:table.cell>
                            @if($log->status === 'delivered')
                                <flux:badge color="green" icon="check-circle" size="sm">{{ __('Entregado') }}</flux:badge>
                            @elseif($log->status === 'sent')
                                <flux:badge color="blue" icon="paper-airplane" size="sm">{{ __('Enviado') }}</flux:badge>
                            @elseif($log->status === 'bounced')
                                <flux:badge color="orange" icon="exclamation-triangle" size="sm">{{ __('Rebotado') }}</flux:badge>
                            @elseif($log->status === 'failed')
                                <flux:badge color="red" icon="x-circle" size="sm">{{ __('Fallido') }}</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">{{ $log->status }}</flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell class="font-medium text-zinc-800 dark:text-zinc-200">
                            {{ $log->to }}
                        </flux:table.cell>

                        <flux:table.cell class="max-w-xs truncate">
                            {{ $log->subject }}
                        </flux:table.cell>

                        <flux:table.cell class="font-mono text-[10px] text-zinc-500">
                            {{ $log->mail_id ?: '-' }}
                        </flux:table.cell>

                        <flux:table.cell class="text-xs text-zinc-600 dark:text-zinc-400">
                            {{ $log->sent_at->format('d M Y, H:i') }}
                        </flux:table.cell>

                        <flux:table.cell class="text-right">
                            <flux:button variant="ghost" size="sm" icon="eye" wire:click="verDetalle({{ $log->id }})">
                                {{ __('Ver') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center py-12 text-zinc-400">
                            {{ __('No se encontraron registros de correos.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    {{-- Modal de Detalle de Correo --}}
    <flux:modal wire:model="modalDetalle" class="md:w-3xl">
        @if($this->selectedLog)
            @php $selected = $this->selectedLog; @endphp
            <div class="space-y-6">
                <div class="flex items-start justify-between border-b pb-4 dark:border-zinc-700">
                    <div>
                        <flux:heading size="lg">{{ __('Detalle del Correo Enviado') }}</flux:heading>
                        <flux:subheading size="sm" class="mt-1">
                            {{ __('Enviado el') }} {{ $selected->sent_at->format('d/m/Y a las H:i:s') }}
                        </flux:subheading>
                    </div>
                    <div>
                        @if($selected->status === 'delivered')
                            <flux:badge color="green" icon="check-circle">{{ __('Entregado') }}</flux:badge>
                        @elseif($selected->status === 'sent')
                            <flux:badge color="blue" icon="paper-airplane">{{ __('Enviado') }}</flux:badge>
                        @elseif($selected->status === 'bounced')
                            <flux:badge color="orange" icon="exclamation-triangle">{{ __('Rebotado') }}</flux:badge>
                        @elseif($selected->status === 'failed')
                            <flux:badge color="red" icon="x-circle">{{ __('Fallido') }}</flux:badge>
                        @endif
                    </div>
                </div>

                {{-- Detalles rápidos --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm bg-zinc-50 dark:bg-zinc-800/30 p-4 rounded-xl">
                    <div>
                        <span class="block text-xs font-bold text-zinc-400 uppercase tracking-widest">{{ __('Destinatario') }}</span>
                        <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $selected->to }}</span>
                    </div>
                    <div>
                        <span class="block text-xs font-bold text-zinc-400 uppercase tracking-widest">{{ __('Asunto') }}</span>
                        <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $selected->subject }}</span>
                    </div>
                    <div class="md:col-span-2">
                        <span class="block text-xs font-bold text-zinc-400 uppercase tracking-widest">{{ __('Message-ID / Identificador Único') }}</span>
                        <span class="font-mono text-xs text-zinc-600 dark:text-zinc-400">{{ $selected->mail_id ?: '(No disponible)' }}</span>
                    </div>
                </div>

                {{-- Detalle del Error si falló o rebotó --}}
                @if($selected->error_message)
                    <div class="p-4 bg-red-50 dark:bg-red-950/10 border border-red-200 dark:border-red-900/20 rounded-xl">
                        <div class="flex gap-3 text-red-700 dark:text-red-400">
                            <flux:icon.exclamation-circle class="size-5 shrink-0" />
                            <div>
                                <h4 class="font-bold text-sm">{{ __('Error reportado en la entrega:') }}</h4>
                                <p class="text-xs mt-1 font-mono bg-red-100/50 dark:bg-red-950/20 p-2 rounded">
                                    {{ $selected->error_message }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Cuerpo del Correo en Iframe --}}
                <div>
                    <span class="block text-xs font-bold text-zinc-400 uppercase tracking-widest mb-2">{{ __('Contenido del Mensaje') }}</span>
                    <div class="border rounded-xl overflow-hidden shadow-inner bg-white dark:bg-zinc-900">
                        <iframe 
                            srcdoc="{{ $selected->body }}" 
                            class="w-full h-[400px] border-none"
                            sandbox="allow-same-origin"
                        ></iframe>
                    </div>
                </div>

                <div class="flex justify-end pt-4 border-t dark:border-zinc-700">
                    <flux:button wire:click="$set('modalDetalle', false)" variant="ghost">
                        {{ __('Cerrar') }}
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>