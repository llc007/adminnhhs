<!DOCTYPE html>

<html class="light" lang="es">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Mi Perfil - Liceo New Heaven</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&amp;family=Public+Sans:wght@300;400;500;600;700&amp;display=swap"
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
                        "surface": "#f7f9fc",
                        "tertiary-fixed": "#ffddb3",
                        "on-tertiary-fixed": "#291800",
                        "on-tertiary": "#ffffff",
                        "secondary-fixed": "#cbe7f5",
                        "on-secondary-fixed-variant": "#304a55",
                        "tertiary-container": "#6b4604",
                        "on-error-container": "#93000a",
                        "secondary": "#48626e",
                        "tertiary-fixed-dim": "#f2bd74",
                        "on-tertiary-fixed-variant": "#633f00",
                        "on-secondary": "#ffffff",
                        "outline": "#72787f",
                        "surface-container-highest": "#e0e3e6",
                        "primary-container": "#004d97",
                        "outline-variant": "#c1c7cf",
                        "on-primary-fixed": "#001b3d",
                        "on-primary-container": "#9cc0ff",
                        "surface-container-lowest": "#ffffff",
                        "on-tertiary-container": "#eab66d",
                        "on-secondary-container": "#4e6874",
                        "inverse-surface": "#2d3133",
                        "secondary-fixed-dim": "#afcbd8",
                        "surface-variant": "#e0e3e6",
                        "surface-bright": "#f7f9fc",
                        "on-secondary-fixed": "#021f29",
                        "surface-container-high": "#e6e8eb",
                        "inverse-primary": "#a8c8ff",
                        "on-primary-fixed-variant": "#00468a",
                        "on-surface-variant": "#41474e",
                        "primary": "#00376e",
                        "primary-fixed-dim": "#a8c8ff",
                        "on-surface": "#191c1e",
                        "tertiary": "#4d3100",
                        "error-container": "#ffdad6",
                        "secondary-container": "#cbe7f5",
                        "error": "#ba1a1a",
                        "surface-tint": "#1e5eac",
                        "inverse-on-surface": "#eff1f4",
                        "surface-container": "#eceef1",
                        "surface-dim": "#d8dadd",
                        "primary-fixed": "#d6e3ff",
                        "on-error": "#ffffff",
                        "surface-container-low": "#f2f4f7",
                        "on-primary": "#ffffff",
                        "on-background": "#191c1e",
                        "background": "#f7f9fc"
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
                },
            },
        }
    </script>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        .glass-header {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px);
        }
    </style>
</head>

