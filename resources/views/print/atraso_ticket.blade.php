<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pase de Ingreso - {{ $atraso->estudiante?->nombreCompleto() ?? 'Estudiante' }}</title>
    <style>
        @page {
            margin: 0;
            size: 80mm auto;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, monospace;
            width: 72mm;
            margin: 0 auto;
            padding: 8px 4px;
            color: #000;
            background: #fff;
            font-size: 12px;
            line-height: 1.3;
        }
        .header {
            text-align: center;
            border-bottom: 1px dashed #000;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }
        .school-name {
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .title {
            font-size: 15px;
            font-weight: 900;
            margin: 4px 0;
            letter-spacing: 0.5px;
        }
        .subtitle {
            font-size: 10px;
            text-transform: uppercase;
        }
        .section {
            margin-bottom: 8px;
        }
        .row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }
        .label {
            font-weight: 600;
            color: #333;
        }
        .value {
            font-weight: 800;
            text-align: right;
        }
        .student-name {
            font-size: 13px;
            font-weight: 900;
            text-align: center;
            margin: 6px 0;
            text-transform: uppercase;
        }
        .alert-box {
            border: 1px solid #000;
            padding: 4px;
            text-align: center;
            font-weight: 800;
            font-size: 11px;
            margin: 8px 0;
        }
        .footer {
            border-top: 1px dashed #000;
            padding-top: 8px;
            margin-top: 12px;
            text-align: center;
            font-size: 10px;
        }
        .signature-line {
            margin-top: 28px;
            border-top: 1px solid #000;
            display: inline-block;
            width: 80%;
            padding-top: 2px;
            font-size: 10px;
        }
        @media print {
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: center; margin-bottom: 12px; background: #f0f0f0; padding: 6px; border-radius: 4px;">
        <button onclick="window.print()" style="font-weight: bold; padding: 4px 12px; cursor: pointer;">🖨️ Imprimir Ticket</button>
        <button onclick="window.close()" style="padding: 4px 8px; cursor: pointer;">Cerrar</button>
    </div>

    <div class="header">
        <div class="school-name">{{ $atraso->school?->name ?? 'Colegio' }}</div>
        <div class="title">PASE DE INGRESO</div>
        <div class="subtitle">Control de Inspectoría</div>
    </div>

    <div class="student-name">
        {{ $atraso->estudiante?->nombreCompleto() }}
    </div>

    <div class="section">
        <div class="row">
            <span class="label">Curso:</span>
            <span class="value">{{ $atraso->curso?->nombreCompleto() ?? 'N/A' }}</span>
        </div>
        <div class="row">
            <span class="label">RUT:</span>
            <span class="value">{{ $atraso->estudiante?->rutCompleto() ?? 'N/A' }}</span>
        </div>
        <div class="row">
            <span class="label">Fecha:</span>
            <span class="value">{{ \Carbon\Carbon::parse($atraso->fecha)->format('d/m/Y') }}</span>
        </div>
        <div class="row">
            <span class="label">Hora de Llegada:</span>
            <span class="value">{{ \Carbon\Carbon::parse($atraso->hora)->format('H:i') }} hrs</span>
        </div>
        @if($atraso->minutos_atraso > 0)
        <div class="row">
            <span class="label">Minutos Atraso:</span>
            <span class="value">+{{ $atraso->minutos_atraso }} min</span>
        </div>
        @endif
        <div class="row">
            <span class="label">Condición:</span>
            <span class="value">{{ ucfirst($atraso->estado) }} {{ $atraso->motivo ? "({$atraso->motivo})" : '' }}</span>
        </div>
    </div>

    <div class="alert-box">
        Atraso N° {{ $totalMes }} en el mes en curso
        @if($totalMes >= 3)
            <br>⚠️ CITACIÓN DE APODERADO
        @endif
    </div>

    <div class="footer">
        <div>Válido para presentarse de inmediato con el docente de aula.</div>
        <div class="signature-line">
            Firma Inspectoría / Portería
            @if($atraso->registradoPor)
                <br><small>({{ $atraso->registradoPor->name ?? $atraso->registradoPor->nombres }})</small>
            @endif
        </div>
    </div>

    <script>
        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
            }, 300);
        });
    </script>
</body>
</html>