<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        <flux:navlist aria-label="{{ __('Settings') }}">
            <flux:navlist.item :href="route('profile.edit')" wire:navigate>{{ __('Profile') }}</flux:navlist.item>
            <flux:navlist.item :href="route('appearance.edit')" wire:navigate>{{ __('Appearance') }}</flux:navlist.item>
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6">
        @if(!empty($heading))
            <flux:heading size="xl" class="!font-bold !text-primary">{{ $heading }}</flux:heading>
        @endif
        @if(!empty($subheading))
            <flux:subheading class="!text-secondary">{{ $subheading }}</flux:subheading>
        @endif

        <div class="mt-8 w-full max-w-4xl">
            {{ $slot }}
        </div>
    </div>
</div>
