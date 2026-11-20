@extends('emails.layout')

@section('title', $asunto)

@section('content')
    <div class="intro" style="color: #333333;">{!! nl2br(e($mensaje)) !!}</div>

    <div class="section">
        <div class="section-title">Resumen del {{ $fecha }} (hasta las {{ $hasta }})</div>

        @if ($registros === 0)
            <div class="alert info">Hoy no hubo registros de suspensiones, por eso este correo no lleva archivo adjunto.</div>
        @else
            <div class="detail-row">
                <span class="detail-label">Registros del día</span>
                <span class="detail-value">{{ $registros }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Con errores</span>
                <span class="detail-value">{{ $conErrores }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Con alertas</span>
                <span class="detail-value">{{ $conAlertas }}</span>
            </div>
        @endif
    </div>

    <p class="note">
        {{ $empresa }} · Este correo se envía de forma automática desde Alena Zentrix.
        @if ($registros > 0)
            El Excel adjunto trae los registros desde las 00:00 hasta las {{ $hasta }} (hora de Colombia).
        @endif
    </p>
@endsection
