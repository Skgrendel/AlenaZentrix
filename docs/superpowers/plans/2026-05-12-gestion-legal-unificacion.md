# Gestión Legal — Unificación Capturas/Bandas Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reemplazar los tipos de servicio `Capturas` y `Bandas` con un único tipo `GestionLegal` que tiene un sub-tipo (`tipo_gestion_legal`) con 6 opciones, siguiendo el mismo patrón que `Investigacion` con `tipo_investigacion`.

**Architecture:** Se edita el enum `TipoServicio` eliminando dos casos y agregando `GestionLegal = 'gestion_legal'`. Una nueva columna `tipo_gestion_legal` en `orden_jobs` almacena el sub-tipo. Todos los formularios de creación, edición, revisión y generación de PDF se actualizan en cascada.

**Tech Stack:** Laravel 13, Livewire 4, Filament Forms/Schemas, DomPDF (barryvdh/laravel-dompdf), Pest 4.

---

## Archivos modificados / creados

| Archivo | Acción |
|---------|--------|
| `database/migrations/2024_05_18_084152_create_orden_jobs_table.php` | Agregar columna `tipo_gestion_legal` |
| `app/Models/OrdenJob.php` | Agregar a `$fillable` |
| `app/Enums/TipoServicio.php` | Eliminar Capturas/Bandas, agregar GestionLegal |
| `app/Livewire/Coordinador/OrdenCreate.php` | Sección Gestión Legal con select sub-tipo |
| `app/Livewire/Coordinador/OrdenesTrabajoIndex.php` | Sección edición Gestión Legal |
| `app/Livewire/Tecnico/ReporteCreate.php` | Renombrar capturasSchema → gestionLegalSchema |
| `app/Livewire/Tecnico/ReporteEdit.php` | Renombrar + agregar gestionRiesgoSchema |
| `app/Livewire/Coordinador/ReporteRevisar.php` | datosServicioDisplay GestionLegal |
| `app/Http/Controllers/ReportePdfController.php` | Nuevo método DownLoadInformeGestionLegal |
| `resources/views/livewire/coordinador/reporte-revisar.blade.php` | Sección GestionLegal + botón PDF |
| `resources/views/livewire/tecnico/orden-show.blade.php` | Fix badge color match |
| `resources/views/livewire/coordinador/orden-job-show.blade.php` | Fix badge color match |
| `app/Filament/Widgets/OrdenesPorServicioChart.php` | Fix colorMap |
| `resources/views/pdf/reporte-gestion-legal.blade.php` | Nuevo PDF (crear) |
| `routes/operaciones.php` | Nueva ruta PDF |
| `tests/Feature/ReportePdfTest.php` | Test nuevo tipo |

---

## Task 1: Migración + Modelo + Enum

**Files:**
- Modify: `database/migrations/2024_05_18_084152_create_orden_jobs_table.php`
- Modify: `app/Models/OrdenJob.php`
- Modify: `app/Enums/TipoServicio.php`

- [ ] **Paso 1.1 — Agregar columna en migración**

En `database/migrations/2024_05_18_084152_create_orden_jobs_table.php`, después de la línea `$table->string('tipo_investigacion')->nullable();`, agregar:

```php
$table->string('tipo_gestion_legal')->nullable(); // sub-tipo para GestionLegal
```

- [ ] **Paso 1.2 — Agregar a fillable en OrdenJob**

En `app/Models/OrdenJob.php`, en el array `$fillable`, después de `'tipo_investigacion'`, agregar:

```php
'tipo_gestion_legal',
```

- [ ] **Paso 1.3 — Actualizar el enum TipoServicio**

Reemplazar el contenido completo de `app/Enums/TipoServicio.php`:

```php
<?php

namespace App\Enums;

/**
 * Clasificación del tipo de servicio de una orden de trabajo.
 * Determina el flujo, los campos del reporte y si requiere técnico asignado.
 * Vigilancia es el único tipo sin técnico fijo: los reportes se acumulan mientras la orden sigue EnProgreso.
 */
enum TipoServicio: string
{
    case Fraudes = 'fraudes';
    case Investigacion = 'investigacion';
    case GestionLegal = 'gestion_legal';
    case Vigilancia = 'vigilancia';
    case Gestion_Riesgo = 'Gestión de Riesgo';

    public function label(): string
    {
        return match ($this) {
            self::Fraudes => 'Fraudes',
            self::Investigacion => 'Investigación',
            self::GestionLegal => 'Gestión Legal',
            self::Vigilancia => 'Dispositivos de Vigilancia',
            self::Gestion_Riesgo => 'Gestión de Riesgo',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Fraudes => 'danger',
            self::Investigacion => 'warning',
            self::GestionLegal => 'info',
            self::Vigilancia => 'success',
            self::Gestion_Riesgo => 'primary',
        };
    }

    /** @return bool Servicios que tienen técnico asignado fijo */
    public function tieneTecnicoAsignado(): bool
    {
        return $this !== self::Vigilancia;
    }

    /** @return bool Servicios que requieren contrato y cliente */
    public function tieneContrato(): bool
    {
        return in_array($this, [self::Fraudes, self::Investigacion, self::Vigilancia]);
    }

    /** @return bool Servicios que tienen datos de medidor */
    public function tieneMedidor(): bool
    {
        return $this === self::Fraudes;
    }
}
```

- [ ] **Paso 1.4 — Refresh de base de datos**

```bash
php artisan migrate:refresh --seed
```

Resultado esperado: `Migration table created successfully.` (o similar), sin errores.

