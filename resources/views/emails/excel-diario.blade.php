@extends('emails.layout')

@section('title', $asunto)

@section('content')
    <div class="intro" style="color: #333333;">{!! nl2br(e($mensaje)) !!}</div>

    <div class="section">
        <div class="section-title">Registros recibidos desde el {{ $desde }} hasta el {{ $hasta }}</div>

        @if ($registros === 0)
            <div class="alert info">En ese periodo no llegaron registros de suspensiones nuevos, por eso este correo no lleva archivo adjunto.</div>
        @else
            <div class="detail-row">
                <span class="detail-label">Registros nuevos</span>
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
            El Excel adjunto trae los registros que llegaron al sistema en ese periodo (hora de Colombia): cada correo empieza donde terminó el anterior, así no queda nada por fuera.
        @endif
    </p>
@endsection
