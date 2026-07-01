<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Informe de Vigilancia - {{ $orden->numero_orden }}</title>
    <style>
        @page {
            size: A4;
            margin: 155px 50px 110px 50px;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 9pt;
            line-height: 1.2;
            color: #333;
            margin: 0;
            padding: 0;
        }

        .header {
            position: fixed;
            top: -143px;
            left: 0;
            right: 0;
            height: 133px;
            background: white;
            padding: 10px 50px 6px 50px;
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

        .header-separator {
            position: fixed;
            top: -10px;
            left: 50px;
            right: 50px;
            height: 0;
            border-top: 2px solid #003E72;
            z-index: 999;
        }

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

        .section-title-turno {
            color: #003E72;
            font-size: 10pt;
            font-weight: bold;
            margin: 16px 0 5px 0;
            padding: 6px 10px;
            background-color: #e8edf5;
            border-left: 4px solid #003E72;
            page-break-after: avoid;
        }

        .section-subtitle {
            color: #003E72;
            font-size: 8.5pt;
            font-weight: bold;
            margin: 8px 0 4px 0;
            padding-bottom: 2px;
            border-bottom: 1px solid #e5e7eb;
            page-break-after: avoid;
        }

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

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 7pt;
            font-weight: bold;
        }

        .badge-red {
            background-color: #ef4444;
            color: white;
        }

        .badge-orange {
            background-color: #f97316;
            color: white;
        }

        .badge-green {
            background-color: #10b981;
            color: white;
        }

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

        .objetivo-grupo {
            margin-bottom: 8px;
        }

        .objetivo-grupo:last-child {
            margin-bottom: 0;
        }

        .objetivo-item {
            font-size: 8pt;
            line-height: 1.45;
            text-align: justify;
            color: #333;
            margin-bottom: 5px;
            padding-left: 10px;
        }

        .objetivos-box {
            border: 1px solid #003E72;
            border-radius: 6px;
            padding: 10px 14px;
            margin: 8px 0 14px 0;
            background-color: #f8f9fa;
        }

        .generador-info {
            margin-top: 20px;
            padding-top: 8px;
            border-top: 1px solid #e5e7eb;
            font-size: 7.5pt;
            color: #64748b;
        }
    </style>
</head>

