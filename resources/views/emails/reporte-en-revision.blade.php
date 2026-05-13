@extends('emails.layout')

@section('title', 'Reporte en Revisión — ' . $orden->numero_orden)

@section('content')
    <div class="greeting">Reporte listo para revisión</div>

    <div class="alert info">
        El investigador <strong>{{ $tecnico->nombres }} {{ $tecnico->apellidos }}</strong>
        ha enviado el reporte de la orden <strong>{{ $orden->numero_orden }}</strong> para revisión.
    </div>

    <div class="intro">
        La orden ha pasado a estado <strong>En Revisión</strong>.
        El investigador está esperando tu aprobación.
    </div>

    @include('emails.partials.orden-datos')

    <div class="section">
        <div class="section-title">Investigador</div>
        <div class="detail-row">
            <span class="detail-label">Nombre</span>
            <span class="detail-value">{{ $tecnico->nombres }} {{ $tecnico->apellidos }}</span>
        </div>
        @if ($tecnico->telefono)
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
