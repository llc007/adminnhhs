<?php

use App\Models\Estudiante;
use App\Models\User;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public string $search = '';

    public string $tipo = 'estudiante'; // 'estudiante' or 'funcionario'

    public ?int $selectedId = null;

    public ?array $selectedData = null;

    // Reset Options
    public bool $incluirGoogle = true;

    public string $googleEmail = '';

    public string $googlePassword = '';

    public bool $incluirFullcollege = true;

    public string $fullcollegeUser = '';

    public string $fullcollegePassword = '12345';

    public function mount(): void
    {
        if (! auth()->user()->can('ver-estudiantes') && ! auth()->user()->can('gestionar-funcionarios') && ! auth()->user()->hasRole('superadmin')) {
            abort(403, 'No tienes permiso para acceder a esta página.');
        }
    }

    public function updatedTipo(): void
    {
        $this->limpiarSeleccion();
    }

    public function limpiarSeleccion(): void
    {
        $this->selectedId = null;
        $this->selectedData = null;
        $this->search = '';
        $this->googleEmail = '';
        $this->googlePassword = '';
        $this->fullcollegeUser = '';
        $this->fullcollegePassword = '12345';
        $this->incluirGoogle = true;
        $this->incluirFullcollege = $this->tipo === 'estudiante';
    }

    public function seleccionarEstudiante(int $id): void
    {
        $est = Estudiante::with(['curso', 'user'])->findOrFail($id);
        $this->tipo = 'estudiante';
        $this->selectedId = $est->id;

        $email = $est->email ?? ($est->user?->email ?? '');
        $rutLimpio = $est->rut_numero ? preg_replace('/[^0-9]/', '', (string) $est->rut_numero) : '';

        $apoderadoRutLimpio = $est->apoderado_rut_numero
            ? preg_replace('/[^0-9]/', '', (string) $est->apoderado_rut_numero)
            : $rutLimpio;

        $apoderadoNombre = $est->apoderadoNombreCompleto();

        $this->selectedData = [
            'nombre' => $est->nombreCompleto(),
            'rut' => $est->rutCompleto() ?? 'N/A',
            'curso_cargo' => $est->curso ? $est->curso->nombreCompleto() : 'Sin Curso',
            'email' => $email,
            'apoderado' => $apoderadoNombre ?: 'No registrado',
        ];

        $this->googleEmail = $email;
        $this->googlePassword = $rutLimpio;

        $this->fullcollegeUser = $apoderadoRutLimpio;
        $this->fullcollegePassword = '12345';

        $this->incluirGoogle = true;
        $this->incluirFullcollege = true;
        $this->search = '';
    }

    public function seleccionarFuncionario(int $id): void
    {
        $func = User::findOrFail($id);
        $this->tipo = 'funcionario';
        $this->selectedId = $func->id;

        $rutLimpio = $func->rut_numero ? preg_replace('/[^0-9]/', '', (string) $func->rut_numero) : '';

        $this->selectedData = [
            'nombre' => $func->nombreCompleto(),
            'rut' => $func->rutCompleto() ?? 'N/A',
            'curso_cargo' => 'Funcionario / Docente',
            'email' => $func->email,
            'apoderado' => null,
        ];

        $this->googleEmail = $func->email;
        $this->googlePassword = $rutLimpio;

        $this->fullcollegeUser = $rutLimpio;
        $this->fullcollegePassword = '12345';

        $this->incluirGoogle = true;
        $this->incluirFullcollege = false;
        $this->search = '';
    }

    #[Computed]
    public function resultadosEstudiantes()
    {
        if (strlen(trim($this->search)) < 2 || $this->tipo !== 'estudiante') {
            return collect();
        }

        $words = array_filter(explode(' ', trim($this->search)));

        return Estudiante::query()
            ->with(['curso', 'user'])
            ->activos()
            ->where('school_id', auth()->user()->current_school_id)
            ->where(function ($q) use ($words) {
                foreach ($words as $word) {
                    $w = trim($word);
                    if ($w === '') {
                        continue;
                    }
                    $q->where(function ($sub) use ($w) {
                        $sub->where('nombres_csv', 'like', "%{$w}%")
                            ->orWhere('rut_numero', 'like', "%{$w}%")
                            ->orWhereHas('user', function ($uq) use ($w) {
                                $uq->where('nombres', 'like', "%{$w}%")
                                    ->orWhere('apellido_pat', 'like', "%{$w}%")
                                    ->orWhere('apellido_mat', 'like', "%{$w}%")
                                    ->orWhere('email', 'like', "%{$w}%");
                            });
                    });
                }
            })
            ->take(10)
            ->get();
    }

    #[Computed]
    public function resultadosFuncionarios()
    {
        if (strlen(trim($this->search)) < 2 || $this->tipo !== 'funcionario') {
            return collect();
        }

        $words = array_filter(explode(' ', trim($this->search)));

        return User::query()
            ->where(function ($q) use ($words) {
                foreach ($words as $word) {
                    $w = trim($word);
                    if ($w === '') {
                        continue;
                    }
                    $q->where(function ($sub) use ($w) {
                        $sub->where('nombres', 'like', "%{$w}%")
                            ->orWhere('apellido_pat', 'like', "%{$w}%")
                            ->orWhere('apellido_mat', 'like', "%{$w}%")
                            ->orWhere('rut_numero', 'like', "%{$w}%")
                            ->orWhere('email', 'like', "%{$w}%");
                    });
                }
            })
            ->take(10)
            ->get();
    }
};

