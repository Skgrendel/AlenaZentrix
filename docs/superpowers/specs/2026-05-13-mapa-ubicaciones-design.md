# Módulo Mapa de Ubicaciones

**Fecha:** 2026-05-13
**Estado:** Aprobado

## Objetivo

Agregar un módulo de mapa interactivo al sidebar de la aplicación que permita a coordinadores y administradores visualizar en tiempo real las ubicaciones geográficas desde donde los investigadores/técnicos enviaron sus reportes de campo. Permite seguimiento geográfico y supervisión operativa.

## Alcance

- Solo visible para roles `coordinador` y `administrador`
- Acceso controlado por permiso `mapa.ver`
- Módulo propio en el sidebar
- Sin dependencias de servicios externos de pago (usa OpenStreetMap)

## Arquitectura

### Permiso

Nuevo permiso `mapa.ver` asignado a los roles `coordinador` y `administrador`.

### Ruta

```
GET /mapa  →  mapa.index
```

Registrada en `routes/mapa.php`, incluida desde `routes/web.php`, dentro del grupo `auth + verified + permission:mapa.ver`.

### Componente Livewire

- **Clase:** `app/Livewire/Mapa/MapaIndex.php`
- **Vista:** `resources/views/livewire/mapa/mapa-index.blade.php`
- **Layout:** `<x-layouts.app>` (sidebar existente)

### Sidebar

Nueva entrada en el sidebar, dentro de un grupo **Mapa** independiente, visible con `@can('mapa.ver')`:

```blade
@can('mapa.ver')
<flux:sidebar.group :heading="__('Mapa')" class="grid">
    <flux:sidebar.item icon="map" :href="route('mapa.index')"
        :current="request()->routeIs('mapa.*')" wire:navigate>
        {{ __('Mapa de Ubicaciones') }}
    </flux:sidebar.item>
</flux:sidebar.group>
@endcan
```

### Dependencias npm

- `leaflet` — librería de mapas principal
- `@changey/leaflet-markercluster` o `leaflet.markercluster` — plugin de agrupación de marcadores

## Datos

### Propiedades de filtro (Livewire)

| Propiedad | Tipo | Default |
|---|---|---|
| `$fechaDesde` | `string` | Hace 30 días |
| `$fechaHasta` | `string` | Hoy |
| `$tecnicoId` | `?string` | `null` |
| `$estadoReporte` | `?string` | `null` |
| `$ordenTrabajo` | `?string` | `null` |

### Consulta

```php
reportes::with(['ubicacion', 'personal', 'ordenJob'])
    ->whereHas('ubicacion', fn($q) => $q->whereNotNull('latitud')->whereNotNull('longitud'))
    ->whereBetween('created_at', [$fechaDesde, $fechaHasta])
    ->when($tecnicoId, fn($q) => $q->where('personals_id', $tecnicoId))
    ->when($estadoReporte, fn($q) => $q->where('estado', $estadoReporte))
    ->when($ordenTrabajo, fn($q) => $q->where('numero_orden', 'like', "%$ordenTrabajo%"))
    ->get()
```

### Estructura JSON por marcador

```json
{
  "lat": "-12.0464",
  "lng": "-77.0428",
  "tecnico": "Juan Pérez",
  "fecha": "2026-05-10",
  "numero_orden": "ORD-001",
  "estado": "confirmado",
  "direccion": "Av. Principal 123",
  "tipo_servicio": "Gas Natural",
  "anomalias": ["Fuga detectada"],
  "reporte_url": "/operaciones/ordenes-trabajo/{orden_job_id}"
}
```

- `tipo_servicio` se extrae del campo `datos_servicio` JSON del reporte
- `anomalias` se extrae del campo `anomalia` JSON del reporte
- `reporte_url` se genera con `route('operaciones.ordenes-trabajo.show', $reporte->orden_job_id)`

Este array se despacha a Leaflet mediante `$dispatch('mapa-actualizado', { marcadores: [...] })` cada vez que cambian los filtros.

## Interfaz

### Layout

- **Panel de filtros:** barra horizontal en la parte superior con los 4 filtros (fecha desde, fecha hasta, técnico, estado, número de orden) + botón "Limpiar filtros"
- **Mapa:** debajo del panel, altura `h-[calc(100vh-12rem)]` para ocupar el espacio restante de la pantalla

### Marcadores por estado

| Estado | Color |
|---|---|
| `pendiente` | Amarillo `#EAB308` |
| `revisado` | Azul `#3B82F6` |
| `confirmado` | Verde `#22C55E` |

### Clustering

- Los marcadores cercanos se agrupan automáticamente con `leaflet.markercluster`
- El contador muestra la cantidad de reportes agrupados
- Al hacer zoom, los clusters se separan en marcadores individuales

### Popup al hacer clic

Tarjeta con todos los campos del JSON del marcador + botón "Ver reporte" que navega al detalle de la orden (`wire:navigate`).

### Inicialización del mapa

- Leaflet se inicializa en `@script` dentro de la vista Blade
- Escucha el evento Livewire `mapa-actualizado` para limpiar marcadores anteriores y renderizar los nuevos
- Al cargar marcadores, el mapa centra automáticamente usando `fitBounds`
- Centro inicial: coordenadas fijas de Colombia (`lat: 4.7110, lng: -74.0721`, zoom 6) mientras no haya marcadores cargados; una vez cargados, `fitBounds` toma el control

## Testing

**Archivo:** `tests/Feature/Mapa/MapaIndexTest.php`

Casos a cubrir:

1. Usuario sin permiso `mapa.ver` recibe 403 al acceder a `/mapa`
2. Coordinador con permiso `mapa.ver` puede acceder a `/mapa` (200)
3. Administrador con permiso `mapa.ver` puede acceder a `/mapa` (200)
4. El componente carga solo reportes con `ubicacion` con `latitud` y `longitud` no nulos
5. El filtro por fecha reduce correctamente los resultados
6. El filtro por técnico reduce correctamente los resultados
7. El filtro por estado reduce correctamente los resultados
8. El filtro por número de orden reduce correctamente los resultados
9. Reportes sin `ubicacion` no aparecen en los marcadores retornados
