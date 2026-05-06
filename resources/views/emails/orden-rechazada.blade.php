<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte Rechazado</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f7f7f7; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background-color: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; }
        .header { background-color: #1a1a1a; color: white; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0 0 8px 0; font-size: 26px; font-weight: 600; }
        .orden-badge { display: inline-block; background-color: #dc2626; color: white; padding: 6px 18px; border-radius: 20px; font-size: 18px; font-weight: 700; letter-spacing: 1px; margin-top: 8px; }
        .status-banner { background-color: #fee2e2; border-left: 4px solid #dc2626; padding: 12px 20px; font-size: 14px; color: #7f1d1d; font-weight: 500; }
        .content { padding: 30px; }
        .greeting { font-size: 18px; font-weight: 600; margin-bottom: 12px; color: #1a1a1a; }
        .intro { color: #555; margin-bottom: 24px; }
        .section { background-color: #f9f9f9; border-left: 4px solid #dc2626; border-radius: 4px; padding: 16px; margin: 20px 0; }
        .section-title { font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; color: #dc2626; margin-bottom: 12px; }
        .detail-row { display: flex; padding: 7px 0; border-bottom: 1px solid #ebebeb; font-size: 14px; }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { color: #888; min-width: 140px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.4px; padding-top: 2px; }
        .detail-value { color: #1a1a1a; font-weight: 500; flex: 1; }
        .motivo-box { background-color: #fee2e2; border: 1px solid #fca5a5; border-radius: 6px; padding: 16px; margin: 20px 0; }
        .motivo-title { font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; color: #dc2626; margin-bottom: 8px; }
        .motivo-text { color: #7f1d1d; font-size: 14px; line-height: 1.6; white-space: pre-line; }
        .footer { background-color: #f9f9f9; padding: 20px; text-align: center; color: #999; font-size: 12px; border-top: 1px solid #e0e0e0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ config('app.name') }}</h1>
            <div style="color: #ccc; font-size: 14px; margin-bottom: 8px;">Reporte Rechazado</div>
            <span class="orden-badge">{{ $orden->numero_orden }}</span>
        </div>

        <div class="status-banner">
            ❌ Tu reporte ha sido <strong>rechazado</strong>. Debes corregirlo y reenviarlo.
        </div>

        <div class="content">
            <div class="greeting">Hola, {{ $tecnico->nombres }}.</div>
            <div class="intro">
                El coordinador ha revisado tu reporte para la orden <strong>{{ $orden->numero_orden }}</strong>
                y ha solicitado correcciones. Por favor revisa el motivo y edita el reporte desde la aplicación.
            </div>

            <div class="motivo-box">
                <div class="motivo-title">Motivo del Rechazo</div>
                <div class="motivo-text">{{ $comentarios }}</div>
            </div>

            <div class="section">
                <div class="section-title">Detalle de la Orden</div>
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
            </div>

            <p style="font-size: 13px; color: #666; margin-top: 24px;">
                Ingresa a la aplicación, ve a <strong>Mis Órdenes</strong> y selecciona <em>Editar Reporte</em>
                para corregir y reenviar.
            </p>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
