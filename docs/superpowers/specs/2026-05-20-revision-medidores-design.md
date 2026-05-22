# Diseño: Módulo Revisión de Medidores

**Fecha:** 2026-05-20
**Estado:** Aprobado
**Autor:** skgrendel

---

## Resumen

Nuevo módulo para importar masivamente órdenes de verificación de medidores/lecturas que las empresas clientes entregan en archivos CSV/XLSX. El coordinador importa el lote, las órdenes quedan en estado Pendiente, y luego las asigna (individual o masivamente) a investigadores. El módulo se audita con el mismo sistema de auditorías existente.

---

## Contexto

- La app ya gestiona órdenes de trabajo (`OrdenJob`) con `TipoServicio::Fraudes` de forma manual (coordinador crea una a una).
- Las empresas clientes proporcionan archivos con hasta cientos de contratos para verificación de medidores.
- El flujo, el reporte de campo y la auditoría son idénticos a Fraudes — solo cambia el mecanismo de entrada (import masivo vs. formulario individual).
- **No se requieren migraciones**: `OrdenJob` ya tiene todos los campos necesarios y `tipo_servicio` es una columna `string`.

---

## Decisiones de Diseño

| Decisión | Elección | Motivo |
|---|---|---|
| ¿Nuevo TipoServicio o reutilizar Fraudes? | Nuevo `RevisionMedidores` | Permite filtrar, reportar y auditar por separado |
| ¿Módulo integrado o dedicado? | Módulo dedicado con URL propia | El import masivo es un flujo muy distinto al formulario individual; evita contaminar el listado general |
| ¿Migración de DB? | Ninguna | `OrdenJob.tipo_servicio` ya es `string`; no hay campos nuevos |
| ¿Correos en asignación masiva? | Un solo correo resumen | Evitar spam al investigador cuando se asignan hasta 100 órdenes simultáneas |
| ¿Validación de import? | Filas inválidas se reportan, el resto se importa | Maximiza aprovechamiento del archivo sin bloquear el lote completo |
| Formato de import | CSV y XLSX | Las empresas pueden entregar cualquiera de los dos |
| ¿Reporte de campo? | Reutilizar `fraudesSchema()` y `submitFraudes()` en `ReporteCreate` | Misma estructura de datos — solo agregar el nuevo case al match existente |
| ¿Nuevos permisos? | Ninguno | Los permisos `ordenes.ver`, `ordenes.crear`, `informes` ya cubren el nuevo tipo |
| ¿Aparece en `OrdenesTrabajoIndex`? | Sí — agregar acción PDF para RevisionMedidores | El query no filtra por tipo; las órdenes aparecen ahí también |
| ¿Aparece en Informes/Mapa? | Automático | `InformesIndex` y `MapaIndex` usan `TipoServicio::cases()` → incluyen el nuevo tipo sin cambios |

---

## Stack y Convenciones

Este módulo debe seguir **exactamente** los mismos patrones que los componentes existentes del proyecto.

| Aspecto | Patrón obligatorio |
|---|---|
| Filament version | **v5.6** (`filament/forms`, `filament/tables`, `filament/actions`, `filament/schemas`) |
| Tablas | `InteractsWithTable` + `HasTable` + `Filament\Tables\Table` (mismo que `OrdenesTrabajoIndex`) |
| Formularios | `InteractsWithForms` + `HasForms` + `Filament\Schemas\Schema` (mismo que `OrdenCreate`) |
| Acciones/Modales | `InteractsWithActions` + `HasActions` + `Filament\Actions\Action` |
| Acciones masivas | `Filament\Actions\BulkActionGroup` con `BulkAction` dentro de `table()` |
| Secciones de form | `Filament\Schemas\Components\Section` (no `Filament\Forms\Components\Section`) |
| Layouts | `#[Layout('layouts.app')]` + `#[Title('...')]` en cada componente |
| UUID | `HasUuids` en modelos de entidad; `foreignUuid` en FKs con constraint |
| Tenant | `company_id` inyectado vía `TenantService` en `booted()` — nunca hardcodeado |

