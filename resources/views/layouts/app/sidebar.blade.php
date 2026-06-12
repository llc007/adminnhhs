@php
    $school = auth()->user()->currentSchool;
    $modulos = $school
        ? $school->modulos_publicados
        : [
            'entrevistas' => true,
            'estudiantes' => true,
            'adquisiciones' => true,
            'prestamos' => true,
        ];
    $isAdmin = auth()
        ->user()
        ->hasRole(['administrador', 'directivo', 'superadmin']);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-white dark:bg-zinc-800">
    <flux:sidebar sticky collapsible class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:sidebar.header>
            <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
            <flux:sidebar.collapse
                class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
        </flux:sidebar.header>

        <livewire:admin.seleccionar-colegio />

        <flux:sidebar.nav>
            @if ($isAdmin || ($modulos['entrevistas'] ?? false))
                <flux:sidebar.group :heading="__('Entrevistas')" class="grid">
                    @if (auth()->user()->hasRole(['administrador', 'directivo', 'superadmin']))
                        <flux:sidebar.item icon="home" :href="route('dashboard')"
                            :current="request()->routeIs('dashboard')" wire:navigate>
                            {{ 'Dashboard' }}
                        </flux:sidebar.item>
                    @endif

                    @if (auth()->user()->hasRole(['recepcion', 'directivo', 'administrador', 'superadmin', 'inspector']))
                        <flux:sidebar.item icon="building-office-2" :href="route('entrevistas.recepcion')"
                            :current="request()->routeIs('entrevistas.recepcion')" wire:navigate>
                            {{ __('Recepción / Portería') }}
                        </flux:sidebar.item>
                    @endif

                    @if (auth()->user()->hasRole(['docente', 'inspector', 'administrador', 'directivo', 'superadmin']) &&
                            ($isAdmin || ($modulos['entrevistas'] ?? false)))
                        <flux:sidebar.item icon="calendar-days" :href="route('entrevistas.agenda')"
                            :current="request()->routeIs('entrevistas.agenda')" wire:navigate>
                            {{ __('Mi Agenda') }}
                        </flux:sidebar.item>
                    @endif

                    {{-- @if (auth()->user()->hasRole(['docente', 'inspector', 'administrador', 'directivo', 'superadmin']))
                    <flux:sidebar.item icon="chat-bubble-left-right" :href="route('entrevistas.crear')" :current="request()->routeIs('entrevistas.crear')" wire:navigate>
                        {{ __('Agendar Entrevista') }}
                    </flux:sidebar.item>
                    @endif --}}

                    @if (auth()->user()->hasRole(['docente', 'inspector', 'administrador', 'directivo', 'superadmin']) &&
                            ($isAdmin || ($modulos['entrevistas'] ?? false)))
                        <flux:sidebar.item icon="table-cells" :href="route('entrevistas.index')"
                            :current="request()->routeIs('entrevistas.index')" wire:navigate>
                            {{ __('Historial General') }}
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>
            @endif

            @if ($isAdmin || ($modulos['estudiantes'] ?? false))
                <flux:sidebar.group :heading="__('Gestión Académica')" class="grid mt-4">
                    @if (auth()->user()->hasRole([
                                'directivo',
                                'administrador',
                                'superadmin',
                                'inspector',
                                'docente',
                                'asistente',
                                'psicosocial',
                                'recepcion',
                            ]) &&
                            ($isAdmin || ($modulos['estudiantes'] ?? false)))
                        <flux:sidebar.item icon="users" :href="route('estudiantes.index')"
                            :current="request()->routeIs('estudiantes.*')" wire:navigate>
                            {{ __('Estudiantes') }}
                        </flux:sidebar.item>
                    @endif

                    @if (auth()->user()->hasRole(['directivo', 'administrador', 'superadmin']))
                        <flux:sidebar.item icon="briefcase" :href="route('funcionarios.index')"
                            :current="request()->routeIs('funcionarios.index') || request()->routeIs('funcionarios.ficha') || request()->routeIs('funcionarios.carga_masiva')"
                            wire:navigate>
                            {{ __('Funcionarios') }}
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>
            @endif

            @if (auth()->user()->hasRole(['directivo', 'administrador', 'superadmin']))
                <flux:sidebar.group :heading="__('Administración')" class="grid mt-4">
                    <flux:sidebar.item icon="adjustments-horizontal" :href="route('admin.modules')"
                        :current="request()->routeIs('admin.modules')" wire:navigate>
                        {{ __('Módulos') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="clock" :href="route('funcionarios.calculadora_horas')"
                        :current="request()->routeIs('funcionarios.calculadora_horas')" wire:navigate>
                        {{ __('Calculadora 42 Hrs') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="envelope" :href="route('admin.mail_logs')"
                        :current="request()->routeIs('admin.mail_logs')" wire:navigate>
                        {{ __('Historial de Correos') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            @endif


            @if ($isAdmin || ($modulos['adquisiciones'] ?? false))
                <flux:sidebar.group :heading="__('Adquisiciones e Inventario')" class="grid mt-4">
                    @if ($isAdmin || ($modulos['adquisiciones'] ?? false))
                        <flux:sidebar.item icon="document-text" :href="route('adquisiciones.crear')"
                            :current="request()->routeIs('adquisiciones.crear')" wire:navigate>
                            {{ __('Solicitar Adquisición') }}
                        </flux:sidebar.item>
                    @endif

                    @if (auth()->user()->hasRole(['directivo', 'administrador', 'superadmin']))
                        <flux:sidebar.item icon="shield-check" :href="route('adquisiciones.revision')"
                            :current="request()->routeIs('adquisiciones.revision')" wire:navigate>
                            {{ __('Bandeja de Revisión') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="shopping-cart" :href="route('adquisiciones.compras')"
                            :current="request()->routeIs('adquisiciones.compras')" wire:navigate>
                            {{ __('Recepción de Compras') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="archive-box" :href="route('inventario.index')"
                            :current="request()->routeIs('inventario.index')" wire:navigate>
                            {{ __('Inventario General') }}
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>
            @endif

            @php
                $isTI = auth()
                    ->user()
                    ->hasRole(['ti', 'administrador', 'superadmin']);
            @endphp
            @if ($isTI || ($modulos['prestamos'] ?? false))
                <flux:sidebar.group :heading="__('Informática')" class="grid mt-4">
                    @if ($isTI)
                        <flux:sidebar.item icon="briefcase" :href="route('ti.prestamos.index')"
                            :current="request()->routeIs('ti.prestamos.*')" wire:navigate>
                            {{ __('Préstamos') }}
                        </flux:sidebar.item>
                    @elseif ($modulos['prestamos'] ?? false)
                        <flux:sidebar.item icon="briefcase" :href="route('ti.prestamos.mis_prestamos')"
                            :current="request()->routeIs('ti.prestamos.mis_prestamos')" wire:navigate>
                            {{ __('Mis Préstamos/Asignaciones') }}
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>
            @endif


        </flux:sidebar.nav>

        <flux:spacer />

        <flux:sidebar.nav>
            <flux:sidebar.item icon="folder-git-2" href="https://github.com/luisferlop/adminnhhs" target="_blank">
                {{ __('Repositorio') }}
            </flux:sidebar.item>
        </flux:sidebar.nav>

        <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
    </flux:sidebar>

    <!-- Mobile User Menu -->
    <flux:header class="lg:hidden">
        <flux:sidebar.toggle class="lg:hidden shrink-0" icon="bars-2" inset="left" />

        <flux:spacer />

        <div class="flex items-center gap-2">
            <livewire:layout.notifications-bell />

            <flux:dropdown position="top" align="end">
                <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer" data-test="logout-button">
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </div>
    </flux:header>

    {{ $slot }}

    {{-- Toast para notificaciones globales --}}
    <flux:toast position="top right" />

    @fluxScripts
</body>

</html>
