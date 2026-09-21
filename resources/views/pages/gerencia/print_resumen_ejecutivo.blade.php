<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informe_Ejecutivo_Gerencia_{{ date('Y-m-d') }}</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        @page {
            size: A4 portrait;
            margin: 8mm 8mm 8mm 8mm;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: #0f172a;
            background-color: #f1f5f9;
            line-height: 1.25;
            font-size: 9px;
            padding: 15px;
        }

        .mono {
            font-family: 'JetBrains Mono', monospace;
        }

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
            gap: 5px;
            font-size: 11px;
            font-weight: 600;
            padding: 5px 12px;
            border-radius: 9999px;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-print {
            background-color: #2563eb;
            color: #ffffff;
        }
        .btn-print:hover {
            background-color: #1d4ed8;
        }

        .btn-close {
            background-color: #475569;
            color: #ffffff;
        }
        .btn-close:hover {
            background-color: #334155;
        }

        .sheet-container {
            max-width: 820px;
            margin: 0 auto;
            background: #ffffff;
            padding: 16px 20px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.08);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }

        .school-name {
            font-size: 13.5px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .doc-title {
            font-size: 11px;
            font-weight: 700;
            color: #1e40af;
            margin-top: 1px;
            text-transform: uppercase;
        }

        .meta-box {
            text-align: right;
            font-size: 8px;
            color: #64748b;
            line-height: 1.3;
        }

        .meta-box strong {
            color: #0f172a;
        }

        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-bottom: 12px;
        }

        .kpi-card {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 7px 9px;
            border-left: 3.5px solid #2563eb;
        }

        .kpi-card.emerald { border-left-color: #059669; }
        .kpi-card.purple { border-left-color: #7c3aed; }
        .kpi-card.amber { border-left-color: #d97706; }

        .kpi-label {
            font-size: 7.5px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.2px;
        }

        .kpi-value {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.1;
            margin-top: 2px;
        }

        .kpi-sub {
            font-size: 7.5px;
            color: #64748b;
            margin-top: 2px;
        }

        .section-title {
            font-size: 9.5px;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 6px;
            padding-bottom: 3px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 12px;
        }

        table.simple-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5px;
        }

        table.simple-table th {
            background: #f1f5f9;
            color: #334155;
            font-weight: 700;
            text-align: left;
            padding: 4px 6px;
            border: 1px solid #cbd5e1;
            font-size: 8px;
            text-transform: uppercase;
        }

        table.simple-table td {
            padding: 4px 6px;
            border: 1px solid #e2e8f0;
            color: #1e293b;
        }

        table.simple-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }

        .pill {
            display: inline-block;
            padding: 1px 5px;
            border-radius: 3px;
            font-size: 7.5px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .pill-blue { background: #dbeafe; color: #1e40af; }
        .pill-green { background: #dcfce7; color: #166534; }
        .pill-amber { background: #fef3c7; color: #92400e; }
        .pill-red { background: #fee2e2; color: #991b1b; }

        .footer-signatures {
            margin-top: 18px;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
            padding: 0 40px;
            page-break-inside: avoid;
        }

        .signature-box {
            text-align: center;
            border-top: 1px solid #0f172a;
            padding-top: 4px;
            font-size: 8px;
            font-weight: 600;
            color: #334155;
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
            table.simple-table {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

    <div class="no-print-bar">
        <button class="btn-action btn-print" onclick="window.print()">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            Imprimir / Guardar PDF
        </button>
        <button class="btn-action btn-close" onclick="window.close()">
            ✕ Cerrar
        </button>
    </div>

    <div class="sheet-container">
        
        <!-- Header -->
        <header class="header">
            <div>
                <div class="school-name">{{ $school?->name ?? 'Establecimiento Educacional' }}</div>
                <div class="doc-title">Resumen Ejecutivo de Gestión y Entrevistas</div>
                <div style="font-size: 8px; color: #475569; margin-top: 2px;">
                    Período: <strong>{{ $periodoLabel }}</strong> • Ciclo: <strong>{{ $ciclo === 'todos' ? 'Institucional Completo' : ($ciclo === 'basica' ? 'Enseñanza Básica' : 'Enseñanza Media') }}</strong>
                </div>
            </div>
            <div class="meta-box">
                <div>Emitido: <strong>{{ now('America/Santiago')->translatedFormat('d/m/Y H:i') }} hrs</strong></div>
                <div>Generado por: <strong>{{ $user->nombreCompleto() }}</strong></div>
                <div>Perfil: <strong>Gerencia / Sostenedor</strong></div>
            </div>
        </header>

        <!-- Macrométricas -->
        <section class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-label">Familias Atendidas</div>
                <div class="kpi-value mono">{{ $realizadas }}</div>
                <div class="kpi-sub">de {{ $totalAgendadas }} citaciones agendadas</div>
            </div>
            <div class="kpi-card emerald">
                <div class="kpi-label">Efectividad de Concreción</div>
                <div class="kpi-value mono" style="color: #059669;">{{ $tasaConcrecion }}%</div>
                <div class="kpi-sub">actas cerradas exitosamente</div>
            </div>
            <div class="kpi-card purple">
                <div class="kpi-label">Cobertura Docente</div>
                <div class="kpi-value mono" style="color: #7c3aed;">{{ $coberturaDocente }}%</div>
                <div class="kpi-sub">{{ $docentesActivos }} de {{ $docentesColegio }} docentes activos</div>
            </div>
            <div class="kpi-card amber">
                <div class="kpi-label">Inasistencia / No Concretadas</div>
                <div class="kpi-value mono" style="color: #b45309;">{{ $tasaInasistencia }}%</div>
                <div class="kpi-sub">{{ $ausentes }} ausencias • {{ $canceladas }} canceladas</div>
            </div>
        </section>

        <!-- Grid 2 Columnas: Problemáticas y Ciclos -->
        <div class="grid-2">
            <!-- Problemáticas -->
            <div>
                <div class="section-title">
                    <span>Problemáticas Dominantes</span>
                    <span class="mono" style="font-size: 8px; color: #2563eb; font-weight: 700;">Principal: {{ $topProblematica }} ({{ $topProblematicaPct }}%)</span>
                </div>
                <table class="simple-table">
                    <thead>
                        <tr>
                            <th>Motivo / Problemática</th>
                            <th style="width: 45px; text-align: center;">Citas</th>
                            <th style="width: 55px; text-align: center;">% Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($conteoProblematicas as $mot => $cant)
                            @php
                                $pct = $totalAgendadas > 0 ? round(($cant / $totalAgendadas) * 100, 1) : 0;
                            @endphp
                            <tr>
                                <td style="font-weight: 600;">{{ $mot }}</td>
                                <td class="mono" style="text-align: center; font-weight: 700;">{{ $cant }}</td>
                                <td class="mono" style="text-align: center; color: #475569;">{{ $pct }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Comparativa Ciclos Educativos -->
            <div>
                <div class="section-title">
                    <span>Comparativa por Ciclos</span>
                    <span style="font-size: 8px; color: #475569;">Básica vs. Media</span>
                </div>
                <table class="simple-table mb-3">
                    <thead>
                        <tr>
                            <th>Ciclo Educativo</th>
                            <th style="width: 55px; text-align: center;">Agendadas</th>
                            <th style="width: 55px; text-align: center;">Realizadas</th>
                            <th style="width: 60px; text-align: center;">Efectividad</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="font-weight: 700;">Enseñanza Básica (1°-8°)</td>
                            <td class="mono" style="text-align: center;">{{ $totalBasica }}</td>
                            <td class="mono" style="text-align: center; font-weight: 700; color: #059669;">{{ $realizadasBasica }}</td>
                            <td class="mono" style="text-align: center; font-weight: 800;">{{ $tasaBasica }}%</td>
                        </tr>
                        <tr>
                            <td style="font-weight: 700;">Enseñanza Media (1°-4°)</td>
                            <td class="mono" style="text-align: center;">{{ $totalMedia }}</td>
                            <td class="mono" style="text-align: center; font-weight: 700; color: #059669;">{{ $realizadasMedia }}</td>
                            <td class="mono" style="text-align: center; font-weight: 800;">{{ $tasaMedia }}%</td>
                        </tr>
                    </tbody>
                </table>

                <div class="section-title" style="margin-top: 10px;">
                    <span>Top Cursos con Mayor Atención</span>
                </div>
                <table class="simple-table">
                    <thead>
                        <tr>
                            <th>Curso</th>
                            <th style="width: 45px; text-align: center;">Total</th>
                            <th>Causa Dominante</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topCursos as $tc)
                            <tr>
                                <td style="font-weight: 700;">{{ $tc->curso->nombreAbreviado() }}</td>
                                <td class="mono" style="text-align: center; font-weight: 700;">{{ $tc->total }}</td>
                                <td style="color: #475569;">{{ $tc->causa_dominante }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" style="text-align: center; color: #94a3b8;">Sin registros</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Top Docentes de Mayor Compromiso -->
        <div style="margin-bottom: 12px;">
            <div class="section-title">
                <span>Top Docentes con Mayor Volumen de Entrevistas</span>
                <span style="font-size: 8px; color: #475569;">Liderazgo en vinculación familia-escuela</span>
            </div>
            <table class="simple-table">
                <thead>
                    <tr>
                        <th style="width: 25px;">N°</th>
                        <th>Docente</th>
                        <th style="width: 80px; text-align: center;">Total Citadas</th>
                        <th style="width: 80px; text-align: center;">Realizadas</th>
                        <th style="width: 70px; text-align: center;">Efectividad</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($topDocentes as $i => $td)
                        @php
                            $efect = $td->total > 0 ? round(($td->realizadas / $td->total) * 100) : 0;
                        @endphp
                        <tr>
                            <td class="mono" style="color: #64748b; text-align: center;">{{ $i + 1 }}</td>
                            <td style="font-weight: 600;">{{ $td->user->nombreCompleto() }}</td>
                            <td class="mono" style="text-align: center;">{{ $td->total }}</td>
                            <td class="mono" style="text-align: center; font-weight: 700; color: #059669;">{{ $td->realizadas }}</td>
                            <td class="mono" style="text-align: center; font-weight: 700;">{{ $efect }}%</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; color: #94a3b8;">Sin registros</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Observaciones y Firmas -->
        <div class="footer-signatures">
            <div class="signature-box">
                <div>Dirección / Rectoría</div>
                <div style="font-size: 7px; color: #64748b; margin-top: 1px;">{{ $school?->name }}</div>
            </div>
            <div class="signature-box">
                <div>Gerencia / Sostenedor</div>
                <div style="font-size: 7px; color: #64748b; margin-top: 1px;">Control de Gestión Institucional</div>
            </div>
        </div>

    </div>

</body>
</html>
