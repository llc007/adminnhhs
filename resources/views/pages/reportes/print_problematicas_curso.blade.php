<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte_Problematicas_por_Curso_{{ date('Y-m-d') }}</title>
    
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

        /* Contenedor de la hoja A4 Vertical */
        .sheet-container {
            max-width: 820px;
            margin: 0 auto;
            background: #ffffff;
            padding: 18px 20px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.08);
        }

        /* Encabezado oficial */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }

        .school-name {
            font-size: 14px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .doc-title {
            font-size: 11.5px;
            font-weight: 700;
            color: #1e40af;
            margin-top: 1px;
            text-transform: uppercase;
        }

        .meta-box {
            text-align: right;
            font-size: 8.5px;
            color: #64748b;
            line-height: 1.3;
        }

        .meta-box strong {
            color: #0f172a;
        }

        /* Cuadro de resumen ejecutivo */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-bottom: 10px;
        }

        .summary-card {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 3px;
            padding: 6px 10px;
            text-align: center;
        }

        .summary-card.highlight {
            background-color: #eff6ff;
            border-color: #93c5fd;
        }

        .summary-title {
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.3px;
        }

        .summary-value {
            font-size: 13px;
            font-weight: 800;
            color: #0f172a;
            margin-top: 1px;
        }

        /* Tabla estilo Hoja de Cálculo (Spreadsheet) */
        table.spreadsheet {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
            margin-bottom: 10px;
        }

        table.spreadsheet th,
        table.spreadsheet td {
            border: 1px solid #94a3b8;
            padding: 3.5px 4.5px;
            vertical-align: middle;
        }

        table.spreadsheet th {
            background-color: #0f172a;
            color: #ffffff;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 7.5px;
            letter-spacing: 0.3px;
            text-align: center;
        }

        table.spreadsheet th.text-left {
            text-align: left;
        }

        table.spreadsheet tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        table.spreadsheet tbody tr:hover {
            background-color: #f1f5f9;
        }

        table.spreadsheet td.center {
            text-align: center;
        }

        table.spreadsheet td.right {
            text-align: right;
        }

        /* Intensidad / Heatmap sutil */
        .heat-0 {
            color: #94a3b8;
        }
        .heat-low {
            background-color: #f8fafc;
            font-weight: 600;
            color: #334155;
        }
        .heat-med {
            background-color: #fef3c7;
            font-weight: 700;
            color: #92400e;
        }
        .heat-high {
            background-color: #fee2e2;
            font-weight: 800;
            color: #991b1b;
        }

        /* Badge causa dominante */
        .badge-cause {
            display: inline-block;
            font-size: 7.5px;
            font-weight: 700;
            padding: 1px 4px;
            border-radius: 2px;
            background-color: #e0e7ff;
            color: #3730a3;
            border: 1px solid #c7d2fe;
            white-space: nowrap;
        }

        /* Fila de totales */
        table.spreadsheet tfoot tr {
            background-color: #e2e8f0;
            font-weight: 800;
            color: #0f172a;
        }

        table.spreadsheet tfoot td {
            border-top: 2px solid #0f172a;
            border-bottom: 2px solid #0f172a;
        }

        /* Pie de página oficial y firmas */
        .footer-signatures {
            margin-top: 20px;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
            padding: 0 30px;
            page-break-inside: avoid;
        }

        .signature-box {
            text-align: center;
            border-top: 1px solid #0f172a;
            padding-top: 4px;
            font-size: 8.5px;
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

            table.spreadsheet {
                page-break-inside: auto;
            }

            table.spreadsheet tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }

            table.spreadsheet thead {
                display: table-header-group;
            }

            table.spreadsheet tfoot {
                display: table-footer-group;
            }
        }
    </style>
