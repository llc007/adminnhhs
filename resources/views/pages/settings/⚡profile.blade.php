<?php

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Flux\Flux;

new #[Title('Profile settings')] class extends Component {
    use ProfileValidationRules;

    public string $nombres = '';
    public string $apellido_pat = '';
    public string $apellido_mat = '';
    public string $rut_numero = '';
    public string $rut_dv = '';
    public string $fecha_nacimiento = '';
    public string $telefono = '';
    public string $direccion = '';
    public string $email = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->nombres = Auth::user()->nombres ?? '';
        $this->apellido_pat = Auth::user()->apellido_pat ?? '';
        $this->apellido_mat = Auth::user()->apellido_mat ?? '';
        $this->rut_numero = Auth::user()->rut_numero ?? '';
        $this->rut_dv = Auth::user()->rut_dv ?? '';
        $this->fecha_nacimiento = Auth::user()->fecha_nacimiento ?? '';
        $this->telefono = Auth::user()->telefono ?? '';
        $this->direccion = Auth::user()->direccion ?? '';
        $this->email = Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
        
        if (isset($user->rut_dv) && $user->rut_dv !== '') {
            $user->rut_dv = strtoupper((string) $user->rut_dv);
        } else {
            $user->rut_dv = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->nombres);
        
        Flux::toast(
            heading: 'Cambios guardados',
            text: 'Tu perfil ha sido actualizado correctamente.',
            variant: 'success'
        );
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-pages::settings.layout>
        
        <!-- Profile Header Card (Editorial Style) -->
        <section class="mb-10 flex flex-col md:flex-row items-center md:items-end gap-8">
            <div class="relative">
                <div class="w-32 h-32 rounded-xl overflow-hidden shadow-sm bg-surface-container-high border-4 border-white dark:border-zinc-900 relative">
                    @if(Auth::user()->avatar)
                        <img class="w-full h-full object-cover" src="{{ Auth::user()->avatar }}" alt="Profile Avatar" referrerpolicy="no-referrer" onerror="this.onerror=null;this.style.display='none';this.nextElementSibling.style.display='flex';" />
                        <div class="w-full h-full bg-primary-fixed flex items-center justify-center text-primary font-bold text-4xl" style="display: none;">
                            {{ substr(Auth::user()->nombres, 0, 1) }}{{ substr(Auth::user()->apellido_pat, 0, 1) }}
                        </div>
                    @else
                        <div class="w-full h-full bg-primary-fixed flex items-center justify-center text-primary font-bold text-4xl">
                            {{ substr(Auth::user()->nombres, 0, 1) }}{{ substr(Auth::user()->apellido_pat, 0, 1) }}
                        </div>
                    @endif
                </div>
            </div>
            <div class="flex-1 text-center md:text-left space-y-1">
                <h2 class="font-headline text-3xl font-extrabold text-primary">{{ Auth::user()->nombreCompleto() }}</h2>
                <p class="font-body text-secondary text-lg flex items-center justify-center md:justify-start gap-2">
                    <flux:icon.academic-cap class="size-5 text-secondary" />
                    Docente / Funcionario
                </p>
                <div class="flex gap-2 mt-4 justify-center md:justify-start">
                    <span class="px-3 py-1 bg-secondary-container text-on-secondary-container text-xs font-bold rounded-full">PLANTA</span>
                    <span class="px-3 py-1 bg-surface-container-high text-secondary text-xs font-bold rounded-full">CUENTA VERIFICADA</span>
                </div>
            </div>
        </section>

        <!-- Main Form Card (Bento/Editorial Style) -->
        <div class="bg-surface-container-lowest rounded-xl shadow-sm border border-slate-100 dark:border-zinc-800 overflow-hidden mb-8">
            <div class="p-8 border-b border-slate-50 dark:border-zinc-800/50 bg-white dark:bg-zinc-900">
                <h3 class="font-headline text-xl font-bold text-primary dark:text-zinc-100">Ficha de Datos Personales</h3>
                <p class="text-secondary dark:text-zinc-400 text-sm">Mantenga su información actualizada para asegurar una comunicación efectiva con la institución.</p>
            </div>
            <form wire:submit="updateProfileInformation" class="p-8 space-y-10 bg-white dark:bg-zinc-900">
                
                <!-- Section 1: Identificación -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-x-10 gap-y-8">
                    <div class="md:col-span-12">
                        <h4 class="text-xs font-bold uppercase tracking-widest text-slate-400 dark:text-zinc-500 mb-2 border-b border-slate-100 dark:border-zinc-800 pb-2">Identificación Personal</h4>
                    </div>
                    
                    <div class="md:col-span-4">
                        <flux:input wire:model="nombres" :label="__('Nombre(s)')" type="text" required autofocus />
                    </div>
                    <div class="md:col-span-4">
                        <flux:input wire:model="apellido_pat" :label="__('Apellido Paterno')" type="text" required />
                    </div>
                    <div class="md:col-span-4">
                        <flux:input wire:model="apellido_mat" :label="__('Apellido Materno')" type="text" />
                    </div>
                    
                    <div class="md:col-span-4">
                        <div class="flex gap-2 items-end">
                            <flux:input wire:model="rut_numero" :label="__('RUT')" placeholder="12345678" class="flex-1" />
                            <flux:input wire:model="rut_dv" :label="__('DV')" placeholder="K" class="w-16" maxlength="1" />
                        </div>
                    </div>
                    <div class="md:col-span-4">
                        <flux:input wire:model="fecha_nacimiento" :label="__('Fecha de Nacimiento')" type="date" max="2999-12-31" />
                    </div>
                </div>

                <!-- Section 2: Contacto y Ubicación -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-x-10 gap-y-8 pt-6">
                    <div class="md:col-span-12">
                        <h4 class="text-xs font-bold uppercase tracking-widest text-slate-400 dark:text-zinc-500 mb-2 border-b border-slate-100 dark:border-zinc-800 pb-2">Contacto y Ubicación</h4>
                    </div>
                    
                    <div class="md:col-span-12">
                        <flux:input wire:model="direccion" :label="__('Dirección Particular')" type="text" placeholder="Calle, Número, Depto, Comuna" />
                    </div>
                    
                    <div class="md:col-span-6">
                        <flux:field>
                            <flux:label>{{ __('Teléfono Móvil') }}</flux:label>
                            <flux:input.group>
                                <flux:input.group.prefix>+56 9</flux:input.group.prefix>
                                <flux:input wire:model="telefono" type="tel" placeholder="1234 5678" />
                            </flux:input.group>
                            <flux:error name="telefono" />
                        </flux:field>
                    </div>
                    
                    <div class="md:col-span-6">
                        <flux:input wire:model="email" :label="__('Correo Electrónico (Institucional)')" type="email" required autocomplete="email" disabled />
                        
                        @if ($this->hasUnverifiedEmail)
                            <div class="mt-2">
                                <flux:text>
                                    {{ __('Your email address is unverified.') }}
                                    <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                        {{ __('Click here to re-send the verification email.') }}
                                    </flux:link>
                                </flux:text>
                                @if (session('status') === 'verification-link-sent')
                                    <flux:text class="mt-2 font-medium !dark:text-green-400 !text-green-600">
                                        {{ __('A new verification link has been sent to your email address.') }}
                                    </flux:text>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex flex-col sm:flex-row items-center justify-end gap-4 pt-10 border-t border-slate-50 dark:border-zinc-800/50 mt-4">
                    <flux:button icon="check" variant="primary" type="submit" class="w-full sm:w-auto px-10" data-test="update-profile-button">
                        {{ __('Guardar Cambios') }}
                    </flux:button>
                </div>
            </form>
        </div>

        <!-- Contextual Information (Editorial Side Note) -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="p-6 bg-blue-50/50 dark:bg-blue-900/20 rounded-xl flex items-start gap-4 border border-blue-100 dark:border-blue-800">
                <flux:icon.information-circle class="size-6 text-primary" />
                <div>
                    <h5 class="font-bold text-primary text-sm">Privacidad de Datos</h5>
                    <p class="text-xs text-secondary leading-relaxed mt-1">Su información personal está protegida bajo la Ley 19.628 de Protección de la Vida Privada. Estos datos son de uso exclusivo institucional.</p>
                </div>
            </div>
            <div class="p-6 bg-orange-50/50 dark:bg-orange-900/20 rounded-xl flex items-start gap-4 border border-orange-100 dark:border-orange-800">
                <flux:icon.shield-check class="size-6 text-orange-600 dark:text-orange-400" />
                <div>
                    <h5 class="font-bold text-orange-600 dark:text-orange-400 text-sm">Verificación de Identidad</h5>
                    <p class="text-xs text-secondary leading-relaxed mt-1">Los cambios en RUT o Nombres legales requieren presentar el documento de identidad original en secretaría administrativa.</p>
                </div>
            </div>
        </div>

        @if ($this->showDeleteUser)
            <div class="mt-12 hidden">
                <livewire:pages::settings.delete-user-form />
            </div>
        @endif
    </x-pages::settings.layout>
</section>
