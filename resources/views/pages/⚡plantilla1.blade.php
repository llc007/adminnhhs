<!DOCTYPE html>

<html lang="es">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Gestiòn Escolar - Dashboard Ejecutivo</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&amp;family=Public_Sans:wght@300;400;500;600&amp;display=swap"
        rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
        rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
        rel="stylesheet" />
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "primary-fixed": "#d6e3ff",
                        "error": "#ba1a1a",
                        "on-tertiary-container": "#eab66d",
                        "surface-container-highest": "#e0e3e6",
                        "outline-variant": "#c1c7cf",
                        "surface": "#f7f9fc",
                        "on-secondary-fixed-variant": "#304a55",
                        "on-secondary-container": "#4e6874",
                        "tertiary-fixed": "#ffddb3",
                        "background": "#f7f9fc",
                        "on-primary-fixed": "#001b3d",
                        "on-background": "#191c1e",
                        "surface-bright": "#f7f9fc",
                        "tertiary": "#4d3100",
                        "on-primary": "#ffffff",
                        "error-container": "#ffdad6",
                        "inverse-on-surface": "#eff1f4",
                        "secondary": "#48626e",
                        "tertiary-fixed-dim": "#f2bd74",
                        "inverse-primary": "#a8c8ff",
                        "secondary-fixed": "#cbe7f5",
                        "on-tertiary-fixed": "#291800",
                        "on-surface": "#191c1e",
                        "outline": "#72787f",
                        "surface-dim": "#d8dadd",
                        "surface-container-lowest": "#ffffff",
                        "secondary-container": "#cbe7f5",
                        "on-error-container": "#93000a",
                        "on-primary-fixed-variant": "#00468a",
                        "surface-tint": "#1e5eac",
                        "on-secondary": "#ffffff",
                        "secondary-fixed-dim": "#afcbd8",
                        "on-tertiary": "#ffffff",
                        "on-surface-variant": "#41474e",
                        "surface-variant": "#e0e3e6",
                        "tertiary-container": "#6b4604",
                        "on-secondary-fixed": "#021f29",
                        "surface-container-high": "#e6e8eb",
                        "surface-container-low": "#f2f4f7",
                        "primary-container": "#004d97",
                        "on-tertiary-fixed-variant": "#633f00",
                        "inverse-surface": "#2d3133",
                        "primary": "#00376e",
                        "on-primary-container": "#9cc0ff",
                        "on-error": "#ffffff",
                        "surface-container": "#eceef1",
                        "primary-fixed-dim": "#a8c8ff"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.125rem",
                        "lg": "0.25rem",
                        "xl": "0.5rem",
                        "full": "0.75rem"
                    },
                    "fontFamily": {
                        "headline": ["Manrope"],
                        "display": ["Manrope"],
                        "body": ["Public Sans"],
                        "label": ["Public Sans"]
                    }
                }
            }
        }
    </script>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        body {
            font-family: 'Public Sans', sans-serif;
        }

        h1,
        h2,
        h3 {
            font-family: 'Manrope', sans-serif;
        }

        .glass-panel {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px);
        }
    </style>
</head>

