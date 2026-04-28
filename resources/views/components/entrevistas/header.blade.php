@props(['titulo', 'subtitulo', 'icono' => 'calendar'])

<div class="mb-8 flex flex-col md:flex-row md:items-end justify-between gap-4">
    <div>
        <flux:heading size="xl" class="text-[#00376e] dark:text-blue-400 font-extrabold">
            {{ $titulo }}
        </flux:heading>
        <p class="text-zinc-500 text-sm mt-1 flex items-center gap-2">
            <flux:icon name="{{ $icono }}" class="size-4" />
            {{ $subtitulo }}
        </p>
    </div>
    <div class="flex items-center gap-4">
        {{ $slot }}
        
        <livewire:layout.notifications-bell />

        <div class="flex items-center gap-3 border-l border-zinc-200 dark:border-zinc-700 pl-4">
            <div class="text-right hidden sm:block">
                <p class="text-xs font-bold text-[#00376e] dark:text-blue-400 leading-none">
                    {{ trim(auth()->user()->nombres . ' ' . auth()->user()->apellido_pat) ?: 'Profesor' }}</p>
                <p class="text-[10px] text-zinc-500 uppercase mt-1">{{ ucfirst(auth()->user()->active_roles[0] ?? 'Docente') }}</p>
            </div>
            
            @if(auth()->user()->avatar)
                <div class="relative w-10 h-10">
                    <img src="{{ auth()->user()->avatar }}" alt="{{ auth()->user()->nombres }}" class="w-10 h-10 rounded-full object-cover" referrerpolicy="no-referrer">
                    <span class="absolute top-0 right-0 w-3 h-3 bg-emerald-500 border-2 border-white dark:border-zinc-900 rounded-full"></span>
                </div>
            @else
                <div class="w-10 h-10 rounded-full bg-[#00376e] text-white flex items-center justify-center font-bold relative">
                    {{ substr(auth()->user()->nombres ?? 'P', 0, 1) }}
                    <span class="absolute top-0 right-0 w-3 h-3 bg-emerald-500 border-2 border-white dark:border-zinc-900 rounded-full"></span>
                </div>
            @endif
        </div>
    </div>
</div>