- [ ] **Paso 1.5 — Commit**

```bash
git add database/migrations/2024_05_18_084152_create_orden_jobs_table.php app/Models/OrdenJob.php app/Enums/TipoServicio.php
git commit -m "feat: reemplazar Capturas/Bandas con GestionLegal en migración, modelo y enum"
```

---

## Task 2: OrdenCreate — Formulario del coordinador

**Files:**
- Modify: `app/Livewire/Coordinador/OrdenCreate.php`

- [ ] **Paso 2.1 — Reemplazar sección Capturas/Bandas por Gestión Legal**

Localizar el bloque que empieza con `// ── Sección: Capturas y Bandas` y reemplazarlo completo:

```php
// ── Sección: Gestión Legal ────────────────────────────────
Section::make('Datos de Gestión Legal')
    ->columns(1)
    ->visible(fn ($get) => $get('tipo_servicio') === TipoServicio::GestionLegal->value)
    ->schema([
        Forms\Components\Select::make('tipo_gestion_legal')
            ->label('Tipo de Gestión Legal')
            ->options([
                'capturas'             => 'Capturas Criminales',
                'bandas'               => 'Desarticulación de Bandas',
                'querella'             => 'Querella Penal',
                'seguimiento_denuncia' => 'Seguimiento a Denuncia',
                'diligencia_judicial'  => 'Diligencia Judicial',
                'incautacion_equipos'  => 'Incautación de Equipos',
            ])
            ->required(fn ($get) => $get('tipo_servicio') === TipoServicio::GestionLegal->value)
            ->placeholder('Seleccionar'),
        Forms\Components\TextInput::make('numero_noticia_criminal')
            ->label('N° Noticia Criminal')
            ->maxLength(255),
        Forms\Components\Textarea::make('objetivo')
            ->label('Objetivo de la Gestión Legal')
            ->required(fn ($get) => $get('tipo_servicio') === TipoServicio::GestionLegal->value)
            ->rows(5)
            ->cols(5)
            ->maxLength(1000),
    ]),
```

- [ ] **Paso 2.2 — Pint**

```bash
vendor/bin/pint app/Livewire/Coordinador/OrdenCreate.php --format agent
```

- [ ] **Paso 2.3 — Commit**

```bash
git add app/Livewire/Coordinador/OrdenCreate.php
git commit -m "feat: reemplazar sección Capturas/Bandas con formulario Gestión Legal en OrdenCreate"
```

---

## Task 3: OrdenesTrabajoIndex — Sección de edición inline

**Files:**
- Modify: `app/Livewire/Coordinador/OrdenesTrabajoIndex.php`

- [ ] **Paso 3.1 — Reemplazar sección Capturas y Bandas**

Localizar el bloque `// ── Capturas y Bandas` en `OrdenesTrabajoIndex.php` (alrededor de línea 327) y reemplazarlo:

```php
// ── Gestión Legal ─────────────────────────────────────────
Section::make('Datos de Gestión Legal')
    ->columns(2)
    ->visible($tipoServicio === TipoServicio::GestionLegal)
    ->schema([
        Forms\Components\Select::make('tipo_gestion_legal')
            ->label('Tipo de Gestión Legal')
            ->options([
                'capturas'             => 'Capturas Criminales',
                'bandas'               => 'Desarticulación de Bandas',
                'querella'             => 'Querella Penal',
                'seguimiento_denuncia' => 'Seguimiento a Denuncia',
                'diligencia_judicial'  => 'Diligencia Judicial',
                'incautacion_equipos'  => 'Incautación de Equipos',
            ])
            ->placeholder('Seleccionar')
            ->columnSpanFull(),
        Forms\Components\TextInput::make('numero_noticia_criminal')->label('N° Noticia Criminal')->maxLength(255),
        Forms\Components\TextInput::make('direccion')->label('Dirección')->maxLength(255),
        Forms\Components\TextInput::make('barrio')->label('Municipio')->maxLength(255),
    ]),
```

- [ ] **Paso 3.2 — Pint y commit**

```bash
vendor/bin/pint app/Livewire/Coordinador/OrdenesTrabajoIndex.php --format agent
git add app/Livewire/Coordinador/OrdenesTrabajoIndex.php
git commit -m "feat: actualizar sección de edición inline para Gestión Legal en OrdenesTrabajoIndex"
```

---

## Task 4: ReporteCreate — Técnico

**Files:**
- Modify: `app/Livewire/Tecnico/ReporteCreate.php`

- [ ] **Paso 4.1 — Actualizar form() match**

En `form()`, reemplazar:

```php
TipoServicio::Capturas, TipoServicio::Bandas => $this->capturasSchema(),
```

Con:

```php
TipoServicio::GestionLegal => $this->gestionLegalSchema(),
```

- [ ] **Paso 4.2 — Actualizar submit() match**

En `submit()`, reemplazar:

```php
TipoServicio::Capturas => $this->submitServicioGenerico($data),
TipoServicio::Bandas => $this->submitServicioGenerico($data),
```

Con:

```php
TipoServicio::GestionLegal => $this->submitServicioGenerico($data),
```

- [ ] **Paso 4.3 — Renombrar capturasSchema a gestionLegalSchema**

Renombrar el método `private function capturasSchema(): array` → `private function gestionLegalSchema(): array`. No hay cambio en el contenido del método.

- [ ] **Paso 4.4 — Pint y commit**

