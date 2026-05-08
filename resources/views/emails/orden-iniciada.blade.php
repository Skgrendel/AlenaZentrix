@extends('emails.layout')

@section('title', 'Orden en Progreso — ' . $orden->numero_orden)

@section('content')
    <div class="greeting">Notificación de seguimiento</div>

    <div class="alert warning">
        El técnico <strong>{{ $tecnico->nombres }} {{ $tecnico->apellidos }}</strong>
        ha iniciado la atención de la orden <strong>{{ $orden->numero_orden }}</strong>.
    </div>

    <div class="intro">
        La orden ha cambiado a estado <strong>En Progreso</strong>.
        El técnico se encuentra actualmente en el punto de servicio.
    </div>

    <div class="section">
        <div class="section-title">Punto de Servicio</div>
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
    </div>

    <div class="section">
        <div class="section-title">Técnico Asignado</div>
        <div class="detail-row">
            <span class="detail-label">Nombre</span>
            <span class="detail-value">{{ $tecnico->nombres }} {{ $tecnico->apellidos }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Documento</span>
            <span class="detail-value">{{ $tecnico->numero_documento }}</span>
        </div>
        @if($tecnico->telefono)
        <div class="detail-row">
            <span class="detail-label">Teléfono</span>
            <span class="detail-value">{{ $tecnico->telefono }}</span>
        </div>
        @endif
        @if($orden->fecha_limite)
        <div class="detail-row">
            <span class="detail-label">Fecha Límite</span>
            <span class="detail-value" style="color: #c0392b; font-weight: 700;">
                {{ $orden->fecha_limite->format('d/m/Y H:i') }}
            </span>
        </div>
        @endif
    </div>
@endsection
