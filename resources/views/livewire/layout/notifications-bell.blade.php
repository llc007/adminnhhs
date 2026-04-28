<flux:dropdown position="bottom" align="end">
    <flux:button variant="ghost" class="relative hover:bg-zinc-100 dark:hover:bg-zinc-800" icon="bell">
        @if($notifications->count() > 0)
            <span class="absolute top-2 right-2 flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span>
            </span>
        @endif
    </flux:button>

    <flux:menu class="w-80 max-h-96 overflow-y-auto">
        <div class="px-4 py-2 font-bold text-sm border-b border-zinc-200 dark:border-zinc-700 flex justify-between items-center">
            <span>Notificaciones</span>
            @if($notifications->count() > 0)
                <span class="text-xs bg-red-100 text-red-600 px-2 py-0.5 rounded-full">{{ $notifications->count() }} nuevas</span>
            @endif
        </div>
        
        @forelse($notifications as $notification)
            <flux:menu.item wire:click="markAsRead('{{ $notification->id }}', '{{ $notification->data['url'] ?? '#' }}')" class="cursor-pointer border-b border-zinc-100 dark:border-zinc-800 last:border-0 py-3">
                <div class="flex gap-3 items-start w-full">
                    <div class="bg-{{ $notification->data['color'] ?? 'blue' }}-100 dark:bg-{{ $notification->data['color'] ?? 'blue' }}-900/50 p-2 rounded-full text-{{ $notification->data['color'] ?? 'blue' }}-600 dark:text-{{ $notification->data['color'] ?? 'blue' }}-400 flex-shrink-0">
                        <flux:icon name="{{ $notification->data['icon'] ?? 'bell' }}" class="size-4" />
                    </div>
                    <div class="flex flex-col flex-1 overflow-hidden">
                        <span class="text-sm font-bold text-zinc-900 dark:text-white truncate w-full">{{ $notification->data['titulo'] ?? 'Nueva Notificación' }}</span>
                        <span class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5 line-clamp-2 w-full whitespace-normal">{{ $notification->data['mensaje'] ?? '' }}</span>
                        <span class="text-[10px] text-zinc-400 mt-1 font-mono">{{ $notification->created_at->diffForHumans() }}</span>
                    </div>
                </div>
            </flux:menu.item>
        @empty
            <div class="px-4 py-6 text-center text-sm text-zinc-500 flex flex-col items-center gap-2">
                <flux:icon.bell-slash class="size-6 text-zinc-300 dark:text-zinc-600" />
                No tienes notificaciones nuevas
            </div>
        @endforelse
    </flux:menu>
</flux:dropdown>
