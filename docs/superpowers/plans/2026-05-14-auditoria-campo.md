# Auditorías de Campo — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add "Auditorías de Campo" service type with two subtypes and a 9-section digital report form (including canvas-based signatures) to the existing OrdenJob/Reporte lifecycle.

**Architecture:** Follows the exact `GestionLegal` pattern — new `TipoServicio` enum case, nullable `tipo_auditoria_campo` column on `orden_jobs`, report data in `datos_servicio` JSON. Signatures captured via `signature_pad` npm package (no CDN), exposed globally from `app.js`, driven by Alpine.js canvas components in the blade view.

**Tech Stack:** Laravel 13, Livewire 4, Filament Forms (Schemas), Alpine.js, signature_pad (npm), Tailwind CSS v4, Flux UI

---

## File Map

| File | Change |
|------|--------|
| `package.json` | Add `signature_pad` dependency |
| `resources/js/app.js` | Import and expose `SignaturePad` globally |
| `app/Enums/TipoServicio.php` | Add `AuditoriaCampo` case |
| `database/migrations/2026_05_14_000001_add_tipo_auditoria_campo_to_orden_jobs.php` | Create — add column |
| `app/Livewire/Coordinador/OrdenCreate.php` | Add subtype section for AuditoriaCampo |
| `app/Livewire/Coordinador/OrdenesTrabajoIndex.php` | Add subtype to edit form |
| `app/Livewire/Tecnico/ReporteCreate.php` | Add 2 public props + schema method + submit method |
| `resources/views/livewire/tecnico/reporte-create.blade.php` | Add signature canvas Alpine components |
| `app/Livewire/Coordinador/ReporteRevisar.php` | Add `AuditoriaCampo` case to `datosServicioDisplay()` |
| `resources/views/livewire/coordinador/reporte-revisar.blade.php` | Add display section for AuditoriaCampo |
| `tests/Feature/AuditoriaCampoTest.php` | Create — feature tests |

---

### Task 1: Install signature_pad and expose globally

**Files:**
- Modify: `package.json`
- Modify: `resources/js/app.js`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/AuditoriaCampoTest.php
<?php

use App\Enums\TipoServicio;

