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

    @include('emails.partials.orden-datos')

    <p class="note">
        Ingresa a la aplicación, ve a <strong>Mis Órdenes</strong> y selecciona <em>Editar Reporte</em>
        para corregir y reenviar.
    </p>
@endsection
