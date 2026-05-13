{{-- Datos generales comunes a todos los tipos --}}
<div class="section">
    <div class="section-title">Datos de la Orden</div>
    <div class="detail-row">
        <span class="detail-label">N° Orden</span>
        <span class="detail-value">{{ $orden->numero_orden }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Tipo de Servicio</span>
        <span class="detail-value">{{ $orden->tipo_servicio->label() }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Prioridad</span>
        <span class="detail-value">{{ $orden->prioridad->label() }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Estado</span>
        <span class="detail-value">{{ $orden->estado->label() }}</span>
    </div>
    @if ($orden->fecha_asignacion)
        <div class="detail-row">
            <span class="detail-label">Fecha Asignación</span>
            <span class="detail-value">{{ $orden->fecha_asignacion->format('d/m/Y H:i') }}</span>
        </div>
    @endif
    @if ($orden->fecha_limite)
        <div class="detail-row">
            <span class="detail-label">Fecha Límite</span>
            <span class="detail-value" style="color: #c0392b; font-weight: 700;">
                {{ $orden->fecha_limite->format('d/m/Y H:i') }}
            </span>
        </div>
    @endif
</div>

{{-- Datos específicos según tipo de servicio --}}
@if (in_array($orden->tipo_servicio, [\App\Enums\TipoServicio::Fraudes, \App\Enums\TipoServicio::Vigilancia]))
    <div class="section">
        <div class="section-title">Punto de Servicio</div>
        @if ($orden->contrato)
            <div class="detail-row">
                <span class="detail-label">Contrato</span>
                <span class="detail-value">{{ $orden->contrato }}</span>
            </div>
        @endif
        @if ($orden->cliente)
            <div class="detail-row">
                <span class="detail-label">Cliente</span>
                <span class="detail-value">{{ $orden->cliente }}</span>
            </div>
        @endif
        @if ($orden->direccion)
            <div class="detail-row">
                <span class="detail-label">Dirección</span>
                <span class="detail-value">{{ $orden->direccion }}{{ $orden->barrio ? ', ' . $orden->barrio : '' }}</span>
            </div>
        @endif
        @if ($orden->medidor)
            <div class="detail-row">
                <span class="detail-label">N° Medidor</span>
                <span class="detail-value">{{ $orden->medidor }}</span>
            </div>
        @endif
        @if ($orden->tipo_medidor)
            <div class="detail-row">
                <span class="detail-label">Tipo Medidor</span>
                <span class="detail-value">{{ $orden->tipo_medidor }}</span>
            </div>
        @endif
        @if ($orden->ciclo)
            <div class="detail-row">
                <span class="detail-label">Ciclo</span>
                <span class="detail-value">{{ $orden->ciclo }}</span>
            </div>
        @endif
    </div>

@elseif ($orden->tipo_servicio === \App\Enums\TipoServicio::GestionLegal)
    @php
        $subtiposGL = [
            'capturas'             => 'Capturas Criminales',
            'bandas'               => 'Desarticulación de Bandas',
            'querella'             => 'Querella Penal',
            'seguimiento_denuncia' => 'Seguimiento a Denuncia',
            'diligencia_judicial'  => 'Diligencia Judicial',
        ];
        $tipoGLLabel = $subtiposGL[$orden->tipo_gestion_legal ?? ''] ?? ($orden->tipo_gestion_legal ?? '—');
    @endphp
    <div class="section">
        <div class="section-title">Gestión Legal</div>
        <div class="detail-row">
            <span class="detail-label">Tipo</span>
            <span class="detail-value">{{ $tipoGLLabel }}</span>
        </div>
        @if ($orden->numero_noticia_criminal)
            <div class="detail-row">
                <span class="detail-label">N° Noticia Criminal</span>
                <span class="detail-value">{{ $orden->numero_noticia_criminal }}</span>
            </div>
        @endif
        @if ($orden->objetivo)
            <div class="detail-row">
                <span class="detail-label">Objetivo</span>
                <span class="detail-value">{{ $orden->objetivo }}</span>
            </div>
        @endif
        @if ($orden->direccion)
            <div class="detail-row">
                <span class="detail-label">Dirección</span>
                <span class="detail-value">{{ $orden->direccion }}{{ $orden->barrio ? ', ' . $orden->barrio : '' }}</span>
            </div>
        @endif
    </div>

@elseif ($orden->tipo_servicio === \App\Enums\TipoServicio::Investigacion)
    <div class="section">
        <div class="section-title">Investigación</div>
        @if ($orden->tipo_investigacion)
            <div class="detail-row">
                <span class="detail-label">Tipo</span>
                <span class="detail-value">{{ $orden->tipo_investigacion }}</span>
            </div>
        @endif
        @if ($orden->numero_noticia_criminal)
            <div class="detail-row">
                <span class="detail-label">N° Noticia Criminal</span>
                <span class="detail-value">{{ $orden->numero_noticia_criminal }}</span>
            </div>
        @endif
        @if ($orden->objetivo)
            <div class="detail-row">
                <span class="detail-label">Objetivo</span>
                <span class="detail-value">{{ $orden->objetivo }}</span>
            </div>
        @endif
    </div>

@elseif ($orden->tipo_servicio === \App\Enums\TipoServicio::Gestion_Riesgo)
    <div class="section">
        <div class="section-title">Gestión de Riesgo</div>
        @if ($orden->objetivo)
            <div class="detail-row">
                <span class="detail-label">Objetivo</span>
                <span class="detail-value">{{ $orden->objetivo }}</span>
            </div>
        @endif
        @if ($orden->direccion)
            <div class="detail-row">
                <span class="detail-label">Dirección</span>
                <span class="detail-value">{{ $orden->direccion }}{{ $orden->barrio ? ', ' . $orden->barrio : '' }}</span>
            </div>
        @endif
    </div>
@endif
