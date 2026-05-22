@extends('emails.layout')

@section('title', $ordenes->count() . ' Órdenes Asignadas — Revisión de Medidores')

@section('content')
    <div class="greeting">¡Hola {{ $investigador->nombres }} {{ $investigador->apellidos }}!</div>

    <div class="alert info">
        Se te han asignado <strong>{{ $ordenes->count() }} órdenes</strong> de Revisión de Medidores.
        A continuación encontrarás el listado completo para atenderlas.
    </div>

    <div class="section">
        <div class="section-title">Órdenes Asignadas ({{ $ordenes->count() }})</div>

        <table style="width:100%; border-collapse: collapse; font-size: 8.5pt;">
            <thead>
                <tr style="background:#003E72; color:white;">
                    <th style="padding:6px 8px; text-align:left;">N° Orden</th>
                    <th style="padding:6px 8px; text-align:left;">Contrato</th>
                    <th style="padding:6px 8px; text-align:left;">Cliente</th>
                    <th style="padding:6px 8px; text-align:left;">Dirección</th>
                    <th style="padding:6px 8px; text-align:left;">Medidor</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($ordenes as $index => $orden)
                    <tr style="background: {{ $index % 2 === 0 ? '#f9fafb' : '#ffffff' }};">
                        <td style="padding:5px 8px; border-bottom:1px solid #e5e7eb;">{{ $orden->numero_orden }}</td>
                        <td style="padding:5px 8px; border-bottom:1px solid #e5e7eb;">{{ $orden->contrato }}</td>
                        <td style="padding:5px 8px; border-bottom:1px solid #e5e7eb;">{{ $orden->cliente }}</td>
                        <td style="padding:5px 8px; border-bottom:1px solid #e5e7eb;">{{ $orden->direccion }}, {{ $orden->barrio }}</td>
                        <td style="padding:5px 8px; border-bottom:1px solid #e5e7eb;">{{ $orden->medidor }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="note">Puedes ingresar al sistema para ver el detalle completo de cada orden y comenzar a reportar tus resultados.</p>
@endsection
