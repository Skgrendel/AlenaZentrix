# PDF Consolidado de Vigilancia

**Fecha:** 2026-07-01
**Estado:** Aprobado

## Contexto

Las Órdenes de Vigilancia (`TipoServicio::Vigilancia`) acumulan múltiples reportes de turno en la tabla `reportes`, uno por cada turno registrado por el investigador. Todos los demás tipos de servicio generan un PDF por reporte individual. Este diseño agrega un PDF consolidado que reúne todos los turnos de una misma orden en un único documento.

## Objetivo

Generar un informe PDF descargable por los coordinadores que consolide todos los dispositivos (turnos) de una Orden de Vigilancia, siguiendo la misma estructura visual que los informes existentes (investigación, fraude, etc.).

## Estructura del PDF

El documento usa el mismo sistema de header/footer fijo, numeración de páginas y estilos que `reporte-investigacion.blade.php`.

### Secciones en orden

1. **Header fijo** — Título "Informe de Vigilancia", número de orden, fecha de generación, logo PRODERI.
2. **Footer fijo** — "Informe de Vigilancia — Uso Interno y Confidencial".
3. **Antecedentes** — Condicional (`@if`), tomado de `$orden->antecedente`.
4. **Objetivos** — Condicional (`@if`), tomado de `$orden->objetivo`.
5. **Bloque por turno** — Repetido para cada reporte, ordenado por `created_at` ASC:
   - **Título de sección**: `TURNO N° X — dd/mm/YYYY`
   - **Tabla técnica**: fecha, horario (inicio – fin), investigador, resultado (nombre del `ResultadoReporte`)
   - **Novedades** — Sub-sección visible solo si `datos_servicio.hay_novedad === true`:
     - Descripción de novedades (`datos_servicio.descripcion_novedad`)
     - Badge de reincidencia si `datos_servicio.hay_reincidencia === true`
   - **Observaciones** — Siempre presente, texto libre del campo `reporte->observaciones`
   - **GeoReferencia** — Condicional, si `reporte->geo_referencia` no está vacío
   - **Evidencias fotográficas** — Todas las imágenes del turno, en tabla de 2 columnas, embebidas en base64 desde S3. Numeradas como "Evidencia 1", "Evidencia 2"...
6. **Generador del informe** — Al final del documento: nombre completo y email del usuario autenticado, fecha y hora de generación.

## Datos que se pasan a la vista

```php
[
    'orden'          => OrdenJob,          // con relaciones personal, creadoPor, proceso
    'reportes'       => Collection,        // todos los reportes de la orden, ordenados por created_at ASC
    'resultados'     => array,             // mapa [id => nombre] de ResultadoReporte para lookup rápido
    'logoProderi'    => string,            // base64 data URI
    'imagenesPerTurno' => array,           // [reporte_id => [base64, ...]] para cada turno
    'fechaGeneracion'  => string,          // now()->format('d-m-Y')
    'generador'      => array,             // ['nombre' => ..., 'email' => ...]
    'datosOrden'     => array,             // buildDatosOrden($orden) — mismo helper existente
]
```

## Componentes a crear / modificar

| Archivo | Acción |
|---|---|
| `app/Http/Controllers/ReportePdfController.php` | Agregar método `DownLoadInformeVigilancia(OrdenJob $orden)` |
| `resources/views/pdf/reporte-vigilancia.blade.php` | Crear vista nueva |
| `routes/operaciones.php` | Agregar ruta GET dentro del middleware existente |
| `resources/views/livewire/coordinador/reportes-vigilancia-index.blade.php` | Agregar botón descarga (siempre visible en el header) |
| Vista de revisión individual (`ReporteRevisar`) | Agregar botón solo si `$orden->estado === EstadoOrden::Completada` |

## Ruta

```
GET operaciones/ordenes-trabajo/{orden}/reporte-vigilancia
name: operaciones.ordenes-trabajo.reporte-vigilancia
controlador: ReportePdfController@DownLoadInformeVigilancia
middleware: auth, verified, permission:ordenes.ver
nombre de descarga: reporte-vigilancia-{numero_orden}.pdf
```

## Lógica del controlador

```
1. abort_unless tipo_servicio === Vigilancia, 403
2. abort_unless auth()->user()->can('informes'), 403
3. ini_set('memory_limit', '256M')
4. Cargar orden con: personal, creadoPor, proceso
5. Cargar todos los reportes de la orden con: personal.usuario — ordenados por created_at ASC
6. Para cada reporte, resolver imágenes desde S3 usando resolveImagenes() existente
7. Precargar mapa de ResultadoReporte para lookup eficiente (evitar N+1)
8. Construir $generador desde auth()->user()
9. Construir $datosOrden con buildDatosOrden() existente
10. Cargar vista pdf.reporte-vigilancia con todas las variables
11. Descargar como PDF A4 portrait
```

## Permisos

Mismo permiso que los otros informes: `permission:informes` en el controller (via `abort_unless auth()->user()->can('informes')`), con la ruta protegida por `permission:ordenes.ver`.

## Botón en la UI

- **`reportes-vigilancia-index.blade.php`**: botón `flux:button` con `icon="arrow-down-tray"` y `href="{{ route('operaciones.ordenes-trabajo.reporte-vigilancia', $orden) }}"`, siempre visible en el header junto al botón "Volver".
- **`resources/views/livewire/coordinador/reporte-revisar.blade.php`**: botón equivalente, siguiendo el mismo patrón `@if ($orden->tipo_servicio === TipoServicio::Vigilancia && $orden->estado === EstadoOrden::Completada && auth()->user()->can('informes'))`. La ruta **no** lleva query param `?reporte=` ya que el PDF cubre todos los turnos de la orden.

## Consideraciones

- Las imágenes se resuelven con el helper `resolveImagenes()` ya existente en el controlador (fetch desde S3 + encode base64).
- El orden de los turnos es cronológico ascendente (`created_at ASC`), igual que la tabla en la UI.
- Si una orden no tiene reportes, el PDF se genera igualmente con un mensaje "Sin turnos registrados".
- No se incluye bloque de firma formal — solo datos del generador al pie del documento.
