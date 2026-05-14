# Auditorías de Campo — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add "Auditorías de Campo" service type with two subtypes and a 9-section digital report form (including canvas-based signatures) to the existing OrdenJob/Reporte lifecycle.

**Architecture:** Follows the exact `GestionLegal` pattern — new `TipoServicio` enum case, nullable `tipo_auditoria_campo` column on `orden_jobs`, report data in `datos_servicio` JSON. Signatures use a Filament custom field (`SignaturePad extends Field`) with `$wire.entangle($getStatePath())` — integrates directly into the form schema, no separate Livewire public properties needed. `signature_pad` npm package (no CDN), exposed globally from `app.js`.

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
| `app/Filament/Forms/Components/SignaturePad.php` | Create — Filament custom field extending `Field` |
| `resources/views/filament/forms/components/signature-pad.blade.php` | Create — canvas + Alpine + `$wire.entangle` |
| `app/Livewire/Tecnico/ReporteCreate.php` | Add schema method + submit method (no extra public props) |
| `app/Livewire/Coordinador/ReporteRevisar.php` | Add `AuditoriaCampo` case to `datosServicioDisplay()` |
| `resources/views/livewire/coordinador/reporte-revisar.blade.php` | Add display section + PDF download button for AuditoriaCampo |
| `routes/operaciones.php` | Add PDF download route |
| `app/Http/Controllers/ReportePdfController.php` | Add `DownLoadInformeAuditoriaCampo()` + `buildDatosReporteAuditoriaCampo()` |
| `resources/views/pdf/reporte-auditoria-campo.blade.php` | Create — PDF template with hand-drawn signatures |
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

### Task 5: ReporteCreate — schema and submit for AuditoriaCampo

**Files:**
- Modify: `app/Livewire/Tecnico/ReporteCreate.php`

No extra public properties are needed — signature state is managed by Filament form state via the `SignaturePad` custom field (Task 6).

- [ ] **Step 1: Add the AuditoriaCampo case to `form()` method**

In `form()`, the `match` block currently ends with `Gestion_Riesgo`. Add the new case:

```php
TipoServicio::AuditoriaCampo => $this->auditoriaCampoSchema(),
```

- [ ] **Step 2: Add the AuditoriaCampo case to `submit()` method**

In `submit()`, the `match` block currently ends with `Gestion_Riesgo`. Add:

```php
TipoServicio::AuditoriaCampo => $this->submitAuditoriaCampo($data),
```

- [ ] **Step 3: Add `auditoriaCampoSchema()` method**

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
                \App\Filament\Forms\Components\SignaturePad::make('firma_usuario')
                    ->label('Firma del Usuario')
                    ->columnSpanFull(),
                \App\Filament\Forms\Components\SignaturePad::make('firma_auditor')
                    ->label('Firma del Auditor')
                    ->columnSpanFull(),
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

- [ ] **Step 4: Add `submitAuditoriaCampo()` method**

Add this private method after `submitServicioGenerico()`. `firma_usuario` and `firma_auditor` come from `$data` directly — they are Filament form fields managed by the custom `SignaturePad` field (Task 6).

```php
/** @param array<string, mixed> $data */
private function submitAuditoriaCampo(array $data): void
{
    $datosServicio = $data;
    unset($datosServicio['observaciones'], $datosServicio['imagenes']);

    reportes::create([
        'orden_job_id'   => $this->orden->id,
        'personals_id'   => auth()->user()->personals_id,
        'numero_orden'   => $this->orden->numero_orden,
        'observaciones'  => $data['observaciones'] ?? null,
        'imagenes'       => $data['imagenes'] ?? null,
        'datos_servicio' => $datosServicio,
    ]);

    $this->orden->update(['estado' => EstadoOrden::EnRevision]);

    $this->notificarCoordinador();

    $this->dispatch('borrador-limpiar');

    $this->redirect(route('tecnico.mis-ordenes'), navigate: true);
}
```

- [ ] **Step 5: Run pint**

