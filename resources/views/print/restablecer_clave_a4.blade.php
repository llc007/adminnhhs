<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrega de Credenciales - {{ $nombreCompleto }}</title>
    <style>
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        @page {
            size: letter portrait;
            margin: 15mm;
        }
        html, body {
            margin: 0;
            padding: 0;
            background: #fff;
            color: #000;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
            line-height: 1.4;
        }
        .no-print {
            text-align: center;
            padding: 10px;
            background: #f1f5f9;
            border-bottom: 1px solid #cbd5e1;
            margin-bottom: 15px;
        }
        .no-print button {
            font-size: 14px;
            font-weight: bold;
            padding: 8px 18px;
            margin: 0 5px;
            cursor: pointer;
            border-radius: 6px;
            border: 1px solid #00376e;
            background: #00376e;
            color: #fff;
        }
        .no-print button.btn-secondary {
            background: #e2e8f0;
            color: #1e293b;
            border-color: #cbd5e1;
        }
        .page-container {
            max-width: 750px;
            margin: 0 auto;
            padding: 10px;
        }
        
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .header-table td {
            vertical-align: middle;
        }
        .logo-cell {
            width: 90px;
        }
        .logo-img {
            max-width: 80px;
            max-height: 80px;
            object-fit: contain;
        }
        .header-title {
            font-size: 22px;
            font-weight: 800;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #000;
        }

        .section-block {
            margin-bottom: 25px;
        }

        .intro-text {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .data-table th, .data-table td {
            border: 1.5px solid #000;
            padding: 6px 10px;
            font-size: 13px;
        }
        .data-table td.label-col {
            font-weight: bold;
            width: 140px;
            background-color: #fafafa;
        }
        .data-table td.value-col {
            font-weight: 500;
            font-size: 14px;
            letter-spacing: 0.3px;
        }

        .note-box {
            font-size: 12px;
            text-align: center;
            margin-top: 8px;
            margin-bottom: 8px;
        }
        .note-box strong {
            font-size: 13px;
        }
        .note-box a {
            color: #0056b3;
            text-decoration: underline;
            font-weight: bold;
        }

        .contact-line {
            text-align: right;
            font-size: 12px;
            font-weight: 600;
            margin-top: 5px;
        }

        .divider-logo {
            text-align: left;
            margin: 20px 0 10px 0;
        }

        .qr-section {
            margin-top: 25px;
            text-align: center;
        }
        .qr-title {
            font-size: 15px;
            font-weight: 800;
            text-transform: uppercase;
            margin-bottom: 12px;
        }
        .qr-table {
            width: 100%;
            border-collapse: collapse;
        }
        .qr-table td {
            width: 50%;
            text-align: center;
            vertical-align: top;
        }
        .qr-label {
            font-weight: bold;
            font-size: 13px;
            margin-bottom: 6px;
        }
        .qr-code-img {
            width: 140px;
            height: 140px;
            object-fit: contain;
            border: 1px solid #ddd;
            padding: 4px;
            background: #fff;
        }

        @media print {
            .no-print {
                display: none !important;
            }
            .page-container {
                padding: 0;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">🖨️ Imprimir Hoja (Carta / A4)</button>
        <button onclick="window.close()" class="btn-secondary">Cerrar</button>
    </div>

    <div class="page-container">
        {{-- Header Principal --}}
        <table class="header-table">
            <tr>
                <td class="logo-cell">
                    @if($school && $school->logo)
                        <img src="{{ $school->logo }}" class="logo-img" alt="Logo">
                    @else
                        <div style="width:70px; height:70px; border-radius:50%; border:2px solid #00376e; display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:10px; text-align:center; padding:2px;">
                            {{ $school?->name ?? 'COLEGIO' }}
                        </div>
                    @endif
                </td>
                <td class="header-title">
                    ENTREGA DE CORREO INSTITUCIONAL
                </td>
            </tr>
        </table>

        {{-- Datos de identificación de la persona --}}
        <div style="border: 1px solid #ccc; background-color: #f8fafc; padding: 8px 12px; border-radius: 4px; margin-bottom: 20px;">
            <div style="font-size: 14px; font-weight: bold; text-transform: uppercase;">
                {{ $tipo === 'funcionario' ? 'Funcionario' : 'Estudiante' }}: {{ $nombreCompleto }}
            </div>
            <div style="font-size: 12px; color: #475569; margin-top: 2px;">
                RUT: <strong>{{ $rutCompleto }}</strong> | {{ $tipo === 'funcionario' ? 'Cargo' : 'Curso' }}: <strong>{{ $cursoCargo }}</strong>
                @if($apoderadoNombre)
                    | Apoderado: <strong>{{ $apoderadoNombre }}</strong>
                @endif
            </div>
        </div>

        {{-- Bloque 1: Correo Institucional Google --}}
        @if($incluirGoogle)
            <div class="section-block">
                <div class="intro-text">
                    Se hace entrega de usuario y contraseña {{ $tipo === 'funcionario' ? 'al funcionario:' : 'al estudiante:' }}
                </div>

                <table class="data-table">
                    <tr>
                        <td class="label-col">Correo</td>
                        <td class="value-col">{{ $googleEmail }}</td>
                    </tr>
                    <tr>
                        <td class="label-col">Contraseña</td>
                        <td class="value-col">{{ $googlePassword }}</td>
                    </tr>
                </table>

                <div class="note-box">
                    A través de <a href="https://gmail.com" target="_blank">Gmail.com</a> podrá entrar a los servicios de Google incluidos en la cuenta (Google Classroom, Google Drive entre otros)
                </div>

                <div class="contact-line">
                    Contacto: {{ $contactoEmail }}
                </div>
            </div>
        @endif

        {{-- Bloque 2: Cuenta Fullcollege --}}
        @if($incluirFullcollege)
            <div class="section-block" style="{{ $incluirGoogle ? 'margin-top: 30px; border-top: 1px dashed #bbb; padding-top: 20px;' : '' }}">
                <div class="divider-logo">
                    @if($school && $school->logo)
                        <img src="{{ $school->logo }}" style="max-height: 45px;" alt="Logo Mini">
                    @endif
                </div>

                <div class="intro-text">
                    Se hace entrega de usuario y contraseña para ingresar a <a href="https://www.fullcollege.cl" target="_blank" style="color:#000; text-decoration:underline;">www.fullcollege.cl</a>:
                </div>

                <table class="data-table">
                    <tr>
                        <td class="label-col">USUARIO</td>
                        <td class="value-col">{{ $fullcollegeUser }}</td>
                    </tr>
                    <tr>
                        <td class="label-col">CONTRASEÑA</td>
                        <td class="value-col">{{ $fullcollegePassword }}</td>
                    </tr>
                </table>

                <div class="note-box">
                    A través de <strong>fullcollege.cl</strong> podrá entrar a revisar notas, obtener certificado de alumno regular entre otros
                </div>

                {{-- Sección QR Apps Fullcollege --}}
                <div class="qr-section">
                    <div class="qr-title">APP Fullcollege</div>
                    <table class="qr-table">
                        <tr>
                            <td>
                                <div class="qr-label">iPhone</div>
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=https://apps.apple.com/cl/app/fullcollege/id1451558482" class="qr-code-img" alt="QR iPhone Fullcollege">
                            </td>
                            <td>
                                <div class="qr-label">ANDROID</div>
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=https://play.google.com/store/apps/details?id=cl.fullcollege.app" class="qr-code-img" alt="QR Android Fullcollege">
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        @endif
    </div>
</body>
</html>
