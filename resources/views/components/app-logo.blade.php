@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="New Heaven High School" class="max-w-[190px] truncate" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md">
            <img src="{{ asset('images/logo.png') }}" alt="Logo NHHS" class="size-8 object-contain" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="New Heaven High School" class="max-w-[150px] sm:max-w-none truncate" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md">
            <img src="{{ asset('images/logo.png') }}" alt="Logo NHHS" class="size-8 object-contain" />
        </x-slot>
    </flux:brand>
@endif
