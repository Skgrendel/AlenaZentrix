@extends('emails.layout')

@section('title', 'Alerta de Incidencia — ' . $orden->numero_orden)

@section('content')
    <div class="greeting">Incidencias detectadas en campo</div>

    <div class="alert danger">
        Se detectaron <strong>{{ count($incidencias) }}</strong> incidencia(s) en el reporte
        de la orden <strong>{{ $orden->numero_orden }}</strong> que requieren atención inmediata.
    </div>

    <div class="intro">
        El técnico asignado ha reportado incidencias para esta orden.
    </div>

    <div class="section">
        <div class="section-title">Incidencias Reportadas</div>
        @foreach($incidencias as $incidencia)
        <div class="detail-row" style="color: #7f1d1d; font-weight: 600;">
            <span style="margin-right: 10px;">&#9888;</span>
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

    <p class="note">Ingresa al sistema y revisa el reporte completo en <strong>Órdenes de Trabajo</strong>.</p>
@endsection