<body>

    {{-- ── Header fijo ── --}}
    <div class="header">
        <table class="header-inner">
            <tr>
                <td class="header-td-center">
                    <h1>Informe de Vigilancia</h1>
                    <h2>Control y Seguimiento de Dispositivos de Vigilancia</h2>
                    <div class="header-meta">
                        Orden: <strong>{{ $orden->numero_orden }}</strong>
                        @if (!empty($datosOrden['proceso']))
                            &nbsp;&nbsp;|&nbsp;&nbsp;
                            Proceso: <strong>{{ $datosOrden['proceso'] }}</strong>
                        @endif
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
        <div class="footer-line">Informe de Vigilancia — Uso Interno y Confidencial</div>
        <div>© {{ date('Y') }} AlenaZentrix / PRODERI S.A.S. — Todos los derechos reservados</div>
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

    <div class="content">

        <p style="font-size:8.5pt; line-height:1.5; text-align:justify; margin-bottom:10px;">
            Este documento constituye el informe consolidado de dispositivos de vigilancia para la Orden
            N° <strong>{{ $orden->numero_orden }}</strong>. Reúne todos los turnos registrados en campo,
            incluyendo novedades, observaciones y evidencias fotográficas por cada turno.
        </p>

        {{-- ── Antecedentes ── --}}
        @if (!empty($datosOrden['antecedente']) && $datosOrden['antecedente'] !== '—')
            <div class="section">
                <div class="section-title">ANTECEDENTES</div>
                <div class="objetivos-box">
                    <div class="objetivo-grupo">
                        <div class="objetivo-item">{{ $datosOrden['antecedente'] }}</div>
                    </div>
                </div>
            </div>
        @endif

        {{-- ── Objetivos ── --}}
        @if (!empty($datosOrden['objetivo']) && $datosOrden['objetivo'] !== '—')
            <div class="section">
                <div class="section-title">OBJETIVOS</div>
                <div class="objetivos-box">
                    <div class="objetivo-grupo">
                        <div class="objetivo-item">{{ $datosOrden['objetivo'] }}</div>
                    </div>
                </div>
            </div>
        @endif

        {{-- ── Bloques de turno ── --}}
        @forelse ($reportes as $index => $reporte)
            @php
                $datos = $reporte->datos_servicio ?? [];
                $fechaTurno = isset($datos['fecha_turno'])
                    ? \Carbon\Carbon::parse($datos['fecha_turno'])->format('d/m/Y')
                    : '—';
                $horaInicio = $datos['hora_inicio'] ?? '—';
                $horaFin    = $datos['hora_fin'] ?? '—';
                $hayNovedad      = (bool) ($datos['hay_novedad'] ?? false);
                $hayReincidencia = (bool) ($datos['hay_reincidencia'] ?? false);
                $descNovedad  = $datos['descripcion_novedad'] ?? '';
                $resultadoNombre = $resultados[$datos['resultado_reporte_id'] ?? null] ?? '—';
                $nombreInvestigador = $reporte->personal
                    ? trim($reporte->personal->nombres . ' ' . $reporte->personal->apellidos)
                    : '—';
                $imagenesDelTurno = $imagenesPerTurno[$reporte->id] ?? [];
            @endphp

            <div class="section">
                <div class="section-title-turno">
                    TURNO N° {{ $index + 1 }} — {{ $fechaTurno }}
                </div>

                {{-- Datos del turno --}}
                <table class="tabla-tecnica">
                    <tr>
                        <td class="lbl">Fecha</td>
                        <td class="val">{{ $fechaTurno }}</td>
                        <td class="lbl">Horario</td>
                        <td class="val">{{ $horaInicio }} – {{ $horaFin }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Investigador</td>
                        <td class="val">{{ $nombreInvestigador }}</td>
                        <td class="lbl">Resultado</td>
                        <td class="val">{{ $resultadoNombre }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Novedades</td>
                        <td class="val">
                            @if ($hayNovedad)
                                <span class="badge badge-orange">SÍ</span>
                            @else
                                <span class="badge badge-green">NO</span>
                            @endif
                        </td>
                        <td class="lbl">Reincidencia</td>
                        <td class="val">
                            @if ($hayReincidencia)
                                <span class="badge badge-red">SÍ</span>
                            @else
                                <span class="badge" style="background-color:#94a3b8;color:white;">NO</span>
                            @endif
                        </td>
                    </tr>
                </table>

                {{-- Descripción de novedades --}}
                @if ($hayNovedad && $descNovedad)
                    <div class="section-subtitle">DESCRIPCIÓN DE NOVEDADES</div>
                    <div class="content-box">{{ $descNovedad }}</div>
                @endif

                {{-- Observaciones del turno --}}
                <div class="section-subtitle">OBSERVACIONES</div>
                <div class="content-box">{{ $reporte->observaciones ?: 'Sin observaciones registradas.' }}</div>

                {{-- GeoReferencia --}}
                @if (!empty($reporte->geo_referencia))
                    <div class="section-subtitle">GEOREFERENCIA</div>
                    <div class="info-box">
                        <div class="info-grid">
                            <div class="info-row">
                                <div class="info-label">URL GeoReferencia:</div>
                                <div class="info-value" style="word-break: break-all;">{{ $reporte->geo_referencia }}</div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Evidencias fotográficas del turno --}}
                @if (count($imagenesDelTurno) > 0)
                    <div class="section-subtitle">EVIDENCIAS FOTOGRÁFICAS</div>
                    @php $rows = array_chunk($imagenesDelTurno, 2); @endphp
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
                @endif
            </div>

        @empty
            <div class="content-box" style="color:#64748b; font-style:italic;">
                Sin turnos registrados para esta orden.
            </div>
        @endforelse

        {{-- ── Destinación del Informe ── --}}
        @include('pdf.partials.destinacion-informe')

        {{-- ── Generador del informe ── --}}
        <div class="generador-info">
            Informe generado el <strong>{{ $generador['fecha'] }}</strong> por
            <strong>{{ $generador['nombre'] }}</strong> ({{ $generador['email'] }})
        </div>

    </div>
</body>

</html>
