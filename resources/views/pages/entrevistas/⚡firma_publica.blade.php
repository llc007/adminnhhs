<?php

use Livewire\Component;
use App\Models\Bitacora;
use Flux\Flux;

new class extends Component {
    public string $token = '';
    public ?Bitacora $bitacora = null;
    public bool $tokenValido = false;
    public bool $yaFirmada = false;
    public bool $exitoso = false;

    public string $firmanteNombre = '';
    public string $firmanteRutNumero = '';
    public string $firmanteRutDv = '';
    public string $firmaSvg = '';

    public function mount(string $token)
    {
        $this->token = $token;

        $this->bitacora = Bitacora::with(['entrevista.estudiante.curso', 'entrevista.user'])
            ->where('firma_token', $token)
            ->first();

        if (! $this->bitacora) {
            $this->tokenValido = false;
            return;
        }

        if ($this->bitacora->firma_token_expires_at && $this->bitacora->firma_token_expires_at->isPast()) {
            $this->tokenValido = false;
            return;
        }

        $this->tokenValido = true;

        if ($this->bitacora->estado_firma !== 'pendiente') {
            $this->yaFirmada = true;
        }

        $estudiante = $this->bitacora->entrevista->estudiante;

        $this->firmanteNombre = $this->bitacora->firmante_nombre 
            ?: ($estudiante ? ($estudiante->apoderado_nombres ?? '') : '');

        $this->firmanteRutNumero = $this->bitacora->firmante_rut 
            ?: ($estudiante ? ($estudiante->apoderado_rut_numero ?? '') : '');

        $this->firmanteRutDv = $estudiante ? ($estudiante->apoderado_rut_dv ?? '') : '';
    }

    public function firmar()
    {
        if (! $this->tokenValido || $this->yaFirmada) {
            return;
        }

        $this->validate([
            'firmanteNombre' => 'required|string|min:3|max:255',
            'firmanteRutNumero' => 'required|string|min:7|max:12',
        ], [
            'firmanteNombre.required' => 'Debe ingresar el nombre del firmante.',
            'firmanteRutNumero.required' => 'Debe ingresar el RUT del firmante.',
        ]);

        $rutCompleto = trim($this->firmanteRutNumero);
        if ($this->firmanteRutDv !== '') {
            $rutCompleto .= '-' . strtoupper($this->firmanteRutDv);
        }

        $this->bitacora->update([
            'estado_firma' => 'firmada_online',
            'firmante_nombre' => mb_strtoupper($this->firmanteNombre, 'UTF-8'),
            'firmante_rut' => $rutCompleto,
            'firma_svg' => $this->firmaSvg ?: null,
            'firmado_at' => now(),
        ]);

        $this->yaFirmada = true;
        $this->exitoso = true;
    }
};
?>