```bash
vendor/bin/pint app/Livewire/Tecnico/ReporteCreate.php --format agent
git add app/Livewire/Tecnico/ReporteCreate.php
git commit -m "feat: renombrar capturasSchema a gestionLegalSchema y actualizar match en ReporteCreate"
```

---

## Task 5: ReporteEdit — Técnico

**Files:**
- Modify: `app/Livewire/Tecnico/ReporteEdit.php`

- [ ] **Paso 5.1 — Actualizar form() match**

Reemplazar:

```php
TipoServicio::Capturas, TipoServicio::Bandas => $this->capturasSchema(),
TipoServicio::Vigilancia => $this->vigilanciaSchema(),
```

Con:

```php
TipoServicio::GestionLegal => $this->gestionLegalSchema(),
TipoServicio::Gestion_Riesgo => $this->gestionRiesgoSchema(),
TipoServicio::Vigilancia => $this->vigilanciaSchema(),
```

- [ ] **Paso 5.2 — Renombrar capturasSchema → gestionLegalSchema**

Renombrar el método `private function capturasSchema(): array` → `private function gestionLegalSchema(): array`. No hay cambio en el cuerpo.

- [ ] **Paso 5.3 — Agregar gestionRiesgoSchema() a ReporteEdit**

Agregar el siguiente método antes de `private function vigilanciaSchema()`:

```php
/** @return array<int, Section> */
private function gestionRiesgoSchema(): array
{
    return [
        Section::make('1. Actividades Realizadas')
            ->icon('heroicon-o-check-badge')
            ->schema([
                Forms\Components\Textarea::make('observaciones')
                    ->label('Novedades / Observaciones')
                    ->placeholder('Describe los hallazgos y novedades...')
                    ->rows(4)
                    ->columnSpanFull(),
            ]),

        Section::make('2. Evidencias')
            ->icon('heroicon-o-paper-clip')
            ->schema([
                Forms\Components\FileUpload::make('imagenes')
                    ->label('Archivos / Evidencias')
                    ->disk('s3')
                    ->directory('reportes/'.$this->orden->numero_orden)
                    ->fetchFileInformation(false)
                    ->multiple()
                    ->acceptedFileTypes([
                        'image/*',
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ])
                    ->maxFiles(10)
                    ->maxSize(10240)
                    ->reorderable()
                    ->panelLayout('grid')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('video')
                    ->label('URL del Video')
                    ->url()
                    ->maxLength(500),
            ]),
    ];
}
```

- [ ] **Paso 5.4 — Pint y commit**

```bash
vendor/bin/pint app/Livewire/Tecnico/ReporteEdit.php --format agent
git add app/Livewire/Tecnico/ReporteEdit.php
git commit -m "feat: actualizar ReporteEdit para GestionLegal y agregar gestionRiesgoSchema"
```

---

## Task 6: ReporteRevisar — datosServicioDisplay

**Files:**
- Modify: `app/Livewire/Coordinador/ReporteRevisar.php`

- [ ] **Paso 6.1 — Reemplazar rama Capturas/Bandas por GestionLegal**

Localizar el bloque `if (in_array($tipo, [TipoServicio::Capturas, TipoServicio::Bandas]))` y reemplazarlo:

```php
if ($tipo === TipoServicio::GestionLegal) {
    $subtipos = [
        'capturas'             => 'Capturas Criminales',
        'bandas'               => 'Desarticulación de Bandas',
        'querella'             => 'Querella Penal',
        'seguimiento_denuncia' => 'Seguimiento a Denuncia',
        'diligencia_judicial'  => 'Diligencia Judicial',
        'incautacion_equipos'  => 'Incautación de Equipos',
    ];

    return [
        'tipo_gestion_label'       => $subtipos[$this->orden->tipo_gestion_legal ?? ''] ?? ($this->orden->tipo_gestion_legal ?? '—'),
        'numero_noticia_criminal'  => $this->orden->numero_noticia_criminal,
        'tipologia'                => TipologiaOrden::find($datos['tipologia_orden_id'] ?? null)?->nombre,
        'fecha_ejecucion'          => $datos['fecha_ejecucion'] ?? null,
        'departamento'             => $datos['departamento'] ?? null,
        'municipio'                => $datos['municipio'] ?? null,
        'radio_objetivo'           => $datos['radio_objetivo'] ?? null,
        'actividades'              => $datos['actividades'] ?? null,
        'resultado'                => ResultadoReporte::find($datos['resultado_reporte_id'] ?? null)?->nombre,
    ];
}
```

- [ ] **Paso 6.2 — Pint y commit**

```bash
vendor/bin/pint app/Livewire/Coordinador/ReporteRevisar.php --format agent
git add app/Livewire/Coordinador/ReporteRevisar.php
git commit -m "feat: agregar rama GestionLegal en datosServicioDisplay de ReporteRevisar"
```

---

## Task 7: reporte-revisar.blade.php — Sección + botón PDF

**Files:**
- Modify: `resources/views/livewire/coordinador/reporte-revisar.blade.php`

- [ ] **Paso 7.1 — Botón PDF para Gestión Legal**

Localizar el bloque `@if ($orden->tipo_servicio === \App\Enums\TipoServicio::Fraudes && auth()->user()->can('informes.fraude'))` y agregar debajo del cierre `@endif`:

```blade
@if ($orden->tipo_servicio === \App\Enums\TipoServicio::GestionLegal && auth()->user()->can('informes'))
    <flux:button
        href="{{ route('operaciones.ordenes-trabajo.reporte-gestion-legal', [$orden, 'reporte' => $reporte->id]) }}"
        icon="arrow-down-tray" variant="filled" target="_blank">
        Descargar PDF
    </flux:button>
@endif
```

