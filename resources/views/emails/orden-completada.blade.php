@extends('emails.layout')

@section('title', 'Orden Completada — ' . $orden->numero_orden)

@section('content')
    <div class="greeting">Hola{{ $tecnico ? ', ' . $tecnico->nombres : '' }}.</div>

    <div class="alert success">
        Tu reporte ha sido revisado y la orden <strong>{{ $orden->numero_orden }}</strong>
        ha sido marcada como <strong>Completada</strong>. ¡Buen trabajo!
    </div>

    <div class="intro">
        El coordinador ha confirmado que el trabajo fue realizado correctamente.
    </div>

    @include('emails.partials.orden-datos')

    @if ($orden->fecha_completado)
        <div class="section">
            <div class="section-title">Cierre</div>
            <div class="detail-row">
                <span class="detail-label">Completada el</span>
                <span class="detail-value" style="color: #2d9e5f; font-weight: 700;">
                    {{ $orden->fecha_completado->format('d/m/Y H:i') }}
                </span>
            </div>
        </div>
    @endif
@endsection