**Referencia de sibling files:**
- Tabla con acciones: `app/Livewire/Coordinador/OrdenesTrabajoIndex.php`
- Formulario con secciones dinámicas: `app/Livewire/Coordinador/OrdenCreate.php`
- PDF controller: `app/Http/Controllers/ReportePdfController.php`
- Vista PDF: `resources/views/pdf/reporte-fraude.blade.php`

---

## Arquitectura

### Enum

**Archivo:** `app/Enums/TipoServicio.php`

Agregar nuevo case:
```php
case RevisionMedidores = 'revision_medidores';
```

Con métodos:
- `label()` → `'Revisión de Medidores'`
- `color()` → `'cyan'` (o similar, distinto a los existentes)
- `tieneContrato()` → `true`
- `tieneMedidor()` → `true`
- `tieneTecnicoAsignado()` → `true`

### Rutas

**Archivo:** `routes/web.php` (grupo Coordinador)

```
GET  operaciones/revision-medidores              → RevisionMedidoresIndex
GET  operaciones/revision-medidores/importar     → ImportarRevisionMedidores
```

Middleware: `role:Coordinador|Administrador`

### Componentes Livewire

| Componente | Namespace | Responsabilidad |
|---|---|---|
| `RevisionMedidoresIndex` | `App\Livewire\Coordinador\RevisionMedidores` | Listado de órdenes `RevisionMedidores`, asignación individual y masiva |
| `ImportarRevisionMedidores` | `App\Livewire\Coordinador\RevisionMedidores` | Upload de archivo, parseo, validación, creación en lote |

### Mailable nuevo

| Clase | Responsabilidad |
|---|---|
| `App\Mail\OrdenesAsignadasResumen` | Correo único al investigador con total y lista de contratos asignados en bloque |

El mailable `OrdenAsignada` existente se sigue usando solo para asignaciones individuales (1 orden).

---

## Componentes Existentes que Requieren Cambios

Estos archivos ya existen y deben ser modificados — no son nuevos.

### `app/Livewire/Tecnico/ReporteCreate.php` ⚠️ Crítico
Dos `match($this->orden->tipo_servicio)` **sin `default`** → `UnhandledMatchError` si el tipo no está listado.
- Agregar `TipoServicio::RevisionMedidores => $this->fraudesSchema()` en el match de schema (~línea 92)
- Agregar `TipoServicio::RevisionMedidores => $this->submitFraudes($data)` en el match de submit (~línea 951)

### `app/Livewire/Tecnico/ReporteEdit.php` ⚠️ Crítico
Un `match` **sin `default`** en el schema (~línea 93) → mismo riesgo que ReporteCreate.
- Agregar `TipoServicio::RevisionMedidores => $this->fraudesSchema()` en el match de schema
- El match del submit (~línea 889) ya tiene `default` → safe, no requiere cambio

### `app/Livewire/Coordinador/OrdenCreate.php`
El select de `tipo_servicio` usa `TipoServicio::cases()` → `RevisionMedidores` aparecería en el formulario manual. Las órdenes de este tipo solo se crean por import — **excluirlo del dropdown**.

### `app/Livewire/Coordinador/ReporteRevisar.php`
`datosServicioDisplay()` usa `if` (no match) por tipo. Fraudes cae al return vacío por defecto. `RevisionMedidores` tiene la misma estructura → **no requiere cambio**. Safe.

### `app/Livewire/Coordinador/OrdenesTrabajoIndex.php`
El query no filtra por tipo — las órdenes de `RevisionMedidores` aparecen en este listado también. Hay acciones de PDF condicionales por tipo de servicio. **Debe agregarse la acción de descarga PDF para RevisionMedidores** con `->visible(fn($r) => $r->tipo_servicio === TipoServicio::RevisionMedidores && $r->estado === EstadoOrden::Completada)`.

### `resources/data/objetivos-pdf.json`
El `ReportePdfController` llama `resolveObjetivos('revision_medidores')` — si no existe esa clave devuelve array vacío (no rompe). **Debe agregarse** la clave `revision_medidores` con el texto de objetivos correspondiente al servicio.

### `resources/views/layouts/app/sidebar.blade.php`
Agregar nuevo `flux:sidebar.item` para "Revisión de Medidores" dentro del grupo Operaciones, bajo el guard `@can('ordenes.ver')`, con el route name `operaciones.revision-medidores` y marcado activo cuando `request()->routeIs('operaciones.revision-medidores*')`.

