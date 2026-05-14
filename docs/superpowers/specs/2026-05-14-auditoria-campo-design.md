# Auditorías de Campo — Design Spec

## Goal
Add a new service type "Auditorías de Campo" with two subtypes (Visita de Calidad Emergencia, Visita de Calidad Trabajos Varios) to the OrdenJob lifecycle, including a full 9-section field report form with canvas-based digital signatures.

## Architecture

Follows the exact existing pattern of `GestionLegal`:
- New `TipoServicio::AuditoriaCampo` enum case
- New nullable column `tipo_auditoria_campo` on `orden_jobs`
- Report data stored in `datos_servicio` JSON on `reportes` (including signatures as base64)
- `signature_pad` npm package (no CDN), imported in `resources/js/app.js`, exposed globally for Alpine.js

## Tech Stack
Laravel 13, Livewire 4, Filament Forms, Alpine.js, signature_pad (npm), Tailwind CSS v4, Flux UI

---

## Data Model

### `orden_jobs` — new column
```
tipo_auditoria_campo: string nullable
  values: 'visita_calidad_emergencia' | 'visita_calidad_trabajos_varios'
```

### `reportes.datos_servicio` JSON shape for AuditoriaCampo
```json
{
  "fecha_hora_atencion": "2026-05-14T10:30",
  "ciudad": "Barranquilla",
  "celular_usuario": "3001234567",
  "comentario_usuario": "...",
  "comentario_tecnico": "...",
  "nombre_atiende": "Juan Pérez",
  "vinculo_usuario": "familiar",
  "celular_atiende": "3009876543",
  "recibio_visita": true,
  "se_realizo_revision": true,
  "fecha_ejecucion": "2026-05-10",
  "tiempo_ejecucion": "2 horas",
  "descripcion_trabajo": "...",
  "se_detecto_fuga": false,
  "ppm": null,
  "vol_porcentaje": null,
  "problema_persiste": false,
  "evidencia_reparacion": true,
  "servicio_correcto": true,
  "se_usaron_materiales": false,
  "quien_suministro_materiales": null,
  "usuario_asumio_costo": false,
  "valor_pagado": null,
  "medio_pago": null,
  "entrego_factura": false,
  "tecnico_dejo_documento": "IVT firmado",
  "informaron_no_estaba": false,
  "autoriza": true,
  "firma_usuario": "data:image/png;base64,...",
  "cedula_usuario": "12345678",
  "firma_auditor": "data:image/png;base64,...",
  "cedula_auditor": "87654321"
}
```

---

## Files Changed / Created

| File | Action |
|------|--------|
| `app/Enums/TipoServicio.php` | Modify — add `AuditoriaCampo` case |
| `database/migrations/..._add_tipo_auditoria_campo_to_orden_jobs.php` | Create |
| `app/Livewire/Coordinador/OrdenCreate.php` | Modify — add subtype selector |
| `app/Livewire/Coordinador/OrdenesTrabajoIndex.php` | Modify — add subtype to edit form |
| `app/Livewire/Tecnico/ReporteCreate.php` | Modify — add schema + submit method + 2 public signature properties |
| `app/Livewire/Coordinador/ReporteRevisar.php` | Modify — add `datosServicioDisplay()` case |
| `resources/views/livewire/tecnico/reporte-create.blade.php` | Modify — add signature canvas Alpine components |
| `resources/views/livewire/coordinador/reporte-revisar.blade.php` | Modify — display signatures as `<img>` |
| `resources/js/app.js` | Modify — import and expose `SignaturePad` globally |
| `package.json` + `package-lock.json` | Modify — add `signature_pad` dependency |

---

## Form Schema (auditoriaCampoSchema)

### Section 1 — Información General
Read-only placeholders (from orden): OT, Contrato, Nombre del Usuario, Dirección.
Editable: `fecha_hora_atencion`* (DateTimePicker), `ciudad` (TextInput), `celular_usuario` (TextInput), `comentario_usuario` (Textarea), `comentario_tecnico` (Textarea).

### Section 2 — Datos de Quién Atiende
`nombre_atiende`* (TextInput), `vinculo_usuario` (Select: familiar/vecino/otro), `celular_atiende` (TextInput).

### Section 3 — Validación de la Atención
`recibio_visita`* (Toggle), `se_realizo_revision`* (Toggle), `fecha_ejecucion` (DatePicker), `tiempo_ejecucion` (TextInput), `descripcion_trabajo` (Textarea).

