@extends('emails.layout')

@section('title', 'Orden de Trabajo Asignada')
@section('badge', $orden->numero_orden)
@section('banner_type', 'info')
@section('banner', 'Se te ha asignado una nueva orden de trabajo.')

@section('content')
    <div class="greeting">¡Hola {{ $tecnico->nombres }} {{ $tecnico->apellidos }}!</div>
    <div class="intro">
        A continuación encontrarás todos los detalles necesarios para atender la orden <strong>{{ $orden->numero_orden }}</strong>.
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
            <span class="detail-value">{{ $orden->prioridad->label() }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Estado</span>
            <span class="detail-value">{{ $orden->estado->label() }}</span>
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
        <div class="detail-row">
            <span class="detail-label">Detalle</span>
            <span class="detail-value">{{ $orden->descripcion }}</span>
        </div>
        @endif
    </div>
    @endif

    <p class="note">Si tienes dudas o inconvenientes con esta orden, por favor comunícate con el coordinador del sistema.</p>
@endsection
