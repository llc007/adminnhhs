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

        <flux:dropdown position="bottom" align="end">
            <button type="button" class="flex items-center gap-3 border-l border-zinc-200 dark:border-zinc-700 pl-4 text-left focus:outline-none cursor-pointer group">
                <div class="text-right hidden sm:block">
                    <p class="text-xs font-bold text-[#00376e] dark:text-blue-400 leading-none group-hover:text-blue-600 dark:group-hover:text-blue-300 transition-colors">
                        {{ trim(auth()->user()->nombres . ' ' . auth()->user()->apellido_pat) ?: 'Usuario' }}
                    </p>
                    <p class="text-[10px] text-zinc-500 uppercase mt-1">{{ ucfirst(auth()->user()->active_roles[0] ?? 'Personal') }}</p>
                </div>
                
                @if(auth()->user()->avatar)
                    <div class="relative w-10 h-10 shrink-0">
                        <img src="{{ auth()->user()->avatar }}" alt="{{ auth()->user()->nombres }}" class="w-10 h-10 rounded-full object-cover border border-zinc-200 dark:border-zinc-700 group-hover:border-blue-500 dark:group-hover:border-blue-400 transition-colors" referrerpolicy="no-referrer">
                        <span class="absolute top-0 right-0 w-3 h-3 bg-emerald-500 border-2 border-white dark:border-zinc-900 rounded-full"></span>
                    </div>
                @else
                    <div class="w-10 h-10 rounded-full bg-[#00376e] text-white flex items-center justify-center font-bold relative shrink-0 group-hover:bg-blue-600 transition-colors">
                        {{ substr(auth()->user()->nombres ?? 'U', 0, 1) }}
                        <span class="absolute top-0 right-0 w-3 h-3 bg-emerald-500 border-2 border-white dark:border-zinc-900 rounded-full"></span>
                    </div>
                @endif
            </button>

            <flux:menu class="w-48">
                <div class="flex items-center gap-2 px-2 py-1.5 text-start text-sm">
                    <flux:avatar
                        :name="auth()->user()->nombreCompleto()"
                        :initials="auth()->user()->initials()"
                        size="sm"
                    />
                    <div class="grid flex-1 text-start text-xs leading-tight">
                        <flux:heading class="truncate font-bold">{{ auth()->user()->nombreCompleto() }}</flux:heading>
                        <flux:text class="truncate text-[10px] text-zinc-400">{{ auth()->user()->email }}</flux:text>
                    </div>
                </div>
                
                <flux:menu.separator />
                
                <flux:menu.radio.group>
                    <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                        {{ __('Ajustes') }}
                    </flux:menu.item>
                    
                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer text-red-500 hover:text-red-600 dark:text-red-400 dark:hover:text-red-300"
                        >
                            {{ __('Cerrar sesión') }}
                        </flux:menu.item>
                    </form>
                </flux:menu.radio.group>
            </flux:menu>
        </flux:dropdown>
    </div>
</div>
