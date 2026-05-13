# Diseño: Unificación Capturas/Bandas → Gestión Legal

**Fecha:** 2026-05-12  
**Estado:** Aprobado

---

## Problema

`TipoServicio::Capturas` y `TipoServicio::Bandas` son dos casos del enum que comparten
idéntica lógica de formulario, submit y vista de revisión. Ambos pertenecen al área legal
de la empresa (defraudación de fluidos, seguimiento en Fiscalía). Mantenerlos separados
genera duplicación de código y limita la capacidad de agregar nuevos sub-tipos legales
sin tocar el enum.

---

## Solución

Reemplazar `Capturas` y `Bandas` por un único tipo **`GestionLegal`**, con un campo
`tipo_gestion_legal` en `orden_jobs` que almacena el sub-tipo específico — patrón
idéntico al que ya usa `Investigacion` con `tipo_investigacion`.

---

## Cambios en la base de datos

### Migración `2024_05_18_084152_create_orden_jobs_table.php` (edición directa + refresh)

Agregar columna:
```php
$table->string('tipo_gestion_legal')->nullable(); // sub-tipo para GestionLegal
```

El campo `numero_noticia_criminal` se mantiene nullable — relevante para varios sub-tipos
pero nunca requerido.

---

## Enum `TipoServicio`

### Casos que desaparecen
- `Capturas = 'capturas'`
- `Bandas = 'bandas'`

### Caso nuevo
- `GestionLegal = 'gestion_legal'`

### Métodos afectados
- `label()` → `'Gestión Legal'`
- `color()` → `'info'` (heredado de Capturas)
- `tieneNoticiaCriminal()` → renombrar o eliminar; ya no aplica por tipo, sino por sub-tipo
- `tieneTecnicoAsignado()` → `GestionLegal` sí tiene técnico asignado (mismo que antes)

---

## Sub-tipos de Gestión Legal

| Valor DB             | Etiqueta visible            |
|----------------------|-----------------------------|
| `capturas`           | Capturas Criminales         |
| `bandas`             | Desarticulación de Bandas   |
| `querella`           | Querella Penal              |
| `seguimiento_denuncia` | Seguimiento a Denuncia    |
| `diligencia_judicial` | Diligencia Judicial        |
| `incautacion_equipos` | Incautación de Equipos     |

---

## `OrdenCreate.php`

La sección conjunta "Datos de la Orden" (antes Capturas + Bandas) se reemplaza por
"Datos de Gestión Legal" visible cuando `tipo_servicio === 'gestion_legal'`:

```
- Select: tipo_gestion_legal (required, 6 opciones)
- TextInput: numero_noticia_criminal (nullable)
- Textarea: objetivo — "Objetivo de la Gestión Legal" (required)
```

---

## `ReporteCreate.php`

- `capturasSchema()` → renombrar a `gestionLegalSchema()` (sin cambios en secciones)
- `form()` match: `GestionLegal => $this->gestionLegalSchema()`
  - Eliminar el caso `Capturas, Bandas`
- `submit()` match: `GestionLegal => $this->submitServicioGenerico($data)`
  - Eliminar los casos `Capturas` y `Bandas`

---

## `ReporteRevisar.php`

`datosServicioDisplay()`: agregar rama `GestionLegal`:
```php
if ($tipo === TipoServicio::GestionLegal) {
    return [
        'tipo_gestion_legal' => $datos['tipo_gestion_legal'] ?? null,  // sub-tipo
        'numero_noticia_criminal' => $this->orden->numero_noticia_criminal,
        'actividades' => $datos['actividades'] ?? null,
    ];
}
```
Eliminar la rama `Capturas/Bandas`.

---

## `reporte-revisar.blade.php`

Reemplazar el bloque `@elseif(in_array(...Capturas, ...Bandas))` por
`@elseif($orden->tipo_servicio === GestionLegal)`:

Muestra:
- Badge con el label del sub-tipo (resuelto desde `$datosServicio['tipo_gestion_legal']`)
- N° Noticia Criminal si existe
- Resto de campos idénticos a la vista actual de Capturas/Bandas

---

## Archivos impactados

| Archivo | Tipo de cambio |
|---------|---------------|
| `database/migrations/2024_05_18_084152_create_orden_jobs_table.php` | Agregar columna `tipo_gestion_legal` |
| `app/Enums/TipoServicio.php` | Reemplazar casos, actualizar métodos |
| `app/Livewire/Coordinador/OrdenCreate.php` | Fusionar secciones, agregar select sub-tipo |
| `app/Livewire/Tecnico/ReporteCreate.php` | Renombrar schema, simplificar match |
| `app/Livewire/Coordinador/ReporteRevisar.php` | Actualizar datosServicioDisplay |
| `resources/views/livewire/coordinador/reporte-revisar.blade.php` | Actualizar sección legal |

---

## Consideraciones

- **Datos existentes:** Con migrate:refresh los datos existentes se pierden (entorno local).
  Si se requiere migración de producción, se necesita un script que actualice
  `tipo_servicio = 'capturas'/'bandas'` → `'gestion_legal'` y ponga `tipo_gestion_legal`
  en el valor correspondiente.
- **PDF/Informes:** Verificar si hay vistas PDF que referencien `Capturas` o `Bandas`
  y actualizar sus condiciones.
