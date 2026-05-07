@extends('emails.layout')

@section('title', 'Reporte Enviado para Revisión')
@section('badge', $orden->numero_orden)
@section('banner_type', 'info')
@section('banner')El técnico <strong>{{ $tecnico->nombres }} {{ $tecnico->apellidos }}</strong> ha enviado el reporte para revisión.@endsection

@section('content')
    <div class="greeting">Reporte listo para revisión</div>
    <div class="intro">
        La orden <strong>{{ $orden->numero_orden }}</strong> ha pasado a estado <strong>En Revisión</strong>.
        El técnico ha completado su reporte y está esperando tu aprobación.
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
        <div class="section-title">Técnico</div>
        <div class="detail-row">
            <span class="detail-label">Nombre</span>
            <span class="detail-value">{{ $tecnico->nombres }} {{ $tecnico->apellidos }}</span>
        </div>
        @if($tecnico->celular)
        <div class="detail-row">
            <span class="detail-label">Teléfono</span>
            <span class="detail-value">{{ $tecnico->celular }}</span>
        </div>
        @endif
    </div>

    <p class="note">
        Ingresa a <strong>Órdenes de Trabajo</strong> y haz clic en <em>Revisar</em> para ver el reporte completo.
    </p>
@endsection
