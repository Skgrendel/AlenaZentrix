@extends('emails.layout')

@section('title', 'Orden en Progreso — ' . $orden->numero_orden)

@section('content')
    <div class="greeting">Notificación de seguimiento</div>

    <div class="alert warning">
        El investigador <strong>{{ $tecnico->nombres }} {{ $tecnico->apellidos }}</strong>
        ha iniciado la atención de la orden <strong>{{ $orden->numero_orden }}</strong>.
    </div>

    <div class="intro">
        La orden ha cambiado a estado <strong>En Progreso</strong>.
        El investigador se encuentra actualmente atendiendo esta orden.
    </div>

    @include('emails.partials.orden-datos')

    <div class="section">
        <div class="section-title">Investigador Asignado</div>
        <div class="detail-row">
            <span class="detail-label">Nombre</span>
            <span class="detail-value">{{ $tecnico->nombres }} {{ $tecnico->apellidos }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Documento</span>
            <span class="detail-value">{{ $tecnico->numero_documento }}</span>
        </div>
        @if ($tecnico->telefono)
            <div class="detail-row">
                <span class="detail-label">Teléfono</span>
                <span class="detail-value">{{ $tecnico->telefono }}</span>
            </div>
        @endif
    </div>
@endsection