```bash
vendor/bin/pint app/Livewire/Tecnico/ReporteCreate.php --format agent
```

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/Tecnico/ReporteCreate.php
git commit -m "feat: add AuditoriaCampo form schema and submit logic to ReporteCreate"
```

---

### Task 6: Filament custom field — SignaturePad

**Files:**
- Create: `app/Filament/Forms/Components/SignaturePad.php`
- Create: `resources/views/filament/forms/components/signature-pad.blade.php`

The `SignaturePad` Filament field extends `Field` and renders a canvas. State is bound via `$wire.entangle($getStatePath()).defer` — the field value (base64 PNG or null) goes directly into Filament form state, no Livewire public properties needed. The `endStroke` event from SignaturePad.js triggers the state update.

- [ ] **Step 1: Generate the Filament form field**

```bash
php artisan make:filament-form-field SignaturePad --no-interaction
```

This creates `app/Filament/Forms/Components/SignaturePad.php` and `resources/views/filament/forms/components/signature-pad.blade.php`.

- [ ] **Step 2: Write the field class**

Replace `app/Filament/Forms/Components/SignaturePad.php` contents with:

```php
<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;

class SignaturePad extends Field
{
    protected string $view = 'filament.forms.components.signature-pad';
}
```

- [ ] **Step 3: Write the blade view**

Replace `resources/views/filament/forms/components/signature-pad.blade.php` contents with:

```blade
<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        x-data="{
            state: $wire.entangle('{{ $getStatePath() }}').defer,
            pad: null,
            init() {
                this.pad = new SignaturePad(this.$refs.canvas, { backgroundColor: 'rgb(255,255,255)' });
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                const canvas = this.$refs.canvas;
                canvas.width = canvas.offsetWidth * ratio;
                canvas.height = canvas.offsetHeight * ratio;
                canvas.getContext('2d').scale(ratio, ratio);
                this.pad.addEventListener('endStroke', () => {
                    this.state = this.pad.isEmpty() ? null : this.pad.toDataURL('image/png');
                });
            },
            clear() { this.pad.clear(); this.state = null; }
        }"
    >
        <div class="relative rounded-lg border-2 border-zinc-300 bg-white dark:border-zinc-600" style="height: 200px;">
            <canvas x-ref="canvas" class="h-full w-full touch-none rounded-lg"></canvas>
            <div class="absolute bottom-2 right-2">
                <button
                    type="button"
                    @click="clear()"
                    class="rounded bg-zinc-100 px-2 py-1 text-xs text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-700 dark:text-zinc-300"
                >
                    Limpiar
                </button>
            </div>
        </div>
    </div>
</x-dynamic-component>
```

- [ ] **Step 4: Run pint**

```bash
vendor/bin/pint app/Filament/Forms/Components/SignaturePad.php --format agent
```

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Forms/Components/SignaturePad.php resources/views/filament/forms/components/signature-pad.blade.php
git commit -m "feat: add SignaturePad Filament custom field with canvas and entangle binding"
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

---

### Task 9: PDF report for AuditoriaCampo

**Files:**
- Modify: `routes/operaciones.php`
- Modify: `app/Http/Controllers/ReportePdfController.php`
- Create: `resources/views/pdf/reporte-auditoria-campo.blade.php`
- Modify: `resources/views/livewire/coordinador/reporte-revisar.blade.php` (add download button)

Follows the exact `GestionLegal` PDF pattern. The notable difference: `datos_servicio` contains `firma_usuario` and `firma_auditor` as `data:image/png;base64,...` strings (canvas drawings). DomPDF renders these directly as `<img src="...">`.

- [ ] **Step 1: Add route**

In `routes/operaciones.php`, after the existing `reporte-gestion-legal` route line, add:

```php
Route::get('ordenes-trabajo/{orden}/reporte-auditoria-campo', [ReportePdfController::class, 'DownLoadInformeAuditoriaCampo'])->name('ordenes-trabajo.reporte-auditoria-campo');
```

The route group context (look at the surrounding code — it's inside `Route::middleware(['auth'])->prefix('operaciones')->name('operaciones.')->group(...)`) means the full named route becomes `operaciones.ordenes-trabajo.reporte-auditoria-campo`.

- [ ] **Step 2: Add controller method and helper**

In `app/Http/Controllers/ReportePdfController.php`, add before the last closing brace `}`:

```php
public function DownLoadInformeAuditoriaCampo(OrdenJob $orden): Response
{
    abort_unless($orden->tipo_servicio === TipoServicio::AuditoriaCampo, 403);
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

    $imagenes = $this->resolveImagenes($reporte->imagenes ?? []);

    /** @var User $firmante */
    $firmante = auth()->user();

    $firma = [
        'nombre' => $firmante->fullName(),
        'cargo'  => $firmante->personal?->cargo,
        'email'  => $firmante->email,
        'ip'     => request()->ip(),
        'agente' => request()->userAgent() ?? 'No disponible',
        'fecha'  => now()->format('d/m/Y H:i:s'),
    ];

    $subtipos = [
        'visita_calidad_emergencia'      => 'Visita de Calidad — Emergencia',
        'visita_calidad_trabajos_varios' => 'Visita de Calidad — Trabajos Varios',
    ];

    $datosOrden               = $this->buildDatosOrden($orden);
    $datosOrden['subtipo']    = $subtipos[$orden->tipo_auditoria_campo ?? ''] ?? ($orden->tipo_auditoria_campo ?? '—');

    $datosReporte = $this->buildDatosReporteAuditoriaCampo($reporte);

    $pdf = Pdf::loadView('pdf.reporte-auditoria-campo', [
        'orden'            => $orden,
        'reporte'          => $reporte,
        'logoProderi'      => $logoProderi,
        'imagenes'         => $imagenes,
        'fechaGeneracion'  => now()->format('d-m-Y'),
        'firma'            => $firma,
        'datosOrden'       => $datosOrden,
        'datosReporte'     => $datosReporte,
    ]);

    $pdf->setPaper('A4', 'portrait');

    return $pdf->download("reporte-auditoria-campo-{$orden->numero_orden}.pdf");
}

