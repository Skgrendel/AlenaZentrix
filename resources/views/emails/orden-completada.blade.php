@extends('emails.layout')

@section('title', 'Orden Completada')
@section('badge', $orden->numero_orden)
@section('banner_type', 'success')
@section('banner', 'Tu reporte ha sido revisado y la orden ha sido marcada como <strong>Completada</strong>. ¡Buen trabajo!')

@section('content')
    <div class="greeting">Hola{{ $tecnico ? ', ' . $tecnico->nombres : '' }}.</div>
    <div class="intro">
        El coordinador ha revisado tu reporte para la orden <strong>{{ $orden->numero_orden }}</strong>
        y ha confirmado que el trabajo fue realizado correctamente.
    </div>

    <div class="section">
        <div class="section-title">Detalle de la Orden</div>
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
        @if($orden->fecha_completado)
        <div class="detail-row">
            <span class="detail-label">Completada el</span>
            <span class="detail-value" style="color: #2d9e5f; font-weight: 700;">
                {{ $orden->fecha_completado->format('d/m/Y H:i') }}
            </span>
        </div>
        @endif
    </div>
@endsection