- [ ] **Paso 7.2 — Reemplazar sección Capturas/Bandas por Gestión Legal**

Localizar el bloque `@elseif(in_array($orden->tipo_servicio, [\App\Enums\TipoServicio::Capturas, \App\Enums\TipoServicio::Bandas]))` y reemplazarlo:

```blade
{{-- ═══ GESTIÓN LEGAL ═══ --}}
@elseif($orden->tipo_servicio === \App\Enums\TipoServicio::GestionLegal)
    <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
            Gestión Legal</h3>
        <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
            @if ($datosServicio['tipo_gestion_label'] ?? null)
                <div class="sm:col-span-2">
                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Tipo de Gestión</dt>
                    <dd class="mt-0.5">
                        <flux:badge color="blue" size="sm">{{ $datosServicio['tipo_gestion_label'] }}</flux:badge>
                    </dd>
                </div>
            @endif
            @if ($datosServicio['numero_noticia_criminal'] ?? null)
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">N° Noticia Criminal</dt>
                    <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                        {{ $datosServicio['numero_noticia_criminal'] }}</dd>
                </div>
            @endif
            @if ($datosServicio['tipologia'] ?? null)
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Tipología</dt>
                    <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                        {{ $datosServicio['tipologia'] }}</dd>
                </div>
            @endif
            @if ($datosServicio['fecha_ejecucion'] ?? null)
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Fecha de Ejecución</dt>
                    <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                        {{ \Illuminate\Support\Carbon::parse($datosServicio['fecha_ejecucion'])->format('d/m/Y') }}</dd>
                </div>
            @endif
            @if ($datosServicio['departamento'] ?? null)
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Departamento</dt>
                    <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                        {{ $datosServicio['departamento'] }}</dd>
                </div>
            @endif
            @if ($datosServicio['municipio'] ?? null)
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Municipio</dt>
                    <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                        {{ $datosServicio['municipio'] }}</dd>
                </div>
            @endif
            @if ($datosServicio['radio_objetivo'] ?? null)
                <div class="sm:col-span-2">
                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Radio del Objetivo</dt>
                    <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                        {{ $datosServicio['radio_objetivo'] }}</dd>
                </div>
            @endif
            @if ($datosServicio['resultado'] ?? null)
                <div class="sm:col-span-2">
                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Resultado</dt>
                    <dd class="mt-0.5">
                        <flux:badge color="blue" size="sm">{{ $datosServicio['resultado'] }}</flux:badge>
                    </dd>
                </div>
            @endif
        </dl>
    </div>
```

- [ ] **Paso 7.3 — Commit**

```bash
git add resources/views/livewire/coordinador/reporte-revisar.blade.php
git commit -m "feat: sección Gestión Legal y botón PDF en vista de revisión del coordinador"
```

---

## Task 8: Badges y colores

**Files:**
- Modify: `resources/views/livewire/tecnico/orden-show.blade.php`
- Modify: `resources/views/livewire/coordinador/orden-job-show.blade.php`
- Modify: `app/Filament/Widgets/OrdenesPorServicioChart.php`

- [ ] **Paso 8.1 — orden-show.blade.php**

Buscar el match de badge con `'capturas'=> 'yellow', 'bandas' => 'red'` y reemplazarlo:

```blade
:color="match($orden->tipo_servicio->value) {
    'fraudes'          => 'zinc',
    'investigacion'    => 'blue',
    'gestion_legal'    => 'info',
    'vigilancia'       => 'green',
    'Gestión de Riesgo' => 'purple',
    default            => 'zinc',
}">
```

- [ ] **Paso 8.2 — orden-job-show.blade.php**

Aplicar el mismo cambio de match en `resources/views/livewire/coordinador/orden-job-show.blade.php` (mismo patrón exacto que el paso anterior).

- [ ] **Paso 8.3 — OrdenesPorServicioChart.php**

Reemplazar el `$colorMap` completo:

```php
$colorMap = [
    'fraudes'          => 'rgba(239,68,68,0.8)',
    'investigacion'    => 'rgba(245,158,11,0.8)',
    'gestion_legal'    => 'rgba(59,130,246,0.8)',
    'vigilancia'       => 'rgba(16,185,129,0.8)',
    'Gestión de Riesgo' => 'rgba(99,102,241,0.8)',
];
```

- [ ] **Paso 8.4 — Pint y commit**

```bash
vendor/bin/pint app/Filament/Widgets/OrdenesPorServicioChart.php --format agent
git add resources/views/livewire/tecnico/orden-show.blade.php resources/views/livewire/coordinador/orden-job-show.blade.php app/Filament/Widgets/OrdenesPorServicioChart.php
git commit -m "fix: actualizar badge colors y colorMap para reemplazar capturas/bandas con gestion_legal"
```

---

## Task 9: PDF — Controlador y ruta

**Files:**
- Modify: `app/Http/Controllers/ReportePdfController.php`
- Modify: `routes/operaciones.php`

- [ ] **Paso 9.1 — Agregar método DownLoadInformeGestionLegal**

En `ReportePdfController.php`, agregar después del método `DownLoadInformeInvestigacion`:

