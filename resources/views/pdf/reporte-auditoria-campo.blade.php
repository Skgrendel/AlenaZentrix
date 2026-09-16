<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Informe de Auditoría de Campo - {{ $orden->numero_orden }}</title>
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
            width: 180px;
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

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 7pt;
            font-weight: bold;
        }

        .badge-blue {
            background-color: #3b82f6;
            color: white;
        }

        .badge-green {
            background-color: #10b981;
            color: white;
        }

        .badge-red {
            background-color: #ef4444;
            color: white;
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

        .firma-canvas-tabla {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        .firma-canvas-td {
            width: 50%;
            padding: 6px;
            vertical-align: top;
            text-align: center;
        }

        .firma-canvas-img {
            max-width: 100%;
            max-height: 100px;
            border: 1px solid #003E72;
            border-radius: 4px;
            background: white;
            display: block;
            margin: 0 auto;
        }

        .firma-canvas-label {
            font-size: 8pt;
            font-weight: bold;
            color: #003E72;
            margin-bottom: 4px;
        }

        .firma-canvas-cedula {
            font-size: 7.5pt;
            color: #64748b;
            margin-top: 3px;
        }

        .col2-tabla {
            width: 100%;
            border-collapse: collapse;
        }

        .col2-lbl {
            font-weight: bold;
            color: #003E72;
            font-size: 8pt;
            vertical-align: top;
            padding: 3px 8px 3px 0;
            width: 140px;
        }

        .col2-val {
            font-size: 8pt;
            vertical-align: top;
            padding: 3px 10px 3px 0;
        }

        .col2-sep {
            width: 1px;
            border-left: 1px solid #e5e7eb;
            padding: 0;
        }

        .col2-lbl-r {
            font-weight: bold;
            color: #003E72;
            font-size: 8pt;
            vertical-align: top;
            padding: 3px 8px 3px 10px;
            width: 140px;
        }

        .col2-val-r {
            font-size: 8pt;
            vertical-align: top;
            padding: 3px 0;
        }
    </style>
</head>

<body>

    <div class="header">
        <table class="header-inner">
            <tr>
                <td class="header-td-center">
                    <h1>INFORME DE AUDITORÍA DE CAMPO</h1>
                    <h2>{{ $datosOrden['subtipo'] ?? 'Control y Seguimiento de Visita en Campo' }}</h2>
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

    <div class="header-separator"></div>

    <div class="footer">
        <div class="footer-line">Informe de Auditoría de Campo — Uso Interno y Confidencial</div>
        <div>© {{ date('Y') }} AlenaZentrix / PRODERI S.A.S. — Todos los derechos reservados</div>
        <div>Este documento contiene información confidencial protegida por las leyes de propiedad intelectual</div>
    </div>

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

        {{-- ── Datos de la Orden ── --}}
        {{-- <div class="section">
            <div class="section-title">DATOS DE LA ORDEN</div>
            <div class="info-box">
                <div class="info-grid">
                    <div class="info-row">
                        <div class="info-label">Número de Orden:</div>
                        <div class="info-value">{{ $datosOrden['numero_orden'] }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Subtipo de Auditoría:</div>
                        <div class="info-value"><span class="badge badge-blue">{{ $datosOrden['subtipo'] }}</span></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Estado:</div>
                        <div class="info-value">{{ $datosOrden['estado'] }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Auditor:</div>
                        <div class="info-value">{{ $datosOrden['tecnico'] }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Contrato:</div>
                        <div class="info-value">{{ $datosOrden['contrato'] }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Nombre del Usuario:</div>
                        <div class="info-value">{{ $datosOrden['cliente'] }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Dirección:</div>
                        <div class="info-value">{{ $datosOrden['direccion'] }}</div>
                    </div>
                    @if ($datosReporte['ciudad'])
                        <div class="info-row">
                            <div class="info-label">Ciudad:</div>
                            <div class="info-value">{{ $datosReporte['ciudad'] }}</div>
                        </div>
                    @endif
                    @if ($datosReporte['celular_usuario'])
                        <div class="info-row">
                            <div class="info-label">Celular del Usuario:</div>
                            <div class="info-value">{{ $datosReporte['celular_usuario'] }}</div>
                        </div>
                    @endif
                    @if ($datosReporte['fecha_hora_atencion'])
                        <div class="info-row">
                            <div class="info-label">Fecha y Hora Atención:</div>
                            <div class="info-value">{{ $datosReporte['fecha_hora_atencion'] }}</div>
                        </div>
                    @endif
                    <div class="info-row">
                        <div class="info-label">Fecha de Asignación:</div>
                        <div class="info-value">{{ $datosOrden['fecha_asignacion'] }}</div>
                    </div>
                </div>
            </div>
        </div> --}}

        {{-- ── Solicitud y comentarios ── --}}
        @if ($datosReporte['comentario_usuario'])
            <div class="section">
                <div class="section-title">SOLICITUD DEL USUARIO</div>
                <div class="content-box">{{ $datosReporte['comentario_usuario'] }}</div>
            </div>
        @endif
        @if ($datosReporte['comentario_tecnico'])
            <div class="section">
                <div class="section-title">COMENTARIO DEL TÉCNICO</div>
                <div class="content-box">{{ $datosReporte['comentario_tecnico'] }}</div>
            </div>
        @endif

        {{-- ── Quién Atendió ── --}}
        @if ($datosReporte['nombre_atiende'])
            <div class="section">
                <div class="section-title">QUIEN ATENDIÓ LA VISITA</div>
                <div class="info-box">
                    <div class="info-grid">
                        <div class="info-row">
                            <div class="info-label">Nombre:</div>
                            <div class="info-value">{{ $datosReporte['nombre_atiende'] }}</div>
                        </div>
                        @if ($datosReporte['vinculo_usuario'])
                            <div class="info-row">
                                <div class="info-label">Vínculo con el usuario:</div>
                                <div class="info-value">{{ ucfirst($datosReporte['vinculo_usuario']) }}</div>
                            </div>
                        @endif
                        @if ($datosReporte['celular_atiende'])
                            <div class="info-row">
                                <div class="info-label">Celular:</div>
                                <div class="info-value">{{ $datosReporte['celular_atiende'] }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- ── Validación de la Atención ── --}}
        @unless ($datosReporte['es_medidores_digitales'] ?? false)
        <div class="section">
            <div class="section-title">VALIDACIÓN DE LA ATENCIÓN Y EJECUCIÓN</div>
            <div class="info-box">
                <table class="col2-tabla">
                    <tr>
                        <td class="col2-lbl">¿Recibió la visita?</td>
                        <td class="col2-val"><span
                                class="badge {{ $datosReporte['recibio_visita'] ? 'badge-green' : 'badge-red' }}">{{ $datosReporte['recibio_visita'] ? 'SÍ' : 'NO' }}</span>
                        </td>
                        <td class="col2-sep"></td>
                        <td class="col2-lbl-r">¿Se realizó revisión?</td>
                        <td class="col2-val-r"><span
                                class="badge {{ $datosReporte['se_realizo_revision'] ? 'badge-green' : 'badge-red' }}">{{ $datosReporte['se_realizo_revision'] ? 'SÍ' : 'NO' }}</span>
                        </td>
                    </tr>
                    @if ($datosReporte['fecha_ejecucion'] || $datosReporte['tiempo_ejecucion'])
                        <tr>
                            <td class="col2-lbl">Fecha de ejecución:</td>
                            <td class="col2-val">
                                {{ $datosReporte['fecha_ejecucion'] ? \Illuminate\Support\Carbon::parse($datosReporte['fecha_ejecucion'])->format('d/m/Y') : '—' }}
                            </td>
                            <td class="col2-sep"></td>
                            <td class="col2-lbl-r">Tiempo de ejecución:</td>
                            <td class="col2-val-r">{{ $datosReporte['tiempo_ejecucion'] ?? '—' }}</td>
                        </tr>
                    @endif
                </table>
            </div>
            @if ($datosReporte['descripcion_trabajo'])
                <div style="font-size:8pt; font-weight:bold; color:#003E72; margin:6px 0 2px 0;">Descripción del trabajo
                    realizado:</div>
                <div class="content-box" style="margin-top:4px;">{{ $datosReporte['descripcion_trabajo'] }}</div>
            @endif
        </div>

        {{-- ── Hallazgos Técnicos ── --}}
        <div class="section">
            <div class="section-title">HALLAZGOS TÉCNICOS</div>
            <div class="info-box">
                <table class="col2-tabla">
                    <tr>
                        <td class="col2-lbl">¿Se detectó fuga/falla?</td>
                        <td class="col2-val">
                            <span
                                class="badge {{ $datosReporte['se_detecto_fuga'] ? 'badge-red' : 'badge-green' }}">{{ $datosReporte['se_detecto_fuga'] ? 'SÍ' : 'NO' }}</span>
                            @if ($datosReporte['se_detecto_fuga'])
                                @if ($datosReporte['ppm'])
                                    &nbsp;PPM: {{ $datosReporte['ppm'] }}
                                @endif
                                @if ($datosReporte['vol_porcentaje'])
                                    &nbsp;VOL%: {{ $datosReporte['vol_porcentaje'] }}
                                @endif
                            @endif
                        </td>
                        <td class="col2-sep"></td>
                        <td class="col2-lbl-r">¿Problema persiste?</td>
                        <td class="col2-val-r"><span
                                class="badge {{ $datosReporte['problema_persiste'] ? 'badge-red' : 'badge-green' }}">{{ $datosReporte['problema_persiste'] ? 'SÍ' : 'NO' }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="col2-lbl">¿Evidencia de reparación?</td>
                        <td class="col2-val"><span
                                class="badge {{ $datosReporte['evidencia_reparacion'] ? 'badge-green' : 'badge-red' }}">{{ $datosReporte['evidencia_reparacion'] ? 'SÍ' : 'NO' }}</span>
                        </td>
                        <td class="col2-sep"></td>
                        <td class="col2-lbl-r">¿Servicio correcto?</td>
                        <td class="col2-val-r"><span
                                class="badge {{ $datosReporte['servicio_correcto'] ? 'badge-green' : 'badge-red' }}">{{ $datosReporte['servicio_correcto'] ? 'SÍ' : 'NO' }}</span>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        {{-- ── Materiales y Cobros ── --}}
        <div class="section">
            <div class="section-title">MATERIALES Y COBROS</div>
            <div class="info-box">
                <table class="col2-tabla">
                    <tr>
                        <td class="col2-lbl">¿Se usaron materiales?</td>
                        <td class="col2-val">
                            <span
                                class="badge {{ $datosReporte['se_usaron_materiales'] ? 'badge-green' : 'badge-red' }}">{{ $datosReporte['se_usaron_materiales'] ? 'SÍ' : 'NO' }}</span>{{ $datosReporte['quien_suministro_materiales'] ? ' — Suministrado por: ' . ucfirst($datosReporte['quien_suministro_materiales']) : '' }}
                        </td>
                        <td class="col2-sep"></td>
                        <td class="col2-lbl-r">¿Usuario asumió costo?</td>
                        <td class="col2-val-r">
                            <span
                                class="badge {{ $datosReporte['usuario_asumio_costo'] ? 'badge-green' : 'badge-red' }}">{{ $datosReporte['usuario_asumio_costo'] ? 'SÍ' : 'NO' }}</span>{{ $datosReporte['valor_pagado'] ? ' — $' . $datosReporte['valor_pagado'] . ' (' . ($datosReporte['medio_pago'] ?? '—') . ')' : '' }}
                        </td>
                    </tr>
                    <tr>
                        <td class="col2-lbl">¿Entregó factura/IVT?</td>
                        <td class="col2-val"><span
                                class="badge {{ $datosReporte['entrego_factura'] ? 'badge-green' : 'badge-red' }}">{{ $datosReporte['entrego_factura'] ? 'SÍ' : 'NO' }}</span>
                        </td>
                        <td class="col2-sep"></td>
                        <td class="col2-lbl-r">¿Informaron que no estaba?</td>
                        <td class="col2-val-r"><span
                                class="badge {{ $datosReporte['informaron_no_estaba'] ? 'badge-red' : 'badge-green' }}">{{ $datosReporte['informaron_no_estaba'] ? 'SÍ' : 'NO' }}</span>
                        </td>
                    </tr>
                    @if ($datosReporte['tecnico_dejo_documento'])
                        <tr>
                            <td class="col2-lbl">Documento dejado:</td>
                            <td class="col2-val">{{ $datosReporte['tecnico_dejo_documento'] }}</td>
                            <td class="col2-sep"></td>
                            <td colspan="2"></td>
                        </tr>
                    @endif
                </table>
            </div>
        </div>
        @endunless

        {{-- ── Validación y Hallazgos — Medidores Digitales ── --}}
        @if ($datosReporte['es_medidores_digitales'] ?? false)
        <div class="section">
            <div class="section-title">VALIDACIÓN Y HALLAZGOS — MEDIDORES DIGITALES</div>
            <div class="info-box">
                <table class="col2-tabla">
                    <tr>
                        <td class="col2-lbl">Técnico que atendió:</td>
                        <td class="col2-val">{{ $datosReporte['tecnico_atendio_medidor'] ?? '—' }}</td>
                        <td class="col2-sep"></td>
                        <td class="col2-lbl-r">Número de medidor instalado:</td>
                        <td class="col2-val-r">{{ $datosReporte['numero_medidor_instalado'] ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="col2-lbl">Resultado de la auditoría:</td>
                        <td class="col2-val" colspan="4">{{ $datosReporte['resultado_medidor'] ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="col2-lbl">¿Informó gratuidad medidor/instalación?</td>
                        <td class="col2-val"><span
                                class="badge {{ $datosReporte['informo_gratuidad_medidor'] ? 'badge-green' : 'badge-red' }}">{{ $datosReporte['informo_gratuidad_medidor'] ? 'SÍ' : 'NO' }}</span>
                        </td>
                        <td class="col2-sep"></td>
                        <td class="col2-lbl-r">¿Solicitó, insinuó o recibió dinero?</td>
                        <td class="col2-val-r"><span
                                class="badge {{ $datosReporte['solicito_dinero_medidor'] ? 'badge-red' : 'badge-green' }}">{{ $datosReporte['solicito_dinero_medidor'] ? 'SÍ' : 'NO' }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="col2-lbl">¿Gestión conforme a procedimientos?</td>
                        <td class="col2-val"><span
                                class="badge {{ $datosReporte['gestion_conforme_medidor'] ? 'badge-green' : 'badge-red' }}">{{ $datosReporte['gestion_conforme_medidor'] ? 'SÍ' : 'NO' }}</span>
                        </td>
                        <td class="col2-sep"></td>
                        <td class="col2-lbl-r">¿Requiere seguimiento?</td>
                        <td class="col2-val-r"><span
                                class="badge {{ $datosReporte['requiere_seguimiento_medidor'] ? 'badge-red' : 'badge-green' }}">{{ $datosReporte['requiere_seguimiento_medidor'] ? 'SÍ' : 'NO' }}</span>
                        </td>
                    </tr>
                </table>
            </div>
            @if ($datosReporte['hallazgos_medidor'])
                <div style="font-size:8pt; font-weight:bold; color:#003E72; margin:6px 0 2px 0;">Hallazgos / observaciones:</div>
                <div class="content-box" style="margin-top:4px;">{{ $datosReporte['hallazgos_medidor'] }}</div>
            @endif
        </div>
        @endif

        {{-- ── Observaciones ── --}}
        <div class="section">
            <div class="section-title">OBSERVACIONES</div>
            <div class="content-box">{{ $datosReporte['observaciones'] }}</div>
        </div>

        {{-- ── GeoReferencia ── --}}
        @if (!empty($datosReporte['geo_referencia']))
            <div class="section">
                <div class="section-title">GEOREFERENCIA</div>
                <div class="info-box">
                    <div class="info-grid">
                        <div class="info-row">
                            <div class="info-label">URL GeoReferencia:</div>
                            <div class="info-value" style="word-break: break-all;">{{ $datosReporte['geo_referencia'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- ── Evidencias Fotográficas ── --}}
        <div class="section">
            <div class="section-title">EVIDENCIAS FOTOGRÁFICAS</div>
            @if (count($imagenes) > 0)
                <p style="font-size:7.5pt; color:#64748b; margin:4px 0 8px 0;">
                    Se registraron <strong>{{ count($imagenes) }}</strong> evidencia(s) fotográfica(s).
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
                    No se registraron evidencias fotográficas para esta operación.
                </div>
            @endif
        </div>

        {{-- ── URLs de Evidencia ── --}}
        @if (!empty($datosReporte['urls_evidencia']))
            <div class="section">
                <div class="section-title">URLS DE EVIDENCIA / DOCUMENTOS</div>
                <div class="info-box">
                    @foreach ($datosReporte['urls_evidencia'] as $url)
                        <div style="font-size:8pt; margin:2px 0; word-wrap:break-word;">{{ $url }}</div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- ── Destinación ── --}}
        @include('pdf.partials.destinacion-informe')

        {{-- ── Autorización y Firmas manuscritas ── --}}
        <div class="firma-section">
            <div class="section-title">AUTORIZACIÓN Y FIRMAS</div>
            <div class="info-box" style="margin-bottom:10px;">
                <div class="info-grid">
                    <div class="info-row">
                        <div class="info-label">Autoriza recolección:</div>
                        <div class="info-value">
                            <span class="badge {{ $datosReporte['autoriza'] ? 'badge-green' : 'badge-red' }}">
                                {{ $datosReporte['autoriza'] ? 'SÍ AUTORIZA' : 'NO AUTORIZA' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <table class="firma-canvas-tabla">
                <tr>
                    <td class="firma-canvas-td">
                        <div class="firma-canvas-label">Firma del Usuario</div>
                        @if ($datosReporte['firma_usuario'])
                            <img src="{{ $datosReporte['firma_usuario'] }}" class="firma-canvas-img"
                                alt="Firma Usuario">
                        @else
                            <div style="font-size:7.5pt; color:#9ca3af; font-style:italic;">Sin firma registrada</div>
                        @endif
                        <div class="firma-canvas-cedula">C.C.: {{ $datosReporte['cedula_usuario'] ?? '—' }}</div>
                    </td>
                    <td class="firma-canvas-td">
                        <div class="firma-canvas-label">Firma del Auditor</div>
                        @if ($datosReporte['firma_auditor'])
                            <img src="{{ $datosReporte['firma_auditor'] }}" class="firma-canvas-img"
                                alt="Firma Auditor">
                        @else
                            <div style="font-size:7.5pt; color:#9ca3af; font-style:italic;">Sin firma registrada</div>
                        @endif
                        <div class="firma-canvas-cedula">C.C.: {{ $datosReporte['cedula_auditor'] ?? '—' }}</div>
                    </td>
                </tr>
            </table>
        </div>

        {{-- ── Firma Digital del Coordinador ── --}}
        <div class="firma-section">
            <div class="section-title">FIRMA DIGITAL</div>
            <p style="font-size:8pt; margin:5px 0 10px 0;">
                El investigador responsable identificado a continuación ha generado y validado este informe mediante
                firma digital en cumplimiento de los protocolos de seguridad establecidos para garantizar la integridad
                y autenticidad del documento.
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

    </div>
</body>

</html>