it('AuditoriaCampo enum case exists with correct label and color', function () {
    $case = TipoServicio::AuditoriaCampo;
    expect($case->label())->toBe('Auditorías de Campo')
        ->and($case->color())->toBe('purple')
        ->and($case->tieneTecnicoAsignado())->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --compact --filter="AuditoriaCampo enum case exists"
```
Expected: FAIL — `AuditoriaCampo` not found on `TipoServicio`

- [ ] **Step 3: Install signature_pad**

```bash
npm install signature_pad
```

- [ ] **Step 4: Update `resources/js/app.js`**

Add after the existing leaflet imports:

```js
import SignaturePad from 'signature_pad';
window.SignaturePad = SignaturePad;
```

Full file after change:
```js
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import 'leaflet.markercluster';
import 'leaflet.markercluster/dist/MarkerCluster.css';
import 'leaflet.markercluster/dist/MarkerCluster.Default.css';

window.L = L;

import SignaturePad from 'signature_pad';
window.SignaturePad = SignaturePad;
```

- [ ] **Step 5: Build assets**

```bash
npm run build
```
Expected: build completes with no errors.

- [ ] **Step 6: Commit**

```bash
git add package.json package-lock.json resources/js/app.js
git commit -m "feat: install signature_pad and expose globally"
```

---

### Task 2: TipoServicio enum + migration

**Files:**
- Modify: `app/Enums/TipoServicio.php`
- Create: `database/migrations/2026_05_14_000001_add_tipo_auditoria_campo_to_orden_jobs.php`

- [ ] **Step 1: Add `AuditoriaCampo` case to `app/Enums/TipoServicio.php`**

Full file after change:

```php
<?php

namespace App\Enums;

enum TipoServicio: string
{
    case Fraudes = 'fraudes';
    case Investigacion = 'investigacion';
    case GestionLegal = 'gestion_legal';
    case Vigilancia = 'vigilancia';
    case Gestion_Riesgo = 'Gestión de Riesgo';
    case AuditoriaCampo = 'auditoria_campo';

    public function label(): string
    {
        return match ($this) {
            self::Fraudes        => 'Fraudes',
            self::Investigacion  => 'Investigación',
            self::GestionLegal   => 'Gestión Legal',
            self::Vigilancia     => 'Dispositivos de Vigilancia',
            self::Gestion_Riesgo => 'Gestión de Riesgo',
            self::AuditoriaCampo => 'Auditorías de Campo',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Fraudes        => 'danger',
            self::Investigacion  => 'warning',
            self::GestionLegal   => 'info',
            self::Vigilancia     => 'success',
            self::Gestion_Riesgo => 'primary',
            self::AuditoriaCampo => 'purple',
        };
    }

    public function tieneTecnicoAsignado(): bool
    {
        return $this !== self::Vigilancia;
    }

    public function tieneContrato(): bool
    {
        return in_array($this, [self::Fraudes, self::Investigacion, self::Vigilancia]);
    }

    public function tieneMedidor(): bool
    {
        return $this === self::Fraudes;
    }
}
```

- [ ] **Step 2: Run the test from Task 1 — should pass now**

```bash
php artisan test --compact --filter="AuditoriaCampo enum case exists"
```
Expected: PASS

- [ ] **Step 3: Create the migration**

```bash
php artisan make:migration add_tipo_auditoria_campo_to_orden_jobs --no-interaction
```

- [ ] **Step 4: Write migration content**

Open the generated file and replace contents with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orden_jobs', function (Blueprint $table) {
            $table->string('tipo_auditoria_campo')->nullable()->after('tipo_gestion_legal');
        });
    }

    public function down(): void
    {
        Schema::table('orden_jobs', function (Blueprint $table) {
            $table->dropColumn('tipo_auditoria_campo');
        });
    }
};
```

Also add `tipo_auditoria_campo` to the original create migration for consistency:
In `database/migrations/*_create_orden_jobs_table.php`, find the `tipo_gestion_legal` line and add after it:
```php
$table->string('tipo_auditoria_campo')->nullable();
```

- [ ] **Step 5: Add `tipo_auditoria_campo` to `OrdenJob::$fillable`**

In `app/Models/OrdenJob.php`, add `'tipo_auditoria_campo'` to the `$fillable` array, after `'tipo_gestion_legal'`:

```php
protected $fillable = [
    'personals_id',
    'creado_por_id',
    'company_id',
    'tipo_servicio',
    'numero_orden',
    'contrato',
    'cliente',
    'direccion',
    'barrio',
    'medidor',
    'tipo_medidor',
    'ciclo',
    'antecedente',
    'objetivo',
    'actividades',
    'numero_noticia_criminal',
    'tipo_investigacion',
    'tipo_gestion_legal',
    'tipo_auditoria_campo',
    'documentos',
    'prioridad',
    'estado',
    'estado_servicio',
    'fecha_asignacion',
    'fecha_limite',
    'fecha_completado',
];
```

- [ ] **Step 6: Write test for OrdenJob creation with subtype**

Add to `tests/Feature/AuditoriaCampoTest.php`:

```php
it('can create an OrdenJob with tipo_auditoria_campo', function () {
    $company = \App\Models\Company::factory()->create();
    $user = \App\Models\User::factory()->create(['company_id' => $company->id, 'is_superadmin' => false]);
    $this->actingAs($user);

    $orden = \App\Models\OrdenJob::create([
        'tipo_servicio'         => \App\Enums\TipoServicio::AuditoriaCampo,
        'tipo_auditoria_campo'  => 'visita_calidad_emergencia',
        'prioridad'             => \App\Enums\PrioridadOrden::Media,
        'estado'                => \App\Enums\EstadoOrden::Pendiente,
        'estado_servicio'       => true,
    ]);

    expect($orden->tipo_auditoria_campo)->toBe('visita_calidad_emergencia')
        ->and($orden->tipo_servicio)->toBe(\App\Enums\TipoServicio::AuditoriaCampo);
});
```

- [ ] **Step 7: Run migration and tests**

```bash
php artisan migrate --no-interaction
php artisan test --compact --filter="AuditoriaCampo"
```
Expected: 2 tests PASS

- [ ] **Step 8: Run pint**

```bash
vendor/bin/pint app/Enums/TipoServicio.php app/Models/OrdenJob.php --format agent
```

- [ ] **Step 9: Commit**

```bash
git add app/Enums/TipoServicio.php app/Models/OrdenJob.php database/migrations/
git commit -m "feat: add AuditoriaCampo TipoServicio enum case and tipo_auditoria_campo migration"
```

---

### Task 3: OrdenCreate — subtype selector for AuditoriaCampo

**Files:**
- Modify: `app/Livewire/Coordinador/OrdenCreate.php`

- [ ] **Step 1: Add AuditoriaCampo section to `OrdenCreate::form()`**

In `app/Livewire/Coordinador/OrdenCreate.php`, after the Gestión de Riesgo section (around line 208), add a new section before the `Actividad A Realizar` section:

```php
// ── Sección: Auditoría de Campo ───────────────────────────────
Section::make('Datos de Auditoría de Campo')
    ->columns(2)
    ->visible(fn ($get) => $get('tipo_servicio') === TipoServicio::AuditoriaCampo->value)
    ->schema([
        Forms\Components\Select::make('tipo_auditoria_campo')
            ->label('Subtipo de Auditoría')
            ->options([
                'visita_calidad_emergencia'      => 'Visita de Calidad — Emergencia',
                'visita_calidad_trabajos_varios' => 'Visita de Calidad — Trabajos Varios',
            ])
            ->required(fn ($get) => $get('tipo_servicio') === TipoServicio::AuditoriaCampo->value)
            ->placeholder('Seleccionar')
            ->columnSpanFull(),
        Forms\Components\TextInput::make('cliente')
            ->label('Nombre del Usuario')
            ->required(fn ($get) => $get('tipo_servicio') === TipoServicio::AuditoriaCampo->value)
            ->maxLength(255),
        Forms\Components\TextInput::make('contrato')
            ->label('Contrato')
            ->maxLength(255),
        Forms\Components\TextInput::make('direccion')
            ->label('Dirección')
            ->maxLength(255)
            ->columnSpanFull(),
    ]),
```

- [ ] **Step 2: Run pint**

```bash
vendor/bin/pint app/Livewire/Coordinador/OrdenCreate.php --format agent
```

- [ ] **Step 3: Commit**

```bash
git add app/Livewire/Coordinador/OrdenCreate.php
git commit -m "feat: add AuditoriaCampo subtype selector to OrdenCreate"
```

---

### Task 4: OrdenesTrabajoIndex — subtype in edit form

**Files:**
- Modify: `app/Livewire/Coordinador/OrdenesTrabajoIndex.php`

- [ ] **Step 1: Add AuditoriaCampo section to `ordenFormSchema()`**

In `app/Livewire/Coordinador/OrdenesTrabajoIndex.php`, inside `ordenFormSchema()`, after the Vigilancia section (look for the `Section::make('Datos del Punto Vigilado')` block), add:

```php
// ── Auditoría de Campo ────────────────────────────────────────
Section::make('Datos de Auditoría de Campo')
    ->columns(2)
    ->visible($tipoServicio === TipoServicio::AuditoriaCampo)
    ->schema([
        Forms\Components\Select::make('tipo_auditoria_campo')
            ->label('Subtipo de Auditoría')
            ->options([
                'visita_calidad_emergencia'      => 'Visita de Calidad — Emergencia',
                'visita_calidad_trabajos_varios' => 'Visita de Calidad — Trabajos Varios',
            ])
            ->placeholder('Seleccionar')
            ->columnSpanFull(),
        Forms\Components\TextInput::make('cliente')
            ->label('Nombre del Usuario')
            ->maxLength(255),
        Forms\Components\TextInput::make('contrato')
            ->label('Contrato')
            ->maxLength(255),
        Forms\Components\TextInput::make('direccion')
            ->label('Dirección')
            ->maxLength(255)
            ->columnSpanFull(),
    ]),
```

- [ ] **Step 2: Run pint**

```bash
vendor/bin/pint app/Livewire/Coordinador/OrdenesTrabajoIndex.php --format agent
```

- [ ] **Step 3: Commit**

```bash
git add app/Livewire/Coordinador/OrdenesTrabajoIndex.php
git commit -m "feat: add AuditoriaCampo section to OrdenesTrabajoIndex edit form"
```

---

### Task 5: ReporteCreate — schema, submit, and signature properties

**Files:**
- Modify: `app/Livewire/Tecnico/ReporteCreate.php`

- [ ] **Step 1: Add two public properties for signatures**

In `app/Livewire/Tecnico/ReporteCreate.php`, add these two properties after `public array $data = [];`:

```php
public ?string $firmaUsuarioBase64 = null;
public ?string $firmaAuditorBase64 = null;
```

- [ ] **Step 2: Add the AuditoriaCampo case to `form()` method**

In `form()`, the `match` block currently ends with `Gestion_Riesgo`. Add the new case:

```php
TipoServicio::AuditoriaCampo => $this->auditoriaCampoSchema(),
```

- [ ] **Step 3: Add the AuditoriaCampo case to `submit()` method**

In `submit()`, the `match` block currently ends with `Gestion_Riesgo`. Add:

```php
TipoServicio::AuditoriaCampo => $this->submitAuditoriaCampo($data),
```

- [ ] **Step 4: Add `auditoriaCampoSchema()` method**

Add this private method after `gestionRiesgoSchema()`:

```php
/** @return array<int, Section> */
private function auditoriaCampoSchema(): array
{
    $subtipo = match ($this->orden->tipo_auditoria_campo) {
        'visita_calidad_emergencia'      => 'Visita de Calidad — Emergencia',
        'visita_calidad_trabajos_varios' => 'Visita de Calidad — Trabajos Varios',
        default                          => 'Auditoría de Campo',
    };

    return [
        Section::make('1. Información General')
            ->icon('heroicon-o-document-text')
            ->columns(2)
            ->schema([
                Forms\Components\Placeholder::make('subtipo_display')
                    ->label('Subtipo')
                    ->content($subtipo)
                    ->columnSpanFull(),
                Forms\Components\Placeholder::make('ot_display')
                    ->label('OT')
                    ->content($this->orden->numero_orden),
                Forms\Components\Placeholder::make('contrato_display')
                    ->label('Contrato')
                    ->content($this->orden->contrato ?? '—'),
                Forms\Components\Placeholder::make('cliente_display')
                    ->label('Nombre del Usuario')
                    ->content($this->orden->cliente ?? '—'),
                Forms\Components\Placeholder::make('direccion_display')
                    ->label('Dirección')
                    ->content($this->orden->direccion ?? '—'),
                Forms\Components\DateTimePicker::make('fecha_hora_atencion')
                    ->label('Fecha y Hora de Atención')
                    ->required()
                    ->displayFormat('d/m/Y H:i')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('ciudad')
                    ->label('Ciudad')
                    ->maxLength(255),
                Forms\Components\TextInput::make('celular_usuario')
                    ->label('Celular del Usuario')
                    ->tel()
                    ->maxLength(20),
                Forms\Components\Textarea::make('comentario_usuario')
                    ->label('Comentario Usuario / Solicitud')
                    ->rows(3)
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('comentario_tecnico')
                    ->label('Comentario del Técnico')
                    ->rows(3)
                    ->columnSpanFull(),
            ]),

        Section::make('2. Datos de Quién Atiende la Visita')
            ->icon('heroicon-o-user-circle')
            ->columns(2)
            ->schema([
                Forms\Components\TextInput::make('nombre_atiende')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('vinculo_usuario')
                    ->label('Vínculo con el usuario')
                    ->options([
                        'titular'  => 'Titular',
                        'familiar' => 'Familiar',
                        'vecino'   => 'Vecino',
                        'otro'     => 'Otro',
                    ])
                    ->placeholder('Seleccionar'),
                Forms\Components\TextInput::make('celular_atiende')
                    ->label('Celular')
                    ->tel()
                    ->maxLength(20),
            ]),

        Section::make('3. Validación de la Atención y Ejecución')
            ->icon('heroicon-o-check-circle')
            ->columns(2)
            ->schema([
                Forms\Components\Toggle::make('recibio_visita')
                    ->label('¿Recibió la visita del técnico?')
                    ->required()
                    ->default(false),
                Forms\Components\Toggle::make('se_realizo_revision')
                    ->label('¿Se realizó revisión técnica o trabajos?')
                    ->required()
                    ->default(false),
                Forms\Components\DatePicker::make('fecha_ejecucion')
                    ->label('Fecha de ejecución')
                    ->displayFormat('d/m/Y'),
                Forms\Components\TextInput::make('tiempo_ejecucion')
                    ->label('Tiempo aproximado de ejecución')
                    ->placeholder('Ej: 2 horas')
                    ->maxLength(100),
                Forms\Components\Textarea::make('descripcion_trabajo')
                    ->label('Descripción del trabajo / reparación realizada')
                    ->rows(3)
                    ->columnSpanFull(),
            ]),

        Section::make('4. Hallazgos Técnicos y Validación Física')
            ->icon('heroicon-o-magnifying-glass')
            ->columns(2)
            ->schema([
                Forms\Components\Toggle::make('se_detecto_fuga')
                    ->label('¿Se detectó fuga o falla?')
                    ->default(false)
                    ->live(),
                Forms\Components\TextInput::make('ppm')
                    ->label('Medición PPM')
                    ->maxLength(50)
                    ->visible(fn ($get) => (bool) $get('se_detecto_fuga')),
                Forms\Components\TextInput::make('vol_porcentaje')
                    ->label('VOL %')
                    ->maxLength(50)
                    ->visible(fn ($get) => (bool) $get('se_detecto_fuga')),
                Forms\Components\Toggle::make('problema_persiste')
                    ->label('¿Actualmente el problema persiste?')
                    ->default(false),
                Forms\Components\Toggle::make('evidencia_reparacion')
                    ->label('¿Se evidencia la reparación en el punto?')
                    ->default(false),
                Forms\Components\Toggle::make('servicio_correcto')
                    ->label('¿El servicio funciona correctamente?')
                    ->default(false),
            ]),

        Section::make('5. Materiales y Cobros')
            ->icon('heroicon-o-currency-dollar')
            ->columns(2)
            ->schema([
                Forms\Components\Toggle::make('se_usaron_materiales')
                    ->label('¿Se utilizaron materiales?')
                    ->default(false)
                    ->live(),
                Forms\Components\Select::make('quien_suministro_materiales')
                    ->label('¿Quién suministró los materiales?')
                    ->options([
                        'tecnico'  => 'Técnico',
                        'usuario'  => 'Usuario',
                        'empresa'  => 'Empresa',
                    ])
                    ->placeholder('Seleccionar')
                    ->visible(fn ($get) => (bool) $get('se_usaron_materiales')),
                Forms\Components\Toggle::make('usuario_asumio_costo')
                    ->label('¿El usuario asumió algún costo?')
                    ->default(false)
                    ->live(),
                Forms\Components\TextInput::make('valor_pagado')
                    ->label('Valor pagado')
                    ->maxLength(100)
                    ->visible(fn ($get) => (bool) $get('usuario_asumio_costo')),
                Forms\Components\Select::make('medio_pago')
                    ->label('Medio de pago')
                    ->options([
                        'efectivo'     => 'Efectivo',
                        'transferencia' => 'Transferencia',
                    ])
                    ->placeholder('Seleccionar')
                    ->visible(fn ($get) => (bool) $get('usuario_asumio_costo')),
                Forms\Components\Toggle::make('entrego_factura')
                    ->label('¿Se entregó factura, IVT o soporte de cobro?')
                    ->default(false),
            ]),

        Section::make('6. Trazabilidad y Contradicción')
            ->icon('heroicon-o-document-check')
            ->columns(2)
            ->schema([
                Forms\Components\TextInput::make('tecnico_dejo_documento')
                    ->label('¿El técnico dejó algún documento (IVT, certificado)?')
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('informaron_no_estaba')
                    ->label('¿Le informaron erróneamente que no estaba en la vivienda?')
                    ->default(false),
            ]),

        Section::make('7. Autorización y Firmas')
            ->icon('heroicon-o-pencil')
            ->columns(2)
            ->schema([
                Forms\Components\Toggle::make('autoriza')
                    ->label('¿El usuario autoriza la recolección de esta información?')
                    ->required()
                    ->default(false)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('cedula_usuario')
                    ->label('Cédula del Usuario')
                    ->required()
                    ->maxLength(20),
                Forms\Components\TextInput::make('cedula_auditor')
                    ->label('Cédula del Auditor')
                    ->required()
                    ->maxLength(20),
            ]),

        Section::make('8. Observaciones')
            ->icon('heroicon-o-pencil-square')
            ->schema([
                Forms\Components\Textarea::make('observaciones')
                    ->label('Observaciones')
                    ->placeholder('Observaciones adicionales...')
                    ->rows(4)
                    ->columnSpanFull(),
            ]),

        Section::make('9. Soporte Fotográfico')
            ->icon('heroicon-o-camera')
            ->schema([
                Forms\Components\FileUpload::make('imagenes')
                    ->label('Fotos')
                    ->disk('s3')
                    ->directory('reportes/'.$this->orden->numero_orden)
                    ->fetchFileInformation(false)
                    ->multiple()
                    ->image()
                    ->imagePreviewHeight('100')
                    ->maxFiles(10)
                    ->maxSize(5120)
                    ->reorderable()
                    ->panelLayout('grid')
                    ->extraInputAttributes(['capture' => 'environment'])
                    ->columnSpanFull(),
            ]),
    ];
}
```

- [ ] **Step 5: Add `submitAuditoriaCampo()` method**

Add this private method after `submitServicioGenerico()`:

```php
/** @param array<string, mixed> $data */
private function submitAuditoriaCampo(array $data): void
{
    $datosServicio = $data;
    unset($datosServicio['observaciones'], $datosServicio['imagenes'], $datosServicio['firma_usuario_canvas'], $datosServicio['firma_auditor_canvas']);

    $datosServicio['firma_usuario'] = $this->firmaUsuarioBase64;
    $datosServicio['firma_auditor'] = $this->firmaAuditorBase64;

    reportes::create([
        'orden_job_id'  => $this->orden->id,
        'personals_id'  => auth()->user()->personals_id,
        'numero_orden'  => $this->orden->numero_orden,
        'observaciones' => $data['observaciones'] ?? null,
        'imagenes'      => $data['imagenes'] ?? null,
        'datos_servicio' => $datosServicio,
    ]);

    $this->orden->update(['estado' => EstadoOrden::EnRevision]);

    $this->notificarCoordinador();

    $this->dispatch('borrador-limpiar');

    $this->redirect(route('tecnico.mis-ordenes'), navigate: true);
}
```

- [ ] **Step 6: Run pint**

```bash
vendor/bin/pint app/Livewire/Tecnico/ReporteCreate.php --format agent
```

- [ ] **Step 7: Commit**

```bash
git add app/Livewire/Tecnico/ReporteCreate.php
git commit -m "feat: add AuditoriaCampo form schema and submit logic to ReporteCreate"
```

---

### Task 6: Signature pad canvas in reporte-create blade

**Files:**
- Modify: `resources/views/livewire/tecnico/reporte-create.blade.php`

The signature canvases are rendered in the blade INSIDE `<form wire:submit="submit">`, after `{{ $this->form }}`, only for AuditoriaCampo. On `@pointerup` each canvas calls `$wire.set()` to update the two public Livewire properties. The `cedula_usuario` and `cedula_auditor` fields are already in the Filament schema (section 7) and captured via normal form state.

- [ ] **Step 1: Add signature section to the blade**

In `resources/views/livewire/tecnico/reporte-create.blade.php`, find this block:

```blade
    <form wire:submit="submit" class="space-y-6">
        <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            {{ $this->form }}
        </div>

        <div class="flex justify-end gap-3">
```

Replace with:

```blade
    <form wire:submit="submit" class="space-y-6">
        <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            {{ $this->form }}
        </div>

        @if ($orden->tipo_servicio === \App\Enums\TipoServicio::AuditoriaCampo)
            <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900 space-y-4">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                    Firmas
                </h3>
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    {{-- Firma Usuario --}}
                    <div x-data="{
                        pad: null,
                        init() {
                            this.pad = new SignaturePad(this.$refs.canvas, { backgroundColor: 'rgb(255,255,255)' });
                            const ratio = Math.max(window.devicePixelRatio || 1, 1);
                            this.$refs.canvas.width = this.$refs.canvas.offsetWidth * ratio;
                            this.$refs.canvas.height = this.$refs.canvas.offsetHeight * ratio;
                            this.$refs.canvas.getContext('2d').scale(ratio, ratio);
                        },
                        clear() { this.pad.clear(); $wire.set('firmaUsuarioBase64', null); }
                    }">
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Firma del Usuario</label>
                        <div class="relative rounded-lg border-2 border-zinc-300 bg-white dark:border-zinc-600" style="height:180px;">
                            <canvas x-ref="canvas" class="w-full h-full rounded-lg touch-none"
                                @pointerup="$wire.set('firmaUsuarioBase64', pad.isEmpty() ? null : pad.toDataURL('image/png'))">
                            </canvas>
                            <div class="absolute bottom-2 right-2">
                                <button type="button" @click="clear()"
                                    class="rounded bg-zinc-100 px-2 py-1 text-xs text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-700 dark:text-zinc-300">
                                    Limpiar
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Firma Auditor --}}
                    <div x-data="{
                        pad: null,
                        init() {
                            this.pad = new SignaturePad(this.$refs.canvas, { backgroundColor: 'rgb(255,255,255)' });
                            const ratio = Math.max(window.devicePixelRatio || 1, 1);
                            this.$refs.canvas.width = this.$refs.canvas.offsetWidth * ratio;
                            this.$refs.canvas.height = this.$refs.canvas.offsetHeight * ratio;
                            this.$refs.canvas.getContext('2d').scale(ratio, ratio);
                        },
                        clear() { this.pad.clear(); $wire.set('firmaAuditorBase64', null); }
                    }">
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Firma del Auditor</label>
                        <div class="relative rounded-lg border-2 border-zinc-300 bg-white dark:border-zinc-600" style="height:180px;">
                            <canvas x-ref="canvas" class="w-full h-full rounded-lg touch-none"
                                @pointerup="$wire.set('firmaAuditorBase64', pad.isEmpty() ? null : pad.toDataURL('image/png'))">
                            </canvas>
                            <div class="absolute bottom-2 right-2">
                                <button type="button" @click="clear()"
                                    class="rounded bg-zinc-100 px-2 py-1 text-xs text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-700 dark:text-zinc-300">
                                    Limpiar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="flex justify-end gap-3">
```

- [ ] **Step 2: Commit**

```bash
git add resources/views/livewire/tecnico/reporte-create.blade.php
git commit -m "feat: add signature canvas pads to reporte-create blade for AuditoriaCampo"
```

---

### Task 7: ReporteRevisar — display AuditoriaCampo

**Files:**
- Modify: `app/Livewire/Coordinador/ReporteRevisar.php`
- Modify: `resources/views/livewire/coordinador/reporte-revisar.blade.php`

- [ ] **Step 1: Add `AuditoriaCampo` case to `datosServicioDisplay()`**

In `app/Livewire/Coordinador/ReporteRevisar.php`, inside `datosServicioDisplay()`, add a new `if` block after the existing `TipoServicio::Vigilancia` block and before `return []`:

```php
if ($tipo === TipoServicio::AuditoriaCampo) {
    $subtipo = match ($this->orden->tipo_auditoria_campo) {
        'visita_calidad_emergencia'      => 'Visita de Calidad — Emergencia',
        'visita_calidad_trabajos_varios' => 'Visita de Calidad — Trabajos Varios',
        default                          => 'Auditoría de Campo',
    };

    return [
        'subtipo'                     => $subtipo,
        'fecha_hora_atencion'         => $datos['fecha_hora_atencion'] ?? null,
        'ciudad'                      => $datos['ciudad'] ?? null,
        'celular_usuario'             => $datos['celular_usuario'] ?? null,
        'comentario_usuario'          => $datos['comentario_usuario'] ?? null,
        'comentario_tecnico'          => $datos['comentario_tecnico'] ?? null,
        'nombre_atiende'              => $datos['nombre_atiende'] ?? null,
        'vinculo_usuario'             => $datos['vinculo_usuario'] ?? null,
        'celular_atiende'             => $datos['celular_atiende'] ?? null,
        'recibio_visita'              => $datos['recibio_visita'] ?? false,
        'se_realizo_revision'         => $datos['se_realizo_revision'] ?? false,
        'fecha_ejecucion'             => $datos['fecha_ejecucion'] ?? null,
        'tiempo_ejecucion'            => $datos['tiempo_ejecucion'] ?? null,
        'descripcion_trabajo'         => $datos['descripcion_trabajo'] ?? null,
        'se_detecto_fuga'             => $datos['se_detecto_fuga'] ?? false,
        'ppm'                         => $datos['ppm'] ?? null,
        'vol_porcentaje'              => $datos['vol_porcentaje'] ?? null,
        'problema_persiste'           => $datos['problema_persiste'] ?? false,
        'evidencia_reparacion'        => $datos['evidencia_reparacion'] ?? false,
        'servicio_correcto'           => $datos['servicio_correcto'] ?? false,
        'se_usaron_materiales'        => $datos['se_usaron_materiales'] ?? false,
        'quien_suministro_materiales' => $datos['quien_suministro_materiales'] ?? null,
        'usuario_asumio_costo'        => $datos['usuario_asumio_costo'] ?? false,
        'valor_pagado'                => $datos['valor_pagado'] ?? null,
        'medio_pago'                  => $datos['medio_pago'] ?? null,
        'entrego_factura'             => $datos['entrego_factura'] ?? false,
        'tecnico_dejo_documento'      => $datos['tecnico_dejo_documento'] ?? null,
        'informaron_no_estaba'        => $datos['informaron_no_estaba'] ?? false,
        'autoriza'                    => $datos['autoriza'] ?? false,
        'firma_usuario'               => $datos['firma_usuario'] ?? null,
        'cedula_usuario'              => $datos['cedula_usuario'] ?? null,
        'firma_auditor'               => $datos['firma_auditor'] ?? null,
        'cedula_auditor'              => $datos['cedula_auditor'] ?? null,
    ];
}
```

Also add `TipoServicio` use statement to imports if `AuditoriaCampo` is used inline — but it's already imported via the enum.

- [ ] **Step 2: Add AuditoriaCampo display block to the blade view**

In `resources/views/livewire/coordinador/reporte-revisar.blade.php`, inside the `{{-- ═══ Tipos de Servicio ═══ --}}` block, find where the other `@if` blocks end (after Vigilancia or the last service type block) and add:

```blade
@if ($orden->tipo_servicio === \App\Enums\TipoServicio::AuditoriaCampo)
    {{-- Subtipo --}}
    <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500">Auditoría de Campo</h3>
        <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ $datosServicio['subtipo'] ?? '—' }}</p>
    </div>

    {{-- Sección 1: Información General --}}
    <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500">1. Información General</h3>
        <dl class="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-3 text-sm">
            <div><dt class="text-xs font-medium uppercase text-zinc-400">OT</dt><dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">{{ $orden->numero_orden }}</dd></div>
            <div><dt class="text-xs font-medium uppercase text-zinc-400">Contrato</dt><dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">{{ $orden->contrato ?? '—' }}</dd></div>
            <div><dt class="text-xs font-medium uppercase text-zinc-400">Usuario</dt><dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">{{ $orden->cliente ?? '—' }}</dd></div>
            <div><dt class="text-xs font-medium uppercase text-zinc-400">Dirección</dt><dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">{{ $orden->direccion ?? '—' }}</dd></div>
            <div><dt class="text-xs font-medium uppercase text-zinc-400">Ciudad</dt><dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">{{ $datosServicio['ciudad'] ?? '—' }}</dd></div>
            <div><dt class="text-xs font-medium uppercase text-zinc-400">Celular</dt><dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">{{ $datosServicio['celular_usuario'] ?? '—' }}</dd></div>
            <div class="col-span-full"><dt class="text-xs font-medium uppercase text-zinc-400">Fecha y Hora de Atención</dt><dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">{{ $datosServicio['fecha_hora_atencion'] ?? '—' }}</dd></div>
            @if($datosServicio['comentario_usuario'])
            <div class="col-span-full"><dt class="text-xs font-medium uppercase text-zinc-400">Solicitud del Usuario</dt><dd class="mt-0.5 text-zinc-700 dark:text-zinc-300">{{ $datosServicio['comentario_usuario'] }}</dd></div>
            @endif
            @if($datosServicio['comentario_tecnico'])
            <div class="col-span-full"><dt class="text-xs font-medium uppercase text-zinc-400">Comentario Técnico</dt><dd class="mt-0.5 text-zinc-700 dark:text-zinc-300">{{ $datosServicio['comentario_tecnico'] }}</dd></div>
            @endif
        </dl>
    </div>

    {{-- Sección 2: Quién Atiende --}}
    <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500">2. Quién Atendió la Visita</h3>
        <dl class="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-3 text-sm">
            <div><dt class="text-xs font-medium uppercase text-zinc-400">Nombre</dt><dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">{{ $datosServicio['nombre_atiende'] ?? '—' }}</dd></div>
            <div><dt class="text-xs font-medium uppercase text-zinc-400">Vínculo</dt><dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">{{ $datosServicio['vinculo_usuario'] ?? '—' }}</dd></div>
            <div><dt class="text-xs font-medium uppercase text-zinc-400">Celular</dt><dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">{{ $datosServicio['celular_atiende'] ?? '—' }}</dd></div>
        </dl>
    </div>

    {{-- Sección 3+4+5+6: Validación, Hallazgos, Materiales, Trazabilidad --}}
    <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500">3–6. Validación y Hallazgos</h3>
        <dl class="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-3 text-sm">
            @php
                $yesno = fn($v) => $v ? 'Sí' : 'No';
            @endphp
            <div><dt class="text-xs font-medium uppercase text-zinc-400">Recibió visita</dt><dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">{{ $yesno($datosServicio['recibio_visita']) }}</dd></div>
            <div><dt class="text-xs font-medium uppercase text-zinc-400">Se realizó revisión</dt><dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">{{ $yesno($datosServicio['se_realizo_revision']) }}</dd></div>
            <div><dt class="text-xs font-medium uppercase text-zinc-400">Fecha ejecución</dt><dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">{{ $datosServicio['fecha_ejecucion'] ?? '—' }}</dd></div>
            <div><dt class="text-xs font-medium uppercase text-zinc-400">Tiempo ejecución</dt><dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">{{ $datosServicio['tiempo_ejecucion'] ?? '—' }}</dd></div>
            @if($datosServicio['descripcion_trabajo'])
            <div class="col-span-full"><dt class="text-xs font-medium uppercase text-zinc-400">Trabajo realizado</dt><dd class="mt-0.5 text-zinc-700 dark:text-zinc-300">{{ $datosServicio['descripcion_trabajo'] }}</dd></div>
            @endif
            <div><dt class="text-xs font-medium uppercase text-zinc-400">Fuga / falla detectada</dt><dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">{{ $yesno($datosServicio['se_detecto_fuga']) }}{{ $datosServicio['ppm'] ? ' · PPM: '.$datosServicio['ppm'] : '' }}{{ $datosServicio['vol_porcentaje'] ? ' · VOL%: '.$datosServicio['vol_porcentaje'] : '' }}</dd></div>
            <div><dt class="text-xs font-medium uppercase text-zinc-400">Problema persiste</dt><dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">{{ $yesno($datosServicio['problema_persiste']) }}</dd></div>
            <div><dt class="text-xs font-medium uppercase text-zinc-400">Evidencia reparación</dt><dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">{{ $yesno($datosServicio['evidencia_reparacion']) }}</dd></div>
            <div><dt class="text-xs font-medium uppercase text-zinc-400">Servicio correcto</dt><dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">{{ $yesno($datosServicio['servicio_correcto']) }}</dd></div>
            <div><dt class="text-xs font-medium uppercase text-zinc-400">Se usaron materiales</dt><dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">{{ $yesno($datosServicio['se_usaron_materiales']) }}{{ $datosServicio['quien_suministro_materiales'] ? ' · '.$datosServicio['quien_suministro_materiales'] : '' }}</dd></div>
            <div><dt class="text-xs font-medium uppercase text-zinc-400">Usuario asumió costo</dt><dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">{{ $yesno($datosServicio['usuario_asumio_costo']) }}{{ $datosServicio['valor_pagado'] ? ' · $'.$datosServicio['valor_pagado'].' ('.$datosServicio['medio_pago'].')' : '' }}</dd></div>
            <div><dt class="text-xs font-medium uppercase text-zinc-400">Entregó factura/IVT</dt><dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">{{ $yesno($datosServicio['entrego_factura']) }}</dd></div>
            @if($datosServicio['tecnico_dejo_documento'])
            <div class="col-span-full"><dt class="text-xs font-medium uppercase text-zinc-400">Documento dejado</dt><dd class="mt-0.5 text-zinc-700 dark:text-zinc-300">{{ $datosServicio['tecnico_dejo_documento'] }}</dd></div>
            @endif
            <div><dt class="text-xs font-medium uppercase text-zinc-400">Informaron que no estaba</dt><dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">{{ $yesno($datosServicio['informaron_no_estaba']) }}</dd></div>
        </dl>
    </div>

    {{-- Sección 7: Autorización y Firmas --}}
    <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500">7. Autorización y Firmas</h3>
        <p class="mb-4 text-sm"><span class="font-medium">Autoriza:</span> {{ $datosServicio['autoriza'] ? 'SÍ' : 'NO' }}</p>
        <div class="grid grid-cols-2 gap-6">
            <div>
                <p class="mb-1 text-xs font-medium uppercase text-zinc-400">Firma del Usuario</p>
                @if($datosServicio['firma_usuario'])
                    <img src="{{ $datosServicio['firma_usuario'] }}" alt="Firma usuario"
                         class="rounded border border-zinc-200 bg-white dark:border-zinc-600" style="max-height:120px;">
                @else
                    <p class="text-sm text-zinc-400">Sin firma</p>
                @endif
                <p class="mt-1 text-xs text-zinc-500">Cédula: {{ $datosServicio['cedula_usuario'] ?? '—' }}</p>
            </div>
            <div>
                <p class="mb-1 text-xs font-medium uppercase text-zinc-400">Firma del Auditor</p>
                @if($datosServicio['firma_auditor'])
                    <img src="{{ $datosServicio['firma_auditor'] }}" alt="Firma auditor"
                         class="rounded border border-zinc-200 bg-white dark:border-zinc-600" style="max-height:120px;">
                @else
                    <p class="text-sm text-zinc-400">Sin firma</p>
                @endif
                <p class="mt-1 text-xs text-zinc-500">Cédula: {{ $datosServicio['cedula_auditor'] ?? '—' }}</p>
            </div>
        </div>
    </div>
@endif
```

- [ ] **Step 3: Run pint**

```bash
vendor/bin/pint app/Livewire/Coordinador/ReporteRevisar.php --format agent
```

- [ ] **Step 4: Commit**

```bash
git add app/Livewire/Coordinador/ReporteRevisar.php resources/views/livewire/coordinador/reporte-revisar.blade.php
git commit -m "feat: add AuditoriaCampo display to ReporteRevisar"
```

---

### Task 8: Tests and final verification

**Files:**
- Modify: `tests/Feature/AuditoriaCampoTest.php`

- [ ] **Step 1: Add report creation test**

Add to `tests/Feature/AuditoriaCampoTest.php`:

```php
it('saves auditoria campo report with datos_servicio including signatures', function () {
    $company = \App\Models\Company::factory()->create();
    $user = \App\Models\User::factory()->create(['company_id' => $company->id, 'is_superadmin' => false]);
    $this->actingAs($user);

    $orden = \App\Models\OrdenJob::create([
        'tipo_servicio'        => \App\Enums\TipoServicio::AuditoriaCampo,
        'tipo_auditoria_campo' => 'visita_calidad_trabajos_varios',
        'prioridad'            => \App\Enums\PrioridadOrden::Media,
        'estado'               => \App\Enums\EstadoOrden::EnProgreso,
        'estado_servicio'      => true,
    ]);

    $reporte = \App\Models\reportes::create([
        'orden_job_id'   => $orden->id,
        'personals_id'   => null,
        'numero_orden'   => $orden->numero_orden,
        'datos_servicio' => [
            'nombre_atiende'  => 'María García',
            'recibio_visita'  => true,
            'autoriza'        => true,
            'firma_usuario'   => 'data:image/png;base64,abc123',
            'cedula_usuario'  => '12345678',
            'firma_auditor'   => 'data:image/png;base64,xyz789',
            'cedula_auditor'  => '87654321',
        ],
    ]);

    expect($reporte->datos_servicio['nombre_atiende'])->toBe('María García')
        ->and($reporte->datos_servicio['firma_usuario'])->toStartWith('data:image/png;base64,')
        ->and($reporte->datos_servicio['autoriza'])->toBeTrue();
});

it('datosServicioDisplay returns structured array for AuditoriaCampo', function () {
    $company = \App\Models\Company::factory()->create();
    $user = \App\Models\User::factory()->create(['company_id' => $company->id, 'is_superadmin' => false]);
    $this->actingAs($user);

    $orden = \App\Models\OrdenJob::create([
        'tipo_servicio'        => \App\Enums\TipoServicio::AuditoriaCampo,
        'tipo_auditoria_campo' => 'visita_calidad_emergencia',
        'prioridad'            => \App\Enums\PrioridadOrden::Media,
        'estado'               => \App\Enums\EstadoOrden::EnRevision,
        'estado_servicio'      => true,
    ]);

    $reporte = \App\Models\reportes::create([
        'orden_job_id'   => $orden->id,
        'personals_id'   => null,
        'numero_orden'   => $orden->numero_orden,
        'datos_servicio' => ['autoriza' => true, 'nombre_atiende' => 'Test', 'recibio_visita' => true],
    ]);

    $component = new \App\Livewire\Coordinador\ReporteRevisar;
    $component->orden = $orden->load('personal');
    $component->reporte = $reporte;

    $display = $component->datosServicioDisplay();

    expect($display['subtipo'])->toBe('Visita de Calidad — Emergencia')
        ->and($display['nombre_atiende'])->toBe('Test')
        ->and($display['autoriza'])->toBeTrue();
});
```

- [ ] **Step 2: Run all AuditoriaCampo tests**

```bash
php artisan test --compact --filter="AuditoriaCampo"
```
Expected: 4 tests PASS

- [ ] **Step 3: Run full test suite**

```bash
php artisan test --compact
```
Expected: all tests PASS

- [ ] **Step 4: Build final assets**

```bash
npm run build
```

- [ ] **Step 5: Final commit**

```bash
git add tests/Feature/AuditoriaCampoTest.php
git commit -m "test: feature tests for AuditoriaCampo service type"
```
