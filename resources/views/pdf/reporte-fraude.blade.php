<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Informe de Inspección por Fraude - {{ $orden->numero_orden }}</title>
    <style>
        @page {
            size: A4;
            margin: 190px 50px 110px 50px;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 9pt;
            line-height: 1.2;
            color: #333;
            margin: 0;
            padding: 0;
        }

        /* ── Header fijo en todas las páginas ── */
        .header {
            position: fixed;
            top: -175px;
            left: 0;
            right: 0;
            height: 165px;
            background: white;
            padding: 12px 50px 8px 50px;
            z-index: 1000;
        }

        .header-inner {
            width: 100%;
            border-collapse: collapse;
        }

        .header-td-center {
            text-align: left;
            vertical-align: middle;
        }

        .header-td-proderi {
            width: 140px;
            text-align: right;
            vertical-align: middle;
        }

        .logo-proderi {
            width: 130px;
            height: auto;
        }

        .header h1 {
            color: #003E72;
            font-size: 13pt;
            margin: 0 0 3px 0;
            font-weight: bold;
        }

        .header h2 {
            color: #64748b;
            font-size: 9pt;
            margin: 0 0 4px 0;
            font-weight: normal;
        }

        .header-meta {
            font-size: 7.5pt;
            color: #64748b;
            margin-top: 3px;
        }

        /* ── Línea separadora del header ── */
        .header-separator {
            position: fixed;
            top: -12px;
            left: 50px;
            right: 50px;
            height: 0;
            border-top: 2px solid #003E72;
            z-index: 999;
        }

        /* ── Footer fijo en todas las páginas ── */
        .footer {
            position: fixed;
            bottom: -95px;
            left: 0;
            right: 0;
            height: 75px;
            background: white;
            border-top: 2px solid #003E72;
            padding: 10px 50px;
            font-size: 7pt;
            color: #64748b;
            text-align: center;
        }

        .footer-line {
            font-weight: bold;
            margin-bottom: 2px;
        }

        /* ── Contenido principal ── */
        .content {
            margin-top: 10px;
        }

        .section {
            margin: 10px 0;
            page-break-inside: avoid;
        }

        .section-title {
            color: #003E72;
            font-size: 10pt;
            font-weight: bold;
            margin: 12px 0 5px 0;
            padding-bottom: 3px;
            border-bottom: 1px solid #e5e7eb;
            page-break-after: avoid;
        }

        /* ── Info box (fondo gris con borde) ── */
        .info-box {
            border: 1px solid #003E72;
            border-radius: 6px;
            padding: 8px 10px;
            margin: 5px 0;
            background-color: #f8f9fa;
            page-break-inside: avoid;
        }

        .info-grid {
            display: table;
            width: 100%;
        }

        .info-row {
            display: table-row;
        }

        .info-label {
            display: table-cell;
            font-weight: bold;
            padding: 3px 12px 3px 0;
            width: 160px;
            vertical-align: top;
            font-size: 8pt;
            color: #003E72;
        }

        .info-value {
            display: table-cell;
            padding: 3px 0;
            vertical-align: top;
            font-size: 8pt;
        }

        /* ── Tabla de dos columnas para datos técnicos ── */
        .tabla-tecnica {
            width: 100%;
            border-collapse: collapse;
        }

        .tabla-tecnica td {
            border: 1px solid #ccc;
            padding: 5px 8px;
            font-size: 8pt;
            vertical-align: middle;
        }

        .tabla-tecnica .lbl {
            background-color: #e8edf5;
            font-weight: bold;
            color: #003E72;
            width: 22%;
            white-space: nowrap;
        }

        .tabla-tecnica .val {
            width: 28%;
            color: #222;
        }

        .tabla-tecnica .lbl-full {
            background-color: #e8edf5;
            font-weight: bold;
            color: #003E72;
            width: 22%;
            white-space: nowrap;
        }

        .tabla-tecnica .val-full {
            color: #222;
        }

        /* ── Badges de incidencias ── */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 7pt;
            font-weight: bold;
        }

        .badge-red   { background-color: #ef4444; color: white; }
        .badge-orange { background-color: #f97316; color: white; }
        .badge-green  { background-color: #10b981; color: white; }

        /* ── Observaciones / texto libre ── */
        .content-box {
            border: 1px solid #003E72;
            border-radius: 6px;
            background-color: #f8f9fa;
            padding: 8px 10px;
            margin: 5px 0;
            white-space: pre-wrap;
            word-wrap: break-word;
            font-size: 8pt;
            line-height: 1.4;
        }

        /* ── Evidencias fotográficas ── */
        .evidencias-tabla {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        .evidencia-td {
            width: 50%;
            padding: 5px;
            vertical-align: top;
            text-align: center;
        }

        .evidencia-img {
            max-width: 100%;
            max-height: 190px;
            display: block;
            margin: 0 auto;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
        }

        .evidencia-num {
            font-size: 7pt;
            color: #64748b;
            margin-top: 3px;
        }

        /* ── Firma digital ── */
        .firma-section {
            margin-top: 20px;
            page-break-inside: avoid;
        }

        .firma-box {
            border: 2px solid #003E72;
            border-radius: 8px;
            padding: 14px 18px;
            margin: 6px 0;
            background-color: #f0f4f9;
        }

        .firma-badge {
            display: inline-block;
            background-color: #10b981;
            color: white;
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 8pt;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .firma-linea {
            border-top: 1.5px solid #003E72;
            margin: 14px 0 8px 0;
            width: 220px;
        }

        .firma-nombre {
            font-weight: bold;
            font-size: 10pt;
            color: #003E72;
            margin-bottom: 2px;
        }

        .firma-meta {
            font-size: 7.5pt;
            color: #64748b;
            margin: 2px 0;
        }

        .firma-meta strong {
            color: #333;
        }
    </style>
</head>
<body>

    {{-- ── Header fijo ── --}}
    <div class="header">
        <table class="header-inner">
            <tr>
                <td class="header-td-center">
                    <h1>INFORME DE INSPECCIÓN TÉCNICA</h1>
                    <h2>Control y Detección de Fraude en Medición de Gas</h2>
                    <div class="header-meta">
                        Orden: <strong>{{ $orden->numero_orden }}</strong>
                        &nbsp;&nbsp;|&nbsp;&nbsp;
                        Contrato: <strong>{{ $orden->contrato ?? '—' }}</strong>
                        &nbsp;&nbsp;|&nbsp;&nbsp;
                        Fecha de generación: <strong>{{ $fechaGeneracion }}</strong>
                    </div>
                </td>
                <td class="header-td-proderi">
                    <img src="{{ $logoProderi }}" class="logo-proderi" alt="PRODERI">
                </td>
            </tr>
        </table>
    </div>

    {{-- ── Línea separadora del header ── --}}
    <div class="header-separator"></div>

    {{-- ── Footer fijo ── --}}
    <div class="footer">
        <div class="footer-line">Informe de Inspección Técnica por Fraude — Uso Interno y Confidencial</div>
        <div>© {{ date('Y') }} Alena / PRODERI S.A.S. — Todos los derechos reservados</div>
        <div>Este documento contiene información confidencial protegida por las leyes de propiedad intelectual</div>
    </div>

    {{-- ── Numeración de páginas ── --}}
    <script type="text/php">
        if (isset($pdf)) {
            $text = "Página {PAGE_NUM} de {PAGE_COUNT}";
            $size = 7;
            $font = $fontMetrics->getFont("DejaVu Sans");
            $width = $fontMetrics->get_text_width($text, $font, $size);
            $pageWidth = $pdf->get_width();
            $x = ($pageWidth - $width) / 2;
            $y = $pdf->get_height() - 90;
            $pdf->page_text($x, $y, $text, $font, $size, array(0.4, 0.45, 0.53));
        }
    </script>

    {{-- ══════════════════════════════════
         CONTENIDO PRINCIPAL
    ══════════════════════════════════ --}}
    <div class="content">

        {{-- ── Introducción ── --}}
        <p style="font-size:8.5pt; line-height:1.5; text-align:justify; margin-bottom:10px;">
            Este documento constituye el informe individual de inspección y revisión para el Contrato
            N° <strong>{{ $orden->contrato ?? $orden->numero_orden }}</strong>, con el propósito de registrar los
            datos técnicos, las lecturas del medidor y la evidencia fotográfica. La información aquí
            presentada es fundamental para la evaluación de riesgos y la gestión operativa asociada.
        </p>

        {{-- ── 1. Información de la Orden ── --}}
        <div class="section">
            <div class="section-title">1. INFORMACIÓN DE LA ORDEN</div>
            <div class="info-box">
                <div class="info-grid">
                    <div class="info-row">
                        <div class="info-label">Número de Orden:</div>
                        <div class="info-value">{{ $orden->numero_orden }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Contrato:</div>
                        <div class="info-value">{{ $orden->contrato ?? '—' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Cliente / Referencia:</div>
                        <div class="info-value">{{ $orden->cliente ?? '—' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Dirección:</div>
                        <div class="info-value">{{ $orden->direccion }}{{ $orden->barrio ? ', '.$orden->barrio : '' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Fecha de Inspección:</div>
                        <div class="info-value">{{ $reporte->created_at->format('d/m/Y') }}</div>
                    </div>
                    @if ($orden->numero_noticia_criminal)
                    <div class="info-row">
                        <div class="info-label">N° Noticia Criminal:</div>
                        <div class="info-value">{{ $orden->numero_noticia_criminal }}</div>
                    </div>
                    @endif
                    @php $tecnico = $orden->personal; @endphp
                    @if ($tecnico)
                    <div class="info-row">
                        <div class="info-label">Técnico Responsable:</div>
                        <div class="info-value">{{ $tecnico->nombres }} {{ $tecnico->apellidos }}</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ── 2. Comercio Inspeccionado ── --}}
        @if ($reporte->comercio)
        <div class="section">
            <div class="section-title">2. COMERCIO INSPECCIONADO</div>
            <div class="info-box">
                <div class="info-grid">
                    <div class="info-row">
                        <div class="info-label">Nombre del Comercio:</div>
                        <div class="info-value">{{ $reporte->comercio->nombre_comercio ?? '—' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Tipo de Comercio:</div>
                        <div class="info-value">{{ $reporte->comercio->tipoComercio?->nombre ?? '—' }}</div>
                    </div>
                    @if ($reporte->comercio->medidor_anomalia)
                    <div class="info-row">
                        <div class="info-label">Medidor Anomalía:</div>
                        <div class="info-value">{{ $reporte->comercio->medidor_anomalia }}</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endif

        {{-- ── 3. Datos Técnicos del Medidor ── --}}
        <div class="section">
            <div class="section-title">3. DATOS TÉCNICOS DEL MEDIDOR</div>
            <table class="tabla-tecnica">
                <tr>
                    <td class="lbl">Medidor</td>
                    <td class="val">{{ $reporte->numero_medidor ?? '—' }}</td>
                    <td class="lbl">Lectura N°</td>
                    <td class="val">{{ $reporte->lectura ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="lbl">Marca del Regulador</td>
                    <td class="val">{{ $reporte->marcaRegulador?->nombre ?? '—' }}</td>
                    <td class="lbl">Marca del Medidor</td>
                    <td class="val">{{ $reporte->marcaMedidor?->nombre ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="lbl">Tipo de Presión</td>
                    <td class="val">{{ $reporte->tipoRegulador?->nombre ?? '—' }}</td>
                    <td class="lbl">Imposibilidad</td>
                    <td class="val">{{ $reporte->tipoImposibilidad?->nombre ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="lbl">Descripción del Medidor</td>
                    <td class="val">{{ $reporte->marcaRegulador?->nombre ?? '—' }}</td>
                    <td class="lbl">Alertas CAU</td>
                    <td class="val">{{ $reporte->tipoNotificacionAlerta?->nombre ?? 'Sin Alertas' }}</td>
                </tr>
            </table>
        </div>

        {{-- ── 4. Anomalías e Incidencias ── --}}
        <div class="section">
            <div class="section-title">4. ANOMALÍAS E INCIDENCIAS DETECTADAS</div>
            <div class="info-box">
                <div class="info-grid">
                    <div class="info-row">
                        <div class="info-label">Anomalías:</div>
                        <div class="info-value">{{ $anomalias ?: 'Ninguna' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Fuga de Gas:</div>
                        <div class="info-value">
                            @if ($reporte->hay_fuga_gas)
                                <span class="badge badge-red">SÍ — Fuga Detectada</span>
                            @else
                                <span class="badge badge-green">No</span>
                            @endif
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Excede Capacidad:</div>
                        <div class="info-value">
                            @if ($reporte->excede_capacidad)
                                <span class="badge badge-orange">SÍ — Excede Capacidad</span>
                            @else
                                <span class="badge badge-green">No</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── 5. Observaciones ── --}}
        <div class="section">
            <div class="section-title">5. OBSERVACIONES</div>
            <div class="content-box">{{ $reporte->observaciones ?: 'Sin observaciones registradas.' }}</div>
        </div>

        {{-- ── 6. Evidencias Fotográficas ── --}}
        <div class="section">
            <div class="section-title">6. EVIDENCIAS FOTOGRÁFICAS</div>
            @if (count($imagenes) > 0)
                <p style="font-size:7.5pt; color:#64748b; margin:4px 0 8px 0;">
                    Se registraron <strong>{{ count($imagenes) }}</strong> evidencia(s) fotográfica(s) durante la inspección del Contrato N° <strong>{{ $orden->contrato ?? $orden->numero_orden }}</strong>.
                </p>
                @php $rows = array_chunk($imagenes, 2); @endphp
                <table class="evidencias-tabla">
                    @foreach ($rows as $rowIndex => $row)
                        <tr>
                            @foreach ($row as $colIndex => $imgSrc)
                                <td class="evidencia-td">
                                    <img src="{{ $imgSrc }}" class="evidencia-img" alt="Evidencia">
                                    <div class="evidencia-num">Evidencia {{ $rowIndex * 2 + $colIndex + 1 }}</div>
                                </td>
                            @endforeach
                            @if (count($row) === 1)
                                <td class="evidencia-td"></td>
                            @endif
                        </tr>
                    @endforeach
                </table>
            @else
                <div class="content-box" style="color:#64748b; font-style:italic;">
                    No se registraron evidencias fotográficas para esta inspección.
                </div>
            @endif
        </div>

        {{-- ── 7. Firma Digital del Coordinador ── --}}
        <div class="firma-section">
            <div class="section-title">7. FIRMA DIGITAL DEL COORDINADOR</div>
            <p style="font-size:8pt; margin:5px 0 10px 0;">
                El coordinador identificado a continuación ha generado y validado este informe mediante
                firma digital, quedando registrado el acceso con los datos técnicos del dispositivo utilizado.
            </p>
            <div class="firma-box">
                <div class="firma-badge">&#10003; FIRMADO DIGITALMENTE</div>

                <div class="firma-linea"></div>

                <div class="firma-nombre">{{ $firma['nombre'] }}</div>
                @if ($firma['cargo'])
                <div class="firma-meta"><strong>Cargo:</strong> {{ $firma['cargo'] }}</div>
                @endif
                <div class="firma-meta"><strong>Correo electrónico:</strong> {{ $firma['email'] }}</div>

                <div style="margin-top:8px;">
                    <div class="firma-meta"><strong>Fecha y hora de firma:</strong> {{ $firma['fecha'] }}</div>
                    <div class="firma-meta"><strong>IP del firmante:</strong> {{ $firma['ip'] }}</div>
                    <div class="firma-meta"><strong>Dispositivo / Navegador:</strong> {{ $firma['agente'] }}</div>
                </div>
            </div>
        </div>

    </div>{{-- Fin del contenido --}}
</body>
</html>
