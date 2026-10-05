<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Credenciales - {{ $nombreCompleto }}</title>
    <style>
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        @page {
            margin: 0;
            size: 72mm auto;
        }
        html, body {
            margin: 0;
            padding: 0;
            background: #fff;
            color: #000;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, monospace;
            width: 72mm;
            padding: 4px 6px;
            font-size: 11px;
            line-height: 1.25;
            page-break-inside: avoid;
            break-inside: avoid;
            padding-bottom: 5mm;
        }
        .no-print {
            text-align: center;
            margin-bottom: 8px;
            background: #f0f0f0;
            padding: 6px;
            border-radius: 4px;
        }
        .no-print button {
            font-weight: bold;
            padding: 4px 10px;
            cursor: pointer;
            font-size: 11px;
        }
        .header {
            text-align: center;
            border-bottom: 1px dashed #000;
            padding-bottom: 6px;
            margin-bottom: 6px;
        }
        .school-name {
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .title {
            font-size: 14px;
            font-weight: 900;
            margin: 3px 0;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .user-info {
            background: #f4f4f5;
            border: 1px solid #000;
            padding: 4px;
            margin-bottom: 8px;
            text-align: center;
        }
        .user-name {
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
        }
        .user-meta {
            font-size: 10px;
            font-weight: 600;
            color: #333;
        }

        .section-title {
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
            border-bottom: 1px solid #000;
            padding-bottom: 2px;
            margin-top: 8px;
            margin-bottom: 4px;
        }
        .data-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
            font-size: 11px;
        }
        .data-label {
            font-weight: 700;
        }
        .data-value {
            font-weight: 500;
            text-align: right;
            word-break: break-all;
        }

        .data-box {
            border: 1px solid #000;
            padding: 4px;
            margin-bottom: 6px;
        }

        .instruction-note {
            font-size: 9.5px;
            text-align: center;
            margin-top: 3px;
            margin-bottom: 6px;
            line-height: 1.2;
        }

        .qr-container {
            margin-top: 8px;
            border-top: 1px dashed #000;
            padding-top: 6px;
            text-align: center;
        }
        .qr-header {
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .qr-flex {
            display: flex;
            justify-content: space-around;
            align-items: center;
            margin-top: 4px;
        }
        .qr-item {
            text-align: center;
            font-size: 9px;
            font-weight: 800;
        }
        .qr-img {
            width: 85px;
            height: 85px;
            border: 1px solid #000;
            padding: 2px;
        }

        .footer {
            border-top: 1px dashed #000;
            padding-top: 6px;
            margin-top: 10px;
            text-align: center;
            font-size: 9px;
        }

        .feed-space {
            height: 5mm;
        }

        @media print {
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">🧾 Imprimir Ticket POS80</button>
        <button onclick="window.close()">Cerrar</button>
    </div>

    <div class="header">
        <div class="school-name">{{ $school?->name ?? 'COLEGIO' }}</div>
        <div class="title">ENTREGA DE CREDENCIALES</div>
        <div style="font-size: 9px; text-transform: uppercase;">Control de Accesos</div>
    </div>

    <div class="user-info">
        <div class="user-name">{{ $nombreCompleto }}</div>
        <div class="user-meta">
            RUT: {{ $rutCompleto }} | {{ $cursoCargo }}
        </div>
        @if($apoderadoNombre)
            <div class="user-meta" style="font-size: 9px;">Apoderado: {{ $apoderadoNombre }}</div>
        @endif
    </div>

    {{-- Bloque Correo Google --}}
    @if($incluirGoogle)
        <div class="section-title">📧 Correo Institucional (Google)</div>
        <div class="data-box">
            <div class="data-row">
                <span class="data-label">Correo:</span>
            </div>
            <div class="data-value" style="text-align: left; font-size: 11px; margin-bottom: 4px; font-weight: 500;">
                {{ $googleEmail }}
            </div>
            <div class="data-row">
                <span class="data-label">Contraseña:</span>
                <span class="data-value" style="font-weight: 500;">{{ $googlePassword }}</span>
            </div>
        </div>
        <div class="instruction-note">
            Acceso en <strong>Gmail.com</strong> (Google Classroom, Drive, Meet).
        </div>
    @endif

    {{-- Bloque Fullcollege --}}
    @if($incluirFullcollege)
        <div class="section-title">🌐 Cuenta Fullcollege</div>
        <div class="data-box">
            <div class="data-row">
                <span class="data-label">USUARIO:</span>
                <span class="data-value" style="font-weight: 500;">{{ $fullcollegeUser }}</span>
            </div>
            <div class="data-row">
                <span class="data-label">CONTRASEÑA:</span>
                <span class="data-value" style="font-weight: 500;">{{ $fullcollegePassword }}</span>
            </div>
        </div>
        <div class="instruction-note">
            Acceso en <strong>www.fullcollege.cl</strong> para notas, asistencia y certificados.
        </div>

        <div class="qr-container">
            <div class="qr-header">APP Fullcollege</div>
            <div class="qr-flex">
                <div class="qr-item">
                    <div>iPhone</div>
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=https://apps.apple.com/cl/app/fullcollege/id1451558482" class="qr-img" alt="QR iOS">
                </div>
                <div class="qr-item">
                    <div>ANDROID</div>
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=https://play.google.com/store/apps/details?id=cl.fullcollege.app" class="qr-img" alt="QR Android">
                </div>
            </div>
        </div>
    @endif

    <div class="footer">
        <div>Fecha: {{ now('America/Santiago')->format('d/m/Y H:i') }}</div>
        <div>Contacto: {{ $contactoEmail }}</div>
    </div>

    {{-- Espacio extra de avance de papel para el cortador automático --}}
    <div class="feed-space"></div>
</body>
</html>
