<!DOCTYPE html>

<html lang="es">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Nueva Cita | Portal de Entrevistas</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;700;800&amp;family=Public_Sans:wght@400;500;600&amp;display=swap"
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
                    colors: {
                        "primary-fixed": "#d6e3ff",
                        "on-error": "#ffffff",
                        "on-error-container": "#93000a",
                        "on-tertiary-fixed": "#291800",
                        "tertiary": "#4d3100",
                        "inverse-surface": "#2d3133",
                        "surface-tint": "#1e5eac",
                        "on-primary-fixed-variant": "#00468a",
                        "error": "#ba1a1a",
                        "on-secondary-fixed": "#021f29",
                        "on-secondary-container": "#4e6874",
                        "surface-container-high": "#e6e8eb",
                        "on-primary-fixed": "#001b3d",
                        "error-container": "#ffdad6",
                        "surface": "#f7f9fc",
                        "on-primary-container": "#9cc0ff",
                        "on-secondary": "#ffffff",
                        "surface-container": "#eceef1",
                        "on-surface": "#191c1e",
                        "on-surface-variant": "#41474e",
                        "on-primary": "#ffffff",
                        "outline": "#72787f",
                        "background": "#f7f9fc",
                        "tertiary-fixed-dim": "#f2bd74",
                        "inverse-on-surface": "#eff1f4",
                        "surface-variant": "#e0e3e6",
                        "primary": "#00376e",
                        "on-tertiary": "#ffffff",
                        "on-tertiary-fixed-variant": "#633f00",
                        "inverse-primary": "#a8c8ff",
                        "primary-container": "#004d97",
                        "surface-container-low": "#f2f4f7",
                        "tertiary-fixed": "#ffddb3",
                        "outline-variant": "#c1c7cf",
                        "secondary-fixed-dim": "#afcbd8",
                        "surface-container-lowest": "#ffffff",
                        "secondary-container": "#cbe7f5",
                        "on-tertiary-container": "#eab66d",
                        "surface-bright": "#f7f9fc",
                        "on-background": "#191c1e",
                        "tertiary-container": "#6b4604",
                        "primary-fixed-dim": "#a8c8ff",
                        "secondary": "#48626e",
                        "surface-container-highest": "#e0e3e6",
                        "secondary-fixed": "#cbe7f5",
                        "surface-dim": "#d8dadd",
                        "on-secondary-fixed-variant": "#304a55"
                    },
                    fontFamily: {
                        "headline": ["Manrope"],
                        "body": ["Public Sans"],
                        "label": ["Public Sans"]
                    },
                    borderRadius: {
                        "DEFAULT": "0.125rem",
                        "lg": "0.25rem",
                        "xl": "0.5rem",
                        "full": "0.75rem"
                    },
                },
            },
        }
    </script>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        body {
            font-family: 'Public Sans', sans-serif;
            background-color: #f7f9fc;
        }

        .font-headline {
            font-family: 'Manrope', sans-serif;
        }
    </style>
</head>

