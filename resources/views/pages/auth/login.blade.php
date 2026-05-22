<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head', ['title' => __('Log in')])
        <style>
            @keyframes float-slow-1 {
                0%, 100% {
                    transform: translate(0px, 0px) scale(1) rotate(0deg);
                }
                33% {
                    transform: translate(40px, -60px) scale(1.1) rotate(120deg);
                }
                66% {
                    transform: translate(-30px, 30px) scale(0.95) rotate(240deg);
                }
            }
            @keyframes float-slow-2 {
                0%, 100% {
                    transform: translate(0px, 0px) scale(1) rotate(360deg);
                }
                50% {
                    transform: translate(-50px, 40px) scale(1.15) rotate(180deg);
                }
            }
            @keyframes float-slow-3 {
                0%, 100% {
                    transform: translate(0px, 0px) scale(1) rotate(0deg);
                }
                40% {
                    transform: translate(50px, 50px) scale(0.9) rotate(-90deg);
                }
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
    </head>
    <body class="relative min-h-screen overflow-hidden bg-slate-950 font-sans antialiased selection:bg-blue-600 selection:text-white">
        
        <!-- Background Container -->
        <div class="absolute inset-0 -z-10 overflow-hidden bg-slate-950">
            <!-- Subtle Blue Grid Overlay -->
            <div class="absolute inset-0 bg-[linear-gradient(to_right,#0284c703_1px,transparent_1px),linear-gradient(to_bottom,#0284c703_1px,transparent_1px)] bg-[size:4rem_4rem] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_50%,#000_70%,transparent_100%)]"></div>
            
            <!-- Animated Blue/Cyan/Indigo Orbs -->
            <div class="absolute top-[10%] left-[5%] h-[30rem] w-[30rem] rounded-full bg-blue-600/12 blur-[100px] mix-blend-screen" style="animation: float-slow-1 25s infinite ease-in-out;"></div>
            <div class="absolute bottom-[5%] right-[5%] h-[35rem] w-[35rem] rounded-full bg-sky-500/10 blur-[110px] mix-blend-screen" style="animation: float-slow-2 28s infinite ease-in-out;"></div>
            <div class="absolute top-[35%] right-[25%] h-[25rem] w-[25rem] rounded-full bg-indigo-600/10 blur-[90px] mix-blend-screen" style="animation: float-slow-3 22s infinite ease-in-out;"></div>
        </div>

        <!-- Main Wrapper -->
        <div class="flex min-h-screen items-center justify-center p-4 sm:p-6 md:p-10">
            <div class="relative w-full max-w-md">
                
                <!-- Ambient outer blue glow -->
                <div class="absolute -inset-1 rounded-2xl bg-gradient-to-r from-blue-600/20 via-sky-400/20 to-indigo-600/20 opacity-45 blur-xl transition duration-1000"></div>
                
                <!-- Card Container -->
                <div class="relative flex flex-col gap-8 rounded-2xl border border-white/10 bg-slate-900/60 p-8 shadow-2xl backdrop-blur-2xl sm:p-10">
                    
                    <!-- School Logo Section with Interactive Mascot Transition -->
                    <div class="flex flex-col items-center gap-4">
                        <div class="group/logo relative flex h-36 w-36 items-center justify-center rounded-full bg-white/5 p-1 border border-white/15 cursor-help overflow-hidden transition-all duration-300 hover:border-blue-400/30" style="animation: pulse-glow 5s infinite ease-in-out;">
                            <!-- Official Circular Logo (Default State) -->
                            <img src="{{ asset('images/logo.png') }}" alt="Logo New Heaven High School" class="h-32 w-32 object-contain transition-all duration-500 group-hover/logo:opacity-0 group-hover/logo:scale-75 group-hover/logo:rotate-12 absolute" />
                            <!-- Mascot Logo (Hover State) -->
                            <img src="{{ asset('images/logo-mascota.png') }}" alt="Mascota New Heaven High School" class="h-28 w-28 object-contain opacity-0 scale-75 -rotate-12 transition-all duration-500 group-hover/logo:opacity-100 group-hover/logo:scale-100 group-hover/logo:rotate-0 absolute" />
                        </div>
                        
                        <!-- Typography Headings -->
                        <div class="text-center">
                            <h1 class="bg-gradient-to-r from-white via-neutral-100 to-slate-400 bg-clip-text text-2xl font-bold tracking-tight text-transparent sm:text-3xl">
                                New Heaven
                            </h1>
                            <p class="mt-1 text-xs font-semibold uppercase tracking-widest text-sky-400 sm:text-sm">
                                High School
                            </p>
                            <p class="mt-3 text-xs text-slate-400 sm:text-sm font-medium tracking-wide">
                                {{ __('Alta Administración & Gestión Académica') }}
                            </p>
                        </div>
                    </div>

                    <!-- Status Messages & Session Errors -->
                    @if (session('status'))
                        <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-3 text-center text-sm font-medium text-emerald-400">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="rounded-xl border border-red-500/20 bg-red-500/10 p-3 text-center text-sm font-medium text-red-400">
                            {{ session('error') }}
                        </div>
                    @endif

                    <!-- Google Authentication Action Button -->
                    <div class="flex flex-col gap-4">
                        <a href="{{ route('auth.google') }}" class="group relative flex w-full items-center justify-center gap-3 rounded-xl border border-white/10 bg-white px-5 py-3.5 text-center text-sm font-bold text-slate-900 shadow-lg transition-all duration-300 hover:bg-slate-100 hover:shadow-xl hover:shadow-blue-500/10 active:scale-[0.98] cursor-pointer">
                            <!-- Google Vector Icon -->
                            <svg class="h-5 w-5 transition-transform duration-300 group-hover:scale-110" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
                                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                            </svg>
                            <span>{{ __('Ingresar con Google') }}</span>
                        </a>
                    </div>
                    
                    <!-- Footer branding info -->
                    <div class="text-center text-xs text-slate-500 tracking-wider">
                        &copy; {{ date('Y') }} {{ config('app.name', 'NHHS') }}. {{ __('Todos los derechos reservados.') }}
                    </div>
                </div>
            </div>
        </div>
        
        @fluxScripts
    </body>
</html>
