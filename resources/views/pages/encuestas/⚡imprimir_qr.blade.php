<?php

use App\Models\EvaluacionDocente;
use App\Models\School;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.blank')] #[Title('Imprimir Códigos QR — Evaluación Docente')] class extends Component
{
    public function mount(): void
    {
        $user = auth()->user();
        if (! $user || ! $user->hasRole(['comision_ed', 'superadmin'])) {
            abort(403, 'No tienes permiso para acceder a esta página.');
        }
    }

    #[Computed]
    public function school(): ?School
    {
        return auth()->user()?->currentSchool ?? School::first();
    }

    #[Computed]
    public function urlEstudiantes(): string
    {
        return route('encuestas.kiosco', [
            'schoolId' => $this->school?->id,
            'tipo' => 'estudiante',
        ]);
    }

    #[Computed]
    public function urlApoderados(): string
    {
        return route('encuestas.kiosco', [
            'schoolId' => $this->school?->id,
            'tipo' => 'apoderado',
        ]);
    }

    #[Computed]
    public function qrEstudiantesSvg(): string
    {
        return EvaluacionDocente::generarQrSvg($this->urlEstudiantes, 220);
    }

    #[Computed]
    public function qrApoderadosSvg(): string
    {
        return EvaluacionDocente::generarQrSvg($this->urlApoderados, 220);
    }
};
?>

<div class="min-h-screen bg-zinc-100 print:bg-white text-zinc-900 py-8 px-4 print:p-0">
    {{-- Barra de acciones (No se imprime) --}}
    <div class="max-w-4xl mx-auto mb-6 flex items-center justify-between bg-white rounded-xl p-4 shadow-sm border border-zinc-200 print:hidden">
        <div class="flex items-center gap-3">
            <a href="{{ route('encuestas.resultados') }}"
                class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-300 bg-white hover:bg-zinc-50 px-3 py-2 text-xs font-semibold text-zinc-700 transition">
                <flux:icon.arrow-left class="size-4" />
                Volver al Panel
            </a>
            <span class="text-sm font-bold text-zinc-800">
                Hoja de Códigos QR y PINs para Tablets
            </span>
        </div>

        <button type="button" onclick="window.print()"
            class="inline-flex items-center gap-2 rounded-lg bg-[#00376e] hover:bg-[#002a54] px-4 py-2 text-xs font-bold text-white shadow transition cursor-pointer">
            <flux:icon.printer class="size-4" />
            Imprimir QR
        </button>
    </div>

    {{-- Contenido Imprimible --}}
    <div class="max-w-4xl mx-auto space-y-6 print:space-y-8">
        <div class="text-center border-b-2 border-[#00376e] pb-4">
            <h1 class="text-2xl font-extrabold text-[#00376e] uppercase tracking-tight">
                {{ $this->school?->name ?? 'Liceo New Heaven High School' }}
            </h1>
            <p class="text-xs font-bold uppercase tracking-widest text-zinc-500 mt-1">
                Acceso por Tablet / Código QR — Evaluación Docente Anónima
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 print:grid-cols-2 gap-6">
            {{-- Tarjeta 1: Encuesta Estudiantes --}}
            <div class="bg-white rounded-2xl border-2 border-zinc-300 p-6 flex flex-col items-center text-center space-y-4">
                <span class="inline-flex items-center rounded-full bg-blue-100 px-3.5 py-1 text-xs font-extrabold uppercase tracking-wider text-[#00376e]">
                    Encuesta Estudiantes
                </span>
                <h2 class="text-lg font-extrabold text-zinc-900">
                    Evaluación Docente — Estudiantes
                </h2>
                <p class="text-xs text-zinc-500">
                    Escanea este código QR con la cámara de la tablet para abrir la encuesta de 10 preguntas (20 estudiantes por profesor).
                </p>

                <div class="p-3 bg-white rounded-xl border border-zinc-200 shadow-xs">
                    {!! $this->qrEstudiantesSvg !!}
                </div>

                <div class="w-full rounded-xl bg-zinc-100 border border-zinc-300 p-3">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-zinc-500">
                        PIN de Acceso Estudiantes
                    </p>
                    <p class="text-3xl font-mono font-extrabold tracking-[0.25em] text-[#00376e] mt-0.5">
                        {{ $this->school?->pinEncuestaEstudiante() }}
                    </p>
                </div>

                <p class="text-[10px] font-mono text-zinc-400 break-all">
                    {{ $this->urlEstudiantes }}
                </p>
            </div>

            {{-- Tarjeta 2: Encuesta Apoderados --}}
            <div class="bg-white rounded-2xl border-2 border-zinc-300 p-6 flex flex-col items-center text-center space-y-4">
                <span class="inline-flex items-center rounded-full bg-indigo-100 px-3.5 py-1 text-xs font-extrabold uppercase tracking-wider text-indigo-800">
                    Encuesta Apoderados
                </span>
                <h2 class="text-lg font-extrabold text-zinc-900">
                    Evaluación Docente — Apoderados
                </h2>
                <p class="text-xs text-zinc-500">
                    Escanea este código QR con la cámara de la tablet para abrir la encuesta de 6-7 preguntas (2 apoderados por profesor).
                </p>

                <div class="p-3 bg-white rounded-xl border border-zinc-200 shadow-xs">
                    {!! $this->qrApoderadosSvg !!}
                </div>

                <div class="w-full rounded-xl bg-zinc-100 border border-zinc-300 p-3">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-zinc-500">
                        PIN de Acceso Apoderados
                    </p>
                    <p class="text-3xl font-mono font-extrabold tracking-[0.25em] text-indigo-800 mt-0.5">
                        {{ $this->school?->pinEncuestaApoderado() }}
                    </p>
                </div>

                <p class="text-[10px] font-mono text-zinc-400 break-all">
                    {{ $this->urlApoderados }}
                </p>
            </div>
        </div>
    </div>
</div>
