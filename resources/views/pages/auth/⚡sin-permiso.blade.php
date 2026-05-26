<?php

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;

new #[Layout('layouts.blank')] class extends Component {
    /**
     * Store IDs of notifications that have already triggered a Toast in the current session/page lifecycle.
     *
     * @var array<int, string>
     */
    public array $notifiedIds = [];

    /**
     * Terminate the session and log out the user.
     */
    public function logout()
    {
        Auth::guard('web')->logout();
        session()->invalidate();
        session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Submit an access request for the given role, notify admins, and log out.
     */
    public function solicitarAcceso(string $rol)
    {
        $user = Auth::user();
        $schoolId = $user->current_school_id;

        if ($schoolId) {
            // Find all administrators and superadministrators for the current school
            $administradores = \App\Models\User::whereHas('schools', function ($q) use ($schoolId) {
                $q->where('school_id', $schoolId)
                  ->where(function($sub) {
                      $sub->whereJsonContains('school_user.roles', 'administrador')
                          ->orWhereJsonContains('school_user.roles', 'superadmin');
                  });
            })->get();

            // Create notification instance
            $notification = new \App\Notifications\SolicitudAcceso($user, $rol);

            // Notify each administrator in real-time
            foreach ($administradores as $admin) {
                $admin->notify($notification);
            }
        }

        // Safely invalidate session and log out
        Auth::guard('web')->logout();
        session()->invalidate();
        session()->regenerateToken();

        // Redirect back to login with a clean success message
        return redirect()->route('login')->with('status', 'Solicitud de acceso enviada con éxito. El administrador ha sido notificado.');
    }
};
?>