```php
public function DownLoadInformeGestionLegal(OrdenJob $orden): Response
{
    abort_unless($orden->tipo_servicio === TipoServicio::GestionLegal, 403);
    abort_unless(auth()->user()->can('informes'), 403);

    ini_set('memory_limit', '256M');

    $reporteId = request()->query('reporte');

    $orden->load(['personal', 'creadoPor']);

    $query = $orden->reportes()->with([
        'personal.usuario',
        'auditoria.auditadoPor.personal',
    ]);

    $reporte = $reporteId
        ? $query->where('id', $reporteId)->firstOrFail()
        : $query->latest()->firstOrFail();

    $logoProderi = $this->fileToBase64(public_path('assets/image/LOGO PRODERI CON SLOGAN.png'), 'image/png');

    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'tif'];
    $imagePaths = collect($reporte->imagenes ?? [])
        ->filter(fn (string $path) => in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $imageExtensions, true))
        ->values()
        ->all();

    $imagenes = $this->resolveImagenes($imagePaths);

    $investigador = $reporte->personal;

    $firma = [
        'nombre' => trim(($investigador?->nombres ?? '').' '.($investigador?->apellidos ?? '')) ?: 'Sin nombre',
        'cargo'  => $investigador?->cargo,
        'email'  => $investigador?->usuario?->email ?? '—',
        'ip'     => request()->ip(),
        'agente' => request()->userAgent() ?? 'No disponible',
        'fecha'  => $reporte->created_at?->format('d/m/Y H:i:s') ?? now()->format('d/m/Y H:i:s'),
    ];

    $subtipos = [
        'capturas'             => 'Capturas Criminales',
        'bandas'               => 'Desarticulación de Bandas',
        'querella'             => 'Querella Penal',
        'seguimiento_denuncia' => 'Seguimiento a Denuncia',
        'diligencia_judicial'  => 'Diligencia Judicial',
        'incautacion_equipos'  => 'Incautación de Equipos',
    ];

    $datosOrden = $this->buildDatosOrden($orden);
    $datosOrden['tipo_gestion_label'] = $subtipos[$orden->tipo_gestion_legal ?? ''] ?? ($orden->tipo_gestion_legal ?? '—');

    $datosReporte = $this->buildDatosReporteGestionLegal($reporte);

    $pdf = Pdf::loadView('pdf.reporte-gestion-legal', [
        'orden'           => $orden,
        'reporte'         => $reporte,
        'logoProderi'     => $logoProderi,
        'imagenes'        => $imagenes,
        'fechaGeneracion' => now()->format('d-m-Y'),
        'firma'           => $firma,
        'datosOrden'      => $datosOrden,
        'datosReporte'    => $datosReporte,
    ]);

    $pdf->setPaper('A4', 'portrait');

    return $pdf->download("reporte-gestion-legal-{$orden->numero_orden}.pdf");
}
```

- [ ] **Paso 9.2 — Agregar método buildDatosReporteGestionLegal**

Agregar antes del método `private function formatTipoMedidor`:

```php
/**
 * @return array<string, mixed>
 */
private function buildDatosReporteGestionLegal(reportes $reporte): array
{
    $datos = $reporte->datos_servicio ?? [];

    return [
        'tipologia'      => \App\Models\TipologiaOrden::find($datos['tipologia_orden_id'] ?? null)?->nombre ?? null,
        'fecha_ejecucion' => $datos['fecha_ejecucion'] ?? null,
        'departamento'   => $datos['departamento'] ?? null,
        'municipio'      => $datos['municipio'] ?? null,
        'radio_objetivo' => $datos['radio_objetivo'] ?? null,
        'actividades'    => $this->normalizeActividades($reporte->actividades ?? '[]'),
        'resultado'      => \App\Models\ResultadoReporte::find($datos['resultado_reporte_id'] ?? null)?->nombre ?? '—',
        'observaciones'  => $reporte->observaciones ?: 'Sin observaciones registradas.',
        'conclusion'     => $reporte->conclusion ?: null,
    ];
}
```

- [ ] **Paso 9.3 — Agregar ruta en operaciones.php**

En `routes/operaciones.php`, después de la línea de `reporte-investigacion`, agregar:

```php
Route::get('ordenes-trabajo/{orden}/reporte-gestion-legal', [ReportePdfController::class, 'DownLoadInformeGestionLegal'])->name('ordenes-trabajo.reporte-gestion-legal');
```

- [ ] **Paso 9.4 — Pint y commit**

```bash
vendor/bin/pint app/Http/Controllers/ReportePdfController.php --format agent
git add app/Http/Controllers/ReportePdfController.php routes/operaciones.php
git commit -m "feat: agregar DownLoadInformeGestionLegal y ruta PDF para Gestión Legal"
```

---

## Task 10: PDF — Vista Blade

**Files:**
- Create: `resources/views/pdf/reporte-gestion-legal.blade.php`

- [ ] **Paso 10.1 — Crear la vista PDF**

Crear `resources/views/pdf/reporte-gestion-legal.blade.php` con el siguiente contenido (CSS idéntico a `reporte-investigacion.blade.php`, solo se adapta el contenido):

