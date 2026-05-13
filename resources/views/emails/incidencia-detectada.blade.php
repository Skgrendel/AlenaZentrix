@extends('emails.layout')

@section('title', 'Alerta de Incidencia — ' . $orden->numero_orden)

@section('content')
    <div class="greeting">Incidencias detectadas en campo</div>

    <div class="alert danger">
        Se detectaron <strong>{{ count($incidencias) }}</strong> incidencia(s) en el reporte
        de la orden <strong>{{ $orden->numero_orden }}</strong> que requieren atención inmediata.
    </div>

    <div class="intro">
        El investigador asignado ha reportado incidencias para esta orden.
    </div>

    <div class="section">
        <div class="section-title">Incidencias Reportadas</div>
        @foreach ($incidencias as $incidencia)
            <div class="detail-row" style="color: #7f1d1d; font-weight: 600;">
                <span style="margin-right: 10px;">&#9888;</span>
                <span>{{ $incidencia }}</span>
            </div>
        @endforeach
    </div>

    @if ($fotoDetectorUrl)
        <div class="section">
            <div class="section-title">Foto del Detector de Fuga</div>
            <div style="padding: 16px; text-align: center;">
                @if ($fotoDetectorData)
                    <img src="{{ $message->embedData($fotoDetectorData, $fotoDetectorNombre ?? 'foto-detector-fuga.jpg') }}"
                         alt="Foto del detector de fuga"
                         style="max-width: 100%; border-radius: 6px; border: 1px solid #e5e7eb;">
                @else
                    <img src="{{ $fotoDetectorUrl }}"
                         alt="Foto del detector de fuga"
                         style="max-width: 100%; border-radius: 6px; border: 1px solid #e5e7eb;">
                @endif
            </div>
        </div>
    @endif

    @include('emails.partials.orden-datos')

    @if ($orden->personal)
        <div class="section">
            <div class="section-title">Investigador Responsable</div>
            <div class="detail-row">
                <span class="detail-label">Nombre</span>
                <span class="detail-value">{{ $orden->personal->nombres }} {{ $orden->personal->apellidos }}</span>
            </div>
            @if ($orden->personal->telefono)
                <div class="detail-row">
                    <span class="detail-label">Teléfono</span>
                    <span class="detail-value">{{ $orden->personal->telefono }}</span>
                </div>
            @endif
        </div>
    @endif

    <p class="note">Ingresa al sistema y revisa el reporte completo en <strong>Órdenes de Trabajo</strong>.</p>
@endsection