### Section 4 — Hallazgos Técnicos
`se_detecto_fuga` (Toggle, live) → shows `ppm` + `vol_porcentaje`.
`problema_persiste` (Toggle), `evidencia_reparacion` (Toggle), `servicio_correcto` (Toggle).

### Section 5 — Materiales y Cobros
`se_usaron_materiales` (Toggle, live) → shows `quien_suministro_materiales` (Select: tecnico/usuario/empresa).
`usuario_asumio_costo` (Toggle, live) → shows `valor_pagado` (TextInput) + `medio_pago` (Select: efectivo/transferencia).
`entrego_factura` (Toggle).

### Section 6 — Trazabilidad
`tecnico_dejo_documento` (TextInput), `informaron_no_estaba` (Toggle).

### Section 7 — Autorización y Firmas
`autoriza`* (Toggle).
Canvas firma usuario (Alpine + SignaturePad) + `cedula_usuario`* (TextInput).
Canvas firma auditor (Alpine + SignaturePad) + `cedula_auditor`* (TextInput).
Each canvas has a "Limpiar" button.

### Section 8 — Observaciones
`observaciones` (Textarea) — existing `reportes.observaciones` column.

### Section 9 — Soporte Fotográfico
`imagenes` (FileUpload, S3, multiple, camera capture) — existing `reportes.imagenes` column.

---

## Signature Pad Implementation

```js
// resources/js/app.js
import SignaturePad from 'signature_pad';
window.SignaturePad = SignaturePad;
```

Alpine.js component in Blade:
```html
<div x-data="{
    pad: null,
    init() { this.pad = new SignaturePad(this.$refs.canvas) },
    clear() { this.pad.clear() },
    getData() { return this.pad.isEmpty() ? null : this.pad.toDataURL() }
}" ...>
  <canvas x-ref="canvas" width="400" height="200"></canvas>
  <button type="button" @click="clear()">Limpiar</button>
</div>
```

Livewire component has:
```php
public ?string $firmaUsuarioBase64 = null;
public ?string $firmaAuditorBase64 = null;
```

Before Livewire submit, JS reads both canvases and calls `$wire.set()`:
```js
document.addEventListener('livewire:init', () => {
    Livewire.hook('commit', ({ component, commit, respond, succeed, fail }) => { ... })
})
```

Actually, simpler: use a dedicated `guardarFirmas()` Livewire action called before submit, or intercept the form's submit event with Alpine to set the values first via `$wire.firmaUsuarioBase64 = ...` then call `$wire.submit()`.

In `submitAuditoriaCampo()`:
```php
$datosServicio = $data; // form data
$datosServicio['firma_usuario'] = $this->firmaUsuarioBase64;
$datosServicio['firma_auditor'] = $this->firmaAuditorBase64;
unset($datosServicio['observaciones'], $datosServicio['imagenes']);

reportes::create([
    'orden_job_id' => $this->orden->id,
    'personals_id' => auth()->user()->personals_id,
    'numero_orden' => $this->orden->numero_orden,
    'observaciones' => $data['observaciones'] ?? null,
    'imagenes' => $data['imagenes'] ?? null,
    'datos_servicio' => $datosServicio,
]);
$this->orden->update(['estado' => EstadoOrden::EnRevision]);
```

---

## ReporteRevisar — datosServicioDisplay() case

Returns structured array for AuditoriaCampo with human-readable labels for all fields. Signature fields are returned as-is (base64 strings) for `<img>` rendering.

---

## OrdenCreate Changes

When `tipo_servicio === AuditoriaCampo`, a conditional `Select` appears for `tipo_auditoria_campo`:
- `visita_calidad_emergencia` → "Visita de Calidad — Emergencia"
- `visita_calidad_trabajos_varios` → "Visita de Calidad — Trabajos Varios"

Same pattern as `tipo_gestion_legal` in the existing form.

---

## Lifecycle

Identical to other services with assigned technician:
`Pendiente → Asignada → EnProgreso → EnRevision → Completada`

Coordinator reviews via existing `ReporteRevisar` component.

---

## Testing

- `TipoServicio::AuditoriaCampo` resolves label and color correctly
- OrdenJob can be created with `tipo_auditoria_campo` values
- Report saves `datos_servicio` with all fields including signature base64
- `datosServicioDisplay()` returns correct structure for AuditoriaCampo