```blade
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Informe de Gestión Legal - {{ $orden->numero_orden }}</title>
    <style>
        @page { size: A4; margin: 155px 50px 110px 50px; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9pt; line-height: 1.2; color: #333; margin: 0; padding: 0; }
        .header { position: fixed; top: -143px; left: 0; right: 0; height: 133px; background: white; padding: 10px 50px 6px 50px; z-index: 1000; }
        .header-inner { width: 100%; border-collapse: collapse; }
        .header-td-center { text-align: left; vertical-align: middle; }
        .header-td-proderi { width: 140px; text-align: right; vertical-align: middle; }
        .logo-proderi { width: 130px; height: auto; }
        .header h1 { color: #003E72; font-size: 13pt; margin: 0 0 3px 0; font-weight: bold; }
        .header h2 { color: #64748b; font-size: 9pt; margin: 0 0 4px 0; font-weight: normal; }
        .header-meta { font-size: 7.5pt; color: #64748b; margin-top: 3px; }
        .header-separator { position: fixed; top: -10px; left: 50px; right: 50px; height: 0; border-top: 2px solid #003E72; z-index: 999; }
        .footer { position: fixed; bottom: -95px; left: 0; right: 0; height: 75px; background: white; border-top: 2px solid #003E72; padding: 10px 50px; font-size: 7pt; color: #64748b; text-align: center; }
        .footer-line { font-weight: bold; margin-bottom: 2px; }
        .content { margin-top: 10px; }
        .section { margin: 10px 0; page-break-inside: avoid; }
        .section-title { color: #003E72; font-size: 10pt; font-weight: bold; margin: 12px 0 5px 0; padding-bottom: 3px; border-bottom: 1px solid #e5e7eb; page-break-after: avoid; }
        .info-box { border: 1px solid #003E72; border-radius: 6px; padding: 8px 10px; margin: 5px 0; background-color: #f8f9fa; page-break-inside: avoid; }
        .info-grid { display: table; width: 100%; }
        .info-row { display: table-row; }
        .info-label { display: table-cell; font-weight: bold; padding: 3px 12px 3px 0; width: 160px; vertical-align: top; font-size: 8pt; color: #003E72; }
        .info-value { display: table-cell; padding: 3px 0; vertical-align: top; font-size: 8pt; }
        .content-box { border: 1px solid #003E72; border-radius: 6px; background-color: #f8f9fa; padding: 8px 10px; margin: 5px 0; white-space: pre-wrap; word-wrap: break-word; font-size: 8pt; line-height: 1.4; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 7pt; font-weight: bold; }
        .badge-blue { background-color: #3b82f6; color: white; }
        .badge-green { background-color: #10b981; color: white; }
        .badge-red { background-color: #ef4444; color: white; }
        .evidencias-tabla { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .evidencia-td { width: 50%; padding: 5px; vertical-align: top; text-align: center; }
        .evidencia-img { max-width: 100%; max-height: 190px; display: block; margin: 0 auto; border: 1px solid #e5e7eb; border-radius: 4px; }
        .evidencia-num { font-size: 7pt; color: #64748b; margin-top: 3px; }
        .firma-section { margin-top: 20px; page-break-inside: avoid; }
        .firma-box { border: 2px solid #003E72; border-radius: 8px; padding: 14px 18px; margin: 6px 0; background-color: #f0f4f9; }
        .firma-badge { display: inline-block; background-color: #10b981; color: white; padding: 3px 10px; border-radius: 10px; font-size: 8pt; font-weight: bold; margin-bottom: 10px; }
        .firma-linea { border-top: 1.5px solid #003E72; margin: 14px 0 8px 0; width: 220px; }
        .firma-nombre { font-weight: bold; font-size: 10pt; color: #003E72; margin-bottom: 2px; }
        .firma-meta { font-size: 7.5pt; color: #64748b; margin: 2px 0; }
        .firma-meta strong { color: #333; }
    </style>
</head>
<body>

    <div class="header">
        <table class="header-inner">
            <tr>
                <td class="header-td-center">
                    <h1>INFORME DE GESTIÓN LEGAL</h1>
                    <h2>Control y Seguimiento de Operaciones Legales en Campo</h2>
                    <div class="header-meta">
                        Orden: <strong>{{ $orden->numero_orden }}</strong>
                        &nbsp;&nbsp;|&nbsp;&nbsp;
                        Fecha de generación: <strong>{{ $fechaGeneracion }}</strong>
                    </div>
                </td>
                <td class="header-td-proderi">
                    <img src="{{ $logoProderi }}" class="logo-proderi" alt="PRODERI">
                </td>
            </tr>
        </table>
    </div>

    <div class="header-separator"></div>

    <div class="footer">
        <div class="footer-line">Informe de Gestión Legal — Uso Interno y Confidencial</div>
        <div>© {{ date('Y') }} AlenaZentrix / PRODERI S.A.S. — Todos los derechos reservados</div>
        <div>Este documento contiene información confidencial protegida por las leyes de propiedad intelectual</div>
    </div>

    <script type="text/php">
        if (isset($pdf)) {
            $text = "Página {PAGE_NUM} de {PAGE_COUNT}";
            $size = 7;
            $font = $fontMetrics->getFont("DejaVu Sans");
            $width = $fontMetrics->get_text_width($text, $font, $size);
            $pageWidth = $pdf->get_width();
            $x = ($pageWidth - $width) / 2;
            $y = $pdf->get_height() - 90;
            $pdf->page_text($x, $y, $text, $font, $size, array(0.4, 0.45, 0.53));
        }
    </script>

    <div class="content">

        <p style="font-size:8.5pt; line-height:1.5; text-align:justify; margin-bottom:10px;">
            Este documento constituye el informe individual de gestión legal para la Orden
            N° <strong>{{ $datosOrden['numero_orden'] ?? $orden->numero_orden }}</strong>, con el propósito
            de registrar las actividades realizadas, los datos de la operación y la evidencia fotográfica.
        </p>

        {{-- ── Objetivo ── --}}
        @if ($datosOrden['objetivo'] ?? null)
            <div class="section">
                <div class="section-title">OBJETIVO</div>
                <div class="content-box">{{ $datosOrden['objetivo'] }}</div>
            </div>
        @endif

        {{-- ── Datos de la Orden ── --}}
        <div class="section">
            <div class="section-title">DATOS DE LA ORDEN</div>
            <div class="info-box">
                <div class="info-grid">
                    <div class="info-row">
                        <div class="info-label">Número de Orden:</div>
                        <div class="info-value">{{ $datosOrden['numero_orden'] }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Tipo de Gestión:</div>
                        <div class="info-value">
                            <span class="badge badge-blue">{{ $datosOrden['tipo_gestion_label'] }}</span>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Estado:</div>
                        <div class="info-value">{{ $datosOrden['estado'] }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Investigador:</div>
                        <div class="info-value">{{ $datosOrden['tecnico'] }}</div>
                    </div>
                    @if ($datosOrden['noticia_criminal'] !== '—')
                        <div class="info-row">
                            <div class="info-label">N° Noticia Criminal:</div>
                            <div class="info-value">{{ $datosOrden['noticia_criminal'] }}</div>
                        </div>
                    @endif
                    <div class="info-row">
                        <div class="info-label">Fecha de Asignación:</div>
                        <div class="info-value">{{ $datosOrden['fecha_asignacion'] }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Fecha Límite:</div>
                        <div class="info-value">{{ $datosOrden['fecha_limite'] }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Fecha de Completado:</div>
                        <div class="info-value">{{ $datosOrden['fecha_completado'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Datos Operativos ── --}}
        @if ($datosReporte['tipologia'] || $datosReporte['fecha_ejecucion'] || $datosReporte['departamento'])
            <div class="section">
                <div class="section-title">DATOS OPERATIVOS</div>
                <div class="info-box">
                    <div class="info-grid">
                        @if ($datosReporte['tipologia'])
                            <div class="info-row">
                                <div class="info-label">Tipología:</div>
                                <div class="info-value">{{ $datosReporte['tipologia'] }}</div>
                            </div>
                        @endif
                        @if ($datosReporte['fecha_ejecucion'])
                            <div class="info-row">
                                <div class="info-label">Fecha de Ejecución:</div>
                                <div class="info-value">{{ \Illuminate\Support\Carbon::parse($datosReporte['fecha_ejecucion'])->format('d/m/Y') }}</div>
                            </div>
                        @endif
                        @if ($datosReporte['departamento'])
                            <div class="info-row">
                                <div class="info-label">Departamento:</div>
                                <div class="info-value">{{ $datosReporte['departamento'] }}</div>
                            </div>
                        @endif
                        @if ($datosReporte['municipio'])
                            <div class="info-row">
                                <div class="info-label">Municipio:</div>
                                <div class="info-value">{{ $datosReporte['municipio'] }}</div>
                            </div>
                        @endif
                        @if ($datosReporte['radio_objetivo'])
                            <div class="info-row">
                                <div class="info-label">Radio del Objetivo:</div>
                                <div class="info-value">{{ $datosReporte['radio_objetivo'] }}</div>
                            </div>
                        @endif
                        @if ($datosReporte['resultado'] !== '—')
                            <div class="info-row">
                                <div class="info-label">Resultado:</div>
                                <div class="info-value">
                                    <span class="badge badge-green">{{ $datosReporte['resultado'] }}</span>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- ── Actividades ── --}}
        <div class="section">
            <div class="section-title">ACTIVIDAD REALIZADA</div>
            @if (!empty($datosReporte['actividades']))
                @foreach ($datosReporte['actividades'] as $actividad)
                    <div class="info-box" style="margin-bottom:6px;">
                        <div class="info-grid">
                            @if (!empty($actividad['descripcion_orden']))
                                <div class="info-row">
                                    <div class="info-label">Actividad asignada:</div>
                                    <div class="info-value">{{ $actividad['descripcion_orden'] }}</div>
                                </div>
                            @endif
                            <div class="info-row">
                                <div class="info-label">Respuesta del investigador:</div>
                                <div class="info-value">{{ $actividad['actividades'] }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="content-box">Sin actividades registradas.</div>
            @endif
        </div>

        {{-- ── Observaciones ── --}}
        <div class="section">
            <div class="section-title">OBSERVACIONES</div>
            <div class="content-box">{{ $datosReporte['observaciones'] }}</div>
        </div>

        {{-- ── Conclusión del Coordinador ── --}}
        @if (!empty($datosReporte['conclusion']))
            <div class="section">
                <div class="section-title">CONCLUSIÓN DEL COORDINADOR</div>
                <div class="content-box">{{ $datosReporte['conclusion'] }}</div>
            </div>
        @endif

        {{-- ── Resultado de la Auditoría ── --}}
        @php $auditoria = $reporte->auditoria; @endphp
        @if ($auditoria && $auditoria->estado?->value !== 'pendiente_auditoria')
            <div class="section">
                <div class="section-title">RESULTADO DE LA AUDITORÍA</div>
                <div class="info-box">
                    <div class="info-grid">
                        <div class="info-row">
                            <div class="info-label">Resultado:</div>
                            <div class="info-value">
                                @if ($auditoria->estado?->value === 'confirmado')
                                    <span class="badge badge-green">&#10003; Confirmado</span>
                                @else
                                    <span class="badge badge-red">&#10005; Negativo</span>
                                @endif
                            </div>
                        </div>
                        @if ($auditoria->fecha_auditoria)
                            <div class="info-row">
                                <div class="info-label">Fecha de Auditoría:</div>
                                <div class="info-value">{{ $auditoria->fecha_auditoria->format('d/m/Y H:i') }}</div>
                            </div>
                        @endif
                        @if ($auditoria->auditadoPor)
                            <div class="info-row">
                                <div class="info-label">Auditado por:</div>
                                <div class="info-value">{{ $auditoria->auditadoPor->fullName() }}</div>
                            </div>
                        @endif
                        @if ($auditoria->observaciones)
                            <div class="info-row">
                                <div class="info-label">Observaciones:</div>
                                <div class="info-value">{{ $auditoria->observaciones }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- ── Evidencias Fotográficas ── --}}
        <div class="section">
            <div class="section-title">EVIDENCIAS FOTOGRÁFICAS</div>
            @if (count($imagenes) > 0)
                <p style="font-size:7.5pt; color:#64748b; margin:4px 0 8px 0;">
                    Se registraron <strong>{{ count($imagenes) }}</strong> evidencia(s) fotográfica(s).
                </p>
                @php $rows = array_chunk($imagenes, 2); @endphp
                <table class="evidencias-tabla">
                    @foreach ($rows as $rowIndex => $row)
                        <tr>
                            @foreach ($row as $colIndex => $imgSrc)
                                <td class="evidencia-td">
                                    <img src="{{ $imgSrc }}" class="evidencia-img" alt="Evidencia">
                                    <div class="evidencia-num">Evidencia {{ $rowIndex * 2 + $colIndex + 1 }}</div>
                                </td>
                            @endforeach
                            @if (count($row) === 1)<td class="evidencia-td"></td>@endif
                        </tr>
                    @endforeach
                </table>
            @else
                <div class="content-box" style="color:#64748b; font-style:italic;">
                    No se registraron evidencias fotográficas para esta operación.
                </div>
            @endif
        </div>

        {{-- ── Destinación ── --}}
        @include('pdf.partials.destinacion-informe')

        {{-- ── Firma Digital ── --}}
        <div class="firma-section">
            <div class="section-title">FIRMA DIGITAL</div>
            <div class="firma-box">
                <div class="firma-badge">&#10003; FIRMADO DIGITALMENTE</div>
                <div class="firma-linea"></div>
                <div class="firma-nombre">{{ $firma['nombre'] }}</div>
                @if ($firma['cargo'])
                    <div class="firma-meta"><strong>Cargo:</strong> {{ $firma['cargo'] }}</div>
                @endif
                <div class="firma-meta"><strong>Correo electrónico:</strong> {{ $firma['email'] }}</div>
                <div style="margin-top:8px;">
                    <div class="firma-meta"><strong>Fecha y hora de firma:</strong> {{ $firma['fecha'] }}</div>
                    <div class="firma-meta"><strong>IP del firmante:</strong> {{ $firma['ip'] }}</div>
                    <div class="firma-meta"><strong>Dispositivo / Navegador:</strong> {{ $firma['agente'] }}</div>
                </div>
            </div>
        </div>

    </div>
</body>
</html>
```

