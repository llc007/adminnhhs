<!DOCTYPE html>

<html class="light" lang="es">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Historial General de Entrevistas - Liceo Pro</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&amp;family=Public+Sans:wght@300;400;500;600;700&amp;display=swap"
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
                        "surface-container-high": "#e6e8eb",
                        "inverse-on-surface": "#eff1f4",
                        "on-background": "#191c1e",
                        "on-primary-fixed": "#001b3d",
                        "inverse-primary": "#a8c8ff",
                        "on-surface": "#191c1e",
                        "on-primary-container": "#9cc0ff",
                        "primary-fixed": "#d6e3ff",
                        "on-primary": "#ffffff",
                        "inverse-surface": "#2d3133",
                        "error-container": "#ffdad6",
                        "secondary": "#48626e",
                        "surface-variant": "#e0e3e6",
                        "on-error-container": "#93000a",
                        "surface": "#f7f9fc",
                        "on-surface-variant": "#41474e",
                        "on-secondary": "#ffffff",
                        "on-primary-fixed-variant": "#00468a",
                        "secondary-fixed-dim": "#afcbd8",
                        "on-secondary-fixed-variant": "#304a55",
                        "tertiary-container": "#6b4604",
                        "primary-container": "#004d97",
                        "surface-bright": "#f7f9fc",
                        "surface-container-highest": "#e0e3e6",
                        "on-secondary-container": "#4e6874",
                        "surface-container-low": "#f2f4f7",
                        "on-tertiary-fixed-variant": "#633f00",
                        "tertiary-fixed": "#ffddb3",
                        "surface-dim": "#d8dadd",
                        "primary": "#00376e",
                        "background": "#f7f9fc",
                        "secondary-fixed": "#cbe7f5",
                        "outline": "#72787f",
                        "on-tertiary-fixed": "#291800",
                        "on-tertiary-container": "#eab66d",
                        "error": "#ba1a1a",
                        "on-tertiary": "#ffffff",
                        "tertiary-fixed-dim": "#f2bd74",
                        "tertiary": "#4d3100",
                        "outline-variant": "#c1c7cf",
                        "surface-container": "#eceef1",
                        "on-error": "#ffffff",
                        "secondary-container": "#cbe7f5",
                        "surface-tint": "#1e5eac",
                        "surface-container-lowest": "#ffffff",
                        "on-secondary-fixed": "#021f29",
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
                        "body": ["Public Sans"],
                        "label": ["Public Sans"]
                    }
                },
            },
        }
    </script>
    <style>
        body {
            font-family: 'Public Sans', sans-serif;
        }

        h1,
        h2,
        h3 {
            font-family: 'Manrope', sans-serif;
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        .btn-gradient {
            background: linear-gradient(135deg, #00376e 0%, #004d97 100%);
        }
    </style>
</head>

<body class="bg-surface text-on-surface">
    <!-- SideNavBar -->
    <aside class="fixed left-0 top-0 h-full flex flex-col py-6 bg-[#f2f4f7] dark:bg-slate-800 w-64 z-50">
        <div class="px-6 mb-8 flex flex-col gap-2">
            <div class="flex items-center gap-3">
                <img class="w-10 h-10 object-contain"
                    data-alt="Official crest of a prestigious educational institution with gold and navy blue accents on a clean white background"
                    src="https://lh3.googleusercontent.com/aida-public/AB6AXuAAcjEZ9Pa1I3piGN1-TU60AJEFxOQoiHYkzwjYZl-fzxqese8FY3XEx2gyRL-pjufGUaHCXz8LbE0HSwM3u04yBnKBj-qKN_XGFOerfK0SmY8v6X3ye0NaM7GqUj_iAtO75j9hD1SGeV1PeOpVUafLlJXeKF-yOs1VkGN1p1h_W-JqjGi8DZekobTBWDYh5qkRZY4mNqTotV2ssAd80xu7_mO9LiinYCqqzGapVdq-LGNR6xq49PNCSDz322pf3oFh67dmRYL9fAo" />
                <span class="text-lg font-black text-[#00376e] dark:text-blue-300">Liceo Pro</span>
            </div>
            <div>
                <p class="text-xs font-bold text-secondary uppercase tracking-widest opacity-60">Gesti\u00f3n
                    Institucional</p>
                <p class="text-[10px] text-secondary">Panel de Control</p>
            </div>
        </div>
        <nav class="flex-1 flex flex-col gap-1">
            <a class="text-[#48626e] dark:text-slate-400 px-4 py-3 mx-2 flex items-center gap-3 hover:bg-[#f7f9fc] dark:hover:bg-slate-700 transition-all scale-95 active:scale-100 duration-150"
                href="#">
                <span class="material-symbols-outlined">dashboard</span>
                <span class="font-medium">Dashboard</span>
            </a>
            <!-- Active Tab: Entrevistas -->
            <a class="bg-white dark:bg-slate-700 text-[#00376e] dark:text-white shadow-sm rounded-lg mx-2 px-4 py-3 flex items-center gap-3 scale-95 active:scale-100 duration-150"
                href="#">
                <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">calendar_month</span>
                <span class="font-bold">Entrevistas</span>
            </a>
            <a class="text-[#48626e] dark:text-slate-400 px-4 py-3 mx-2 flex items-center gap-3 hover:bg-[#f7f9fc] dark:hover:bg-slate-700 transition-all scale-95 active:scale-100 duration-150"
                href="#">
                <span class="material-symbols-outlined">groups</span>
                <span class="font-medium">Alumnos</span>
            </a>
            <a class="text-[#48626e] dark:text-slate-400 px-4 py-3 mx-2 flex items-center gap-3 hover:bg-[#f7f9fc] dark:hover:bg-slate-700 transition-all scale-95 active:scale-100 duration-150"
                href="#">
                <span class="material-symbols-outlined">record_voice_over</span>
                <span class="font-medium">Docentes</span>
            </a>
            <a class="text-[#48626e] dark:text-slate-400 px-4 py-3 mx-2 flex items-center gap-3 hover:bg-[#f7f9fc] dark:hover:bg-slate-700 transition-all scale-95 active:scale-100 duration-150"
                href="#">
                <span class="material-symbols-outlined">settings</span>
                <span class="font-medium">Configuraci\u00f3n</span>
            </a>
        </nav>
        <div class="px-4 mt-auto">
            <button
                class="w-full btn-gradient text-white py-3 rounded-lg font-bold flex items-center justify-center gap-2 mb-6 shadow-md active:scale-95 transition-transform">
                <span class="material-symbols-outlined text-sm">add</span>
                <span>Nueva Citaci\u00f3n</span>
            </button>
            <a class="text-[#48626e] dark:text-slate-400 px-4 py-3 flex items-center gap-3 hover:bg-[#f7f9fc] transition-all"
                href="#">
                <span class="material-symbols-outlined">logout</span>
                <span class="font-medium">Cerrar Sesi\u00f3n</span>
            </a>
        </div>
    </aside>
    <!-- Main Content Wrapper -->
    <main class="ml-64 min-h-screen flex flex-col">
        <!-- TopNavBar -->
        <header class="bg-[#f7f9fc] dark:bg-slate-900 sticky top-0 z-40">
            <div class="flex justify-between items-center w-full px-8 py-3 max-w-[1920px] mx-auto">
                <div class="flex items-center gap-8 flex-1">
                    <div class="relative w-full max-w-md">
                        <span
                            class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline">search</span>
                        <input
                            class="w-full pl-10 pr-4 py-2 bg-surface-container-low border-none rounded-full text-sm focus:ring-2 focus:ring-primary focus:bg-white transition-all"
                            placeholder="Buscar historial..." type="text" />
                    </div>
                </div>
                <div class="flex items-center gap-6">
                    <div class="flex items-center gap-4">
                        <button class="text-[#48626e] hover:text-[#00376e] transition-colors relative">
                            <span class="material-symbols-outlined">notifications</span>
                            <span class="absolute top-0 right-0 w-2 h-2 bg-error rounded-full"></span>
                        </button>
                        <button class="text-[#48626e] hover:text-[#00376e] transition-colors">
                            <span class="material-symbols-outlined">help_outline</span>
                        </button>
                    </div>
                    <div class="h-8 w-px bg-surface-container-high mx-2"></div>
                    <button
                        class="btn-gradient text-white px-4 py-2 rounded-lg text-sm font-semibold hover:opacity-90 transition-opacity">
                        Descargar Reporte
                    </button>
                    <div class="flex items-center gap-3 ml-4">
                        <div class="text-right">
                            <p class="text-xs font-bold text-primary">Admin Liceo</p>
                            <p class="text-[10px] text-secondary">Administrador Central</p>
                        </div>
                        <img alt="Executive Profile" class="w-10 h-10 rounded-full border-2 border-white shadow-sm"
                            src="https://lh3.googleusercontent.com/aida-public/AB6AXuB7BRmltJ0uvUGen0UWXxZg89RgzisBP_Y3FcC2AqZzhm4xMux-9onPTpZjAa4qEkin9SoAW6DZ3Sx7RUdHbK_DzshUysBR4JM8Qjra6qLFPTTeDI-rhk8szlgxZdzDYnuH9emoThOlzjeH_boffjbspzKGJ3YRqzgNaMG28QSoXVY0YtgdudF6CypN6SqyIwgByoHxIYyw26tFPkRSpzjN8yr7tPNwTZrpSVuolyLwzemBqHur9y_Yju_5gBixf81TBXSLQB9n3GU" />
                    </div>
                </div>
            </div>
            <div class="bg-[#f2f4f7] dark:bg-slate-800 h-px w-full"></div>
        </header>
        <!-- Content Canvas -->
        <div class="p-10 space-y-8 max-w-[1600px] mx-auto w-full">
            <!-- Page Header -->
            <div class="flex justify-between items-end">
                <div>
                    <h1 class="text-3xl font-extrabold text-primary tracking-tight mb-2">Historial General de
                        Entrevistas</h1>
                    <p class="text-secondary font-medium">Liceo New Heaven — Registro unificado de atención a
                        estudiantes y apoderados.</p>
                </div>
                <div class="flex gap-3">
                    <button
                        class="flex items-center gap-2 bg-surface-container-low text-on-surface px-4 py-2 rounded-lg font-semibold hover:bg-surface-container-high transition-colors">
                        <span class="material-symbols-outlined text-lg">filter_alt_off</span>
                        <span>Limpiar</span>
                    </button>
                </div>
            </div>
            <!-- Bento Filter Section -->
            <div class="bg-surface-container-lowest p-8 rounded-xl shadow-sm space-y-6">
                <div class="flex items-center gap-2 text-primary font-bold mb-4">
                    <span class="material-symbols-outlined">tune</span>
                    <span class="uppercase tracking-widest text-xs">Panel de Filtros</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                    <!-- Dropdown: Profesor -->
                    <div class="flex flex-col gap-2">
                        <label class="text-xs font-bold text-secondary uppercase tracking-tighter">Filtrar por
                            Profesor</label>
                        <select
                            class="w-full bg-surface-container-low border-0 border-b-2 border-primary-fixed focus:border-primary focus:ring-0 text-sm py-3 transition-all">
                            <option>Todos los docentes</option>
                            <option>Elena Soto Ruiz</option>
                            <option>Ricardo Lagos Weber</option>
                            <option>Mar\u00eda Paz Est\u00e9vez</option>
                        </select>
                    </div>
                    <!-- Dropdown: Curso -->
                    <div class="flex flex-col gap-2">
                        <label class="text-xs font-bold text-secondary uppercase tracking-tighter">Curso</label>
                        <select
                            class="w-full bg-surface-container-low border-0 border-b-2 border-primary-fixed focus:border-primary focus:ring-0 text-sm py-3 transition-all">
                            <option>Cualquiera</option>
                            <option>1°A</option>
                            <option>1°B</option>
                            <option>2°A</option>
                            <option>4° Medio C</option>
                        </select>
                    </div>
                    <!-- Date Picker -->
                    <div class="flex flex-col gap-2">
                        <label class="text-xs font-bold text-secondary uppercase tracking-tighter">Rango de
                            Fechas</label>
                        <div class="relative">
                            <input
                                class="w-full bg-surface-container-low border-0 border-b-2 border-primary-fixed focus:border-primary focus:ring-0 text-sm py-3 transition-all"
                                type="date" />
                        </div>
                    </div>
                    <!-- Dropdown: Estado -->
                    <div class="flex flex-col gap-2">
                        <label class="text-xs font-bold text-secondary uppercase tracking-tighter">Estado</label>
                        <select
                            class="w-full bg-surface-container-low border-0 border-b-2 border-primary-fixed focus:border-primary focus:ring-0 text-sm py-3 transition-all">
                            <option>Todos los estados</option>
                            <option>Realizada</option>
                            <option>Pendiente</option>
                            <option>Cancelada</option>
                        </select>
                    </div>
                </div>
            </div>
            <!-- Data Table Container -->
            <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface-container-low border-b border-surface-container-high">
                            <th class="px-6 py-5 text-xs font-bold text-secondary uppercase tracking-wider">Fecha</th>
                            <th class="px-6 py-5 text-xs font-bold text-secondary uppercase tracking-wider">Alumno</th>
                            <th class="px-6 py-5 text-xs font-bold text-secondary uppercase tracking-wider">Profesor
                            </th>
                            <th class="px-6 py-5 text-xs font-bold text-secondary uppercase tracking-wider">Motivo</th>
                            <th class="px-6 py-5 text-xs font-bold text-secondary uppercase tracking-wider">Estado</th>
                            <th class="px-6 py-5 text-xs font-bold text-secondary uppercase tracking-wider text-right">
                                Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-container">
                        <!-- Row 1 -->
                        <tr class="hover:bg-surface-bright transition-colors group">
                            <td class="px-6 py-5">
                                <div class="flex items-center gap-3">
                                    <span
                                        class="material-symbols-outlined text-outline group-hover:text-primary transition-colors">event</span>
                                    <span class="text-sm font-semibold text-on-surface">24 Oct, 2023</span>
                                </div>
                            </td>
                            <td class="px-6 py-5">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-8 h-8 rounded-full bg-primary-fixed flex items-center justify-center text-xs font-bold text-on-primary-fixed">
                                        MV</div>
                                    <span class="text-sm font-medium text-on-surface">Matías Valenzuela</span>
                                </div>
                            </td>
                            <td class="px-6 py-5 text-sm text-secondary">Elena Soto Ruiz</td>
                            <td class="px-6 py-5">
                                <span
                                    class="text-xs bg-surface-container text-on-secondary-container px-2 py-1 rounded font-bold uppercase">Rendimiento</span>
                            </td>
                            <td class="px-6 py-5">
                                <span
                                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-primary-fixed text-primary">
                                    <span class="w-1.5 h-1.5 bg-primary rounded-full"></span>
                                    Realizada
                                </span>
                            </td>
                            <td class="px-6 py-5 text-right">
                                <button class="text-sm font-bold text-primary hover:underline transition-all">Ver
                                    Bitácora</button>
                            </td>
                        </tr>
                        <!-- Row 2 -->
                        <tr class="hover:bg-surface-bright transition-colors group">
                            <td class="px-6 py-5">
                                <div class="flex items-center gap-3">
                                    <span
                                        class="material-symbols-outlined text-outline group-hover:text-primary transition-colors">event</span>
                                    <span class="text-sm font-semibold text-on-surface">25 Oct, 2023</span>
                                </div>
                            </td>
                            <td class="px-6 py-5">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-8 h-8 rounded-full bg-tertiary-fixed flex items-center justify-center text-xs font-bold text-on-tertiary-fixed">
                                        JC</div>
                                    <span class="text-sm font-medium text-on-surface">Javier Canales</span>
                                </div>
                            </td>
                            <td class="px-6 py-5 text-sm text-secondary">Ricardo Lagos Weber</td>
                            <td class="px-6 py-5">
                                <span
                                    class="text-xs bg-surface-container text-on-secondary-container px-2 py-1 rounded font-bold uppercase">Convivencia</span>
                            </td>
                            <td class="px-6 py-5">
                                <span
                                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-tertiary-fixed text-tertiary">
                                    <span class="w-1.5 h-1.5 bg-tertiary rounded-full"></span>
                                    Pendiente
                                </span>
                            </td>
                            <td class="px-6 py-5 text-right">
                                <button
                                    class="text-sm font-bold text-primary hover:underline transition-all">Detalles</button>
                            </td>
                        </tr>
                        <!-- Row 3 -->
                        <tr class="hover:bg-surface-bright transition-colors group">
                            <td class="px-6 py-5">
                                <div class="flex items-center gap-3">
                                    <span
                                        class="material-symbols-outlined text-outline group-hover:text-primary transition-colors">event</span>
                                    <span class="text-sm font-semibold text-on-surface">26 Oct, 2023</span>
                                </div>
                            </td>
                            <td class="px-6 py-5">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-8 h-8 rounded-full bg-primary-fixed flex items-center justify-center text-xs font-bold text-on-primary-fixed">
                                        FP</div>
                                    <span class="text-sm font-medium text-on-surface">Francisca Palma</span>
                                </div>
                            </td>
                            <td class="px-6 py-5 text-sm text-secondary">Elena Soto Ruiz</td>
                            <td class="px-6 py-5">
                                <span
                                    class="text-xs bg-surface-container text-on-secondary-container px-2 py-1 rounded font-bold uppercase">Personal</span>
                            </td>
                            <td class="px-6 py-5">
                                <span
                                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-error-container text-error">
                                    <span class="w-1.5 h-1.5 bg-error rounded-full"></span>
                                    Cancelada
                                </span>
                            </td>
                            <td class="px-6 py-5 text-right">
                                <button class="text-sm font-bold text-primary hover:underline transition-all">Ver
                                    Bitácora</button>
                            </td>
                        </tr>
                        <!-- Row 4 -->
                        <tr class="hover:bg-surface-bright transition-colors group">
                            <td class="px-6 py-5">
                                <div class="flex items-center gap-3">
                                    <span
                                        class="material-symbols-outlined text-outline group-hover:text-primary transition-colors">event</span>
                                    <span class="text-sm font-semibold text-on-surface">27 Oct, 2023</span>
                                </div>
                            </td>
                            <td class="px-6 py-5">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-8 h-8 rounded-full bg-secondary-container flex items-center justify-center text-xs font-bold text-on-secondary-fixed">
                                        AM</div>
                                    <span class="text-sm font-medium text-on-surface">Andr\u00e9s Molina</span>
                                </div>
                            </td>
                            <td class="px-6 py-5 text-sm text-secondary">Mar\u00eda Paz Est\u00e9vez</td>
                            <td class="px-6 py-5">
                                <span
                                    class="text-xs bg-surface-container text-on-secondary-container px-2 py-1 rounded font-bold uppercase">Rendimiento</span>
                            </td>
                            <td class="px-6 py-5">
                                <span
                                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-primary-fixed text-primary">
                                    <span class="w-1.5 h-1.5 bg-primary rounded-full"></span>
                                    Realizada
                                </span>
                            </td>
                            <td class="px-6 py-5 text-right">
                                <button class="text-sm font-bold text-primary hover:underline transition-all">Ver
                                    Bitácora</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <!-- Table Footer -->
                <div class="px-6 py-4 bg-surface-container-low flex justify-between items-center">
                    <p class="text-xs text-secondary font-medium">Mostrando 4 de 128 registros</p>
                    <div class="flex items-center gap-2">
                        <button class="p-1 hover:bg-surface-container-high rounded transition-colors">
                            <span class="material-symbols-outlined text-lg">chevron_left</span>
                        </button>
                        <div class="flex gap-1">
                            <span
                                class="w-6 h-6 flex items-center justify-center bg-primary text-white text-xs font-bold rounded">1</span>
                            <span
                                class="w-6 h-6 flex items-center justify-center hover:bg-surface-container-high text-xs font-bold rounded cursor-pointer">2</span>
                            <span
                                class="w-6 h-6 flex items-center justify-center hover:bg-surface-container-high text-xs font-bold rounded cursor-pointer">3</span>
                        </div>
                        <button class="p-1 hover:bg-surface-container-high rounded transition-colors">
                            <span class="material-symbols-outlined text-lg">chevron_right</span>
                        </button>
                    </div>
                </div>
            </div>
            <!-- Stats/Insights Row -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-primary-container p-6 rounded-xl text-on-primary shadow-lg flex items-center gap-4">
                    <div class="bg-white/10 p-3 rounded-lg">
                        <span class="material-symbols-outlined text-3xl">task_alt</span>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-widest font-bold opacity-80">Cumplimiento Mensual</p>
                        <h3 class="text-2xl font-black">94%</h3>
                    </div>
                </div>
                <div
                    class="bg-surface-container-lowest p-6 rounded-xl shadow-sm flex items-center gap-4 border-l-4 border-tertiary">
                    <div class="bg-tertiary-fixed p-3 rounded-lg text-on-tertiary-fixed">
                        <span class="material-symbols-outlined text-3xl">pending_actions</span>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-widest font-bold text-secondary">Pendientes</p>
                        <h3 class="text-2xl font-black text-on-surface">12 Entrevistas</h3>
                    </div>
                </div>
                <div
                    class="bg-surface-container-lowest p-6 rounded-xl shadow-sm flex items-center gap-4 border-l-4 border-error">
                    <div class="bg-error-container p-3 rounded-lg text-on-error-container">
                        <span class="material-symbols-outlined text-3xl">cancel</span>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-widest font-bold text-secondary">Canceladas (Mes)</p>
                        <h3 class="text-2xl font-black text-on-surface">4</h3>
                    </div>
                </div>
            </div>
        </div>
        <!-- Footer Note -->
        <footer
            class="mt-auto px-10 py-6 border-t border-surface-container-high bg-white flex justify-between items-center">
            <p class="text-xs text-secondary">© 2023 Liceo Pro Educational Systems. Todos los derechos reservados.</p>
            <div class="flex gap-4">
                <a class="text-xs text-primary font-bold hover:underline" href="#">Soporte T\u00e9cnico</a>
                <a class="text-xs text-primary font-bold hover:underline" href="#">Pol\u00edticas de
                    Privacidad</a>
            </div>
        </footer>
    </main>
</body>

</html>