<body class="bg-surface text-on-surface">
    <!-- SideNavBar -->
    <aside class="fixed left-0 top-0 h-full flex flex-col z-40 h-screen w-64 bg-[#f7f9fc] dark:bg-slate-950">
        <div class="px-6 py-8">
            <div class="flex items-center gap-3 mb-8">
                <div class="w-10 h-10 rounded-xl bg-primary flex items-center justify-center">
                    <span class="material-symbols-outlined text-white"
                        style="font-variation-settings: 'FILL' 1;">school</span>
                </div>
                <div>
                    <h1 class="font-['Manrope'] font-extrabold text-xl text-[#00376e] dark:text-white leading-tight">
                        Gestiòn Escolar</h1>
                    <p class="text-[10px] uppercase tracking-widest text-secondary font-bold">Panel Ejecutivo</p>
                </div>
            </div>
            <button
                class="w-full bg-gradient-to-br from-primary to-primary-container text-on-primary py-3 px-4 rounded-xl font-bold flex items-center justify-center gap-2 mb-8 shadow-md hover:opacity-90 transition-all scale-98 active:opacity-80">
                <span class="material-symbols-outlined">add_circle</span>
                Nueva Entrevista
            </button>
            <nav class="space-y-1">
                <a class="flex items-center gap-3 px-4 py-3 rounded-lg text-[#00376e] dark:text-blue-300 font-bold border-l-4 border-[#00376e] bg-[#f2f4f7] dark:bg-slate-800 transition-colors duration-200"
                    href="#">
                    <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">dashboard</span>
                    <span class="font-['Public_Sans'] font-medium text-sm tracking-tight">Dashboard</span>
                </a>
                <a class="flex items-center gap-3 px-4 py-3 rounded-lg text-[#48626e] dark:text-slate-400 hover:bg-[#f2f4f7] dark:hover:bg-slate-900 transition-colors duration-200"
                    href="#">
                    <span class="material-symbols-outlined">calendar_today</span>
                    <span class="font-['Public_Sans'] font-medium text-sm tracking-tight">Entrevistas</span>
                </a>
                <a class="flex items-center gap-3 px-4 py-3 rounded-lg text-[#48626e] dark:text-slate-400 hover:bg-[#f2f4f7] dark:hover:bg-slate-900 transition-colors duration-200"
                    href="#">
                    <span class="material-symbols-outlined">badge</span>
                    <span class="font-['Public_Sans'] font-medium text-sm tracking-tight">Docentes</span>
                </a>
                <a class="flex items-center gap-3 px-4 py-3 rounded-lg text-[#48626e] dark:text-slate-400 hover:bg-[#f2f4f7] dark:hover:bg-slate-900 transition-colors duration-200"
                    href="#">
                    <span class="material-symbols-outlined">analytics</span>
                    <span class="font-['Public_Sans'] font-medium text-sm tracking-tight">Reportes</span>
                </a>
                <a class="flex items-center gap-3 px-4 py-3 rounded-lg text-[#48626e] dark:text-slate-400 hover:bg-[#f2f4f7] dark:hover:bg-slate-900 transition-colors duration-200"
                    href="#">
                    <span class="material-symbols-outlined">settings</span>
                    <span class="font-['Public_Sans'] font-medium text-sm tracking-tight">Configuraciòn</span>
                </a>
            </nav>
        </div>
        <div class="mt-auto p-6 border-t border-outline-variant/10">
            <a class="flex items-center gap-3 px-4 py-3 rounded-lg text-[#48626e] hover:bg-[#f2f4f7] transition-all"
                href="#">
                <span class="material-symbols-outlined">help_outline</span>
                <span class="font-['Public_Sans'] font-medium text-sm">Ayuda</span>
            </a>
            <div class="mt-4 flex items-center gap-3 p-2 rounded-xl bg-surface-container-low">
                <img class="w-10 h-10 rounded-lg object-cover"
                    data-alt="portrait of a professional male school administrator in a bright office setting"
                    src="https://lh3.googleusercontent.com/aida-public/AB6AXuDVhxE4bHtsr0iHoUWFPssIT-Jp-c_yv5lRqeRw4Ru0EXNG-_TvXHinz9ArdotRjlsNvmHWhEDpGRKnOe9IVeDiXmuFY_LgxOpbl-KVQzc9kWbUWjZg1EXv-3_wpT-l7yBsb_kCpxUnMwGAma3_UtOO-CDKRHZagjpR920_KKKMAt6XwntepUtbTkp82TZNHYT1skfEe5fFKdM2SbSNeFFfSaJ1t0t51SjZNL1ZrDJpjO3gMUS5Tp5swGIZvvzC4-tMg4p5z3Xn304" />
                <div class="overflow-hidden">
                    <p class="text-xs font-bold truncate">Director Académico</p>
                    <p class="text-[10px] text-secondary truncate">Liceo New Heaven</p>
                </div>
            </div>
        </div>
    </aside>
    <!-- TopAppBar -->
    <header
        class="sticky top-0 w-full flex justify-between items-center px-8 py-4 z-50 ml-64 max-w-[calc(100%-16rem)] bg-[#ffffff]/80 dark:bg-slate-900/80 backdrop-blur-md shadow-sm">
        <div>
            <span
                class="font-['Manrope'] font-bold text-[#00376e] dark:text-blue-100 text-lg uppercase tracking-tight">Portal
                de Entrevistas</span>
        </div>
        <div class="flex items-center gap-6">
            <div
                class="relative focus-within:ring-2 focus-within:ring-[#00376e] transition-all rounded-full bg-surface-container px-4 py-2 flex items-center gap-2">
                <span class="material-symbols-outlined text-secondary text-sm">search</span>
                <input class="bg-transparent border-none focus:ring-0 text-sm w-64 placeholder:text-secondary/50"
                    placeholder="Buscar apoderado o alumno..." type="text" />
            </div>
            <button
                class="bg-primary text-on-primary px-5 py-2 rounded-full font-bold text-sm transition-all hover:scale-105 active:opacity-80">
                Marcar Entrada
            </button>
            <div class="flex items-center gap-4">
                <div class="relative">
                    <span
                        class="material-symbols-outlined text-secondary cursor-pointer hover:text-primary transition-colors">notifications</span>
                    <span class="absolute -top-1 -right-1 w-2 h-2 bg-error rounded-full"></span>
                </div>
                <span
                    class="material-symbols-outlined text-secondary cursor-pointer hover:text-primary transition-colors">account_circle</span>
            </div>
        </div>
    </header>
    <!-- Main Canvas -->
    <main class="ml-64 p-8 min-h-screen bg-surface">
        <div class="max-w-7xl mx-auto space-y-10">
            <!-- Header Section -->
            <section class="flex flex-col gap-2">
                <h2 class="text-3xl font-extrabold text-primary tracking-tight">Estado Operativo de Portería</h2>
                <p class="text-secondary font-medium italic">Gestión Centralizada - Liceo New Heaven</p>
            </section>
            <!-- Top Row: KPI Cards -->
            <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- KPI 1 -->
                <div class="bg-surface-container-lowest p-6 rounded-2xl shadow-sm border-b-4 border-primary">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-12 h-12 bg-primary-fixed rounded-xl flex items-center justify-center">
                            <span class="material-symbols-outlined text-primary"
                                style="font-variation-settings: 'FILL' 1;">event_available</span>
                        </div>
                        <span class="text-[10px] font-bold text-secondary uppercase tracking-tighter">Hoy</span>
                    </div>
                    <p class="text-secondary-container text-xs font-bold">Entrevistas Hoy</p>
                    <p class="text-4xl font-extrabold text-on-background mt-1">24</p>
                    <div class="mt-2 flex items-center gap-1 text-primary text-[10px] font-bold">
                        <span class="material-symbols-outlined text-sm">trending_up</span>
                        <span>+12% vs ayer</span>
                    </div>
                </div>
                <!-- KPI 2 -->
                <div
                    class="bg-surface-container-lowest p-6 rounded-2xl shadow-sm border-b-4 border-on-secondary-container">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-12 h-12 bg-secondary-fixed rounded-xl flex items-center justify-center">
                            <span class="material-symbols-outlined text-on-secondary-container"
                                style="font-variation-settings: 'FILL' 1;">calendar_month</span>
                        </div>
                        <span class="text-[10px] font-bold text-secondary uppercase tracking-tighter">Mes</span>
                    </div>
                    <p class="text-secondary-container text-xs font-bold">Entrevistas Mensuales</p>
                    <p class="text-4xl font-extrabold text-on-background mt-1">186</p>
                    <div class="mt-2 flex items-center gap-1 text-secondary text-[10px] font-bold">
                        <span>Meta: 200 mensual</span>
                    </div>
                </div>
                <!-- KPI 3 -->
                <div
                    class="bg-surface-container-lowest p-6 rounded-2xl shadow-sm border-b-4 border-on-tertiary-container">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-12 h-12 bg-tertiary-fixed rounded-xl flex items-center justify-center">
                            <span class="material-symbols-outlined text-tertiary-container"
                                style="font-variation-settings: 'FILL' 1;">pending_actions</span>
                        </div>
                        <span class="text-[10px] font-bold text-secondary uppercase tracking-tighter">Activo</span>
                    </div>
                    <p class="text-secondary-container text-xs font-bold">En Proceso</p>
                    <p class="text-4xl font-extrabold text-on-background mt-1">08</p>
                    <div class="mt-2 flex items-center gap-1 text-tertiary-container text-[10px] font-bold">
                        <span>Promedio 45 min/cita</span>
                    </div>
                </div>
                <!-- KPI 4 -->
                <div class="bg-surface-container-lowest p-6 rounded-2xl shadow-sm border-b-4 border-error">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-12 h-12 bg-error-container rounded-xl flex items-center justify-center">
                            <span class="material-symbols-outlined text-error"
                                style="font-variation-settings: 'FILL' 1;">event_busy</span>
                        </div>
                        <span class="text-[10px] font-bold text-secondary uppercase tracking-tighter">Histórico</span>
                    </div>
                    <p class="text-secondary-container text-xs font-bold">Canceladas</p>
                    <p class="text-4xl font-extrabold text-on-background mt-1">14</p>
                    <div class="mt-2 flex items-center gap-1 text-error text-[10px] font-bold">
                        <span class="material-symbols-outlined text-sm">trending_down</span>
                        <span>-5% respecto al mes anterior</span>
                    </div>
                </div>
            </section>
            <!-- Middle Row: Charts -->
            <section class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Left Column: Professors Bar Charts -->
                <div class="space-y-6">
                    <div class="bg-surface-container-lowest p-8 rounded-2xl shadow-sm h-full">
                        <div class="flex items-center gap-3 mb-8">
                            <span class="material-symbols-outlined text-primary">groups</span>
                            <h3 class="text-xl font-bold text-on-background tracking-tight">Desempeño Docente</h3>
                        </div>
                        <!-- Top 5 Most -->
                        <div class="mb-10">
                            <p class="text-xs font-bold text-primary uppercase tracking-widest mb-6">Top 5: Mayor
                                Volumen</p>
                            <div class="space-y-4">
                                <div class="space-y-1">
                                    <div class="flex justify-between text-sm font-semibold mb-1">
                                        <span>Rodrigo Valdés</span>
                                        <span class="text-primary">42</span>
                                    </div>
                                    <div class="w-full bg-surface-container h-2.5 rounded-full overflow-hidden">
                                        <div class="bg-primary h-full rounded-full" style="width: 95%"></div>
                                    </div>
                                </div>
                                <div class="space-y-1">
                                    <div class="flex justify-between text-sm font-semibold mb-1">
                                        <span>Marcela Soto</span>
                                        <span class="text-primary">38</span>
                                    </div>
                                    <div class="w-full bg-surface-container h-2.5 rounded-full overflow-hidden">
                                        <div class="bg-primary h-full rounded-full" style="width: 85%"></div>
                                    </div>
                                </div>
                                <div class="space-y-1">
                                    <div class="flex justify-between text-sm font-semibold mb-1">
                                        <span>Javiera Muñoz</span>
                                        <span class="text-primary">35</span>
                                    </div>
                                    <div class="w-full bg-surface-container h-2.5 rounded-full overflow-hidden">
                                        <div class="bg-primary h-full rounded-full" style="width: 78%"></div>
                                    </div>
                                </div>
                                <div class="space-y-1">
                                    <div class="flex justify-between text-sm font-semibold mb-1">
                                        <span>Ignacio Larraín</span>
                                        <span class="text-primary">31</span>
                                    </div>
                                    <div class="w-full bg-surface-container h-2.5 rounded-full overflow-hidden">
                                        <div class="bg-primary h-full rounded-full" style="width: 70%"></div>
                                    </div>
                                </div>
                                <div class="space-y-1">
                                    <div class="flex justify-between text-sm font-semibold mb-1">
                                        <span>Camila Pizarro</span>
                                        <span class="text-primary">29</span>
                                    </div>
                                    <div class="w-full bg-surface-container h-2.5 rounded-full overflow-hidden">
                                        <div class="bg-primary h-full rounded-full" style="width: 65%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Top 5 Fewest -->
                        <div>
                            <p class="text-xs font-bold text-secondary uppercase tracking-widest mb-6">Top 5: Menor
                                Volumen</p>
                            <div class="space-y-4 opacity-75">
                                <div class="flex items-center gap-4">
                                    <span class="w-24 text-xs font-medium truncate">M. Herrera</span>
                                    <div class="flex-1 bg-surface-container h-2 rounded-full overflow-hidden">
                                        <div class="bg-secondary h-full rounded-full" style="width: 15%"></div>
                                    </div>
                                    <span class="text-xs font-bold">04</span>
                                </div>
                                <div class="flex items-center gap-4">
                                    <span class="w-24 text-xs font-medium truncate">F. Tapia</span>
                                    <div class="flex-1 bg-surface-container h-2 rounded-full overflow-hidden">
                                        <div class="bg-secondary h-full rounded-full" style="width: 18%"></div>
                                    </div>
                                    <span class="text-xs font-bold">05</span>
                                </div>
                                <div class="flex items-center gap-4">
                                    <span class="w-24 text-xs font-medium truncate">L. Rojas</span>
                                    <div class="flex-1 bg-surface-container h-2 rounded-full overflow-hidden">
                                        <div class="bg-secondary h-full rounded-full" style="width: 22%"></div>
                                    </div>
                                    <span class="text-xs font-bold">06</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Right Column: Priority and Reason Charts -->
                <div class="grid grid-rows-2 gap-8">
                    <!-- Priority Donut (Visualized as List) -->
                    <div class="bg-surface-container-lowest p-8 rounded-2xl shadow-sm">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-xl font-bold text-on-background tracking-tight">Distribución de Prioridad
                            </h3>
                            <span class="material-symbols-outlined text-secondary">priority_high</span>
                        </div>
                        <div class="flex items-center justify-around">
                            <!-- Simulated Donut Chart -->
                            <div
                                class="relative w-32 h-32 rounded-full border-[12px] border-surface-container flex items-center justify-center">
                                <div
                                    class="absolute inset-0 rounded-full border-[12px] border-primary border-r-transparent border-b-transparent rotate-45">
                                </div>
                                <div
                                    class="absolute inset-0 rounded-full border-[12px] border-error border-l-transparent border-b-transparent -rotate-12">
                                </div>
                                <div class="text-center">
                                    <p class="text-lg font-extrabold text-on-background leading-none">186</p>
                                    <p class="text-[8px] uppercase font-bold text-secondary">Total Citaciones</p>
                                </div>
                            </div>
                            <!-- Legend -->
                            <div class="space-y-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-3 h-3 rounded-full bg-primary"></div>
                                    <div>
                                        <p class="text-xs font-bold leading-none">Normal</p>
                                        <p class="text-[10px] text-secondary">62% (115)</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="w-3 h-3 rounded-full bg-on-tertiary-container"></div>
                                    <div>
                                        <p class="text-xs font-bold leading-none">Prioritario</p>
                                        <p class="text-[10px] text-secondary">25% (47)</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="w-3 h-3 rounded-full bg-error"></div>
                                    <div>
                                        <p class="text-xs font-bold leading-none">Urgente</p>
                                        <p class="text-[10px] text-secondary">13% (24)</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Reason Radar/Bar (Visualized as Grid) -->
                    <div class="bg-surface-container-lowest p-8 rounded-2xl shadow-sm">
                        <h3 class="text-xl font-bold text-on-background tracking-tight mb-6">Motivo de Consulta</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-4 rounded-xl bg-surface-container-low flex flex-col justify-between">
                                <span class="material-symbols-outlined text-primary mb-2">psychology</span>
                                <p class="text-[10px] font-bold text-secondary uppercase">Psicosocial</p>
                                <p class="text-xl font-extrabold">34%</p>
                            </div>
                            <div class="p-4 rounded-xl bg-surface-container-low flex flex-col justify-between">
                                <span class="material-symbols-outlined text-primary mb-2">school</span>
                                <p class="text-[10px] font-bold text-secondary uppercase">Rendimiento</p>
                                <p class="text-xl font-extrabold">28%</p>
                            </div>
                            <div class="p-4 rounded-xl bg-surface-container-low flex flex-col justify-between">
                                <span class="material-symbols-outlined text-primary mb-2">rule</span>
                                <p class="text-[10px] font-bold text-secondary uppercase">Conducta</p>
                                <p class="text-xl font-extrabold">22%</p>
                            </div>
                            <div class="p-4 rounded-xl bg-surface-container-low flex flex-col justify-between">
                                <span class="material-symbols-outlined text-primary mb-2">schedule</span>
                                <p class="text-[10px] font-bold text-secondary uppercase">Asistencia</p>
                                <p class="text-xl font-extrabold">16%</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <!-- Bottom Row: Multi-Metric Grid -->
            <section class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Parent Attendance Rate -->
                <div
                    class="bg-gradient-to-br from-[#00376e] to-[#004d97] p-8 rounded-2xl text-on-primary md:col-span-2">
                    <div class="flex justify-between items-end">
                        <div class="space-y-6">
                            <h3 class="text-xl font-bold">Tasa de Asistencia de Apoderados</h3>
                            <div class="flex items-baseline gap-4">
                                <p class="text-6xl font-extrabold tracking-tighter">92.4%</p>
                                <div class="px-3 py-1 bg-white/20 rounded-full text-xs font-bold">+2.1% este semestre
                                </div>
                            </div>
                            <div class="flex gap-4">
                                <div class="space-y-1">
                                    <p class="text-[10px] font-bold text-on-primary/60 uppercase">Asistieron</p>
                                    <p class="font-bold">172 citas</p>
                                </div>
                                <div class="w-px h-8 bg-white/20"></div>
                                <div class="space-y-1">
                                    <p class="text-[10px] font-bold text-on-primary/60 uppercase">Ausentes</p>
                                    <p class="font-bold">14 citas</p>
                                </div>
                            </div>
                        </div>
                        <div class="hidden lg:block">
                            <!-- Dynamic trend visualization -->
                            <div class="flex items-end gap-1 h-32">
                                <div class="w-4 bg-white/20 rounded-t-sm h-1/2"></div>
                                <div class="w-4 bg-white/30 rounded-t-sm h-2/3"></div>
                                <div class="w-4 bg-white/40 rounded-t-sm h-1/2"></div>
                                <div class="w-4 bg-white/50 rounded-t-sm h-3/4"></div>
                                <div class="w-4 bg-white/70 rounded-t-sm h-5/6"></div>
                                <div class="w-4 bg-white rounded-t-sm h-full"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Average Response Time -->
                <div class="bg-surface-container-lowest p-8 rounded-2xl shadow-sm flex flex-col justify-center">
                    <h3 class="text-xs font-bold text-secondary uppercase tracking-widest mb-2">Tiempo Promedio de
                        Respuesta</h3>
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 bg-surface-container rounded-full flex items-center justify-center">
                            <span class="material-symbols-outlined text-primary text-3xl">timer</span>
                        </div>
                        <div>
                            <p class="text-4xl font-extrabold text-on-background leading-none">4.2 <span
                                    class="text-sm font-bold text-secondary">hrs</span></p>
                            <p class="text-xs text-secondary mt-1">Desde solicitud a confirmación</p>
                        </div>
                    </div>
                    <div class="mt-8 pt-6 border-t border-outline-variant/10">
                        <div class="flex justify-between items-center text-xs font-bold">
                            <span class="text-secondary">Efectividad del Canal</span>
                            <span class="text-primary">Muy Alta</span>
                        </div>
                    </div>
                </div>
            </section>
            <!-- Bottom Editorial Section -->
            <section
                class="bg-surface-container-low p-10 rounded-3xl flex flex-col md:flex-row items-center justify-between gap-8 border-l-8 border-primary">
                <div class="max-w-xl">
                    <h3 class="text-2xl font-extrabold text-primary mb-4 italic">"La seguridad de nuestros estudiantes
                        comienza en la puerta de entrada."</h3>
                    <p class="text-secondary leading-relaxed font-medium">Panel de control actualizado en tiempo real.
                        Todos los protocolos de seguridad de Portería y Registro de Visitas deben ser seguidos
                        rigurosamente según el manual institucional.</p>
                </div>
                <div class="flex gap-4">
                    <button
                        class="px-6 py-3 rounded-xl bg-surface-container-lowest font-bold text-sm shadow-sm hover:bg-white transition-all">Exportar
                        PDF Anual</button>
                    <button
                        class="px-6 py-3 rounded-xl bg-primary text-on-primary font-bold text-sm shadow-md hover:opacity-90 transition-all">Auditar
                        Registros</button>
                </div>
            </section>
        </div>
    </main>
</body>

</html>
