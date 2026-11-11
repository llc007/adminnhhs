<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bitacora_Entrevista_{{ $entrevista->id }}_{{ \Illuminate\Support\Str::slug($estudiante?->nombreCompleto() ?? 'estudiante') }}_{{ date('Y-m-d') }}</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        @page {
            size: A4 portrait;
            margin: 10mm 12mm 10mm 12mm;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: #0f172a;
            background-color: #f1f5f9;
            line-height: 1.35;
            font-size: 9.5px;
            padding: 16px;
        }

        .mono {
            font-family: 'JetBrains Mono', monospace;
        }

        /* Barra flotante de acciones en pantalla */
        .no-print-bar {
            position: fixed;
            top: 12px;
            right: 15px;
            z-index: 9999;
            display: flex;
            gap: 8px;
            background: rgba(15, 23, 42, 0.92);
            padding: 6px 12px;
            border-radius: 9999px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.35);
            backdrop-filter: blur(8px);
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            font-weight: 600;
            padding: 6px 14px;
            border-radius: 9999px;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-print {
            background-color: #00376e;
            color: #ffffff;
        }
        .btn-print:hover {
            background-color: #00254a;
        }

        .btn-close {
            background-color: #475569;
            color: #ffffff;
        }
        .btn-close:hover {
            background-color: #334155;
        }

        /* Hoja A4 */
        .sheet-container {
            max-width: 820px;
            margin: 0 auto;
            background: #ffffff;
            padding: 24px 26px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.08);
        }

        /* Encabezado */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #00376e;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }

        .school-name {
            font-size: 14px;
            font-weight: 800;
            color: #00376e;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .doc-title {
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
            margin-top: 2px;
            text-transform: uppercase;
        }

        .doc-subtitle {
            font-size: 8.5px;
            color: #64748b;
            margin-top: 2px;
        }

        .meta-box {
            text-align: right;
            font-size: 8.5px;
            color: #64748b;
            line-height: 1.35;
        }

        .meta-box strong {
            color: #0f172a;
        }

        .badge-status {
            display: inline-block;
            font-size: 8px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 9999px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 3px;
        }

        .badge-realizada {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .badge-otra {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* Grilla de Datos de Participantes */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-bottom: 12px;
        }

        .info-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 8px 10px;
        }

        .info-card-header {
            font-size: 8px;
            font-weight: 800;
            text-transform: uppercase;
            color: #00376e;
            letter-spacing: 0.4px;
            margin-bottom: 4px;
            padding-bottom: 3px;
            border-bottom: 1px solid #e2e8f0;
        }

        .info-card-row {
            margin-bottom: 2.5px;
            display: flex;
            justify-content: space-between;
            font-size: 8.5px;
        }

        .info-card-label {
            color: #64748b;
            font-weight: 500;
            margin-right: 4px;
        }

        .info-card-value {
            color: #0f172a;
            font-weight: 600;
            text-align: right;
        }

        .info-card-name {
            font-size: 10px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
        }

        /* Barra de Metadata de Cita */
        .appointment-bar {
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 4px;
            padding: 6px 10px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            font-size: 8.5px;
            margin-bottom: 12px;
            gap: 6px;
        }

        .appointment-item strong {
            color: #00376e;
        }

        /* Secciones de Contenido */
        .section-block {
            margin-bottom: 12px;
            page-break-inside: avoid;
        }

        .section-title {
            font-size: 9.5px;
            font-weight: 800;
            color: #00376e;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            padding-bottom: 3px;
            border-bottom: 1.5px solid #cbd5e1;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .section-box {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 8px 10px;
            font-size: 9px;
            color: #1e293b;
            line-height: 1.45;
        }

        .section-box p {
            margin-bottom: 4px;
        }
        .section-box p:last-child {
            margin-bottom: 0;
        }
        .section-box ul, .section-box ol {
            padding-left: 18px;
            margin-bottom: 4px;
        }
        .section-box li {
            margin-bottom: 2px;
        }

        /* Tabla de Acuerdos */
        table.table-acuerdos {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5px;
        }

        table.table-acuerdos th {
            background-color: #f1f5f9;
            color: #334155;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 8px;
            padding: 5px 8px;
            border: 1px solid #cbd5e1;
            text-align: left;
        }

        table.table-acuerdos td {
            padding: 5px 8px;
            border: 1px solid #e2e8f0;
            vertical-align: top;
        }

        table.table-acuerdos tr:nth-child(even) td {
            background-color: #f8fafc;
        }

        /* Área de Firmas */
        .signatures-container {
            margin-top: 18px;
            page-break-inside: avoid;
        }

        .signatures-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-top: 10px;
        }

        .signature-card {
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            background: #ffffff;
            padding: 8px 10px;
            min-height: 110px;
            display: flex;
            flex-col: column;
            justify-content: space-between;
            text-align: center;
        }

        .signature-placeholder {
            height: 55px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .signature-line {
            border-top: 1px solid #0f172a;
            padding-top: 4px;
            font-size: 8px;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
        }

        .signature-sub {
            font-size: 7.5px;
            color: #64748b;
            margin-top: 1px;
            font-weight: 500;
        }

        .digital-verified-box {
            background-color: #ecfdf5;
            border: 1px solid #6ee7b7;
            border-radius: 4px;
            padding: 5px 8px;
            margin-top: 10px;
            font-size: 8px;
            color: #065f46;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .footer-note {
            margin-top: 14px;
            padding-top: 6px;
            border-top: 1px dashed #cbd5e1;
            text-align: center;
            font-size: 7.5px;
            color: #94a3b8;
        }

        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
            }

            .no-print-bar {
                display: none !important;
            }

            .sheet-container {
                max-width: 100% !important;
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
            }

            .section-block {
                page-break-inside: avoid;
            }

            .signatures-container {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

    <!-- Barra flotante de acciones -->
    <div class="no-print-bar">
        <button class="btn-action btn-print" onclick="window.print()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            Imprimir / Guardar como PDF
        </button>
        <button class="btn-action btn-close" onclick="window.close()">
            ✕ Cerrar
        </button>
    </div>

    <div class="sheet-container">
        <!-- Encabezado Institucional -->
        <header class="header">
            <div>
                <div class="school-name">{{ $school?->name ?? 'Liceo New Heaven High School' }}</div>
                <div class="doc-title">Acta Oficial de Entrevista y Bitácora Institucional</div>
                @if($entrevista->es_confidencial)
                    <div style="font-size: 8px; font-weight: 700; color: #7e22ce; margin-top: 2px;">
                        🔒 REGISTRO CONFIDENCIAL / RESERVADO
                    </div>
                @endif
            </div>
            <div class="meta-box">
                @if($entrevista->estado === 'realizada')
                    <div class="badge-status badge-realizada">Entrevista Realizada</div>
                @else
                    <div class="badge-status badge-otra">Estado: {{ ucfirst($entrevista->estado) }}</div>
                @endif
                <div>Folio Entrevista: <strong class="mono">#{{ str_pad($entrevista->id, 5, '0', STR_PAD_LEFT) }}</strong></div>
            </div>
        </header>

        <!-- Grilla de Participantes -->
        <div class="info-grid">
            <!-- 1. Estudiante -->
            <div class="info-card">
                <div class="info-card-header">1. Estudiante Citado</div>
                <div class="info-card-name">{{ $estudiante?->nombreCompleto() ?? 'No registrado' }}</div>
                <div class="info-card-row">
                    <span class="info-card-label">RUT:</span>
                    <span class="info-card-value mono">{{ $estudiante?->rutCompleto() ?? '-' }}</span>
                </div>
                <div class="info-card-row">
                    <span class="info-card-label">Curso:</span>
                    <span class="info-card-value">{{ $estudiante?->curso?->nombreCompleto() ?? 'Sin curso' }}</span>
                </div>
                <div class="info-card-row">
                    <span class="info-card-label">Email:</span>
                    <span class="info-card-value mono" style="font-size: 7.5px;">{{ $estudiante?->email ?? '-' }}</span>
                </div>
            </div>

            <!-- 2. Apoderado / Acompañante -->
            <div class="info-card">
                <div class="info-card-header">2. Apoderado / Asistente</div>
                <div class="info-card-name">
                    {{ $entrevista->apoderado_nombre ?: ($estudiante?->apoderadoNombreCompleto() ?: 'Sin nombre registrado') }}
                </div>
                <div class="info-card-row">
                    <span class="info-card-label">RUT:</span>
                    <span class="info-card-value mono">
                        {{ $entrevista->apoderado_rut ?: ($estudiante?->apoderadoRutCompleto() ?: '-') }}
                    </span>
                </div>
                <div class="info-card-row">
                    <span class="info-card-label">Vínculo:</span>
                    <span class="info-card-value">{{ ucfirst($estudiante?->apoderado_parentesco ?? 'Apoderado') }}</span>
                </div>
                <div class="info-card-row">
                    <span class="info-card-label">Teléfono:</span>
                    <span class="info-card-value mono">
                        {{ $entrevista->apoderado_telefono ?: ($estudiante?->apoderado_telefono ?: '-') }}
                    </span>
                </div>
            </div>

            <!-- 3. Profesional a Cargo -->
            <div class="info-card">
                <div class="info-card-header">3. Profesional / Entrevistador</div>
                <div class="info-card-name">{{ $docente?->nombreCompleto() ?? 'No asignado' }}</div>
                <div class="info-card-row">
                    <span class="info-card-label">RUT:</span>
                    <span class="info-card-value mono">{{ $docente?->rutCompleto() ?? '-' }}</span>
                </div>
                <div class="info-card-row">
                    <span class="info-card-label">Función:</span>
                    <span class="info-card-value">{{ ucfirst($docente?->active_roles[0] ?? 'Docente') }}</span>
                </div>
                <div class="info-card-row">
                    <span class="info-card-label">Email:</span>
                    <span class="info-card-value mono" style="font-size: 7.5px;">{{ $docente?->email ?? '-' }}</span>
                </div>
            </div>
        </div>

        <!-- Barra de Datos de la Cita -->
        <div class="appointment-bar">
            <div class="appointment-item">
                <span>Fecha:</span>
                <strong>{{ \Carbon\Carbon::parse($entrevista->fecha)->translatedFormat('l d \d\e F, Y') }}</strong>
            </div>
            <div class="appointment-item">
                <span>Hora Cita:</span>
                <strong class="mono">{{ \Carbon\Carbon::parse($entrevista->hora)->format('H:i') }} hrs</strong>
            </div>
            <div class="appointment-item">
                <span>Lugar:</span>
                <strong>{{ $entrevista->lugar ?: 'Establecimiento' }}</strong>
            </div>
            <div class="appointment-item">
                <span>Categoría Motivo:</span>
                <strong>{{ \App\Models\Entrevista::normalizarCategoria($entrevista->motivo) }}</strong>
            </div>
            @if($entrevista->hora_llegada)
                <div class="appointment-item">
                    <span>Recepción:</span>
                    <strong class="mono">{{ substr($entrevista->hora_llegada, 0, 5) }} - {{ $entrevista->hora_salida ? substr($entrevista->hora_salida, 0, 5) : 'Presente' }}</strong>
                </div>
            @endif
        </div>

        @if($entrevista->motivo && $entrevista->motivo !== \App\Models\Entrevista::normalizarCategoria($entrevista->motivo))
            <div style="margin-bottom: 10px; font-size: 8.5px; color: #475569; padding: 4px 8px; background: #f8fafc; border-left: 2px solid #00376e;">
                <strong>Detalle específico del motivo:</strong> {{ $entrevista->motivo }}
            </div>
        @endif

        <!-- 1. Resumen de la Conversación -->
        <section class="section-block">
            <h3 class="section-title">
                <span>I. Resumen de la Conversación y Materias Tratadas</span>
            </h3>
            <div class="section-box">
                @if ($bitacora && !empty(trim($bitacora->resumen ?? '')))
                    {!! $bitacora->resumen !!}
                @else
                    <p style="font-style: italic; color: #94a3b8;">No se ha redactado un resumen de la conversación para esta bitácora.</p>
                @endif
            </div>
        </section>

        <!-- 2. Acuerdos y Compromisos -->
        <section class="section-block">
            <h3 class="section-title">
                <span>II. Acuerdos y Compromisos Alcanzados</span>
            </h3>
            @if ($bitacora && !empty($bitacora->acuerdos) && is_array($bitacora->acuerdos) && count($bitacora->acuerdos) > 0)
                <table class="table-acuerdos">
                    <thead>
                        <tr>
                            <th style="width: 28px; text-align: center;">N°</th>
                            <th style="width: 32%;">Compromiso / Acuerdo</th>
                            <th>Descripción y Plazo / Condición</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($bitacora->acuerdos as $idx => $acuerdo)
                            <tr>
                                <td class="mono" style="text-align: center; font-weight: 700; color: #64748b;">{{ $idx + 1 }}</td>
                                <td style="font-weight: 700; color: #00376e;">{{ $acuerdo['titulo'] ?? 'Acuerdo' }}</td>
                                <td>{{ $acuerdo['descripcion'] ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="section-box" style="font-style: italic; color: #94a3b8;">
                    No se registraron acuerdos o compromisos formales en esta entrevista.
                </div>
            @endif
        </section>

        <!-- 3. Observaciones Generales -->
        @if ($bitacora && !empty(trim($bitacora->observaciones ?? '')))
            <section class="section-block">
                <h3 class="section-title">
                    <span>III. Observaciones y Notas de Seguimiento</span>
                </h3>
                <div class="section-box">
                    {!! nl2br(e($bitacora->observaciones)) !!}
                </div>
            </section>
        @endif

        <!-- 4. Adjuntos / Evidencias -->
        @if ($bitacora && !empty($bitacora->adjuntos_drive) && is_array($bitacora->adjuntos_drive) && count($bitacora->adjuntos_drive) > 0)
            <section class="section-block">
                <h3 class="section-title">
                    <span>IV. Documentos y Enlaces de Respaldo</span>
                </h3>
                <div class="section-box">
                    <ul style="margin: 0; padding-left: 16px;">
                        @foreach ($bitacora->adjuntos_drive as $adj)
                            <li>
                                <strong>{{ $adj['nombre'] ?? 'Documento' }}:</strong>
                                <span class="mono" style="font-size: 8px; color: #2563eb;">{{ $adj['url'] ?? '' }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif

        <!-- Conformidad del Apoderado -->
        <section class="signatures-container">
            <h3 class="section-title">
                <span>V. Conformidad del Apoderado / Entrevistado</span>
            </h3>

            <div style="display: flex; justify-content: center; margin-top: 10px;">
                <!-- Firma Apoderado -->
                <div class="signature-card" style="width: 280px; max-width: 100%;">
                    <div class="signature-placeholder">
                        @if ($bitacora && $bitacora->firma_svg)
                            @if(str_starts_with($bitacora->firma_svg, 'data:'))
                                <img src="{{ $bitacora->firma_svg }}" alt="Firma Digital" style="max-height: 52px; max-width: 200px; object-fit: contain;" />
                            @elseif(str_starts_with(trim($bitacora->firma_svg), '<svg'))
                                <div style="max-height: 52px; max-width: 200px; overflow: hidden;">{!! $bitacora->firma_svg !!}</div>
                            @else
                                <img src="{{ $bitacora->firma_svg }}" alt="Firma Digital" style="max-height: 52px; max-width: 200px; object-fit: contain;" />
                            @endif
                        @else
                            <div style="display: flex; align-items: center; justify-content: center; height: 100%; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">
                                Sin firma
                            </div>
                        @endif
                    </div>
                    <div>
                        <div class="signature-line">
                            {{ $bitacora?->firmante_nombre ?: ($entrevista->apoderado_nombre ?: ($estudiante?->apoderadoNombreCompleto() ?: 'Apoderado / Entrevistado')) }}
                        </div>
                        <div class="signature-sub">
                            Firma Apoderado / Entrevistado<br>
                            <span class="mono">RUT: {{ $bitacora?->firmante_rut ?: ($entrevista->apoderado_rut ?: ($estudiante?->apoderadoRutCompleto() ?: '-')) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Validación de Firma Digital si aplica -->
            @if ($bitacora && in_array($bitacora->estado_firma, ['firmada_presencial', 'firmada_online']))
                <div class="digital-verified-box" style="max-width: 520px; margin: 10px auto 0 auto;">
                    <span>
                        ✔ <strong>Acta firmada digitalmente</strong> mediante modalidad 
                        <em>{{ $bitacora->estado_firma === 'firmada_presencial' ? 'Presencial (Dispositivo / Tableta)' : 'Online (Enlace seguro por correo)' }}</em>
                        por <strong>{{ $bitacora->firmante_nombre }}</strong> (RUT {{ $bitacora->firmante_rut }}).
                    </span>
                    <span class="mono">
                        {{ $bitacora->firmado_at ? $bitacora->firmado_at->setTimezone('America/Santiago')->format('d/m/Y H:i') . ' hrs' : '' }}
                    </span>
                </div>
            @endif
        </section>

        <!-- Pie de página -->
        <footer class="footer-note">
            Este documento constituye un registro oficial interno de atención y acompañamiento estudiantil del establecimiento {{ $school?->name ?? 'Liceo New Heaven High School' }}. 
            Generado digitalmente por el sistema institucional.
        </footer>
    </div>
</body>
</html>
