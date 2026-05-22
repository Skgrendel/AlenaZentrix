@pushonce('styles')
    <style>
        #mapa-container { z-index: 1; }
        .leaflet-popup-content { margin: 10px 14px; }
        .mapa-popup-btn {
            display: block; margin-top: 8px; text-align: center;
            background: #3B82F6; color: white; padding: 5px 10px;
            border-radius: 6px; text-decoration: none; font-size: 12px;
        }
        .mapa-popup-btn:hover { background: #2563EB; }
        .mapa-popup-table td { padding: 2px 8px 2px 0; font-size: 13px; vertical-align: top; }
        .mapa-popup-table td:first-child { color: #6b7280; white-space: nowrap; }
    </style>
@endpushonce

<div class="space-y-4">
    <div>
        <flux:heading size="xl">Mapa de Ubicaciones</flux:heading>
        <flux:text class="mt-1 text-sm">Visualiza las ubicaciones desde donde los investigadores enviaron sus reportes.</flux:text>
    </div>

    {{-- Panel de filtros --}}
    <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">

            <div>
                <flux:label>Desde</flux:label>
                <flux:input type="date" wire:model="fechaDesde" />
            </div>

            <div>
                <flux:label>Hasta</flux:label>
                <flux:input type="date" wire:model="fechaHasta" />
            </div>

            <div>
                <flux:label>Investigador</flux:label>
                <flux:select wire:model="tecnicoId">
                    <flux:select.option value="">Todos</flux:select.option>
                    @foreach ($tecnicos as $id => $nombre)
                        <flux:select.option value="{{ $id }}">{{ $nombre }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div>
                <flux:label>Estado</flux:label>
                <flux:select wire:model="estadoReporte">
                    <flux:select.option value="">Todos</flux:select.option>
                    <flux:select.option value="pendiente">Pendiente</flux:select.option>
                    <flux:select.option value="revisado">Revisado</flux:select.option>
                    <flux:select.option value="confirmado">Confirmado</flux:select.option>
                </flux:select>
            </div>

            <div>
                <flux:label>N° Orden</flux:label>
                <flux:input type="text" wire:model="ordenTrabajo" placeholder="ORD-..." />
            </div>
        </div>

        <div class="mt-3 flex items-center justify-between">
            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                {{ count($marcadores) }} {{ count($marcadores) === 1 ? 'ubicación encontrada' : 'ubicaciones encontradas' }}
            </flux:text>
            <div class="flex items-center gap-2">
                <flux:button wire:click="limpiarFiltros" variant="ghost" size="sm" icon="x-mark">
                    Limpiar filtros
                </flux:button>
                <flux:button wire:click="filtrar" variant="primary" size="sm" icon="magnifying-glass">
                    Filtrar
                </flux:button>
            </div>
        </div>
    </div>

    {{-- Leyenda --}}
    <div class="flex flex-wrap gap-4 text-xs text-zinc-600 dark:text-zinc-400">
        <span class="flex items-center gap-1.5">
            <span class="inline-block h-3 w-3 rounded-full border-2 border-white shadow" style="background:#EAB308"></span>
            Pendiente
        </span>
        <span class="flex items-center gap-1.5">
            <span class="inline-block h-3 w-3 rounded-full border-2 border-white shadow" style="background:#3B82F6"></span>
            Revisado
        </span>
        <span class="flex items-center gap-1.5">
            <span class="inline-block h-3 w-3 rounded-full border-2 border-white shadow" style="background:#22C55E"></span>
            Confirmado
        </span>
    </div>

    {{-- Contenedor del mapa --}}
    <div wire:ignore class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
        <div id="mapa-container" class="h-[calc(100vh-18rem)] w-full min-h-96"></div>
    </div>
</div>

@script
<script>
    const COLORES = {
        pendiente: '#EAB308',
        revisado:  '#3B82F6',
        confirmado: '#22C55E',
    };

    function crearIcono(estado) {
        const color = COLORES[estado] || '#6B7280';
        return L.divIcon({
            className: '',
            html: `<div style="background:${color};width:13px;height:13px;border-radius:50%;border:2px solid white;box-shadow:0 1px 4px rgba(0,0,0,0.4)"></div>`,
            iconSize: [13, 13],
            iconAnchor: [6, 6],
            popupAnchor: [0, -8],
        });
    }

    function buildPopup(m) {
        const anomalias = Array.isArray(m.anomalias) && m.anomalias.length
            ? m.anomalias.join(', ')
            : '—';
        return `
            <div style="min-width:230px">
                <div style="font-weight:600;font-size:14px;margin-bottom:6px">${m.numero_orden}</div>
                <table class="mapa-popup-table">
                    <tr><td>Técnico</td><td>${m.tecnico}</td></tr>
                    <tr><td>Fecha</td><td>${m.fecha}</td></tr>
                    <tr><td>Estado</td><td>${m.estado}</td></tr>
                    <tr><td>Dirección</td><td>${m.direccion}</td></tr>
                    <tr><td>Servicio</td><td>${m.tipo_servicio}</td></tr>
                    <tr><td>Anomalías</td><td>${anomalias}</td></tr>
                </table>
                <a href="${m.reporte_url}" class="mapa-popup-btn">Ver reporte →</a>
            </div>
        `;
    }

    let map = L.map('mapa-container').setView([4.7110, -74.0721], 6);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19,
    }).addTo(map);

    let clusterGroup = L.markerClusterGroup({ chunkedLoading: true });
    map.addLayer(clusterGroup);

    function renderMarcadores(marcadores) {
        clusterGroup.clearLayers();

        if (!marcadores.length) { return; }

        const bounds = [];

        marcadores.forEach(m => {
            const marker = L.marker([m.lat, m.lng], { icon: crearIcono(m.estado) });
            marker.bindPopup(buildPopup(m));
            clusterGroup.addLayer(marker);
            bounds.push([m.lat, m.lng]);
        });

        map.fitBounds(bounds, { padding: [40, 40], maxZoom: 14 });
    }

    renderMarcadores(@json($marcadores));

    $wire.on('markers-updated', ({ marcadores }) => {
        renderMarcadores(marcadores);
    });
</script>
@endscript
