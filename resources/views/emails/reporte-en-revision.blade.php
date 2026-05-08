@extends('emails.layout')

@section('title', 'Reporte en Revisión — ' . $orden->numero_orden)

@section('content')
    <div class="greeting">Reporte listo para revisión</div>

    <div class="alert info">
        El técnico <strong>{{ $tecnico->nombres }} {{ $tecnico->apellidos }}</strong>
        ha enviado el reporte de la orden <strong>{{ $orden->numero_orden }}</strong> para revisión.
    </div>

    <div class="intro">
        La orden ha pasado a estado <strong>En Revisión</strong>.
        El técnico está esperando tu aprobación.
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
        @if($tecnico->telefono)
        <div class="detail-row">
            <span class="detail-label">Teléfono</span>
            <span class="detail-value">{{ $tecnico->telefono }}</span>
        </div>
        @endif
    </div>

    <p class="note">
        Ingresa a <strong>Órdenes de Trabajo</strong> y haz clic en <em>Revisar</em> para ver el reporte completo.
    </p>
@endsection
