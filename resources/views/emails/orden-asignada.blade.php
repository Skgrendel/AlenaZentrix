<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orden de Trabajo Asignada</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f7f7f7;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background-color: #1a1a1a;
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0 0 8px 0;
            font-size: 26px;
            font-weight: 600;
        }
        .orden-badge {
            display: inline-block;
            background-color: #0066cc;
            color: white;
            padding: 6px 18px;
            border-radius: 20px;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 1px;
            margin-top: 8px;
        }
        .content {
            padding: 30px;
        }
        .greeting {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 12px;
            color: #1a1a1a;
        }
        .intro {
            color: #555;
            margin-bottom: 24px;
        }
        .section {
            background-color: #f9f9f9;
            border-left: 4px solid #0066cc;
            border-radius: 4px;
            padding: 16px;
            margin: 20px 0;
        }
        .section-title {
            font-weight: 700;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #0066cc;
            margin-bottom: 12px;
        }
        .detail-row {
            display: flex;
            padding: 7px 0;
            border-bottom: 1px solid #ebebeb;
            font-size: 14px;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            color: #888;
            min-width: 140px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            padding-top: 2px;
        }
        .detail-value {
            color: #1a1a1a;
            font-weight: 500;
            flex: 1;
        }
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-baja     { background-color: #e5e7eb; color: #374151; }
        .badge-media    { background-color: #dbeafe; color: #1d4ed8; }
        .badge-alta     { background-color: #fef3c7; color: #92400e; }
        .badge-urgente  { background-color: #fee2e2; color: #b91c1c; }
        .badge-pendiente   { background-color: #e5e7eb; color: #374151; }
        .badge-asignada    { background-color: #dbeafe; color: #1d4ed8; }
        .badge-en_progreso { background-color: #fef3c7; color: #92400e; }
        .badge-completada  { background-color: #d1fae5; color: #065f46; }
        .badge-cancelada   { background-color: #fee2e2; color: #b91c1c; }
        .description-box {
            background-color: #f0f4ff;
            border-radius: 4px;
            padding: 14px;
            margin: 16px 0;
            font-size: 14px;
            color: #444;
            font-style: italic;
        }
        .footer {
            background-color: #f9f9f9;
            padding: 20px;
            text-align: center;
            color: #999;
            font-size: 12px;
            border-top: 1px solid #e0e0e0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ config('app.name') }}</h1>
            <div style="color: #ccc; font-size: 14px; margin-bottom: 8px;">Nueva Orden de Trabajo Asignada</div>
            <span class="orden-badge">{{ $orden->numero_orden }}</span>
        </div>

        <div class="content">
            <div class="greeting">¡Hola {{ $tecnico->nombres }} {{ $tecnico->apellidos }}!</div>
            <div class="intro">
                Se te ha asignado una nueva orden de trabajo. A continuación encontrarás todos los detalles necesarios para atenderla.
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
                    <span class="detail-value">{{ $orden->direccion }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Barrio</span>
                    <span class="detail-value">{{ $orden->barrio }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">N° Medidor</span>
                    <span class="detail-value">{{ $orden->medidor }}</span>
                </div>
                @if($orden->tipo_medidor)
                <div class="detail-row">
                    <span class="detail-label">Tipo Medidor</span>
                    <span class="detail-value">{{ $orden->tipo_medidor }}</span>
                </div>
                @endif
                <div class="detail-row">
                    <span class="detail-label">Ciclo</span>
                    <span class="detail-value">{{ $orden->ciclo }}</span>
                </div>
            </div>

            <div class="section">
                <div class="section-title">Gestión de la Orden</div>
                <div class="detail-row">
                    <span class="detail-label">Prioridad</span>
                    <span class="detail-value">
                        <span class="badge badge-{{ $orden->prioridad->value }}">
                            {{ $orden->prioridad->label() }}
                        </span>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Estado</span>
                    <span class="detail-value">
                        <span class="badge badge-{{ $orden->estado->value }}">
                            {{ $orden->estado->label() }}
                        </span>
                    </span>
                </div>
                @if($orden->fecha_limite)
                <div class="detail-row">
                    <span class="detail-label">Fecha Límite</span>
                    <span class="detail-value" style="color: #c0392b; font-weight: 700;">
                        {{ $orden->fecha_limite->format('d/m/Y H:i') }}
                    </span>
                </div>
                @endif
                @if($orden->fecha_asignacion)
                <div class="detail-row">
                    <span class="detail-label">Fecha Asignación</span>
                    <span class="detail-value">{{ $orden->fecha_asignacion->format('d/m/Y H:i') }}</span>
                </div>
                @endif
            </div>

            @if($orden->titulo || $orden->descripcion)
            <div class="section">
                <div class="section-title">Descripción</div>
                @if($orden->titulo)
                <div class="detail-row">
                    <span class="detail-label">Título</span>
                    <span class="detail-value">{{ $orden->titulo }}</span>
                </div>
                @endif
                @if($orden->descripcion)
                <div class="description-box">{{ $orden->descripcion }}</div>
                @endif
            </div>
            @endif

            <p style="color: #888; font-size: 13px; margin-top: 24px;">
                Si tienes dudas o inconvenientes con esta orden, por favor comunícate con el coordinador del sistema.
            </p>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
