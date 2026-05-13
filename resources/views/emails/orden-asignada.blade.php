@extends('emails.layout')

@section('title', 'Orden de Trabajo Asignada — ' . $orden->numero_orden)

@section('content')
    <div class="greeting">¡Hola {{ $tecnico->nombres }} {{ $tecnico->apellidos }}!</div>

    <div class="alert info">
        Se te ha asignado la orden de trabajo <strong>{{ $orden->numero_orden }}</strong>.
        A continuación encontrarás todos los detalles para atenderla.
    </div>

    @include('emails.partials.orden-datos')

    @if ($orden->actividades)
        <div class="section">
            <div class="section-title">Actividades a Realizar</div>
            @foreach ($orden->actividades as $actividad)
                <div class="detail-row">
                    <span style="margin-right: 8px; color: #17222e;">&#8226;</span>
                    <span class="detail-value">{{ $actividad['descripcion'] ?? $actividad }}</span>
                </div>
            @endforeach
        </div>
    @endif

    <p class="note">Si tienes dudas o inconvenientes con esta orden, por favor comunícate con el coordinador del sistema.</p>
@endsection