<body class="bg-surface font-body text-on-surface">
    <!-- SideNavBar -->
    <aside
        class="h-screen w-72 flex flex-col fixed left-0 top-0 bg-slate-50 dark:bg-slate-950 border-r border-slate-100 dark:border-slate-800 p-4 z-50">
        <div class="mb-8 px-4 flex items-center gap-3">
            <img class="w-10 h-10 object-contain"
                data-alt="Institutional school crest for Liceo New Heaven featuring a classic shield design with elegant blue and gold accents"
                src="https://lh3.googleusercontent.com/aida-public/AB6AXuDkyTzeT4CaNu3L_DULO3ZVcJSmhaUbl_9JeVSPv-9Djl5T_A_EDf_XC8pc2NIfnFiM4FUzNm2nfbJrxKTNflmCW0OBDEiCe5wUANm1XjUlB5IbfFsml_mi09ta6tw5OW4dz6H0F9i1l5xqm4ElfoBXxwCJYWzKogJEmfiqWLD9qOtAYfooYizxLDAYpq8MBQJpHjkqRj_KdJU4aB4KA-QCLJOOqwjU34ZGMJRzn5no1xtCcptc9xyOb0_07ZQ6JcyOLDqDuZtS83g" />
            <div class="flex flex-col">
                <span class="font-manrope font-bold text-blue-900 dark:text-blue-100 text-lg leading-tight">Liceo New
                    Heaven</span>
                <span class="text-[10px] uppercase tracking-widest text-secondary font-semibold">Digital
                    Concierge</span>
            </div>
        </div>
        <nav class="flex flex-col h-full">
            <div class="space-y-1">
                <a class="flex items-center gap-3 text-slate-600 dark:text-slate-400 px-4 py-3 mb-1 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-md transition-all duration-150 ease-in-out"
                    href="#">
                    <span class="material-symbols-outlined" data-icon="dashboard">dashboard</span>
                    <span class="font-public-sans text-sm font-medium">Panel Control</span>
                </a>
                <a class="flex items-center gap-3 text-slate-600 dark:text-slate-400 px-4 py-3 mb-1 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-md transition-all duration-150 ease-in-out"
                    href="#">
                    <span class="material-symbols-outlined" data-icon="badge">badge</span>
                    <span class="font-public-sans text-sm font-medium">Ficha Estudiante</span>
                </a>
                <a class="flex items-center gap-3 text-slate-600 dark:text-slate-400 px-4 py-3 mb-1 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-md transition-all duration-150 ease-in-out"
                    href="#">
                    <span class="material-symbols-outlined" data-icon="how_to_reg">how_to_reg</span>
                    <span class="font-public-sans text-sm font-medium">Asistencia</span>
                </a>
                <a class="flex items-center gap-3 text-slate-600 dark:text-slate-400 px-4 py-3 mb-1 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-md transition-all duration-150 ease-in-out"
                    href="#">
                    <span class="material-symbols-outlined" data-icon="gate">gate</span>
                    <span class="font-public-sans text-sm font-medium">Portería</span>
                </a>
                <a class="flex items-center gap-3 bg-white dark:bg-blue-900/20 text-blue-900 dark:text-blue-300 shadow-sm rounded-md font-bold px-4 py-3 mb-1 transition-all duration-150 ease-in-out"
                    href="#">
                    <span class="material-symbols-outlined" data-icon="settings">settings</span>
                    <span class="font-public-sans text-sm font-medium">Configuración</span>
                </a>
            </div>
            <div class="mt-auto pt-6 border-t border-slate-100 dark:border-slate-800 px-4">
                <div class="flex items-center gap-3 mb-4">
                    <div
                        class="w-10 h-10 rounded-full bg-primary-fixed flex items-center justify-center text-primary font-bold">
                        MS</div>
                    <div class="flex flex-col">
                        <span class="text-xs font-bold text-on-surface">Marcela Soto</span>
                        <span class="text-[10px] text-secondary">Docente</span>
                    </div>
                </div>
            </div>
        </nav>
    </aside>
    <!-- Main Content Canvas -->
    <main class="ml-72 min-h-screen">
        <!-- TopAppBar -->
        <header
            class="flex justify-between items-center px-8 py-4 w-full bg-slate-50 dark:bg-slate-900 sticky top-0 z-40">
            <div class="flex items-center gap-4">
                <h1 class="font-manrope font-extrabold text-xl tracking-tight text-blue-900 dark:text-white">Mi Perfil
                </h1>
            </div>
            <div class="flex items-center gap-6">
                <div class="relative group">
                    <span
                        class="material-symbols-outlined text-slate-500 hover:bg-slate-200/50 p-2 rounded-full cursor-pointer transition-colors"
                        data-icon="notifications">notifications</span>
                    <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-error rounded-full"></span>
                </div>
                <div
                    class="flex items-center gap-2 px-3 py-1.5 rounded-full hover:bg-slate-200/50 transition-colors cursor-pointer">
                    <span class="material-symbols-outlined text-blue-900 dark:text-blue-400"
                        data-icon="account_circle">account_circle</span>
                    <span class="font-manrope font-bold text-sm text-blue-900 dark:text-blue-400">Marcela Soto</span>
                </div>
            </div>
        </header>
        <!-- Content Area -->
        <div class="p-10 max-w-5xl mx-auto">
            <!-- Profile Header Card (Editorial Style) -->
            <section class="mb-10 flex flex-col md:flex-row items-center md:items-end gap-8">
                <div class="relative">
                    <div
                        class="w-32 h-32 rounded-xl overflow-hidden shadow-sm bg-surface-container-high border-4 border-white">
                        <img class="w-full h-full object-cover"
                            data-alt="Professional portrait of a middle-aged female teacher with a warm smile, soft natural lighting, educational background context"
                            src="https://lh3.googleusercontent.com/aida-public/AB6AXuCVF9BkWj6qKa3rJ4ZiBNk7kjz0mL7-7B0t_zrKr3uStzufgXR-Q7FXCMgmp6fNFIKQiEvzbcQlMSJ8AH-slIrG2JmDDFCtZ2bAHRrUsiyBeeD82BTNvGVHcqGvRxt1ti4SnSgw9_2yxKA1Kw3hU82ce0RUplQVibkrygUurVjSbye9TdhQFJps9TKrIfnMUBDL5R5PeaIptbuJ4pY5Yw1bidbujBvFdp_8dKc412vc2N3VIObfU8BlzJeU64tE3kCBpLL7Hr5DBc8" />
                    </div>
                    <button
                        class="absolute -bottom-2 -right-2 bg-primary text-white p-2 rounded-lg shadow-lg hover:scale-105 transition-transform">
                        <span class="material-symbols-outlined text-sm" data-icon="photo_camera">photo_camera</span>
                    </button>
                </div>
                <div class="flex-1 text-center md:text-left space-y-1">
                    <h2 class="font-headline text-3xl font-extrabold text-primary">Marcela Soto</h2>
                    <p class="font-body text-secondary text-lg flex items-center justify-center md:justify-start gap-2">
                        <span class="material-symbols-outlined text-base" data-icon="school">school</span>
                        Docente de Matemáticas
                    </p>
                    <div class="flex gap-2 mt-4 justify-center md:justify-start">
                        <span
                            class="px-3 py-1 bg-secondary-container text-on-secondary-container text-xs font-bold rounded-full">PLANTA</span>
                        <span
                            class="px-3 py-1 bg-surface-container-high text-secondary text-xs font-bold rounded-full">JORNADA
                            COMPLETA</span>
                    </div>
                </div>
            </section>
            <!-- Main Form Card (Bento/Editorial Style) -->
            <div class="bg-surface-container-lowest rounded-xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="p-8 border-b border-slate-50">
                    <h3 class="font-headline text-xl font-bold text-primary">Ficha de Datos Personales</h3>
                    <p class="text-secondary text-sm">Mantenga su información actualizada para asegurar una comunicación
                        efectiva con la institución.</p>
                </div>
                <form class="p-8 space-y-10">
                    <!-- Section 1: Identificación -->
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-x-10 gap-y-8">
                        <div class="md:col-span-12">
                            <h4 class="text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">Identificación
                                Personal</h4>
                        </div>
                        <div class="md:col-span-4 flex flex-col gap-2">
                            <label class="text-sm font-semibold text-secondary ml-1" for="nombre">Nombre</label>
                            <input
                                class="w-full bg-surface-container-low border-0 border-b-2 border-primary-fixed focus:border-primary focus:ring-0 px-1 py-2 text-on-surface font-medium transition-all"
                                id="nombre" placeholder="Ej: Marcela" type="text" value="Marcela" />
                        </div>
                        <div class="md:col-span-4 flex flex-col gap-2">
                            <label class="text-sm font-semibold text-secondary ml-1" for="ap_paterno">Apellido
                                Paterno</label>
                            <input
                                class="w-full bg-surface-container-low border-0 border-b-2 border-primary-fixed focus:border-primary focus:ring-0 px-1 py-2 text-on-surface font-medium transition-all"
                                id="ap_paterno" placeholder="Ej: Soto" type="text" value="Soto" />
                        </div>
                        <div class="md:col-span-4 flex flex-col gap-2">
                            <label class="text-sm font-semibold text-secondary ml-1" for="ap_materno">Apellido
                                Materno</label>
                            <input
                                class="w-full bg-surface-container-low border-0 border-b-2 border-primary-fixed focus:border-primary focus:ring-0 px-1 py-2 text-on-surface font-medium transition-all"
                                id="ap_materno" placeholder="Ej: Guzmán" type="text" value="Guzmán" />
                        </div>
                        <div class="md:col-span-4 flex flex-col gap-2">
                            <label class="text-sm font-semibold text-secondary ml-1" for="rut">RUT</label>
                            <div class="flex items-end gap-3">
                                <input
                                    class="flex-1 bg-surface-container-low border-0 border-b-2 border-primary-fixed focus:border-primary focus:ring-0 px-1 py-2 text-on-surface font-medium transition-all"
                                    id="rut" placeholder="12.345.678" type="text" value="15.678.901" />
                                <span class="pb-2 font-bold text-slate-300">—</span>
                                <input
                                    class="w-12 bg-surface-container-low border-0 border-b-2 border-primary-fixed focus:border-primary focus:ring-0 px-1 py-2 text-center text-on-surface font-medium transition-all uppercase"
                                    id="dv" placeholder="K" type="text" value="K" />
                            </div>
                        </div>
                        <div class="md:col-span-4 flex flex-col gap-2">
                            <label class="text-sm font-semibold text-secondary ml-1" for="nacimiento">Fecha de
                                Nacimiento</label>
                            <div class="relative">
                                <input
                                    class="w-full bg-surface-container-low border-0 border-b-2 border-primary-fixed focus:border-primary focus:ring-0 px-1 py-2 text-on-surface font-medium transition-all"
                                    id="nacimiento" type="date" value="1985-05-14" />
                            </div>
                        </div>
                    </div>
                    <!-- Section 2: Contacto y Ubicación -->
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-x-10 gap-y-8 pt-6">
                        <div class="md:col-span-12">
                            <h4 class="text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">Contacto y
                                Ubicación</h4>
                        </div>
                        <div class="md:col-span-12 flex flex-col gap-2">
                            <label class="text-sm font-semibold text-secondary ml-1" for="direccion">Dirección
                                Particular</label>
                            <textarea
                                class="w-full bg-surface-container-low border-0 border-b-2 border-primary-fixed focus:border-primary focus:ring-0 px-1 py-2 text-on-surface font-medium transition-all resize-none"
                                id="direccion" placeholder="Calle, Número, Depto, Comuna" rows="2">Avenida Libertador Bernardo O'Higgins 456, Depto 1204, Santiago Centro.</textarea>
                        </div>
                        <div class="md:col-span-6 flex flex-col gap-2">
                            <label class="text-sm font-semibold text-secondary ml-1" for="correo_inst">Correo
                                Institucional</label>
                            <div class="relative group">
                                <input
                                    class="w-full bg-slate-100 border-0 border-b-2 border-slate-200 px-1 py-2 text-slate-500 font-medium cursor-not-allowed"
                                    disabled="" id="correo_inst" type="email"
                                    value="m.soto@liceonewheaven.cl" />
                                <span class="material-symbols-outlined absolute right-2 top-2.5 text-slate-400 text-sm"
                                    data-icon="lock" data-weight="fill"
                                    style="font-variation-settings: 'FILL' 1;">lock</span>
                            </div>
                            <p class="text-[10px] text-slate-400 italic">Campo administrado por TI Liceo.</p>
                        </div>
                        <div class="md:col-span-6 flex flex-col gap-2">
                            <label class="text-sm font-semibold text-secondary ml-1" for="correo_pers">Correo
                                Personal</label>
                            <input
                                class="w-full bg-surface-container-low border-0 border-b-2 border-primary-fixed focus:border-primary focus:ring-0 px-1 py-2 text-on-surface font-medium transition-all"
                                id="correo_pers" placeholder="ejemplo@correo.com" type="email"
                                value="marcela.soto.g@gmail.com" />
                        </div>
                    </div>
                    <!-- Actions -->
                    <div
                        class="flex flex-col sm:flex-row items-center justify-end gap-4 pt-12 border-t border-slate-50">
                        <button
                            class="w-full sm:w-auto px-8 py-3 rounded-lg text-secondary font-bold hover:bg-surface-container-high transition-colors"
                            type="button">
                            Cancelar
                        </button>
                        <button
                            class="w-full sm:w-auto px-10 py-3 rounded-lg bg-gradient-to-br from-primary to-primary-container text-white font-bold shadow-lg shadow-blue-900/10 hover:scale-[1.02] active:scale-95 transition-all flex items-center justify-center gap-2"
                            type="submit">
                            <span class="material-symbols-outlined text-lg" data-icon="save">save</span>
                            Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
            <!-- Contextual Information (Editorial Side Note) -->
            <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="p-6 bg-secondary-container/30 rounded-xl flex items-start gap-4">
                    <span class="material-symbols-outlined text-primary" data-icon="info">info</span>
                    <div>
                        <h5 class="font-bold text-primary text-sm">Privacidad de Datos</h5>
                        <p class="text-xs text-on-secondary-container leading-relaxed">Su información personal está
                            protegida bajo la Ley 19.628 de Protección de la Vida Privada. Estos datos son de uso
                            exclusivo institucional.</p>
                    </div>
                </div>
                <div class="p-6 bg-tertiary-fixed/30 rounded-xl flex items-start gap-4">
                    <span class="material-symbols-outlined text-tertiary"
                        data-icon="verified_user">verified_user</span>
                    <div>
                        <h5 class="font-bold text-tertiary text-sm">Verificación de Identidad</h5>
                        <p class="text-xs text-on-tertiary-fixed-variant leading-relaxed">Los cambios en RUT o Nombres
                            legales requieren presentar el documento de identidad original en secretaría administrativa.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>

</html>
