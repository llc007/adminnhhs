<?php

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public function changeSchool($schoolId)
    {
        $user = Auth::user();

        // Verificar que el usuario realmente pertenece a ese colegio
        if ($user->schools()->where('school_id', $schoolId)->exists()) {
            $user->update(['current_school_id' => $schoolId]);

            // Recargar la página actual pero con el nuevo colegio o ir al dashboard
            $this->redirect(request()->header('Referer') ?: '/dashboard', navigate: true);
        }
    }
}; ?>

<div @class(['px-2 mb-2' => auth()->user()->schools->count() > 1])>
    @if (auth()->user()->schools->count() > 1)
        <flux:dropdown align="start">
            <flux:button variant="subtle" icon="building-library" icon-trailing="chevron-down">
                <span class="truncate">{{ auth()->user()->currentSchool?->name ?? __('Colegio') }}</span>
            </flux:button>

            <flux:menu class="min-w-[200px]">
                <flux:menu.radio.group>
                    @foreach (auth()->user()->schools as $school)
                        <flux:menu.radio :checked="auth()->user()->current_school_id === $school->id"
                            wire:click="changeSchool({{ $school->id }})">
                            {{ $school->name }}
                        </flux:menu.radio>
                    @endforeach
                </flux:menu.radio.group>
            </flux:menu>
        </flux:dropdown>
    @endif
</div>