/**
 * @return array<string, mixed>
 */
private function buildDatosReporteAuditoriaCampo(reportes $reporte): array
{
    $datos = $reporte->datos_servicio ?? [];

    return [
        'fecha_hora_atencion'         => $datos['fecha_hora_atencion'] ?? null,
        'ciudad'                      => $datos['ciudad'] ?? null,
        'celular_usuario'             => $datos['celular_usuario'] ?? null,
        'comentario_usuario'          => $datos['comentario_usuario'] ?? null,
        'comentario_tecnico'          => $datos['comentario_tecnico'] ?? null,
        'nombre_atiende'              => $datos['nombre_atiende'] ?? null,
        'vinculo_usuario'             => $datos['vinculo_usuario'] ?? null,
        'celular_atiende'             => $datos['celular_atiende'] ?? null,
        'recibio_visita'              => (bool) ($datos['recibio_visita'] ?? false),
        'se_realizo_revision'         => (bool) ($datos['se_realizo_revision'] ?? false),
        'fecha_ejecucion'             => $datos['fecha_ejecucion'] ?? null,
        'tiempo_ejecucion'            => $datos['tiempo_ejecucion'] ?? null,
        'descripcion_trabajo'         => $datos['descripcion_trabajo'] ?? null,
        'se_detecto_fuga'             => (bool) ($datos['se_detecto_fuga'] ?? false),
        'ppm'                         => $datos['ppm'] ?? null,
        'vol_porcentaje'              => $datos['vol_porcentaje'] ?? null,
        'problema_persiste'           => (bool) ($datos['problema_persiste'] ?? false),
        'evidencia_reparacion'        => (bool) ($datos['evidencia_reparacion'] ?? false),
        'servicio_correcto'           => (bool) ($datos['servicio_correcto'] ?? false),
        'se_usaron_materiales'        => (bool) ($datos['se_usaron_materiales'] ?? false),
        'quien_suministro_materiales' => $datos['quien_suministro_materiales'] ?? null,
        'usuario_asumio_costo'        => (bool) ($datos['usuario_asumio_costo'] ?? false),
        'valor_pagado'                => $datos['valor_pagado'] ?? null,
        'medio_pago'                  => $datos['medio_pago'] ?? null,
        'entrego_factura'             => (bool) ($datos['entrego_factura'] ?? false),
        'tecnico_dejo_documento'      => $datos['tecnico_dejo_documento'] ?? null,
        'informaron_no_estaba'        => (bool) ($datos['informaron_no_estaba'] ?? false),
        'autoriza'                    => (bool) ($datos['autoriza'] ?? false),
        'firma_usuario'               => $datos['firma_usuario'] ?? null,
        'cedula_usuario'              => $datos['cedula_usuario'] ?? null,
        'firma_auditor'               => $datos['firma_auditor'] ?? null,
        'cedula_auditor'              => $datos['cedula_auditor'] ?? null,
        'observaciones'               => $reporte->observaciones ?: 'Sin observaciones registradas.',
    ];
}
```

- [ ] **Step 3: Create PDF blade template**

Create `resources/views/pdf/reporte-auditoria-campo.blade.php` with the full content:

```blade
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Informe de Auditoría de Campo - {{ $orden->numero_orden }}</title>
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
        .info-label { display: table-cell; font-weight: bold; padding: 3px 12px 3px 0; width: 180px; vertical-align: top; font-size: 8pt; color: #003E72; }
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
        .firma-canvas-tabla { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .firma-canvas-td { width: 50%; padding: 6px; vertical-align: top; text-align: center; }
        .firma-canvas-img { max-width: 100%; max-height: 100px; border: 1px solid #003E72; border-radius: 4px; background: white; display: block; margin: 0 auto; }
        .firma-canvas-label { font-size: 8pt; font-weight: bold; color: #003E72; margin-bottom: 4px; }
        .firma-canvas-cedula { font-size: 7.5pt; color: #64748b; margin-top: 3px; }
    </style>
</head>
<body>

    <div class="header">
        <table class="header-inner">
            <tr>
                <td class="header-td-center">
                    <h1>INFORME DE AUDITORÍA DE CAMPO</h1>
                    <h2>{{ $datosOrden['subtipo'] ?? 'Control y Seguimiento de Visita en Campo' }}</h2>
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
        <div class="footer-line">Informe de Auditoría de Campo — Uso Interno y Confidencial</div>
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
                        <div class="info-label">Subtipo de Auditoría:</div>
                        <div class="info-value"><span class="badge badge-blue">{{ $datosOrden['subtipo'] }}</span></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Estado:</div>
                        <div class="info-value">{{ $datosOrden['estado'] }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Auditor:</div>
                        <div class="info-value">{{ $datosOrden['tecnico'] }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Contrato:</div>
                        <div class="info-value">{{ $datosOrden['contrato'] }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Nombre del Usuario:</div>
                        <div class="info-value">{{ $datosOrden['cliente'] }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Dirección:</div>
                        <div class="info-value">{{ $datosOrden['direccion'] }}</div>
                    </div>
                    @if ($datosReporte['ciudad'])
                        <div class="info-row">
                            <div class="info-label">Ciudad:</div>
                            <div class="info-value">{{ $datosReporte['ciudad'] }}</div>
                        </div>
                    @endif
                    @if ($datosReporte['celular_usuario'])
                        <div class="info-row">
                            <div class="info-label">Celular del Usuario:</div>
                            <div class="info-value">{{ $datosReporte['celular_usuario'] }}</div>
                        </div>
                    @endif
                    @if ($datosReporte['fecha_hora_atencion'])
                        <div class="info-row">
                            <div class="info-label">Fecha y Hora Atención:</div>
                            <div class="info-value">{{ $datosReporte['fecha_hora_atencion'] }}</div>
                        </div>
                    @endif
                    <div class="info-row">
                        <div class="info-label">Fecha de Asignación:</div>
                        <div class="info-value">{{ $datosOrden['fecha_asignacion'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Quién Atendió ── --}}
        @if ($datosReporte['nombre_atiende'])
            <div class="section">
                <div class="section-title">QUIEN ATENDIÓ LA VISITA</div>
                <div class="info-box">
                    <div class="info-grid">
                        <div class="info-row">
                            <div class="info-label">Nombre:</div>
                            <div class="info-value">{{ $datosReporte['nombre_atiende'] }}</div>
                        </div>
                        @if ($datosReporte['vinculo_usuario'])
                            <div class="info-row">
                                <div class="info-label">Vínculo con el usuario:</div>
                                <div class="info-value">{{ ucfirst($datosReporte['vinculo_usuario']) }}</div>
                            </div>
                        @endif
                        @if ($datosReporte['celular_atiende'])
                            <div class="info-row">
                                <div class="info-label">Celular:</div>
                                <div class="info-value">{{ $datosReporte['celular_atiende'] }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- ── Validación de la Atención ── --}}
        <div class="section">
            <div class="section-title">VALIDACIÓN DE LA ATENCIÓN Y EJECUCIÓN</div>
            <div class="info-box">
                <div class="info-grid">
                    <div class="info-row">
                        <div class="info-label">¿Recibió la visita?</div>
                        <div class="info-value">
                            <span class="badge {{ $datosReporte['recibio_visita'] ? 'badge-green' : 'badge-red' }}">
                                {{ $datosReporte['recibio_visita'] ? 'SÍ' : 'NO' }}
                            </span>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">¿Se realizó revisión?</div>
                        <div class="info-value">
                            <span class="badge {{ $datosReporte['se_realizo_revision'] ? 'badge-green' : 'badge-red' }}">
                                {{ $datosReporte['se_realizo_revision'] ? 'SÍ' : 'NO' }}
                            </span>
                        </div>
                    </div>
                    @if ($datosReporte['fecha_ejecucion'])
                        <div class="info-row">
                            <div class="info-label">Fecha de ejecución:</div>
                            <div class="info-value">{{ \Illuminate\Support\Carbon::parse($datosReporte['fecha_ejecucion'])->format('d/m/Y') }}</div>
                        </div>
                    @endif
                    @if ($datosReporte['tiempo_ejecucion'])
                        <div class="info-row">
                            <div class="info-label">Tiempo de ejecución:</div>
                            <div class="info-value">{{ $datosReporte['tiempo_ejecucion'] }}</div>
                        </div>
                    @endif
                </div>
            </div>
            @if ($datosReporte['descripcion_trabajo'])
                <div class="content-box" style="margin-top:4px;">{{ $datosReporte['descripcion_trabajo'] }}</div>
            @endif
        </div>

        {{-- ── Hallazgos Técnicos ── --}}
        <div class="section">
            <div class="section-title">HALLAZGOS TÉCNICOS</div>
            <div class="info-box">
                <div class="info-grid">
                    <div class="info-row">
                        <div class="info-label">¿Se detectó fuga/falla?</div>
                        <div class="info-value">
                            <span class="badge {{ $datosReporte['se_detecto_fuga'] ? 'badge-red' : 'badge-green' }}">
                                {{ $datosReporte['se_detecto_fuga'] ? 'SÍ' : 'NO' }}
                            </span>
                            @if ($datosReporte['se_detecto_fuga'])
                                @if ($datosReporte['ppm']) &nbsp; PPM: {{ $datosReporte['ppm'] }} @endif
                                @if ($datosReporte['vol_porcentaje']) &nbsp; VOL%: {{ $datosReporte['vol_porcentaje'] }} @endif
                            @endif
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">¿Problema persiste?</div>
                        <div class="info-value">
                            <span class="badge {{ $datosReporte['problema_persiste'] ? 'badge-red' : 'badge-green' }}">
                                {{ $datosReporte['problema_persiste'] ? 'SÍ' : 'NO' }}
                            </span>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">¿Evidencia de reparación?</div>
                        <div class="info-value">
                            <span class="badge {{ $datosReporte['evidencia_reparacion'] ? 'badge-green' : 'badge-red' }}">
                                {{ $datosReporte['evidencia_reparacion'] ? 'SÍ' : 'NO' }}
                            </span>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">¿Servicio correcto?</div>
                        <div class="info-value">
                            <span class="badge {{ $datosReporte['servicio_correcto'] ? 'badge-green' : 'badge-red' }}">
                                {{ $datosReporte['servicio_correcto'] ? 'SÍ' : 'NO' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Materiales y Cobros ── --}}
        <div class="section">
            <div class="section-title">MATERIALES Y COBROS</div>
            <div class="info-box">
                <div class="info-grid">
                    <div class="info-row">
                        <div class="info-label">¿Se usaron materiales?</div>
                        <div class="info-value">{{ $datosReporte['se_usaron_materiales'] ? 'SÍ' : 'NO' }}{{ $datosReporte['quien_suministro_materiales'] ? ' — Suministrado por: '.ucfirst($datosReporte['quien_suministro_materiales']) : '' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">¿Usuario asumió costo?</div>
                        <div class="info-value">{{ $datosReporte['usuario_asumio_costo'] ? 'SÍ' : 'NO' }}{{ $datosReporte['valor_pagado'] ? ' — $'.$datosReporte['valor_pagado'].' ('.($datosReporte['medio_pago'] ?? '—').')' : '' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">¿Entregó factura/IVT?</div>
                        <div class="info-value">{{ $datosReporte['entrego_factura'] ? 'SÍ' : 'NO' }}</div>
                    </div>
                    @if ($datosReporte['tecnico_dejo_documento'])
                        <div class="info-row">
                            <div class="info-label">Documento dejado:</div>
                            <div class="info-value">{{ $datosReporte['tecnico_dejo_documento'] }}</div>
                        </div>
                    @endif
                    <div class="info-row">
                        <div class="info-label">¿Informaron que no estaba?</div>
                        <div class="info-value">{{ $datosReporte['informaron_no_estaba'] ? 'SÍ' : 'NO' }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Solicitud y comentarios ── --}}
        @if ($datosReporte['comentario_usuario'])
            <div class="section">
                <div class="section-title">SOLICITUD DEL USUARIO</div>
                <div class="content-box">{{ $datosReporte['comentario_usuario'] }}</div>
            </div>
        @endif
        @if ($datosReporte['comentario_tecnico'])
            <div class="section">
                <div class="section-title">COMENTARIO DEL TÉCNICO</div>
                <div class="content-box">{{ $datosReporte['comentario_tecnico'] }}</div>
            </div>
        @endif

        {{-- ── Observaciones ── --}}
        <div class="section">
            <div class="section-title">OBSERVACIONES</div>
            <div class="content-box">{{ $datosReporte['observaciones'] }}</div>
        </div>

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

        {{-- ── Autorización y Firmas manuscritas ── --}}
        <div class="firma-section">
            <div class="section-title">AUTORIZACIÓN Y FIRMAS</div>
            <div class="info-box" style="margin-bottom:10px;">
                <div class="info-grid">
                    <div class="info-row">
                        <div class="info-label">Autoriza recolección:</div>
                        <div class="info-value">
                            <span class="badge {{ $datosReporte['autoriza'] ? 'badge-green' : 'badge-red' }}">
                                {{ $datosReporte['autoriza'] ? 'SÍ AUTORIZA' : 'NO AUTORIZA' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <table class="firma-canvas-tabla">
                <tr>
                    <td class="firma-canvas-td">
                        <div class="firma-canvas-label">Firma del Usuario</div>
                        @if ($datosReporte['firma_usuario'])
                            <img src="{{ $datosReporte['firma_usuario'] }}" class="firma-canvas-img" alt="Firma Usuario">
                        @else
                            <div style="font-size:7.5pt; color:#9ca3af; font-style:italic;">Sin firma registrada</div>
                        @endif
                        <div class="firma-canvas-cedula">C.C.: {{ $datosReporte['cedula_usuario'] ?? '—' }}</div>
                    </td>
                    <td class="firma-canvas-td">
                        <div class="firma-canvas-label">Firma del Auditor</div>
                        @if ($datosReporte['firma_auditor'])
                            <img src="{{ $datosReporte['firma_auditor'] }}" class="firma-canvas-img" alt="Firma Auditor">
                        @else
                            <div style="font-size:7.5pt; color:#9ca3af; font-style:italic;">Sin firma registrada</div>
                        @endif
                        <div class="firma-canvas-cedula">C.C.: {{ $datosReporte['cedula_auditor'] ?? '—' }}</div>
                    </td>
                </tr>
            </table>
        </div>

        {{-- ── Firma Digital del Coordinador ── --}}
        <div class="firma-section">
            <div class="section-title">FIRMA DIGITAL DEL COORDINADOR</div>
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

- [ ] **Step 4: Add PDF download button to reporte-revisar blade**

In `resources/views/livewire/coordinador/reporte-revisar.blade.php`, find the existing GestionLegal download button block:

```blade
@if ($orden->tipo_servicio === \App\Enums\TipoServicio::GestionLegal && auth()->user()->can('informes'))
    <flux:button
        href="{{ route('operaciones.ordenes-trabajo.reporte-gestion-legal', [$orden, 'reporte' => $reporte->id]) }}"
        icon="arrow-down-tray" variant="filled" target="_blank">
        Descargar PDF
    </flux:button>
@endif
```

Add directly after it:

```blade
@if ($orden->tipo_servicio === \App\Enums\TipoServicio::AuditoriaCampo && auth()->user()->can('informes'))
    <flux:button
        href="{{ route('operaciones.ordenes-trabajo.reporte-auditoria-campo', [$orden, 'reporte' => $reporte->id]) }}"
        icon="arrow-down-tray" variant="filled" target="_blank">
        Descargar PDF
    </flux:button>
@endif
```

- [ ] **Step 5: Run pint**

```bash
vendor/bin/pint app/Http/Controllers/ReportePdfController.php routes/operaciones.php --format agent
```

- [ ] **Step 6: Commit**

```bash
git add routes/operaciones.php app/Http/Controllers/ReportePdfController.php resources/views/pdf/reporte-auditoria-campo.blade.php resources/views/livewire/coordinador/reporte-revisar.blade.php
git commit -m "$(cat <<'EOF'
feat: add PDF report for AuditoriaCampo with canvas signature rendering

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```
