@extends('emails.layout')

@section('title', 'Reporte Rechazado — ' . $orden->numero_orden)

@section('content')
    <div class="greeting">Hola{{ $tecnico ? ', ' . $tecnico->nombres : '' }}.</div>

    <div class="alert danger">
        Tu reporte para la orden <strong>{{ $orden->numero_orden }}</strong> ha sido
        <strong>rechazado</strong>. Debes corregirlo y reenviarlo.
    </div>

    <div class="intro">
        El coordinador ha revisado tu reporte y ha solicitado correcciones.
        Por favor revisa el motivo y edita el reporte desde la aplicación.
    </div>

    <div class="comment-box">
        <div class="comment-label">Motivo del Rechazo</div>
        {{ $comentarios }}
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
    </div>

    <p class="note">
        Ingresa a la aplicación, ve a <strong>Mis Órdenes</strong> y selecciona <em>Editar Reporte</em>
        para corregir y reenviar.
    </p>
@endsection