- [ ] **Paso 10.2 — Commit**

```bash
git add resources/views/pdf/reporte-gestion-legal.blade.php
git commit -m "feat: agregar vista PDF para Gestión Legal basada en plantilla de Investigación"
```

---

## Task 11: Pint final + Tests

**Files:**
- Modify: `tests/Feature/ReportePdfTest.php`

- [ ] **Paso 11.1 — Verificar que no quedan referencias a Capturas/Bandas**

```bash
grep -r "TipoServicio::Capturas\|TipoServicio::Bandas\|capturas.*yellow\|bandas.*red" app/ resources/ routes/ --include="*.php" --include="*.blade.php"
```

Resultado esperado: sin coincidencias. Si hay alguna, corregirla antes de continuar.

- [ ] **Paso 11.2 — Agregar tests al ReportePdfTest**

En `tests/Feature/ReportePdfTest.php`, agregar al final del archivo:

```php
test('pdf gestion-legal returns 403 for non-gestion-legal orders', function () {
    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $orden = OrdenJob::factory()->create(['tipo_servicio' => TipoServicio::Fraudes->value]);

    $this->actingAs($user)
        ->get(route('operaciones.ordenes-trabajo.reporte-gestion-legal', $orden))
        ->assertForbidden();
});

test('pdf gestion-legal returns 404 when no reporte exists', function () {
    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::GestionLegal->value,
        'estado'        => EstadoOrden::EnRevision->value,
    ]);

    $this->actingAs($user)
        ->get(route('operaciones.ordenes-trabajo.reporte-gestion-legal', $orden))
        ->assertNotFound();
});

test('guests are redirected to login on gestion-legal pdf download', function () {
    $orden = OrdenJob::factory()->create(['tipo_servicio' => TipoServicio::GestionLegal->value]);

    $this->get(route('operaciones.ordenes-trabajo.reporte-gestion-legal', $orden))
        ->assertRedirect(route('login'));
});
```

- [ ] **Paso 11.3 — Correr los tests**

```bash
php artisan test --compact --filter=ReportePdf
```

Resultado esperado: todos los tests pasan (verde).

- [ ] **Paso 11.4 — Pint en todos los archivos PHP modificados**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Paso 11.5 — Commit final**

```bash
git add tests/Feature/ReportePdfTest.php
git commit -m "test: agregar tests para PDF de Gestión Legal"
```