</head>
<body>

    <!-- Barra flotante de acciones -->
    <div class="no-print-bar">
        <button class="btn-action btn-print" onclick="window.print()">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
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
                <div class="school-name">{{ $school?->name ?? 'Establecimiento Educacional' }}</div>
                <div class="doc-title">Matriz de Diagnóstico: Problemáticas por Curso y Motivo</div>
                <div style="font-size: 8.5px; color: #475569; margin-top: 2px;">
                    <strong>Período:</strong> {{ $periodoLabel }}
                    @if($modalidad !== 'todas') • <strong>Modalidad:</strong> {{ ucfirst($modalidad) }} @endif
                    @if($search) • <strong>Búsqueda:</strong> "{{ $search }}" @endif
                </div>
            </div>
            <div class="meta-box">
                <div><strong>Emisión:</strong> {{ now('America/Santiago')->format('d/m/Y H:i') }} hrs</div>
                <div><strong>Emitido por:</strong> {{ $user->nombreCompleto() }}</div>
                <div><strong>Cursos Analizados:</strong> {{ count($matriz) }} cursos</div>
            </div>
        </header>

        <!-- Resumen Ejecutivo Superior -->
        <div class="summary-grid">
            <div class="summary-card highlight">
                <div class="summary-title">Total Entrevistas Período</div>
                <div class="summary-value mono">{{ $granTotal }} citas</div>
            </div>
            <div class="summary-card" style="background-color: #fff7ed; border-color: #fed7aa;">
                <div class="summary-title" style="color: #9a3412;">Problemática Dominante Colegio</div>
                <div class="summary-value" style="color: #c2410c; font-size: 11px;">
                    {{ $topCatGlobal }} ({{ $topCatGlobalPct }}%)
                </div>
            </div>
            <div class="summary-card" style="background-color: #f0fdf4; border-color: #bbf7d0;">
                <div class="summary-title" style="color: #166534;">Curso con Más Citaciones</div>
                <div class="summary-value" style="color: #15803d; font-size: 11px;">
                    @if($topCurso && $topCurso->total > 0)
                        {{ $topCurso->curso->nombreCompleto() }} ({{ $topCurso->total }} citas)
                    @else
                        Sin citaciones
                    @endif
                </div>
            </div>
        </div>

        <!-- Matriz Estilo Hoja de Cálculo -->
        <table class="spreadsheet">
            <thead>
                <tr>
                    <th style="width: 20px;">N°</th>
                    <th class="text-left" style="width: 100px;">Curso</th>
                    <th class="text-left" style="width: 130px;">Profesor(a) Jefe</th>
                    @foreach ($categorias as $cat)
                        <th style="width: 55px;" title="{{ $cat->nombre }}">
                            @php
                                $shortName = match($cat->nombre) {
                                    'Rendimiento Académico' => 'Rendimiento',
                                    'Conducta y Convivencia' => 'Conducta',
                                    'Asistencia y Puntualidad' => 'Asistencia',
                                    'Asunto Personal / Familiar' => 'Familiar',
                                    'Evaluación Psicopedagógica' => 'Psicoped.',
                                    default => mb_substr($cat->nombre, 0, 9),
                                };
                            @endphp
                            {{ $shortName }}
                        </th>
                    @endforeach
                    <th style="width: 45px;">Otro</th>
                    <th style="width: 50px;">Total</th>
                    <th style="width: 130px;">Causa Dominante</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($matriz as $index => $fila)
                    <tr>
                        <td class="center mono" style="color: #64748b;">{{ $index + 1 }}</td>
                        <td style="font-weight: 700; color: #0f172a;" title="{{ $fila->curso->nombreCompleto() }}">
                            {{ $fila->curso->nombreAbreviado() }}
                        </td>
                        <td style="color: #334155;">
                            {{ $fila->curso->jefe ? $fila->curso->jefe->nombreCompleto() : 'Sin asignar' }}
                        </td>
                        @foreach ($categorias as $cat)
                            @php
                                $val = $fila->conteos[$cat->nombre] ?? 0;
                                $heatClass = $val === 0 ? 'heat-0' : ($val <= 2 ? 'heat-low' : ($val <= 5 ? 'heat-med' : 'heat-high'));
                            @endphp
                            <td class="center mono {{ $heatClass }}">
                                {{ $val > 0 ? $val : '-' }}
                            </td>
                        @endforeach
                        @php
                            $valOtro = $fila->conteos['Otro'] ?? 0;
                            $heatOtro = $valOtro === 0 ? 'heat-0' : ($valOtro <= 2 ? 'heat-low' : ($valOtro <= 5 ? 'heat-med' : 'heat-high'));
                        @endphp
                        <td class="center mono {{ $heatOtro }}">
                            {{ $valOtro > 0 ? $valOtro : '-' }}
                        </td>
                        <td class="center mono" style="font-weight: 800; font-size: 9px; color: #0f172a;">
                            {{ $fila->total }}
                        </td>
                        <td class="center">
                            @if ($fila->total > 0)
                                <span class="badge-cause">
                                    {{ $fila->causa_dominante }} ({{ $fila->porcentaje_dominante }}%)
                                </span>
                            @else
                                <span style="color: #94a3b8; font-size: 7.5px;">Sin citas</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($categorias) + 6 }}" class="center" style="padding: 15px; color: #64748b;">
                            No se encontraron cursos para los filtros seleccionados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" style="text-align: right; padding-right: 8px; text-transform: uppercase;">
                        TOTALES CONSOLIDADOS ({{ count($matriz) }} CURSOS):
                    </td>
                    @foreach ($categorias as $cat)
                        <td class="center mono">{{ $totalesPorCategoria[$cat->nombre] ?? 0 }}</td>
                    @endforeach
                    <td class="center mono">{{ $totalesPorCategoria['Otro'] ?? 0 }}</td>
                    <td class="center mono">{{ $granTotal }}</td>
                    <td class="center mono" style="font-size: 8px;">
                        Top: {{ $topCatGlobal }} ({{ $topCatGlobalPct }}%)
                    </td>
                </tr>
            </tfoot>
        </table>

        <!-- Firmas Institucionales al pie de página -->
        <footer class="footer-signatures">
            <div class="signature-box">
                Firma y Timbre Dirección / Inspectoría
            </div>
            <div class="signature-box">
                Firma y Timbre Unidad Técnico Pedagógica (UTP)
            </div>
        </footer>
    </div>

</body>
</html>