?>

<div>
    <div class="flex flex-col gap-8 w-full max-w-5xl mx-auto pb-12">
        {{-- Header --}}
        <div>
            <x-header 
                :titulo="__('Restablecer Contraseña / Entrega de Credenciales')" 
                :subtitulo="__('Genera e imprime comprobantes de entrega de usuario y contraseña para correo institucional (Google) o plataforma Fullcollege.')" 
                icono="key"
            />
        </div>

        {{-- Selector de Tipo y Búsqueda --}}
        <flux:card class="space-y-6">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pb-4 border-b border-zinc-200 dark:border-zinc-800">
                <div class="flex items-center gap-3">
                    <span class="text-sm font-bold text-zinc-700 dark:text-zinc-300">Buscar en:</span>
                    <div class="inline-flex p-1 bg-zinc-100 dark:bg-zinc-800 rounded-lg">
                        <button 
                            type="button" 
                            wire:click="$set('tipo', 'estudiante')" 
                            class="px-4 py-1.5 text-xs font-bold rounded-md transition-colors {{ $tipo === 'estudiante' ? 'bg-white dark:bg-zinc-700 text-[#00376e] dark:text-blue-300 shadow-sm' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900' }}"
                        >
                            👨‍🎓 Estudiantes
                        </button>
                        <button 
                            type="button" 
                            wire:click="$set('tipo', 'funcionario')" 
                            class="px-4 py-1.5 text-xs font-bold rounded-md transition-colors {{ $tipo === 'funcionario' ? 'bg-white dark:bg-zinc-700 text-[#00376e] dark:text-blue-300 shadow-sm' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900' }}"
                        >
                            👨‍🏫 Funcionarios
                        </button>
                    </div>
                </div>

                @if($selectedId)
                    <flux:button variant="ghost" size="sm" icon="x-mark" wire:click="limpiarSeleccion">
                        Cambiar persona
                    </flux:button>
                @endif
            </div>

            @if(!$selectedId)
                <div class="space-y-4">
                    <flux:input 
                        wire:model.live.debounce.250ms="search" 
                        icon="magnifying-glass" 
                        :placeholder="$tipo === 'estudiante' ? 'Escribe el nombre o RUT del estudiante...' : 'Escribe el nombre o RUT del funcionario...'" 
                        class="w-full"
                    />

                    {{-- Resultados Estudiantes --}}
                    @if($tipo === 'estudiante' && strlen(trim($search)) >= 2)
                        <div class="border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden divide-y divide-zinc-200 dark:divide-zinc-800">
                            @forelse($this->resultadosEstudiantes as $est)
                                <div 
                                    wire:key="est-{{ $est->id }}"
                                    wire:click="seleccionarEstudiante({{ $est->id }})" 
                                    class="p-3.5 hover:bg-blue-50/70 dark:hover:bg-blue-900/20 cursor-pointer flex items-center justify-between transition-colors"
                                >
                                    <div>
                                        <p class="font-bold text-sm text-zinc-900 dark:text-zinc-100 uppercase">
                                            {{ $est->nombreCompleto() }}
                                        </p>
                                        <p class="text-xs text-zinc-500">
                                            RUT: <span class="font-mono font-semibold">{{ $est->rutCompleto() ?? 'Sin RUT' }}</span> | 
                                            Curso: <span class="font-semibold">{{ $est->curso ? $est->curso->nombreCompleto() : 'Sin Curso' }}</span>
                                            @if($est->email)
                                                | Correo: <span class="text-blue-600 dark:text-blue-400">{{ $est->email }}</span>
                                            @endif
                                        </p>
                                    </div>
                                    <flux:button variant="filled" size="xs" icon="check">
                                        Seleccionar
                                    </flux:button>
                                </div>
                            @empty
                                <div class="p-6 text-center text-zinc-500 text-sm">
                                    No se encontraron estudiantes activos con "{{ $search }}".
                                </div>
                            @endforelse
                        </div>
                    @endif

                    {{-- Resultados Funcionarios --}}
                    @if($tipo === 'funcionario' && strlen(trim($search)) >= 2)
                        <div class="border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden divide-y divide-zinc-200 dark:divide-zinc-800">
                            @forelse($this->resultadosFuncionarios as $func)
                                <div 
                                    wire:key="func-{{ $func->id }}"
                                    wire:click="seleccionarFuncionario({{ $func->id }})" 
                                    class="p-3.5 hover:bg-blue-50/70 dark:hover:bg-blue-900/20 cursor-pointer flex items-center justify-between transition-colors"
                                >
                                    <div>
                                        <p class="font-bold text-sm text-zinc-900 dark:text-zinc-100 uppercase">
                                            {{ $func->nombreCompleto() }}
                                        </p>
                                        <p class="text-xs text-zinc-500">
                                            RUT: <span class="font-mono font-semibold">{{ $func->rutCompleto() ?? 'Sin RUT' }}</span> | 
                                            Correo: <span class="text-blue-600 dark:text-blue-400">{{ $func->email }}</span>
                                        </p>
                                    </div>
                                    <flux:button variant="filled" size="xs" icon="check">
                                        Seleccionar
                                    </flux:button>
                                </div>
                            @empty
                                <div class="p-6 text-center text-zinc-500 text-sm">
                                    No se encontraron funcionarios con "{{ $search }}".
                                </div>
                            @endforelse
                        </div>
                    @endif
                </div>
            @endif
        </flux:card>

        {{-- Panel de Datos y Opciones de Restablecimiento --}}
        @if($selectedId && $selectedData)
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                {{-- Columna Ficha Resumen --}}
                <flux:card class="md:col-span-1 border-l-4 border-l-[#00376e] space-y-4">
                    <div>
                        <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-blue-100 dark:bg-blue-900/50 text-[#00376e] dark:text-blue-300">
                            {{ $tipo === 'funcionario' ? 'Funcionario Seleccionado' : 'Estudiante Seleccionado' }}
                        </span>
                        <h3 class="text-base font-extrabold text-zinc-900 dark:text-zinc-100 mt-2 uppercase">
                            {{ $selectedData['nombre'] }}
                        </h3>
                    </div>

                    <div class="space-y-2 text-xs divide-y divide-zinc-100 dark:divide-zinc-800">
                        <div class="pt-2 flex justify-between">
                            <span class="text-zinc-500">RUT:</span>
                            <span class="font-mono font-bold">{{ $selectedData['rut'] }}</span>
                        </div>
                        <div class="pt-2 flex justify-between">
                            <span class="text-zinc-500">{{ $tipo === 'funcionario' ? 'Cargo:' : 'Curso:' }}</span>
                            <span class="font-bold text-zinc-800 dark:text-zinc-200">{{ $selectedData['curso_cargo'] }}</span>
                        </div>
                        <div class="pt-2 flex justify-between">
                            <span class="text-zinc-500">Correo Registrado:</span>
                            <span class="font-semibold text-blue-600 dark:text-blue-400 truncate max-w-[160px]" title="{{ $selectedData['email'] }}">
                                {{ $selectedData['email'] ?: 'No registrado' }}
                            </span>
                        </div>
                        @if($selectedData['apoderado'])
                            <div class="pt-2 flex justify-between">
                                <span class="text-zinc-500">Apoderado:</span>
                                <span class="font-semibold text-zinc-800 dark:text-zinc-200 truncate max-w-[160px]" title="{{ $selectedData['apoderado'] }}">
                                    {{ $selectedData['apoderado'] }}
                                </span>
                            </div>
                        @endif
                    </div>
                </flux:card>

                {{-- Columna Formulario Opciones y Credenciales --}}
                <div class="md:col-span-2 space-y-6">
                    <flux:card class="space-y-6">
                        <h4 class="font-extrabold text-base text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                            <flux:icon.key class="size-5 text-[#00376e]" />
                            Configurar Credenciales para la Entrega
                        </h4>

                        {{-- Opción 1 / 2: Correo Institucional Google --}}
                        <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/40 space-y-4">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" wire:model.live="incluirGoogle" class="rounded border-zinc-300 text-[#00376e] focus:ring-[#00376e]" />
                                <span class="font-bold text-sm text-zinc-900 dark:text-zinc-100">
                                    📧 Correo Institucional (Google)
                                </span>
                            </label>

                            @if($incluirGoogle)
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                                    <div>
                                        <flux:input 
                                            wire:model="googleEmail" 
                                            label="Correo Institucional" 
                                            placeholder="ejemplo@newheavenhs.cl" 
                                        />
                                    </div>
                                    <div>
                                        <flux:input 
                                            wire:model="googlePassword" 
                                            label="Contraseña (Predeterminada: RUT sin DV)" 
                                            placeholder="Ej: 17735913" 
                                        />
                                    </div>
                                </div>
                                <p class="text-[11px] text-zinc-500">
                                    * La contraseña predeterminada para el correo es el RUT de la persona sin puntos, sin guion ni dígito verificador. Puedes modificarla si es necesario.
                                </p>
                            @endif
                        </div>

                        {{-- Opción 3: Cuenta Fullcollege --}}
                        <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/40 space-y-4">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" wire:model.live="incluirFullcollege" class="rounded border-zinc-300 text-[#00376e] focus:ring-[#00376e]" />
                                <span class="font-bold text-sm text-zinc-900 dark:text-zinc-100">
                                    🌐 Cuenta Fullcollege
                                </span>
                            </label>

                            @if($incluirFullcollege)
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                                    <div>
                                        <flux:input 
                                            wire:model="fullcollegeUser" 
                                            label="Usuario (Predeterminado: RUT Apoderado sin DV)" 
                                            placeholder="Ej: 13623502" 
                                        />
                                    </div>
                                    <div>
                                        <flux:input 
                                            wire:model="fullcollegePassword" 
                                            label="Contraseña Fullcollege" 
                                            placeholder="Ej: 12345" 
                                        />
                                    </div>
                                </div>
                                <p class="text-[11px] text-zinc-500">
                                    * Para ingresar a www.fullcollege.cl el usuario es el RUT del apoderado sin DV y la contraseña predeterminada es <span class="font-mono font-bold">12345</span>.
                                </p>
                            @endif
                        </div>

                        {{-- Botones de Impresión --}}
                        <div class="flex flex-col sm:flex-row items-center justify-end gap-3 pt-4 border-t border-zinc-200 dark:border-zinc-800">
                            @php
                                $queryParams = http_build_query([
                                    'tipo' => $tipo,
                                    'id' => $selectedId,
                                    'incluir_google' => $incluirGoogle ? 1 : 0,
                                    'google_email' => $googleEmail,
                                    'google_password' => $googlePassword,
                                    'incluir_fullcollege' => $incluirFullcollege ? 1 : 0,
                                    'fullcollege_user' => $fullcollegeUser,
                                    'fullcollegePassword' => $fullcollegePassword,
                                ]);
                                $urlA4 = route('restablecer_clave.imprimir') . '?' . $queryParams . '&format=a4';
                                $urlPOS = route('restablecer_clave.imprimir') . '?' . $queryParams . '&format=pos80';
                            @endphp

                            <a 
                                href="{{ $urlPOS }}" 
                                target="_blank" 
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg font-bold text-xs bg-zinc-800 hover:bg-zinc-900 text-white shadow-sm transition-all"
                            >
                                🧾 Imprimir Térmico (POS80)
                            </a>

                            <a 
                                href="{{ $urlA4 }}" 
                                target="_blank" 
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg font-bold text-xs bg-[#00376e] hover:bg-blue-900 text-white shadow-md transition-all"
                            >
                                🖨️ Imprimir Hoja (Carta / A4)
                            </a>
                        </div>
                    </flux:card>
                </div>
            </div>
        @endif
    </div>
</div>