---

## Flujo Detallado

### Import

1. Coordinador navega a `operaciones/revision-medidores/importar`
2. Sube archivo `.csv` o `.xlsx`
3. El sistema parsea con `maatwebsite/excel`
4. Validación fila por fila — campos requeridos: `contrato`, `cliente`, `direccion`, `barrio`, `medidor`
5. Filas inválidas → tabla de errores con número de fila y motivo
6. Filas válidas → creadas en lote como `OrdenJob`:
   - `tipo_servicio = 'revision_medidores'`
   - `estado = EstadoOrden::Pendiente`
   - `company_id` → inyectado por `TenantService`
   - `creado_por_id` → usuario autenticado
7. Al finalizar → resumen: X órdenes importadas, Y errores

### Mapeo de campos del archivo → OrdenJob

| Columna del archivo | Campo en `OrdenJob` | Requerido |
|---|---|---|
| `contrato` | `contrato` | Sí |
| `cliente` | `cliente` | Sí |
| `direccion` | `direccion` | Sí |
| `barrio` | `barrio` | Sí |
| `medidor` | `medidor` | Sí |
| `tipo_medidor` | `tipo_medidor` | No |
| `ciclo` | `ciclo` | No |
| `descripcion` | `comentario_usuario` | No |

Campos omitidos del archivo original: `latitud`, `longitud`, `estado`, `estado_servicio`, `personals_id`.

### Asignación Individual

- Coordinador asigna desde `RevisionMedidoresIndex` orden por orden
- Comportamiento idéntico al flujo de `OrdenesTrabajoIndex`
- Estado → `Asignada`
- Se envía `OrdenAsignada` (mailable existente) al investigador

### Asignación Masiva

- Coordinador selecciona múltiples órdenes con checkbox en `RevisionMedidoresIndex`
- Acción "Asignar seleccionadas" → modal para elegir investigador y confirmar
- Todas las órdenes seleccionadas → `estado = Asignada`, `personals_id` asignado
- Se envía **un solo correo** `OrdenesAsignadasResumen` al investigador con:
  - Total de órdenes asignadas
  - Lista de contratos/clientes
- No se generan correos individuales en el flujo masivo

### Flujo del Investigador (sin cambios)

- El investigador ve sus órdenes de `RevisionMedidores` en `tecnico/mis-ordenes` (ya filtra por `personals_id`, no por tipo)
- Ejecuta el reporte de campo — reutiliza el formulario de Fraudes (mismo componente `ReporteCreate`)
- Al completar → estado `Completada`

### Auditoría

- Al cambiar `estado → Completada`, `OrdenJob::booted()` genera automáticamente un registro en `auditorias` (comportamiento existente, sin cambios)
- `RevisionMedidores` se agrega como opción en `UsuarioServicioAuditable`
- El admin puede asignar este servicio a un auditor desde la gestión de usuarios
- El auditor ve estas auditorías en `auditor/auditorias` filtradas por sus servicios asignados
- Revisa con `AuditoriaRevisar` existente

---

## Sidebar

**Archivo:** `resources/views/components/sidebar.blade.php` (o equivalente)

Nueva entrada en sección **Operaciones**, visible para roles `Coordinador` y `Administrador`:

```
Operaciones
├── Órdenes de Trabajo        [existente]
└── Revisión de Medidores     [nuevo]  ← operaciones.revision-medidores
```

---

## Plantilla de Importación Descargable

La pantalla `ImportarRevisionMedidores` incluye un botón **"Descargar Plantilla"** que genera y devuelve un archivo `.xlsx` con:

- Fila de encabezados con los nombres exactos de columna esperados
- Una fila de ejemplo con datos ficticios para guiar al usuario
- Columnas: `contrato`, `cliente`, `direccion`, `barrio`, `medidor`, `tipo_medidor`, `ciclo`, `descripcion`

La plantilla se genera en tiempo real con `maatwebsite/excel` mediante una clase `App\Exports\RevisionMedidoresPlantillaExport` — no se almacena en disco. La descarga se maneja desde una ruta GET dedicada para no bloquear Livewire.

