<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incidencia Detectada</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f7f7f7; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background-color: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; }
        .header { background-color: #b91c1c; color: white; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0 0 8px 0; font-size: 26px; font-weight: 600; }
        .orden-badge { display: inline-block; background-color: #fff; color: #b91c1c; padding: 6px 18px; border-radius: 20px; font-size: 18px; font-weight: 700; letter-spacing: 1px; margin-top: 8px; }
        .alert-banner { background-color: #fee2e2; border-left: 4px solid #b91c1c; padding: 12px 20px; font-size: 14px; color: #7f1d1d; font-weight: 500; }
        .content { padding: 30px; }
        .greeting { font-size: 18px; font-weight: 600; margin-bottom: 12px; color: #1a1a1a; }
        .intro { color: #555; margin-bottom: 24px; }
        .section { background-color: #f9f9f9; border-left: 4px solid #b91c1c; border-radius: 4px; padding: 16px; margin: 20px 0; }
        .section-title { font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; color: #b91c1c; margin-bottom: 12px; }
        .detail-row { display: flex; padding: 7px 0; border-bottom: 1px solid #ebebeb; font-size: 14px; }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { color: #888; min-width: 140px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.4px; padding-top: 2px; }
        .detail-value { color: #1a1a1a; font-weight: 500; flex: 1; }
        .incidencia-item { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid #fecaca; font-size: 14px; font-weight: 600; color: #7f1d1d; }
        .incidencia-item:last-child { border-bottom: none; }
        .footer { background-color: #f9f9f9; padding: 20px; text-align: center; color: #999; font-size: 12px; border-top: 1px solid #e0e0e0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ config('app.name') }}</h1>
            <div style="color: #fca5a5; font-size: 14px; margin-bottom: 8px;">Alerta de Incidencia</div>
            <span class="orden-badge">{{ $orden->numero_orden }}</span>
        </div>

        <div class="alert-banner">
            🚨 Se detectaron <strong>{{ count($incidencias) }}</strong> incidencia(s) en el reporte de la orden <strong>{{ $orden->numero_orden }}</strong>.
        </div>

        <div class="content">
            <div class="greeting">Incidencias detectadas en campo</div>
            <div class="intro">
                El técnico asignado ha reportado incidencias que requieren atención inmediata para la orden <strong>{{ $orden->numero_orden }}</strong>.
            </div>

            <div class="section">
                <div class="section-title">Incidencias Reportadas</div>
                @foreach($incidencias as $incidencia)
                <div class="incidencia-item">
                    <span>⚠️</span>
                    <span>{{ $incidencia }}</span>
                </div>
                @endforeach
            </div>

            <div class="section">
                <div class="section-title">Datos del Punto de Servicio</div>
                <div class="detail-row">
                    <span class="detail-label">Contrato</span>
                    <span class="detail-value">{{ $orden->contrato }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Cliente</span>
                    <span class="detail-value">{{ $orden->cliente }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Dirección</span>
                    <span class="detail-value">{{ $orden->direccion }}, {{ $orden->barrio }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">N° Medidor</span>
                    <span class="detail-value">{{ $orden->medidor }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Ciclo</span>
                    <span class="detail-value">{{ $orden->ciclo }}</span>
                </div>
            </div>

            @if($orden->personal)
            <div class="section">
                <div class="section-title">Técnico Responsable</div>
                <div class="detail-row">
                    <span class="detail-label">Nombre</span>
                    <span class="detail-value">{{ $orden->personal->nombres }} {{ $orden->personal->apellidos }}</span>
                </div>
                @if($orden->personal->telefono)
                <div class="detail-row">
                    <span class="detail-label">Teléfono</span>
                    <span class="detail-value">{{ $orden->personal->telefono }}</span>
                </div>
                @endif
            </div>
            @endif

            <p style="font-size: 13px; color: #666; margin-top: 24px;">
                Ingresa al sistema y revisa el reporte completo en <strong>Órdenes de Trabajo</strong>.
            </p>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
