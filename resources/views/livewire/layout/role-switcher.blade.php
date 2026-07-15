<?php

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Flux\Flux;

new class extends Component {
    /**
     * Get the active roles of the user.
     */
    #[\Livewire\Attributes\Computed]
    public function activeRoles(): array
    {
        return Auth::check() ? Auth::user()->active_roles : [];
    }

    /**
     * Toggle a role for the authenticated user.
     */
    public function toggleRole(string $role): void
    {
        $user = Auth::user();
        if (!$user || !$user->current_school_id) {
            return;
        }

        $roles = $user->active_roles;

        if (in_array($role, $roles)) {
            $roles = array_diff($roles, [$role]);
        } else {
            $roles[] = $role;
        }

        $roles = array_values(array_filter($roles));

        if (empty($roles)) {
            $roles = ['externo'];
        }

        $user->schools()->updateExistingPivot($user->current_school_id, [
            'roles' => json_encode($roles),
        ]);

        // Force refresh relations
        $user->unsetRelation('schools');

        Flux::toast(
            heading: __('Rol Actualizado'),
            text: __('Se ha actualizado tu perfil de roles de desarrollo.'),
            variant: 'success'
        );

        // Refresh page to apply permissions change
        $this->redirect(request()->header('Referer') ?? route('home'));
    }
}; ?>

<div class="fixed bottom-4 left-4 z-50">
    @auth
        <flux:dropdown position="top" align="start">
            <flux:button variant="filled" icon="key" size="sm" class="!bg-amber-600 hover:!bg-amber-700 !text-white border-none shadow-lg">
                {{ __('Dev Roles') }}
            </flux:button>

            <flux:menu class="w-64 max-h-[80vh] overflow-y-auto">
                <flux:menu.heading class="uppercase tracking-widest text-[9px] font-bold">{{ __('Roles de Desarrollo (Local)') }}</flux:menu.heading>
                
                @php
                    $availableRoles = [
                        'superadmin' => ['label' => 'Superadmin Global', 'color' => 'red'],
                        'administrador' => ['label' => 'Administrador', 'color' => 'rose'],
                        'directivo' => ['label' => 'Directivo', 'color' => 'violet'],
                        'docente' => ['label' => 'Docente de Aula', 'color' => 'blue'],
                        'inspector' => ['label' => 'Inspectoría', 'color' => 'indigo'],
                        'asistente' => ['label' => 'Asistente de Ed.', 'color' => 'teal'],
                        'psicosocial' => ['label' => 'Psicosocial', 'color' => 'cyan'],
                        'recepcion' => ['label' => 'Recepción/Portería', 'color' => 'emerald'],
                        'solicitante_adquisiciones' => ['label' => 'Solicitante Adq.', 'color' => 'amber'],
                        'ti' => ['label' => 'Personal de TI', 'color' => 'sky'],
                    ];
                @endphp

                @foreach ($availableRoles as $role => $data)
                    @php $isActive = in_array($role, $this->activeRoles); @endphp
                    <flux:menu.item 
                        wire:click="toggleRole('{{ $role }}')"
                        icon="{{ $isActive ? 'check' : 'minus' }}"
                        class="{{ $isActive ? 'font-bold text-amber-700 dark:text-amber-400 bg-amber-500/10' : '' }}"
                    >
                        {{ $data['label'] }}
                    </flux:menu.item>
                @endforeach
            </flux:menu>
        </flux:dropdown>
    @endauth
</div>