**Ruta adicional:**
```
GET  operaciones/revision-medidores/plantilla  → RevisionMedidoresPlantillaController@download
```

---

## Paquete Requerido

| Paquete | Uso |
|---|---|
| `maatwebsite/excel` | Parseo de CSV/XLSX en el import y generación de la plantilla descargable |

Verificar si ya está instalado antes de hacer `composer require`.

---

## Tests

Cada subtarea de implementación incluye sus propios tests Pest. Los tests clave del módulo completo son:

- Descarga de plantilla retorna archivo XLSX con encabezados correctos
- Import válido crea las órdenes correctas en DB
- Import con filas inválidas importa las válidas y reporta errores
- Import con archivo vacío muestra error adecuado
- Asignación individual envía `OrdenAsignada` (1 correo)
- Asignación masiva envía `OrdenesAsignadasResumen` (1 correo, no N)
- Asignación masiva NO envía `OrdenAsignada` individual
- Auditor con servicio `revision_medidores` ve las auditorías del tipo
- Auditor sin ese servicio no las ve
- Descarga de PDF retorna 200 y content-type correcto para orden RevisionMedidores
- Descarga de PDF retorna 403 para orden de otro tipo de servicio

---

## Subtareas de Implementación

El trabajo se divide en subtareas independientes y ordenadas. Cada una tiene contexto suficiente para ejecutarse de forma aislada.

> **Nota para retomar trabajo:** Si se alcanza el límite de contexto, leer este spec completo y el plan de implementación (`docs/superpowers/plans/`) antes de continuar con la siguiente subtarea.

### ST-1: Enum TipoServicio — Agregar RevisionMedidores
- Agregar `case RevisionMedidores = 'revision_medidores'` con todos los métodos
- Test: verificar que el label, color y métodos retornan los valores correctos

### ST-2: Integrar RevisionMedidores en componentes existentes
Todos estos archivos ya existen — solo se agregan casos al código existente:

**`app/Livewire/Tecnico/ReporteCreate.php`** *(crítico — match sin default en líneas 92 y 951)*
- Agregar `TipoServicio::RevisionMedidores => $this->fraudesSchema()` en el match del schema (línea ~92)
- Agregar `TipoServicio::RevisionMedidores => $this->submitFraudes($data)` en el match del submit (línea ~951)
- Sin este cambio → `UnhandledMatchError` en producción

**`app/Livewire/Tecnico/ReporteEdit.php`** *(crítico — match sin default en línea 93)*
- Agregar `TipoServicio::RevisionMedidores => $this->fraudesSchema()` en el match del schema (línea ~93)
- El match del submit (~línea 889) ya tiene `default` → no requiere cambio
- Sin este cambio → `UnhandledMatchError` si el técnico edita un reporte de RevisionMedidores

**`app/Livewire/Coordinador/OrdenCreate.php`** *(excluir del dropdown)*
- El select de `tipo_servicio` usa `TipoServicio::cases()` → `RevisionMedidores` aparecería en el formulario de creación manual
- Las órdenes de RevisionMedidores se crean **solo por import** — excluir del dropdown:
  `collect(TipoServicio::cases())->filter(fn($c) => $c !== TipoServicio::RevisionMedidores)`

**`app/Livewire/Coordinador/OrdenesTrabajoIndex.php`**
- Agregar acción de descarga PDF para RevisionMedidores con `->visible(fn($r) => $r->tipo_servicio === TipoServicio::RevisionMedidores && $r->estado === EstadoOrden::Completada)` y URL a la nueva ruta de PDF

**`resources/data/objetivos-pdf.json`**
- Agregar clave `"revision_medidores"` con el texto de objetivos del servicio

Tests:
- El técnico puede acceder al formulario de reporte para una orden RevisionMedidores
- El técnico puede editar un reporte de RevisionMedidores sin error
- RevisionMedidores NO aparece en el dropdown de OrdenCreate
- La acción PDF aparece en OrdenesTrabajoIndex para órdenes RevisionMedidores completadas

### ST-3: Paquete maatwebsite/excel
- Verificar si ya está instalado; si no, `composer require maatwebsite/excel`
- Publicar config si aplica