<body class="bg-background text-on-surface">
    <!-- TopAppBar -->
    <header
        class="fixed top-0 z-50 w-full flex justify-between items-center px-6 py-3 bg-[#f7f9fc] dark:bg-slate-900 border-b border-[#f2f4f7] dark:border-slate-800">
        <div class="flex items-center gap-4">
            <span class="text-xl font-bold text-[#00376e] dark:text-blue-500 font-headline">Portal de Entrevistas</span>
            <div class="hidden md:flex ml-8 gap-6">
                <a class="text-[#48626e] dark:text-slate-400 font-medium hover:bg-[#f2f4f7] dark:hover:bg-slate-800 transition-colors px-2 py-1 rounded"
                    href="#">Inicio</a>
                <a class="text-[#00376e] dark:text-blue-400 font-bold px-2 py-1" href="#">Calendario</a>
            </div>
        </div>
        <div class="flex items-center gap-4">
            <button class="p-2 text-[#48626e] hover:bg-[#f2f4f7] rounded-full transition-colors">
                <span class="material-symbols-outlined">notifications</span>
            </button>
            <button class="p-2 text-[#48626e] hover:bg-[#f2f4f7] rounded-full transition-colors">
                <span class="material-symbols-outlined">help</span>
            </button>
            <div class="w-8 h-8 rounded-full bg-primary-container flex items-center justify-center overflow-hidden">
                <img alt="Avatar del Usuario" class="w-full h-full object-cover"
                    data-alt="close up of a professional male teacher smiling, neutral school background, soft cinematic lighting"
                    src="https://lh3.googleusercontent.com/aida-public/AB6AXuAYMSmLbFUsb5j_ttf5yx5MzUGfYnWTvVO9uqwmpuhhHwd_hP6cliC9HC1gwYEr6Lqr9DPwOX8cYfIy6r3Oge1ioZKGo6DQsrN0WpiadOAPetGIre8p7KP_a28trGUm9mTloKziurICcKBTncb7bcPLyTuiaJG7HIlnWQkcJ1MHvFUwKA40hGKBVCovk_NLDCHNZY1qB98D14-wfRQE0JXy6-rCxp1ijgBCsvi_RyPtrRFAjOcEgzZpcTjYx4gZStQqY4YlFo_a5KU" />
            </div>
        </div>
    </header>
    <!-- Sidebar (Suppressed for focused task per UX Goal, but showing layout structure) -->
    <aside
        class="fixed left-0 top-0 h-full w-64 bg-white dark:bg-slate-950 border-r dark:border-slate-800 flex flex-col pt-20 pb-8 hidden lg:flex">
        <nav class="flex-1 px-4 space-y-2">
            <div
                class="flex items-center gap-3 text-[#48626e] dark:text-slate-400 px-4 py-3 hover:bg-[#f7f9fc] transition-transform duration-200">
                <span class="material-symbols-outlined">dashboard</span>
                <span class="font-medium">Inicio</span>
            </div>
            <div
                class="flex items-center gap-3 bg-[#f2f4f7] dark:bg-slate-900 text-[#00376e] dark:text-blue-400 font-bold rounded-lg px-4 py-3">
                <span class="material-symbols-outlined">calendar_month</span>
                <span class="font-medium">Calendario</span>
            </div>
            <div class="flex items-center gap-3 text-[#48626e] dark:text-slate-400 px-4 py-3 hover:bg-[#f7f9fc]">
                <span class="material-symbols-outlined">group</span>
                <span class="font-medium">Apoderados</span>
            </div>
            <div class="flex items-center gap-3 text-[#48626e] dark:text-slate-400 px-4 py-3 hover:bg-[#f7f9fc]">
                <span class="material-symbols-outlined">analytics</span>
                <span class="font-medium">Reportes</span>
            </div>
        </nav>
        <div class="px-4 pt-4 border-t border-outline-variant/15">
            <div class="flex items-center gap-3 text-[#48626e] px-4 py-3 hover:bg-[#f7f9fc]">
                <span class="material-symbols-outlined">settings</span>
                <span class="font-medium">Configuración</span>
            </div>
            <div class="flex items-center gap-3 text-[#48626e] px-4 py-3 hover:bg-[#f7f9fc]">
                <span class="material-symbols-outlined">logout</span>
                <span class="font-medium">Cerrar Sesión</span>
            </div>
        </div>
    </aside>
    <!-- Main Content Canvas -->
    <main class="lg:ml-64 pt-20 min-h-screen flex flex-col items-center p-6 md:p-12">
        <!-- Header Section -->
        <div class="w-full max-w-3xl mb-12">
            <nav class="flex items-center gap-2 text-secondary text-sm mb-4">
                <a class="hover:text-primary transition-colors" href="#">Calendario</a>
                <span class="material-symbols-outlined text-xs">chevron_right</span>
                <span class="font-semibold text-on-surface">Nueva Cita</span>
            </nav>
            <h1 class="text-3xl font-extrabold text-primary font-headline tracking-tight">Agendar Entrevista</h1>
            <p class="text-secondary mt-2">Complete los detalles para coordinar una nueva reunión con el apoderado.</p>
        </div>
        <!-- Form Bento Layout -->
        <form class="w-full max-w-3xl space-y-8">
            <!-- Student & Guardian Information Card -->
            <section class="bg-surface-container-lowest rounded-xl p-8 transition-all">
                <h2 class="text-lg font-bold text-primary font-headline mb-6 flex items-center gap-2">
                    <span class="material-symbols-outlined">person</span>
                    Información del Estudiante
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Student Field -->
                    <div class="relative">
                        <label class="block text-xs font-bold uppercase tracking-wider text-secondary mb-2"
                            for="estudiante">Nombre del Estudiante</label>
                        <input
                            class="w-full bg-surface-container-low border-0 border-b-2 border-primary-fixed focus:border-primary focus:ring-0 transition-all py-3 px-4 font-body text-on-surface"
                            id="estudiante" name="estudiante" placeholder="Ej: Mateo Silva" type="text" />
                    </div>
                    <!-- Guardian Field -->
                    <div class="relative">
                        <label class="block text-xs font-bold uppercase tracking-wider text-secondary mb-2"
                            for="apoderado">RUT Apoderado / Nombre</label>
                        <input
                            class="w-full bg-surface-container-low border-0 border-b-2 border-primary-fixed focus:border-primary focus:ring-0 transition-all py-3 px-4 font-body text-on-surface"
                            id="apoderado" name="apoderado" placeholder="Ej: 12.345.678-9" type="text" />
                    </div>
                </div>
            </section>
            <!-- Schedule & Logistics Bento -->
            <section class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Date Picker Card -->
                <div class="md:col-span-2 bg-surface-container-lowest rounded-xl p-8">
                    <h2 class="text-lg font-bold text-primary font-headline mb-6 flex items-center gap-2">
                        <span class="material-symbols-outlined">event</span>
                        Fecha y Hora
                    </h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-secondary mb-2"
                                for="fecha">Fecha</label>
                            <input
                                class="w-full bg-surface-container-low border-0 border-b-2 border-primary-fixed focus:border-primary focus:ring-0 transition-all py-3 px-4 font-body"
                                id="fecha" name="fecha" type="date" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-secondary mb-2"
                                for="hora">Hora</label>
                            <input
                                class="w-full bg-surface-container-low border-0 border-b-2 border-primary-fixed focus:border-primary focus:ring-0 transition-all py-3 px-4 font-body"
                                id="hora" name="hora" type="time" />
                        </div>
                    </div>
                </div>
                <!-- Quick Action Tags Card -->
                <div class="bg-surface-container-lowest rounded-xl p-8 flex flex-col">
                    <h2 class="text-lg font-bold text-primary font-headline mb-6">Urgencia</h2>
                    <div class="flex flex-col gap-3">
                        <label class="flex items-center gap-3 cursor-pointer group">
                            <input checked="" class="text-primary focus:ring-primary border-outline-variant"
                                name="urgencia" type="radio" value="normal" />
                            <span
                                class="text-sm font-medium text-secondary group-hover:text-primary transition-colors">Normal</span>
                        </label>
                        <label class="flex items-center gap-3 cursor-pointer group">
                            <input class="text-tertiary focus:ring-tertiary border-outline-variant" name="urgencia"
                                type="radio" value="prioritario" />
                            <span
                                class="text-sm font-medium text-secondary group-hover:text-tertiary transition-colors">Prioritario</span>
                        </label>
                        <label class="flex items-center gap-3 cursor-pointer group">
                            <input class="text-error focus:ring-error border-outline-variant" name="urgencia"
                                type="radio" value="urgente" />
                            <span
                                class="text-sm font-medium text-secondary group-hover:text-error transition-colors text-error">Urgente</span>
                        </label>
                    </div>
                </div>
            </section>
            <!-- Reason & Notes Card -->
            <section class="bg-surface-container-lowest rounded-xl p-8">
                <h2 class="text-lg font-bold text-primary font-headline mb-6 flex items-center gap-2">
                    <span class="material-symbols-outlined">subject</span>
                    Motivo de la Entrevista
                </h2>
                <div class="space-y-6">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-secondary mb-2"
                            for="motivo">Categoría Principal</label>
                        <select
                            class="w-full bg-surface-container-low border-0 border-b-2 border-primary-fixed focus:border-primary focus:ring-0 transition-all py-3 px-4 font-body"
                            id="motivo" name="motivo">
                            <option value="rendimiento">Rendimiento Académico</option>
                            <option value="conducta">Conducta y Convivencia</option>
                            <option value="asistencia">Asistencia y Puntualidad</option>
                            <option value="personal">Asunto Personal / Familiar</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-secondary mb-2"
                            for="notas">Observaciones Adicionales</label>
                        <textarea
                            class="w-full bg-surface-container-low border-0 border-b-2 border-primary-fixed focus:border-primary focus:ring-0 transition-all py-3 px-4 font-body resize-none"
                            id="notas" name="notas" placeholder="Breve descripción de los temas a tratar..." rows="4"></textarea>
                    </div>
                </div>
            </section>
            <!-- Actions -->
            <div class="flex flex-col sm:flex-row gap-4 pt-6">
                <button
                    class="flex-1 bg-gradient-to-br from-primary to-primary-container text-white py-4 px-8 rounded-lg font-bold tracking-wide hover:opacity-90 transition-all shadow-md flex items-center justify-center gap-2"
                    type="submit">
                    <span class="material-symbols-outlined">calendar_add_on</span>
                    Confirmar y Agendar Cita
                </button>
                <button
                    class="px-8 py-4 text-secondary font-bold hover:bg-surface-container-high rounded-lg transition-colors"
                    type="button">
                    Cancelar
                </button>
            </div>
        </form>
        <!-- Footer Visual Hint -->
        <footer
            class="mt-24 w-full max-w-3xl flex justify-between items-center text-outline text-xs uppercase tracking-widest border-t border-outline-variant/15 pt-8 mb-12">
            <span>© 2024 Institución Educativa</span>
            <div class="flex gap-4">
                <span>Privacidad</span>
                <span>Soporte</span>
            </div>
        </footer>
    </main>
    <!-- Contextual FAB (Hidden on focused forms as per guidelines, but placed for layout completeness in code) -->
    <!-- The FAB is suppressed here to prioritize the scheduling task focus -->
</body>

</html>
