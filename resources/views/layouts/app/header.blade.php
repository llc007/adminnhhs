<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:header container class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden mr-2 shrink-0" icon="bars-2" inset="left" />

            <x-app-logo href="{{ route('dashboard') }}" wire:navigate />

            <flux:navbar class="-mb-px max-lg:hidden">
                <flux:navbar.item icon="layout-grid" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:navbar.item>
            </flux:navbar>

            <flux:spacer />

            <flux:navbar class="me-1.5 space-x-0.5 rtl:space-x-reverse py-0!">
                <flux:tooltip :content="__('Search')" position="bottom">
                    <flux:navbar.item class="!h-10 [&>div>svg]:size-5" icon="magnifying-glass" href="#" :label="__('Search')" />
                </flux:tooltip>
                <flux:tooltip :content="__('Repository')" position="bottom">
                    <flux:navbar.item
                        class="h-10 max-lg:hidden [&>div>svg]:size-5"
                        icon="folder-git-2"
                        href="https://github.com/laravel/livewire-starter-kit"
                        target="_blank"
                        :label="__('Repository')"
                    />
                </flux:tooltip>
                <flux:tooltip :content="__('Documentation')" position="bottom">
                    <flux:navbar.item
                        class="h-10 max-lg:hidden [&>div>svg]:size-5"
                        icon="book-open-text"
                        href="https://laravel.com/docs/starter-kits#livewire"
                        target="_blank"
                        :label="__('Documentation')"
                    />
                </flux:tooltip>
            </flux:navbar>

            <x-desktop-user-menu />
        </flux:header>

        <!-- Mobile Menu -->
        <flux:sidebar collapsible="mobile" sticky class="lg:hidden border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
            </flux:sidebar.header>

            <livewire:admin.seleccionar-colegio />

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Entrevistas')" class="grid">
                    @if(auth()->user()->hasRole(['administrador', 'directivo', 'superadmin']))
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ "Dashboard" }}
                    </flux:sidebar.item>
                    @endif

                    @if(auth()->user()->hasRole(['recepcion', 'directivo', 'administrador', 'superadmin', 'inspector']))
                    <flux:sidebar.item icon="building-office-2" :href="route('entrevistas.recepcion')" :current="request()->routeIs('entrevistas.recepcion')" wire:navigate>
                        {{ __('Recepción / Portería') }}
                    </flux:sidebar.item>
                    @endif

                    @if(auth()->user()->hasRole(['docente', 'inspector', 'administrador', 'directivo', 'superadmin']))
                    <flux:sidebar.item icon="calendar-days" :href="route('entrevistas.agenda')" :current="request()->routeIs('entrevistas.agenda')" wire:navigate>
                        {{ __('Mi Agenda') }}
                    </flux:sidebar.item>
                    @endif

                    {{-- @if(auth()->user()->hasRole(['docente', 'inspector', 'administrador', 'directivo', 'superadmin']))
                    <flux:sidebar.item icon="chat-bubble-left-right" :href="route('entrevistas.crear')" :current="request()->routeIs('entrevistas.crear')" wire:navigate>
                        {{ __('Agendar Entrevista') }}
                    </flux:sidebar.item>
                    @endif --}}

                    @if(auth()->user()->hasRole(['docente', 'inspector', 'administrador', 'directivo', 'superadmin']))
                    <flux:sidebar.item icon="table-cells" :href="route('entrevistas.index')" :current="request()->routeIs('entrevistas.index')" wire:navigate>
                        {{ __('Historial General') }}
                    </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Gestión Académica')" class="grid mt-4">
                    @if(auth()->user()->hasRole(['directivo', 'administrador', 'superadmin', 'inspector', 'docente', 'asistente', 'psicosocial', 'recepcion']))
                    <flux:sidebar.item icon="users" :href="route('estudiantes.index')" :current="request()->routeIs('estudiantes.*')" wire:navigate>
                        {{ __('Estudiantes') }}
                    </flux:sidebar.item>
                    @endif

                    @if(auth()->user()->hasRole(['directivo', 'administrador', 'superadmin']))
                    <flux:sidebar.item icon="briefcase" :href="route('funcionarios.index')" :current="request()->routeIs('funcionarios.*')" wire:navigate>
                        {{ __('Funcionarios') }}
                    </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="folder-git-2" href="https://github.com/luisferlop/adminnhhs" target="_blank">
                    {{ __('Repositorio') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>
        </flux:sidebar>

        {{ $slot }}

        @fluxScripts
    </body>
</html>