<div class="relative min-h-screen w-full overflow-x-hidden bg-slate-950 font-sans antialiased text-white">
    <style>
        @keyframes float-slow-1 {
            0%, 100% { transform: translate(0px, 0px) scale(1) rotate(0deg); }
            33% { transform: translate(40px, -60px) scale(1.1) rotate(120deg); }
            66% { transform: translate(-30px, 30px) scale(0.95) rotate(240deg); }
        }
        @keyframes float-slow-2 {
            0%, 100% { transform: translate(0px, 0px) scale(1) rotate(360deg); }
            50% { transform: translate(-50px, 40px) scale(1.15) rotate(180deg); }
        }
        @keyframes float-slow-3 {
            0%, 100% { transform: translate(0px, 0px) scale(1) rotate(0deg); }
            40% { transform: translate(50px, 50px) scale(0.9) rotate(-90deg); }
        }
        @keyframes pulse-glow {
            0%, 100% {
                transform: scale(1);
                filter: drop-shadow(0 0 15px rgba(59, 130, 246, 0.2)) drop-shadow(0 0 5px rgba(14, 165, 233, 0.15));
            }
            50% {
                transform: scale(1.03);
                filter: drop-shadow(0 0 25px rgba(59, 130, 246, 0.4)) drop-shadow(0 0 15px rgba(14, 165, 233, 0.3));
            }
        }
    </style>
    
    <!-- Background Container -->
    <div class="absolute inset-0 -z-10 overflow-hidden bg-slate-950">
        <!-- Subtle Blue Grid Overlay -->
        <div class="absolute inset-0 bg-[linear-gradient(to_right,#0284c703_1px,transparent_1px),linear-gradient(to_bottom,#0284c703_1px,transparent_1px)] bg-[size:4rem_4rem] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_50%,#000_70%,transparent_100%)]"></div>
        
        <!-- Animated Blue/Cyan/Indigo Orbs -->
        <div class="absolute top-[5%] left-[5%] h-[28rem] w-[28rem] rounded-full bg-blue-600/10 blur-[100px] mix-blend-screen" style="animation: float-slow-1 25s infinite ease-in-out;"></div>
        <div class="absolute bottom-[5%] right-[5%] h-[32rem] w-[32rem] rounded-full bg-sky-500/8 blur-[110px] mix-blend-screen" style="animation: float-slow-2 28s infinite ease-in-out;"></div>
        <div class="absolute top-[30%] right-[20%] h-[22rem] w-[22rem] rounded-full bg-indigo-600/8 blur-[90px] mix-blend-screen" style="animation: float-slow-3 22s infinite ease-in-out;"></div>
    </div>

    <!-- Top Right Logout Action Button -->
    <div class="absolute top-4 right-4 z-50">
        <flux:button wire:click="logout" variant="ghost" icon="arrow-right-start-on-rectangle" class="text-slate-400 hover:text-white cursor-pointer">
            {{ __('Cerrar Sesión') }}
        </flux:button>
    </div>

    <!-- Main Wrapper -->
    <div class="flex min-h-screen items-center justify-center p-4 sm:p-6 md:p-10">
        <div class="relative w-full max-w-2xl my-8">
            
            <!-- Ambient outer blue glow -->
            <div class="absolute -inset-1 rounded-2xl bg-gradient-to-r from-blue-600/20 via-sky-400/20 to-indigo-600/20 opacity-40 blur-xl transition duration-1000"></div>
            
            <!-- Card Container -->
            <div class="relative flex flex-col gap-8 rounded-2xl border border-white/10 bg-slate-900/60 p-6 shadow-2xl backdrop-blur-2xl sm:p-10">
                
                <!-- School Logo & Banner Header -->
                <div class="flex flex-col items-center gap-4 text-center">
                    <div class="relative flex h-28 w-28 items-center justify-center rounded-full bg-white/5 p-1 border border-white/15" style="animation: pulse-glow 5s infinite ease-in-out;">
                        <img src="{{ asset('images/logo.png') }}" alt="Logo New Heaven High School" class="h-24 w-24 object-contain" />
                    </div>
                    
                    <div class="space-y-1">
                        <h1 class="bg-gradient-to-r from-white via-neutral-100 to-slate-400 bg-clip-text text-2xl font-bold tracking-tight text-transparent sm:text-3xl">
                            {{ __('Permiso Requerido') }}
                        </h1>
                        <p class="text-xs font-semibold uppercase tracking-widest text-sky-400">
                            {{ __('New Heaven High School') }}
                        </p>
                    </div>
                </div>

                <!-- Description Message Box -->
                <div class="rounded-xl border border-blue-500/20 bg-blue-500/5 p-5 text-start space-y-2 text-slate-300">
                    <p class="text-sm font-medium">
                        {!! __('Hola, <strong>:name</strong> (<em>:email</em>).', ['name' => auth()->user()->nombres, 'email' => auth()->user()->email]) !!}
                    </p>
                    <p class="text-xs sm:text-sm text-slate-400 leading-relaxed">
                        {{ __('Tu cuenta ha sido vinculada correctamente al colegio, pero no cuenta con roles activos para ingresar a la plataforma de gestión académica.') }}
                    </p>
                    <p class="text-xs sm:text-sm text-sky-400 font-semibold tracking-wide">
                        {{ __('Para ingresar, por favor selecciona el rol correspondiente a tu función para que el administrador active tu cuenta:') }}
                    </p>
                </div>

                <!-- Role Selection Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Docente -->
                    <button wire:click="solicitarAcceso('docente')" class="group flex items-start gap-4 p-4 rounded-xl border border-white/10 bg-white/5 hover:bg-blue-600/10 hover:border-blue-500/30 transition-all duration-300 text-left active:scale-[0.98] cursor-pointer">
                        <div class="bg-blue-500/10 dark:bg-blue-900/40 p-2.5 rounded-lg text-blue-400 group-hover:bg-blue-500 group-hover:text-white transition-all duration-300">
                            <flux:icon name="calendar-days" class="size-5" />
                        </div>
                        <div>
                            <h3 class="font-bold text-sm text-white group-hover:text-blue-400 transition-colors">{{ __('Docente') }}</h3>
                            <p class="text-xs text-slate-400 mt-1 leading-normal">{{ __('Gestión de mi agenda, citas con apoderados y bitácoras.') }}</p>
                        </div>
                    </button>

                    <!-- Recepción -->
                    <button wire:click="solicitarAcceso('recepcion')" class="group flex items-start gap-4 p-4 rounded-xl border border-white/10 bg-white/5 hover:bg-blue-600/10 hover:border-blue-500/30 transition-all duration-300 text-left active:scale-[0.98] cursor-pointer">
                        <div class="bg-blue-500/10 dark:bg-blue-900/40 p-2.5 rounded-lg text-blue-400 group-hover:bg-blue-500 group-hover:text-white transition-all duration-300">
                            <flux:icon name="building-office-2" class="size-5" />
                        </div>
                        <div>
                            <h3 class="font-bold text-sm text-white group-hover:text-blue-400 transition-colors">{{ __('Recepción / Portería') }}</h3>
                            <p class="text-xs text-slate-400 mt-1 leading-normal">{{ __('Control de acceso al recinto, registro de visitas y asignación de boxes.') }}</p>
                        </div>
                    </button>

                    <!-- Directivo -->
                    <button wire:click="solicitarAcceso('directivo')" class="group flex items-start gap-4 p-4 rounded-xl border border-white/10 bg-white/5 hover:bg-blue-600/10 hover:border-blue-500/30 transition-all duration-300 text-left active:scale-[0.98] cursor-pointer">
                        <div class="bg-blue-500/10 dark:bg-blue-900/40 p-2.5 rounded-lg text-blue-400 group-hover:bg-blue-500 group-hover:text-white transition-all duration-300">
                            <flux:icon name="presentation-chart-bar" class="size-5" />
                        </div>
                        <div>
                            <h3 class="font-bold text-sm text-white group-hover:text-blue-400 transition-colors">{{ __('Directivo') }}</h3>
                            <p class="text-xs text-slate-400 mt-1 leading-normal">{{ __('Visualización de reportes académicos, analítica y monitoreo escolar.') }}</p>
                        </div>
                    </button>

                    <!-- Estudiante -->
                    <button wire:click="solicitarAcceso('estudiante')" class="group flex items-start gap-4 p-4 rounded-xl border border-white/10 bg-white/5 hover:bg-blue-600/10 hover:border-blue-500/30 transition-all duration-300 text-left active:scale-[0.98] cursor-pointer">
                        <div class="bg-blue-500/10 dark:bg-blue-900/40 p-2.5 rounded-lg text-blue-400 group-hover:bg-blue-500 group-hover:text-white transition-all duration-300">
                            <flux:icon name="users" class="size-5" />
                        </div>
                        <div>
                            <h3 class="font-bold text-sm text-white group-hover:text-blue-400 transition-colors">{{ __('Estudiante') }}</h3>
                            <p class="text-xs text-slate-400 mt-1 leading-normal">{{ __('Acceso a mi resumen personal, registro de entrevistas y atenciones.') }}</p>
                        </div>
                    </button>

                    <!-- Inspectoría -->
                    <button wire:click="solicitarAcceso('inspector')" class="group flex items-start gap-4 p-4 rounded-xl border border-white/10 bg-white/5 hover:bg-blue-600/10 hover:border-blue-500/30 transition-all duration-300 text-left active:scale-[0.98] cursor-pointer">
                        <div class="bg-blue-500/10 dark:bg-blue-900/40 p-2.5 rounded-lg text-blue-400 group-hover:bg-blue-500 group-hover:text-white transition-all duration-300">
                            <flux:icon name="shield-check" class="size-5" />
                        </div>
                        <div>
                            <h3 class="font-bold text-sm text-white group-hover:text-blue-400 transition-colors">{{ __('Inspectoría') }}</h3>
                            <p class="text-xs text-slate-400 mt-1 leading-normal">{{ __('Supervisión de disciplina, bitácoras generales y seguridad interna.') }}</p>
                        </div>
                    </button>

                    <!-- Asistente de Educación -->
                    <button wire:click="solicitarAcceso('asistente')" class="group flex items-start gap-4 p-4 rounded-xl border border-white/10 bg-white/5 hover:bg-blue-600/10 hover:border-blue-500/30 transition-all duration-300 text-left active:scale-[0.98] cursor-pointer">
                        <div class="bg-blue-500/10 dark:bg-blue-900/40 p-2.5 rounded-lg text-blue-400 group-hover:bg-blue-500 group-hover:text-white transition-all duration-300">
                            <flux:icon name="briefcase" class="size-5" />
                        </div>
                        <div>
                            <h3 class="font-bold text-sm text-white group-hover:text-blue-400 transition-colors">{{ __('Asistente de Educación') }}</h3>
                            <p class="text-xs text-slate-400 mt-1 leading-normal">{{ __('Funciones de apoyo estudiantil, psicopedagogía y soporte general.') }}</p>
                        </div>
                    </button>
                </div>

                <!-- Note footer info -->
                <div class="text-center text-xs text-slate-500">
                    {{ __('Al presionar un rol, tu sesión actual se cerrará y volverás al Login mientras tu solicitud es analizada por administración.') }}
                </div>
            </div>
        </div>
    </div>
</div>