<div class="min-h-screen bg-zinc-50 dark:bg-zinc-950 py-8 px-4 flex justify-center items-start">
    <div class="w-full max-w-2xl bg-white dark:bg-zinc-900 shadow-xl rounded-2xl border border-zinc-200 dark:border-zinc-800 p-6 md:p-8 space-y-6">

        {{-- Header institucional --}}
        <div class="text-center border-b border-zinc-100 dark:border-zinc-800 pb-6">
            <div class="inline-flex items-center justify-center size-12 rounded-xl bg-[#00376e] text-white mb-3 shadow-md">
                <flux:icon.document-text class="size-6" />
            </div>
            <h1 class="text-xl font-bold text-zinc-900 dark:text-zinc-100">Liceo New Heaven High School</h1>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 uppercase tracking-wider font-medium">
                Firma Digital de Bitácora de Entrevista
            </p>
        </div>

        @if (! $tokenValido)
            <div class="p-6 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 rounded-xl text-center space-y-3">
                <flux:icon.exclamation-triangle class="size-10 text-red-500 mx-auto" />
                <h2 class="text-lg font-bold text-red-700 dark:text-red-400">Enlace No Válido o Expirado</h2>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                    El enlace para firmar esta bitácora no existe o su vigencia ha expirado. Por favor, comuníquese con el establecimiento para solicitar un nuevo envío.
                </p>
            </div>
        @elseif ($exitoso || $yaFirmada)
            <div class="p-6 bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 rounded-xl text-center space-y-3">
                <flux:icon.check-circle class="size-12 text-emerald-600 dark:text-emerald-400 mx-auto" />
                <h2 class="text-xl font-bold text-emerald-800 dark:text-emerald-300">
                    {{ $exitoso ? '¡Bitácora Firmada con Éxito!' : 'Bitácora Previamente Firmada' }}
                </h2>
                <p class="text-sm text-zinc-600 dark:text-zinc-300">
                    Firmado por: <strong>{{ $bitacora->firmante_nombre }}</strong> (RUT: {{ $bitacora->firmante_rut }})
                </p>
                <p class="text-xs text-zinc-500">
                    Fecha y Hora: {{ $bitacora->firmado_at ? $bitacora->firmado_at->format('d/m/Y H:i hrs') : 'Registrado' }}
                </p>
            </div>

            {{-- Resumen de lectura --}}
            <div class="space-y-4 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 mb-1">Resumen de Conversación:</h3>
                    <p class="text-sm text-zinc-800 dark:text-zinc-200 bg-zinc-50 dark:bg-zinc-800/50 p-3 rounded-lg border border-zinc-200 dark:border-zinc-700 whitespace-pre-line">
                        {{ $bitacora->resumen }}
                    </p>
                </div>

                @if (! empty($bitacora->acuerdos))
                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 mb-2">Acuerdos y Compromisos:</h3>
                        <ul class="space-y-2">
                            @foreach ($bitacora->acuerdos as $acuerdo)
                                <li class="p-3 bg-blue-50/50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800/40 rounded-lg text-sm">
                                    <strong class="text-blue-900 dark:text-blue-200">{{ $acuerdo['titulo'] }}:</strong>
                                    <span class="text-zinc-700 dark:text-zinc-300">{{ $acuerdo['descripcion'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

        @else
            {{-- Formulario de Firma --}}
            <div class="space-y-6">
                {{-- Detalle Entrevista --}}
                <div class="bg-zinc-50 dark:bg-zinc-800/50 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                    <div>
                        <span class="text-xs text-zinc-500 uppercase font-bold block">Estudiante:</span>
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">
                            {{ $bitacora->entrevista->estudiante ? $bitacora->entrevista->estudiante->nombreCompleto() : 'N/A' }}
                        </span>
                    </div>
                    <div>
                        <span class="text-xs text-zinc-500 uppercase font-bold block">Curso:</span>
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">
                            {{ $bitacora->entrevista->estudiante && $bitacora->entrevista->estudiante->curso ? $bitacora->entrevista->estudiante->curso->nombreCompleto() : 'N/A' }}
                        </span>
                    </div>
                    <div>
                        <span class="text-xs text-zinc-500 uppercase font-bold block">Entrevistador:</span>
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">
                            {{ $bitacora->entrevista->user ? $bitacora->entrevista->user->nombreCompleto() : 'N/A' }}
                        </span>
                    </div>
                    <div>
                        <span class="text-xs text-zinc-500 uppercase font-bold block">Fecha:</span>
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">
                            {{ \Carbon\Carbon::parse($bitacora->entrevista->fecha)->format('d/m/Y') }}
                        </span>
                    </div>
                </div>

                {{-- Resumen Conversación --}}
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 mb-2">Resumen de la Entrevista:</h3>
                    <div class="p-4 bg-zinc-50 dark:bg-zinc-800/40 rounded-xl border border-zinc-200 dark:border-zinc-700 text-sm text-zinc-800 dark:text-zinc-200 whitespace-pre-line">
                        {{ $bitacora->resumen }}
                    </div>
                </div>

                {{-- Acuerdos --}}
                @if (! empty($bitacora->acuerdos))
                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 mb-2">Acuerdos y Compromisos:</h3>
                        <div class="space-y-2">
                            @foreach ($bitacora->acuerdos as $acuerdo)
                                <div class="p-3 bg-blue-50/60 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-800/50 rounded-xl text-sm">
                                    <div class="font-bold text-blue-900 dark:text-blue-300">{{ $acuerdo['titulo'] }}</div>
                                    <div class="text-zinc-700 dark:text-zinc-300 mt-1">{{ $acuerdo['descripcion'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Datos del Firmante (Editable) --}}
                <div class="border-t border-zinc-200 dark:border-zinc-800 pt-6 space-y-4">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-zinc-900 dark:text-zinc-100">
                        Confirmación y Datos de la Persona que Firma
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-2">
                            <flux:input wire:model="firmanteNombre" :label="__('Nombre Completo de quien Firma')" placeholder="Ej: MARÍA PAZ LÓPEZ" class="uppercase" />
                            <flux:error name="firmanteNombre" />
                        </div>
                        <div class="flex gap-2 items-end">
                            <flux:input wire:model="firmanteRutNumero" :label="__('RUT Firmante')" placeholder="12345678" class="flex-1" />
                            <flux:input wire:model="firmanteRutDv" :label="__('DV')" placeholder="K" class="w-16 uppercase" maxlength="1" />
                        </div>
                    </div>
                    <flux:error name="firmanteRutNumero" />

                    {{-- Canvas de Firma interactivo --}}
                    <div x-data="{
                        canvas: null,
                        ctx: null,
                        isDrawing: false,
                        hasDrawn: false,
                        init() {
                            this.$nextTick(() => {
                                this.setupCanvas();
                            });
                        },
                        setupCanvas() {
                            this.canvas = this.$refs.canvas;
                            if (!this.canvas) return;
                            this.ctx = this.canvas.getContext('2d');
                            
                            const rect = this.canvas.getBoundingClientRect();
                            const w = (rect.width && rect.width > 0) ? Math.round(rect.width) : 450;
                            const h = (rect.height && rect.height > 0) ? Math.round(rect.height) : 160;

                            this.canvas.width = w;
                            this.canvas.height = h;

                            this.ctx.lineWidth = 3;
                            this.ctx.lineCap = 'round';
                            this.ctx.lineJoin = 'round';
                            this.ctx.strokeStyle = '#020617';

                            if (!this.canvas.dataset.listenersAttached) {
                                this.canvas.dataset.listenersAttached = 'true';

                                const getPos = (e) => {
                                    const r = this.canvas.getBoundingClientRect();
                                    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                                    const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                                    const scaleX = r.width > 0 ? (this.canvas.width / r.width) : 1;
                                    const scaleY = r.height > 0 ? (this.canvas.height / r.height) : 1;
                                    return {
                                        x: (clientX - r.left) * scaleX,
                                        y: (clientY - r.top) * scaleY
                                    };
                                };

                                const onDown = (e) => {
                                    e.preventDefault();
                                    if (e.pointerId && this.canvas.setPointerCapture) {
                                        try { this.canvas.setPointerCapture(e.pointerId); } catch(err) {}
                                    }
                                    this.isDrawing = true;
                                    this.hasDrawn = true;
                                    const pos = getPos(e);
                                    this.ctx.beginPath();
                                    this.ctx.moveTo(pos.x, pos.y);
                                };

                                const onMove = (e) => {
                                    if (!this.isDrawing) return;
                                    e.preventDefault();
                                    const pos = getPos(e);
                                    this.ctx.lineTo(pos.x, pos.y);
                                    this.ctx.stroke();
                                };

                                const onUp = (e) => {
                                    if (this.isDrawing) {
                                        this.isDrawing = false;
                                        if (e.pointerId && this.canvas.releasePointerCapture) {
                                            try { this.canvas.releasePointerCapture(e.pointerId); } catch(err) {}
                                        }
                                        $wire.set('firmaSvg', this.canvas.toDataURL('image/png'));
                                    }
                                };

                                this.canvas.addEventListener('pointerdown', onDown);
                                this.canvas.addEventListener('pointermove', onMove);
                                this.canvas.addEventListener('pointerup', onUp);
                                this.canvas.addEventListener('pointercancel', onUp);

                                this.canvas.addEventListener('mousedown', onDown);
                                this.canvas.addEventListener('mousemove', onMove);
                                this.canvas.addEventListener('mouseup', onUp);

                                this.canvas.addEventListener('touchstart', onDown, { passive: false });
                                this.canvas.addEventListener('touchmove', onMove, { passive: false });
                                this.canvas.addEventListener('touchend', onUp);
                            }
                        },
                        clearCanvas() {
                            this.setupCanvas();
                            if (this.canvas && this.ctx) {
                                this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
                                this.hasDrawn = false;
                                $wire.set('firmaSvg', '');
                            }
                        }
                    }" wire:ignore class="space-y-2 pt-2">
                        <label class="block text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            Firma en Pantalla (Táctil o Mouse):
                        </label>
                        <div class="relative border-2 border-dashed border-zinc-300 dark:border-zinc-700 rounded-xl bg-white dark:bg-zinc-950 overflow-hidden">
                            <canvas 
                                x-ref="canvas" 
                                class="w-full h-40 touch-none cursor-crosshair bg-white"
                            ></canvas>
                            <div x-show="!hasDrawn" class="absolute inset-0 flex items-center justify-center pointer-events-none text-xs text-zinc-400">
                                Trace su firma aquí con el dedo o mouse
                            </div>
                        </div>
                        <div class="flex justify-between items-center text-xs">
                            <button type="button" @click="clearCanvas()" class="text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300 underline">
                                Limpiar Firma
                            </button>
                            <span class="text-zinc-400 italic">Al presionar "Firmar", acepta los acuerdos registrados.</span>
                        </div>
                    </div>

                    <div class="pt-4">
                        <flux:button wire:click="firmar" variant="primary" icon="check" class="w-full py-3 text-base">
                            Confirmar y Guardar Firma Digital
                        </flux:button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
