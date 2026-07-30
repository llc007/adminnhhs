<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema en Mantenimiento Programado</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; }
        @keyframes pulseGlow {
            0%, 100% { transform: scale(1); opacity: 0.8; }
            50% { transform: scale(1.08); opacity: 0.4; }
        }
        .animate-glow { animation: pulseGlow 3s infinite ease-in-out; }
    </style>
</head>
<body class="h-full bg-gradient-to-br from-slate-900 via-zinc-900 to-blue-950 text-white flex items-center justify-center p-4 relative overflow-hidden">

    <!-- Abstract glowing Orbs background -->
    <div class="absolute -top-24 -left-24 w-96 h-96 bg-blue-600/20 rounded-full blur-3xl pointer-events-none animate-glow"></div>
    <div class="absolute -bottom-24 -right-24 w-96 h-96 bg-indigo-600/20 rounded-full blur-3xl pointer-events-none animate-glow" style="animation-delay: 1.5s;"></div>

    <div class="max-w-xl w-full text-center relative z-10 space-y-6 bg-white/5 backdrop-blur-xl border border-white/10 p-8 sm:p-10 rounded-3xl shadow-2xl">
        
        <!-- Logo Banner (Reemplaza al engranaje) -->
        <div class="relative inline-flex items-center justify-center">
            <div class="absolute inset-0 bg-blue-500/30 rounded-full blur-xl animate-pulse"></div>
            <div class="relative size-28 sm:size-32 rounded-full bg-white/10 p-2 border border-white/20 flex items-center justify-center shadow-2xl backdrop-blur-md">
                <img src="{{ asset('images/logo.png') }}" alt="{{ $school->name ?? 'Logo Liceo' }}" class="size-24 sm:size-28 object-contain drop-shadow-lg" />
            </div>
        </div>

        <div>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30 uppercase tracking-widest mb-3">
                ⚠️ ACTUALIZACIÓN DEL SISTEMA EN CURSO
            </span>
            <h1 class="text-xl sm:text-2xl font-bold text-white/90 tracking-tight">
                {{ $school->name ?? 'Plataforma Institucional' }}
            </h1>
        </div>

        <div class="bg-zinc-900/60 border border-zinc-800 p-5 rounded-2xl text-left text-zinc-300 text-sm leading-relaxed space-y-2">
            <p class="font-semibold text-zinc-100 text-center text-base">
                Estamos actualizando el sistema 🛠️
            </p>
            <p class="text-center text-zinc-400">
                {{ $mensaje ?? 'El sistema se encuentra en mantenimiento programado. Volveremos en breve.' }}
            </p>
        </div>

        <div class="pt-2 flex flex-col sm:flex-row items-center justify-center gap-3">
            <button onclick="window.location.reload();" class="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-sm transition-all shadow-lg hover:shadow-blue-500/25 cursor-pointer">
                🔄 Reintentar Acceso
            </button>

            <form method="POST" action="{{ route('logout') }}" class="w-full sm:w-auto">
                @csrf
                <button type="submit" class="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-bold text-sm transition-all border border-zinc-700 cursor-pointer">
                    🚪 Cerrar Sesión
                </button>
            </form>
        </div>

        <div class="text-[11px] text-zinc-500 pt-4 border-t border-zinc-800/60">
            Si requieres asistencia urgente, por favor contacta al administrador del establecimiento.
        </div>
    </div>
</body>
</html>
