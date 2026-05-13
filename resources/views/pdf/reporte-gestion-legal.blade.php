<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Informe de Gestión Legal - {{ $orden->numero_orden }}</title>
    <style>
        @page { size: A4; margin: 155px 50px 110px 50px; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9pt; line-height: 1.2; color: #333; margin: 0; padding: 0; }
        .header { position: fixed; top: -143px; left: 0; right: 0; height: 133px; background: white; padding: 10px 50px 6px 50px; z-index: 1000; }
        .header-inner { width: 100%; border-collapse: collapse; }
        .header-td-center { text-align: left; vertical-align: middle; }
        .header-td-proderi { width: 140px; text-align: right; vertical-align: middle; }
        .logo-proderi { width: 130px; height: auto; }
        .header h1 { color: #003E72; font-size: 13pt; margin: 0 0 3px 0; font-weight: bold; }
        .header h2 { color: #64748b; font-size: 9pt; margin: 0 0 4px 0; font-weight: normal; }
        .header-meta { font-size: 7.5pt; color: #64748b; margin-top: 3px; }
        .header-separator { position: fixed; top: -10px; left: 50px; right: 50px; height: 0; border-top: 2px solid #003E72; z-index: 999; }
        .footer { position: fixed; bottom: -95px; left: 0; right: 0; height: 75px; background: white; border-top: 2px solid #003E72; padding: 10px 50px; font-size: 7pt; color: #64748b; text-align: center; }
        .footer-line { font-weight: bold; margin-bottom: 2px; }
        .content { margin-top: 10px; }
        .section { margin: 10px 0; page-break-inside: avoid; }
        .section-title { color: #003E72; font-size: 10pt; font-weight: bold; margin: 12px 0 5px 0; padding-bottom: 3px; border-bottom: 1px solid #e5e7eb; page-break-after: avoid; }
        .info-box { border: 1px solid #003E72; border-radius: 6px; padding: 8px 10px; margin: 5px 0; background-color: #f8f9fa; page-break-inside: avoid; }
        .info-grid { display: table; width: 100%; }
        .info-row { display: table-row; }
        .info-label { display: table-cell; font-weight: bold; padding: 3px 12px 3px 0; width: 160px; vertical-align: top; font-size: 8pt; color: #003E72; }
        .info-value { display: table-cell; padding: 3px 0; vertical-align: top; font-size: 8pt; }
        .content-box { border: 1px solid #003E72; border-radius: 6px; background-color: #f8f9fa; padding: 8px 10px; margin: 5px 0; white-space: pre-wrap; word-wrap: break-word; font-size: 8pt; line-height: 1.4; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 7pt; font-weight: bold; }
        .badge-blue { background-color: #3b82f6; color: white; }
        .badge-green { background-color: #10b981; color: white; }
        .badge-red { background-color: #ef4444; color: white; }
        .evidencias-tabla { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .evidencia-td { width: 50%; padding: 5px; vertical-align: top; text-align: center; }
        .evidencia-img { max-width: 100%; max-height: 190px; display: block; margin: 0 auto; border: 1px solid #e5e7eb; border-radius: 4px; }
        .evidencia-num { font-size: 7pt; color: #64748b; margin-top: 3px; }
        .firma-section { margin-top: 20px; page-break-inside: avoid; }
        .firma-box { border: 2px solid #003E72; border-radius: 8px; padding: 14px 18px; margin: 6px 0; background-color: #f0f4f9; }
        .firma-badge { display: inline-block; background-color: #10b981; color: white; padding: 3px 10px; border-radius: 10px; font-size: 8pt; font-weight: bold; margin-bottom: 10px; }
        .firma-linea { border-top: 1.5px solid #003E72; margin: 14px 0 8px 0; width: 220px; }
        .firma-nombre { font-weight: bold; font-size: 10pt; color: #003E72; margin-bottom: 2px; }
        .firma-meta { font-size: 7.5pt; color: #64748b; margin: 2px 0; }
        .firma-meta strong { color: #333; }
    </style>
</head>
<body>

    <div class="header">
        <table class="header-inner">
            <tr>
                <td class="header-td-center">
                    <h1>INFORME DE GESTIÓN LEGAL</h1>
                    <h2>Control y Seguimiento de Operaciones Legales en Campo</h2>
                    <div class="header-meta">
                        Orden: <strong>{{ $orden->numero_orden }}</strong>
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
        <div class="footer-line">Informe de Gestión Legal — Uso Interno y Confidencial</div>
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

        <p style="font-size:8.5pt; line-height:1.5; text-align:justify; margin-bottom:10px;">
            Este documento constituye el informe individual de gestión legal para la Orden
            N° <strong>{{ $datosOrden['numero_orden'] ?? $orden->numero_orden }}</strong>, con el propósito
            de registrar las actividades realizadas, los datos de la operación y la evidencia fotográfica.
        </p>

        {{-- ── Objetivo ── --}}
        @if ($datosOrden['objetivo'] ?? null)
            <div class="section">
                <div class="section-title">OBJETIVO</div>
                <div class="content-box">{{ $datosOrden['objetivo'] }}</div>
            </div>
        @endif

        {{-- ── Datos de la Orden ── --}}
        <div class="section">
            <div class="section-title">DATOS DE LA ORDEN</div>
            <div class="info-box">
                <div class="info-grid">
                    <div class="info-row">
                        <div class="info-label">Número de Orden:</div>
                        <div class="info-value">{{ $datosOrden['numero_orden'] }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Tipo de Gestión:</div>
                        <div class="info-value">
                            <span class="badge badge-blue">{{ $datosOrden['tipo_gestion_label'] }}</span>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Estado:</div>
                        <div class="info-value">{{ $datosOrden['estado'] }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Gestor de Riesgo:</div>
                        <div class="info-value">{{ $datosOrden['tecnico'] }}</div>
                    </div>
                    @if (($datosOrden['noticia_criminal'] ?? '—') !== '—')
                        <div class="info-row">
                            <div class="info-label">N° Noticia Criminal:</div>
                            <div class="info-value">{{ $datosOrden['noticia_criminal'] }}</div>
                        </div>
                    @endif
                    <div class="info-row">
                        <div class="info-label">Fecha de Asignación:</div>
                        <div class="info-value">{{ $datosOrden['fecha_asignacion'] }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Fecha Límite:</div>
                        <div class="info-value">{{ $datosOrden['fecha_limite'] }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Fecha de Completado:</div>
                        <div class="info-value">{{ $datosOrden['fecha_completado'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Datos Operativos ── --}}
        @if ($datosReporte['tipologia'] || $datosReporte['fecha_ejecucion'] || $datosReporte['departamento'])
            <div class="section">
                <div class="section-title">DATOS OPERATIVOS</div>
                <div class="info-box">
                    <div class="info-grid">
                        @if ($datosReporte['tipologia'])
                            <div class="info-row">
                                <div class="info-label">Tipología:</div>
                                <div class="info-value">{{ $datosReporte['tipologia'] }}</div>
                            </div>
                        @endif
                        @if ($datosReporte['fecha_ejecucion'])
                            <div class="info-row">
                                <div class="info-label">Fecha de Ejecución:</div>
                                <div class="info-value">{{ \Illuminate\Support\Carbon::parse($datosReporte['fecha_ejecucion'])->format('d/m/Y') }}</div>
                            </div>
                        @endif
                        @if ($datosReporte['departamento'])
                            <div class="info-row">
                                <div class="info-label">Departamento:</div>
                                <div class="info-value">{{ $datosReporte['departamento'] }}</div>
                            </div>
                        @endif
                        @if ($datosReporte['municipio'])
                            <div class="info-row">
                                <div class="info-label">Municipio:</div>
                                <div class="info-value">{{ $datosReporte['municipio'] }}</div>
                            </div>
                        @endif
                        @if ($datosReporte['radio_objetivo'])
                            <div class="info-row">
                                <div class="info-label">Radio del Objetivo:</div>
                                <div class="info-value">{{ $datosReporte['radio_objetivo'] }}</div>
                            </div>
                        @endif
                        @if ($datosReporte['resultado'] !== '—')
                            <div class="info-row">
                                <div class="info-label">Resultado:</div>
                                <div class="info-value">
                                    <span class="badge badge-green">{{ $datosReporte['resultado'] }}</span>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- ── Actividades ── --}}
        <div class="section">
            <div class="section-title">ACTIVIDAD REALIZADA</div>
            @if (!empty($datosReporte['actividades']))
                @foreach ($datosReporte['actividades'] as $actividad)
                    <div class="info-box" style="margin-bottom:6px;">
                        <div class="info-grid">
                            @if (!empty($actividad['descripcion_orden']))
                                <div class="info-row">
                                    <div class="info-label">Actividad asignada:</div>
                                    <div class="info-value">{{ $actividad['descripcion_orden'] }}</div>
                                </div>
                            @endif
                            <div class="info-row">
                                <div class="info-label">Respuesta del Gestor Legal:</div>
                                <div class="info-value">{{ $actividad['actividades'] }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="content-box">Sin actividades registradas.</div>
            @endif
        </div>

        {{-- ── Observaciones ── --}}
        <div class="section">
            <div class="section-title">OBSERVACIONES</div>
            <div class="content-box">{{ $datosReporte['observaciones'] }}</div>
        </div>

        {{-- ── Conclusión del Coordinador ── --}}
        @if (!empty($datosReporte['conclusion']))
            <div class="section">
                <div class="section-title">CONCLUSIÓNES</div>
                <div class="content-box">{{ $datosReporte['conclusion'] }}</div>
            </div>
        @endif

        {{-- ── Resultado de la Auditoría ── --}}
        @php $auditoria = $reporte->auditoria; @endphp
        @if ($auditoria && $auditoria->estado?->value !== 'pendiente_auditoria')
            <div class="section">
                <div class="section-title">RESULTADO DE LA AUDITORÍA</div>
                <div class="info-box">
                    <div class="info-grid">
                        <div class="info-row">
                            <div class="info-label">Resultado:</div>
                            <div class="info-value">
                                @if ($auditoria->estado?->value === 'confirmado')
                                    <span class="badge badge-green">&#10003; Confirmado</span>
                                @else
                                    <span class="badge badge-red">&#10005; Negativo</span>
                                @endif
                            </div>
                        </div>
                        @if ($auditoria->fecha_auditoria)
                            <div class="info-row">
                                <div class="info-label">Fecha de Auditoría:</div>
                                <div class="info-value">{{ $auditoria->fecha_auditoria->format('d/m/Y H:i') }}</div>
                            </div>
                        @endif
                        @if ($auditoria->auditadoPor)
                            <div class="info-row">
                                <div class="info-label">Auditado por:</div>
                                <div class="info-value">{{ $auditoria->auditadoPor->fullName() }}</div>
                            </div>
                        @endif
                        @if ($auditoria->observaciones)
                            <div class="info-row">
                                <div class="info-label">Observaciones:</div>
                                <div class="info-value">{{ $auditoria->observaciones }}</div>
                            </div>
                        @endif
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
                            @if (count($row) === 1)<td class="evidencia-td"></td>@endif
                        </tr>
                    @endforeach
                </table>
            @else
                <div class="content-box" style="color:#64748b; font-style:italic;">
                    No se registraron evidencias fotográficas para esta operación.
                </div>
            @endif
        </div>

        {{-- ── Destinación ── --}}
        @include('pdf.partials.destinacion-informe')

        {{-- ── Firma Digital ── --}}
        <div class="firma-section">
            <div class="section-title">FIRMA DIGITAL</div>
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