### ST-4: Export class — RevisionMedidoresPlantillaExport + Controller
- Clase `App\Exports\RevisionMedidoresPlantillaExport` que implementa `FromArray` + `WithHeadings`
- Encabezados: `contrato`, `cliente`, `direccion`, `barrio`, `medidor`, `tipo_medidor`, `ciclo`, `descripcion`
- Una fila de ejemplo con datos ficticios
- `App\Http\Controllers\RevisionMedidoresPlantillaController` con método `download()` que retorna `Excel::download()`
- Ruta GET `operaciones/revision-medidores/plantilla` con middleware `role:Coordinador|Administrador`
- Test: la ruta retorna status 200 y content-type de Excel

### ST-5: Import class — RevisionMedidoresImport
- Clase `App\Imports\RevisionMedidoresImport` que implementa `ToCollection` + `WithHeadingRow` + `SkipsOnFailure`
- Mapeo de columnas del archivo → campos de `OrdenJob`
- Validación fila por fila con reglas específicas
- Retorna colección de órdenes creadas y colección de errores

### ST-6: Mailable OrdenesAsignadasResumen
- `App\Mail\OrdenesAsignadasResumen` con constructor `(personals $investigador, Collection $ordenes)`
- Vista blade con total de órdenes y tabla de contratos/clientes
- Test: estructura del correo

### ST-7: Componente ImportarRevisionMedidores
- Livewire component con upload de archivo (Livewire file upload)
- Llama a `RevisionMedidoresImport`
- Muestra resumen de éxito y tabla de errores
- Tests: import exitoso, import con errores, archivo inválido

### ST-8: Componente RevisionMedidoresIndex
- Listado de `OrdenJob` donde `tipo_servicio = revision_medidores` con paginación y búsqueda
- Asignación individual: mismo patrón que `OrdenesTrabajoIndex`, dispara `OrdenAsignada`
- Asignación masiva: checkbox + modal + acción en lote, dispara `OrdenesAsignadasResumen`
- Tests: listado, asignación individual (1 correo), asignación masiva (1 correo resumen, no N individuales)

### ST-9: Rutas y Sidebar
- Registrar rutas en `routes/web.php`
- Agregar entrada al sidebar con control de visibilidad por rol
- Tests: rutas accesibles para Coordinador/Admin, no accesibles para otros roles

### ST-10: Auditoría — UsuarioServicioAuditable
- Agregar `RevisionMedidores` como opción válida en el formulario de gestión de servicios auditables
- Verificar que el filtro en `AuditoriasIndex` incluye este tipo cuando está asignado al auditor
- Tests: auditor con servicio asignado ve auditorías, auditor sin servicio no las ve

### ST-11: Informe PDF — RevisionMedidores
- Nuevo método `DownLoadInformeRevisionMedidores(OrdenJob $orden)` en `ReportePdfController`
  - Guard: `abort_unless($orden->tipo_servicio === TipoServicio::RevisionMedidores, 403)`
  - Guard: `abort_unless(auth()->user()->can('informes'), 403)`
  - Carga relaciones de `$orden` y `$reporte` igual que `DownLoadInformeFraude`
  - Construye `$firma`, `$datosOrden` con `buildDatosOrden()` existente, `$anomalias` e `$imagenes`
  - Retorna `Pdf::loadView('pdf.reporte-revision-medidores', [...])->download("reporte-revision-medidores-{$orden->numero_orden}.pdf")`
- Nueva vista `resources/views/pdf/reporte-revision-medidores.blade.php`
  - Misma estructura y plantilla que `reporte-fraude.blade.php`
  - Título adaptado: "Revisión de Medidores / Lecturas"
  - Ajustar encabezados de sección según corresponda al servicio
- Nueva ruta GET: `operaciones/revision-medidores/{orden}/reporte` → `ReportePdfController@DownLoadInformeRevisionMedidores`
  - Middleware: `role:Coordinador|Administrador` + permiso `informes`
- Botón "Descargar Informe PDF" en `RevisionMedidoresIndex` por cada orden completada
- Test: la ruta retorna 200 y content-type PDF para orden de tipo RevisionMedidores; retorna 403 para otro tipo

### ST-12: Pint y tests finales
- Ejecutar `vendor/bin/pint --dirty --format agent`
- Ejecutar suite completa de tests del módulo
- Verificar que tests existentes no regresen
