# Revisión de Medidores — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementar el módulo de importación masiva de órdenes de Revisión de Medidores, con asignación individual/masiva a investigadores, descarga masiva de PDFs en ZIP vía queue, notificación en panel + correo cuando el ZIP está listo, informe PDF y auditoría integrada.

**Architecture:** Nuevo `TipoServicio::RevisionMedidores` reutiliza toda la infraestructura existente de `OrdenJob`. Las órdenes se crean exclusivamente vía import CSV/XLSX desde un módulo dedicado (`operaciones/revision-medidores/`). La asignación masiva envía un único correo resumen para evitar spam. El reporte de campo y la auditoría reutilizan los flujos de Fraudes sin crear nuevos componentes para esas fases.

**Tech Stack:** Laravel 13, Livewire 4, Filament v5.6, Pest 4, maatwebsite/excel (nuevo), barryvdh/laravel-dompdf (existente), Spatie Permission (existente).

---

> **IMPORTANTE para retomar trabajo tras límite de contexto:**
> 1. Leer `docs/superpowers/specs/2026-05-20-revision-medidores-design.md` completo.
> 2. Leer este plan desde el task donde se quedó.
> 3. Verificar estado con `git log --oneline -15` para ver qué commits ya existen.
> 4. Correr `php artisan test --filter=RevisionMedidores` para ver qué tests pasan.

---

## Mapa de Archivos

### Archivos nuevos
| Archivo | Responsabilidad |
|---|---|
| `app/Exports/RevisionMedidoresPlantillaExport.php` | Genera XLSX de plantilla descargable |
| `app/Imports/RevisionMedidoresImport.php` | Parsea y valida archivo CSV/XLSX de import |
| `app/Mail/OrdenesAsignadasResumen.php` | Correo único para asignación masiva |
| `app/Http/Controllers/RevisionMedidoresPlantillaController.php` | Descarga de plantilla vía ruta GET |
| `app/Livewire/Coordinador/RevisionMedidores/ImportarRevisionMedidores.php` | Componente Livewire de upload e import |
| `app/Livewire/Coordinador/RevisionMedidores/RevisionMedidoresIndex.php` | Listado, asignación individual y masiva |
| `resources/views/livewire/coordinador/revision-medidores/importar-revision-medidores.blade.php` | Vista del componente de import |
| `resources/views/livewire/coordinador/revision-medidores/revision-medidores-index.blade.php` | Vista del listado |
| `resources/views/emails/ordenes-asignadas-resumen.blade.php` | Vista del correo masivo |
| `resources/views/pdf/reporte-revision-medidores.blade.php` | Plantilla PDF del informe |
| `routes/revision-medidores.php` | Rutas del módulo |
| `tests/Feature/RevisionMedidores/EnumRevisionMedidoresTest.php` | Tests del enum |
| `tests/Feature/RevisionMedidores/ComponentesExistentesTest.php` | Tests de integración en componentes existentes |
| `tests/Feature/RevisionMedidores/PlantillaExportTest.php` | Test de descarga de plantilla |
| `tests/Feature/RevisionMedidores/ImportTest.php` | Tests de import |
| `tests/Feature/RevisionMedidores/OrdenesAsignadasResumenTest.php` | Test del mailable |
| `tests/Feature/RevisionMedidores/ImportarComponenteTest.php` | Tests del componente de import |
| `tests/Feature/RevisionMedidores/IndexComponenteTest.php` | Tests del index y asignación |
| `tests/Feature/RevisionMedidores/RutasSidebarTest.php` | Tests de rutas y sidebar |
| `tests/Feature/RevisionMedidores/AuditoriaTest.php` | Tests de auditoría |
| `tests/Feature/RevisionMedidores/PdfInformeTest.php` | Test del PDF |
| `app/Jobs/GenerarZipReportesRevisionMedidoresJob.php` | Genera ZIP de PDFs en background queue |
| `app/Notifications/ZipReportesListoNotification.php` | Notificación database + mail cuando ZIP está listo |
| `tests/Feature/RevisionMedidores/ZipReportesJobTest.php` | Tests del job y notification |
| `tests/Feature/RevisionMedidores/DescargaMasivaTest.php` | Tests de la bulk action y polling |
| `tests/Feature/RevisionMedidores/ZipDescargaRutaTest.php` | Tests de la ruta de descarga firmada |

### Archivos existentes modificados
| Archivo | Cambio |
|---|---|
| `app/Enums/TipoServicio.php` | Agregar `case RevisionMedidores` |
| `app/Livewire/Tecnico/ReporteCreate.php` | Agregar case en 2 match sin default |
| `app/Livewire/Tecnico/ReporteEdit.php` | Agregar case en 1 match sin default |
| `app/Livewire/Coordinador/OrdenCreate.php` | Excluir RevisionMedidores del dropdown |
| `app/Livewire/Coordinador/OrdenesTrabajoIndex.php` | Agregar acción PDF + sección edit para RevisionMedidores |
| `app/Http/Controllers/ReportePdfController.php` | Nuevo método DownLoadInformeRevisionMedidores |
| `resources/data/objetivos-pdf.json` | Agregar clave `revision_medidores` |
| `resources/views/layouts/app/sidebar.blade.php` | Agregar item Revisión de Medidores |
| `routes/web.php` | Agregar `require __DIR__.'/revision-medidores.php'` |

---

## Task 1: TipoServicio Enum — Agregar RevisionMedidores

**Files:**
- Modify: `app/Enums/TipoServicio.php`
- Create: `tests/Feature/RevisionMedidores/EnumRevisionMedidoresTest.php`

- [ ] **Step 1.1: Crear el test**

```bash
php artisan make:test --pest RevisionMedidores/EnumRevisionMedidoresTest
```

Reemplazar el contenido de `tests/Feature/RevisionMedidores/EnumRevisionMedidoresTest.php`:

```php
<?php

use App\Enums\TipoServicio;

it('tiene el case RevisionMedidores con valor correcto', function () {
    expect(TipoServicio::RevisionMedidores->value)->toBe('revision_medidores');
});

it('retorna el label correcto', function () {
    expect(TipoServicio::RevisionMedidores->label())->toBe('Revisión de Medidores');
});

it('retorna color distinto a los demás tipos', function () {
    $color = TipoServicio::RevisionMedidores->color();
    $otros = collect(TipoServicio::cases())
        ->filter(fn ($c) => $c !== TipoServicio::RevisionMedidores)
        ->map(fn ($c) => $c->color())
        ->unique();
    expect($otros->contains($color))->toBeFalse();
});

it('tieneContrato retorna true', function () {
    expect(TipoServicio::RevisionMedidores->tieneContrato())->toBeTrue();
});

it('tieneMedidor retorna true', function () {
    expect(TipoServicio::RevisionMedidores->tieneMedidor())->toBeTrue();
});

it('tieneTecnicoAsignado retorna true', function () {
    expect(TipoServicio::RevisionMedidores->tieneTecnicoAsignado())->toBeTrue();
});
```

- [ ] **Step 1.2: Correr el test para verificar que falla**

```bash
php artisan test --filter=EnumRevisionMedidoresTest
```

Esperado: FAIL — `RevisionMedidores` no existe todavía.

- [ ] **Step 1.3: Agregar el case al enum**

Abrir `app/Enums/TipoServicio.php`. Agregar el case y actualizar los tres métodos:

```php
// Después del último case existente (AuditoriaCampo)
case RevisionMedidores = 'revision_medidores';
```

En `label()`, agregar:
```php
self::RevisionMedidores => 'Revisión de Medidores',
```

En `color()`, agregar:
```php
self::RevisionMedidores => 'cyan',
```

En `tieneTecnicoAsignado()`, el método actual retorna `$this !== self::Vigilancia` — `RevisionMedidores` retorna `true` automáticamente. Sin cambio.

En `tieneContrato()`, agregar `RevisionMedidores` al array:
```php
public function tieneContrato(): bool
{
    return in_array($this, [self::Fraudes, self::Investigacion, self::Vigilancia, self::RevisionMedidores]);
}
```

En `tieneMedidor()`, agregar:
```php
public function tieneMedidor(): bool
{
    return in_array($this, [self::Fraudes, self::RevisionMedidores]);
}
```

- [ ] **Step 1.4: Correr el test para verificar que pasa**

```bash
php artisan test --filter=EnumRevisionMedidoresTest
```

Esperado: PASS 6 tests.

- [ ] **Step 1.5: Commit**

```bash
git add app/Enums/TipoServicio.php tests/Feature/RevisionMedidores/EnumRevisionMedidoresTest.php
git commit -m "feat: agregar TipoServicio::RevisionMedidores al enum"
```

---

## Task 2: Parchear Componentes Existentes

**Files:**
- Modify: `app/Livewire/Tecnico/ReporteCreate.php`
- Modify: `app/Livewire/Tecnico/ReporteEdit.php`
- Modify: `app/Livewire/Coordinador/OrdenCreate.php`
- Modify: `app/Livewire/Coordinador/OrdenesTrabajoIndex.php`
- Modify: `resources/data/objetivos-pdf.json`
- Create: `tests/Feature/RevisionMedidores/ComponentesExistentesTest.php`

- [ ] **Step 2.1: Crear el test**

```bash
php artisan make:test --pest RevisionMedidores/ComponentesExistentesTest
```

```php
<?php

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Models\Company;
use App\Models\OrdenJob;
use App\Models\personals;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionsSeeder::class);
    $this->company = Company::factory()->create();
});

it('técnico puede ver el formulario de reporte para una orden RevisionMedidores', function () {
    $personal = personals::factory()->create(['company_id' => $this->company->id]);
    $user = User::factory()->create(['personals_id' => $personal->id]);
    $user->assignRole('Lector');

    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::RevisionMedidores->value,
        'estado' => EstadoOrden::Asignada->value,
        'personals_id' => $personal->id,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($user);

    Livewire::test(\App\Livewire\Tecnico\ReporteCreate::class, ['orden' => $orden])
        ->assertOk();
});

it('técnico puede ver el formulario de edición de reporte para una orden RevisionMedidores', function () {
    $personal = personals::factory()->create(['company_id' => $this->company->id]);
    $user = User::factory()->create(['personals_id' => $personal->id]);
    $user->assignRole('Lector');

    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::RevisionMedidores->value,
        'estado' => EstadoOrden::EnProgreso->value,
        'personals_id' => $personal->id,
        'company_id' => $this->company->id,
    ]);

    $reporte = \App\Models\reportes::factory()->create(['orden_job_id' => $orden->id]);

    $this->actingAs($user);

    Livewire::test(\App\Livewire\Tecnico\ReporteEdit::class, ['orden' => $orden])
        ->assertOk();
});

it('RevisionMedidores no aparece en el dropdown de OrdenCreate', function () {
    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $this->actingAs($user);

    Livewire::test(\App\Livewire\Coordinador\OrdenCreate::class)
        ->assertDontSee('Revisión de Medidores');
});
```

- [ ] **Step 2.2: Correr tests para verificar que fallan**

```bash
php artisan test --filter=ComponentesExistentesTest
```

Esperado: FAILs (UnhandledMatchError o assertion failures).

- [ ] **Step 2.3: Parchear ReporteCreate.php**

En `app/Livewire/Tecnico/ReporteCreate.php`, buscar el match del schema (~línea 92):

```php
// ANTES:
$schema = match ($this->orden->tipo_servicio) {
    TipoServicio::Fraudes => $this->fraudesSchema(),
    TipoServicio::Investigacion => $this->investigacionSchema(),
    // ... otros casos
    TipoServicio::AuditoriaCampo => $this->auditoriaCampoSchema(),
};

// DESPUÉS — agregar RevisionMedidores antes del cierre:
$schema = match ($this->orden->tipo_servicio) {
    TipoServicio::Fraudes,
    TipoServicio::RevisionMedidores => $this->fraudesSchema(),
    TipoServicio::Investigacion => $this->investigacionSchema(),
    // ... otros casos sin cambio
    TipoServicio::AuditoriaCampo => $this->auditoriaCampoSchema(),
};
```

Buscar el match del submit (~línea 951):

```php
// ANTES:
match ($this->orden->tipo_servicio) {
    TipoServicio::Fraudes => $this->submitFraudes($data),
    // ...
};

// DESPUÉS:
match ($this->orden->tipo_servicio) {
    TipoServicio::Fraudes,
    TipoServicio::RevisionMedidores => $this->submitFraudes($data),
    // ... resto sin cambio
};
```

- [ ] **Step 2.4: Parchear ReporteEdit.php**

En `app/Livewire/Tecnico/ReporteEdit.php`, buscar el match del schema (~línea 93):

```php
// ANTES:
$schema = match ($this->orden->tipo_servicio) {
    TipoServicio::Fraudes => $this->fraudesSchema(),
    // ...
};

// DESPUÉS:
$schema = match ($this->orden->tipo_servicio) {
    TipoServicio::Fraudes,
    TipoServicio::RevisionMedidores => $this->fraudesSchema(),
    // ... resto sin cambio
};
```

El match del submit en ReporteEdit ya tiene `default` — no modificar.

- [ ] **Step 2.5: Parchear OrdenCreate.php**

En `app/Livewire/Coordinador/OrdenCreate.php`, buscar el select de `tipo_servicio` (~línea 66):

```php
// ANTES:
->options(
    collect(TipoServicio::cases())
        ->mapWithKeys(fn (TipoServicio $case) => [$case->value => $case->label()])
)

// DESPUÉS:
->options(
    collect(TipoServicio::cases())
        ->filter(fn (TipoServicio $case) => $case !== TipoServicio::RevisionMedidores)
        ->mapWithKeys(fn (TipoServicio $case) => [$case->value => $case->label()])
)
```

- [ ] **Step 2.6: Parchear OrdenesTrabajoIndex.php — sección de edición**

En `app/Livewire/Coordinador/OrdenesTrabajoIndex.php`, en `ordenFormSchema()` buscar la sección visible para Fraudes (~línea 350):

```php
// ANTES:
->visible($tipoServicio === TipoServicio::Fraudes)

// DESPUÉS — RevisionMedidores comparte los mismos campos:
->visible(
    $tipoServicio === TipoServicio::Fraudes ||
    $tipoServicio === TipoServicio::RevisionMedidores
)
```

- [ ] **Step 2.7: Agregar objetivos al JSON**

Abrir `resources/data/objetivos-pdf.json` y agregar la clave `revision_medidores` siguiendo la estructura existente:

```json
{
    "objetivo_informe": { ... },
    "revision_medidores": {
        "titulo": "OBJETIVOS",
        "parrafos": [
            "Realizar la verificación física del estado del sistema de medición de gas del contrato asignado, con el fin de establecer si existe alguna anomalía, manipulación o irregularidad en el medidor o instalaciones asociadas. Se documentarán evidencias fotográficas y se registrará el resultado de la revisión para respuesta a la empresa solicitante del servicio."
        ]
    }
}
```

- [ ] **Step 2.8: Correr los tests**

```bash
php artisan test --filter=ComponentesExistentesTest
```

Esperado: PASS 3 tests.

- [ ] **Step 2.9: Correr tests existentes para verificar que no regresen**

```bash
php artisan test --filter="ReporteCreate|ReporteEdit|OrdenCreate|OrdenesTrabajoIndex"
```

Esperado: todos los tests existentes siguen pasando.

- [ ] **Step 2.10: Commit**

```bash
git add app/Enums/TipoServicio.php \
        app/Livewire/Tecnico/ReporteCreate.php \
        app/Livewire/Tecnico/ReporteEdit.php \
        app/Livewire/Coordinador/OrdenCreate.php \
        app/Livewire/Coordinador/OrdenesTrabajoIndex.php \
        resources/data/objetivos-pdf.json \
        tests/Feature/RevisionMedidores/ComponentesExistentesTest.php
git commit -m "feat: integrar RevisionMedidores en ReporteCreate, ReporteEdit, OrdenCreate y OrdenesTrabajoIndex"
```

---

## Task 3: Instalar maatwebsite/excel

**Files:**
- No code files — solo dependencia

- [ ] **Step 3.1: Instalar el paquete**

```bash
composer require maatwebsite/excel
```

- [ ] **Step 3.2: Publicar la configuración**

```bash
php artisan vendor:publish --provider="Maatwebsite\Excel\ExcelServiceProvider" --tag=config
```

- [ ] **Step 3.3: Verificar que está disponible**

```bash
php artisan tinker --execute 'echo class_exists(\Maatwebsite\Excel\Facades\Excel::class) ? "OK" : "FAIL";'
```

Esperado: `OK`

- [ ] **Step 3.4: Commit**

```bash
git add composer.json composer.lock config/excel.php
git commit -m "chore: instalar maatwebsite/excel para import y export de órdenes"
```

---

## Task 4: Plantilla Descargable (Export + Controller + Ruta)

**Files:**
- Create: `app/Exports/RevisionMedidoresPlantillaExport.php`
- Create: `app/Http/Controllers/RevisionMedidoresPlantillaController.php`
- Create: `tests/Feature/RevisionMedidores/PlantillaExportTest.php`

Las rutas se registrarán en Task 9. El test usa la ruta directamente via `get()`.

- [ ] **Step 4.1: Crear el test**

```bash
php artisan make:test --pest RevisionMedidores/PlantillaExportTest
```

```php
<?php

use App\Models\Company;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;

beforeEach(function () {
    $this->seed(PermissionsSeeder::class);
    $company = Company::factory()->create();
    $this->user = User::factory()->create(['company_id' => $company->id]);
    $this->user->assignRole('Coordinador');
});

it('coordinador puede descargar la plantilla de importación', function () {
    $this->actingAs($this->user)
        ->get(route('operaciones.revision-medidores.plantilla'))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('técnico no puede descargar la plantilla', function () {
    $this->user->syncRoles(['Lector']);

    $this->actingAs($this->user)
        ->get(route('operaciones.revision-medidores.plantilla'))
        ->assertForbidden();
});
```

- [ ] **Step 4.2: Crear la Export class**

```bash
php artisan make:class Exports/RevisionMedidoresPlantillaExport
```

Contenido de `app/Exports/RevisionMedidoresPlantillaExport.php`:

```php
<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RevisionMedidoresPlantillaExport implements FromArray, WithHeadings, WithStyles
{
    public function headings(): array
    {
        return [
            'contrato',
            'cliente',
            'direccion',
            'barrio',
            'medidor',
            'tipo_medidor',
            'ciclo',
            'descripcion',
        ];
    }

    public function array(): array
    {
        return [
            [
                'CONT-001234',
                'Juan Pérez García',
                'Calle 45 # 23-10',
                'El Poblado',
                'MED-789456',
                'digital',
                'C12',
                'Verificación de lectura solicitada por empresa',
            ],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
```

- [ ] **Step 4.3: Crear el Controller**

```bash
php artisan make:controller RevisionMedidoresPlantillaController
```

Contenido de `app/Http/Controllers/RevisionMedidoresPlantillaController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Exports\RevisionMedidoresPlantillaExport;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RevisionMedidoresPlantillaController extends Controller
{
    public function download(): BinaryFileResponse
    {
        return Excel::download(
            new RevisionMedidoresPlantillaExport,
            'plantilla-revision-medidores.xlsx'
        );
    }
}
```

- [ ] **Step 4.4: Registrar la ruta temporalmente para correr el test**

Crear `routes/revision-medidores.php` con solo la ruta de plantilla (las demás rutas se agregan en Task 9):

```php
<?php

use App\Http\Controllers\ReportePdfController;
use App\Http\Controllers\RevisionMedidoresPlantillaController;
use App\Livewire\Coordinador\RevisionMedidores\ImportarRevisionMedidores;
use App\Livewire\Coordinador\RevisionMedidores\RevisionMedidoresIndex;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:ordenes.ver'])
    ->prefix('operaciones/revision-medidores')
    ->name('operaciones.revision-medidores.')
    ->group(function () {
        Route::get('plantilla', [RevisionMedidoresPlantillaController::class, 'download'])
            ->middleware('permission:ordenes.crear')
            ->name('plantilla');
    });
```

Agregar en `routes/web.php` antes del último `require`:
```php
require __DIR__.'/revision-medidores.php';
```

- [ ] **Step 4.5: Correr el test**

```bash
php artisan test --filter=PlantillaExportTest
```

Esperado: PASS 2 tests.

- [ ] **Step 4.6: Commit**

```bash
git add app/Exports/RevisionMedidoresPlantillaExport.php \
        app/Http/Controllers/RevisionMedidoresPlantillaController.php \
        routes/revision-medidores.php \
        routes/web.php \
        tests/Feature/RevisionMedidores/PlantillaExportTest.php
git commit -m "feat: agregar exportación de plantilla XLSX para revisión de medidores"
```

---

## Task 5: Import Class

**Files:**
- Create: `app/Imports/RevisionMedidoresImport.php`
- Create: `tests/Feature/RevisionMedidores/ImportTest.php`

- [ ] **Step 5.1: Crear el test**

```bash
php artisan make:test --pest RevisionMedidores/ImportTest
```

```php
<?php

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Imports\RevisionMedidoresImport;
use App\Models\Company;
use App\Models\OrdenJob;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionsSeeder::class);
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->assignRole('Coordinador');
});

it('importa filas válidas como OrdenJob con tipo RevisionMedidores', function () {
    $this->actingAs($this->user);

    $import = new RevisionMedidoresImport($this->company->id, $this->user->id);

    Excel::fake();

    // Simular colección de filas válidas
    $rows = collect([
        [
            'contrato'     => 'CONT-001',
            'cliente'      => 'Cliente Uno',
            'direccion'    => 'Calle 1',
            'barrio'       => 'Centro',
            'medidor'      => 'MED-111',
            'tipo_medidor' => 'digital',
            'ciclo'        => 'C01',
            'descripcion'  => 'Verificación',
        ],
        [
            'contrato'     => 'CONT-002',
            'cliente'      => 'Cliente Dos',
            'direccion'    => 'Calle 2',
            'barrio'       => 'Norte',
            'medidor'      => 'MED-222',
            'tipo_medidor' => null,
            'ciclo'        => null,
            'descripcion'  => null,
        ],
    ]);

    $import->collection($rows);

    expect(OrdenJob::where('tipo_servicio', TipoServicio::RevisionMedidores->value)->count())->toBe(2);

    $orden = OrdenJob::where('contrato', 'CONT-001')->first();
    expect($orden->cliente)->toBe('Cliente Uno')
        ->and($orden->estado->value)->toBe(EstadoOrden::Pendiente->value)
        ->and($orden->company_id)->toBe($this->company->id)
        ->and($orden->creado_por_id)->toBe($this->user->id)
        ->and($orden->comentario_usuario)->toBe('Verificación');
});

it('acumula errores para filas con campos requeridos vacíos', function () {
    $this->actingAs($this->user);

    $import = new RevisionMedidoresImport($this->company->id, $this->user->id);

    $rows = collect([
        [
            'contrato'    => '',
            'cliente'     => 'Cliente Sin Contrato',
            'direccion'   => 'Calle 3',
            'barrio'      => 'Sur',
            'medidor'     => 'MED-333',
            'tipo_medidor' => null,
            'ciclo'       => null,
            'descripcion' => null,
        ],
        [
            'contrato'    => 'CONT-004',
            'cliente'     => '',
            'direccion'   => 'Calle 4',
            'barrio'      => 'Este',
            'medidor'     => 'MED-444',
            'tipo_medidor' => null,
            'ciclo'       => null,
            'descripcion' => null,
        ],
    ]);

    $import->collection($rows);

    expect(OrdenJob::where('tipo_servicio', TipoServicio::RevisionMedidores->value)->count())->toBe(0);
    expect($import->errores())->toHaveCount(2);
    expect($import->errores()[0]['fila'])->toBe(2);
    expect($import->errores()[1]['fila'])->toBe(3);
});

it('importa filas válidas e ignora las inválidas', function () {
    $this->actingAs($this->user);

    $import = new RevisionMedidoresImport($this->company->id, $this->user->id);

    $rows = collect([
        [
            'contrato'    => 'CONT-VALIDO',
            'cliente'     => 'Cliente Válido',
            'direccion'   => 'Calle 5',
            'barrio'      => 'Oeste',
            'medidor'     => 'MED-555',
            'tipo_medidor' => null,
            'ciclo'       => null,
            'descripcion' => null,
        ],
        [
            'contrato'    => '',       // inválido
            'cliente'     => 'X',
            'direccion'   => 'Y',
            'barrio'      => 'Z',
            'medidor'     => 'W',
            'tipo_medidor' => null,
            'ciclo'       => null,
            'descripcion' => null,
        ],
    ]);

    $import->collection($rows);

    expect(OrdenJob::where('tipo_servicio', TipoServicio::RevisionMedidores->value)->count())->toBe(1);
    expect($import->errores())->toHaveCount(1);
});
```

- [ ] **Step 5.2: Correr el test para verificar que falla**

```bash
php artisan test --filter=ImportTest
```

Esperado: FAIL — clase no existe.

- [ ] **Step 5.3: Crear la Import class**

```bash
php artisan make:class Imports/RevisionMedidoresImport
```

Contenido de `app/Imports/RevisionMedidoresImport.php`:

```php
<?php

namespace App\Imports;

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Models\OrdenJob;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class RevisionMedidoresImport implements ToCollection, WithHeadingRow
{
    /** @var array<int, array{fila: int, campo: string, motivo: string}> */
    private array $errores = [];

    private int $filaActual = 1;

    public function __construct(
        private readonly string $companyId,
        private readonly string $creadoPorId,
    ) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $this->filaActual++;
            $erroresFila = $this->validar($row->toArray());

            if (! empty($erroresFila)) {
                foreach ($erroresFila as $error) {
                    $this->errores[] = ['fila' => $this->filaActual, ...$error];
                }
                continue;
            }

            OrdenJob::create([
                'company_id'        => $this->companyId,
                'creado_por_id'     => $this->creadoPorId,
                'tipo_servicio'     => TipoServicio::RevisionMedidores->value,
                'estado'            => EstadoOrden::Pendiente->value,
                'contrato'          => trim($row['contrato'] ?? ''),
                'cliente'           => trim($row['cliente'] ?? ''),
                'direccion'         => trim($row['direccion'] ?? ''),
                'barrio'            => trim($row['barrio'] ?? ''),
                'medidor'           => trim($row['medidor'] ?? ''),
                'tipo_medidor'      => trim($row['tipo_medidor'] ?? '') ?: null,
                'ciclo'             => trim($row['ciclo'] ?? '') ?: null,
                'comentario_usuario' => trim($row['descripcion'] ?? '') ?: null,
                'estado_servicio'   => true,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, array{campo: string, motivo: string}>
     */
    private function validar(array $row): array
    {
        $requeridos = ['contrato', 'cliente', 'direccion', 'barrio', 'medidor'];
        $errores = [];

        foreach ($requeridos as $campo) {
            if (empty(trim($row[$campo] ?? ''))) {
                $errores[] = ['campo' => $campo, 'motivo' => "El campo '{$campo}' es requerido"];
            }
        }

        return $errores;
    }

    /** @return array<int, array{fila: int, campo: string, motivo: string}> */
    public function errores(): array
    {
        return $this->errores;
    }
}
```

- [ ] **Step 5.4: Correr el test**

```bash
php artisan test --filter=ImportTest
```

Esperado: PASS 3 tests.

- [ ] **Step 5.5: Commit**

```bash
git add app/Imports/RevisionMedidoresImport.php tests/Feature/RevisionMedidores/ImportTest.php
git commit -m "feat: agregar RevisionMedidoresImport con validación fila por fila"
```

---

## Task 6: Mailable OrdenesAsignadasResumen

**Files:**
- Create: `app/Mail/OrdenesAsignadasResumen.php`
- Create: `resources/views/emails/ordenes-asignadas-resumen.blade.php`
- Create: `tests/Feature/RevisionMedidores/OrdenesAsignadasResumenTest.php`

- [ ] **Step 6.1: Crear el test**

```bash
php artisan make:test --pest RevisionMedidores/OrdenesAsignadasResumenTest
```

```php
<?php

use App\Mail\OrdenesAsignadasResumen;
use App\Models\Company;
use App\Models\OrdenJob;
use App\Models\personals;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionsSeeder::class);
    $company = Company::factory()->create();
    $this->personal = personals::factory()->create(['company_id' => $company->id]);
    User::factory()->create(['personals_id' => $this->personal->id, 'company_id' => $company->id]);
    $this->ordenes = OrdenJob::factory()->count(3)->create([
        'tipo_servicio' => \App\Enums\TipoServicio::RevisionMedidores->value,
        'personals_id' => $this->personal->id,
        'company_id' => $company->id,
    ]);
});

it('el mailable tiene el subject correcto', function () {
    $mail = new OrdenesAsignadasResumen($this->personal, $this->ordenes);

    expect($mail->envelope()->subject)->toContain('3 Órdenes Asignadas');
});

it('el mailable incluye el nombre del investigador en la vista', function () {
    $mail = new OrdenesAsignadasResumen($this->personal, $this->ordenes);
    $rendered = $mail->render();

    expect($rendered)->toContain($this->personal->nombres);
});

it('el mailable lista los contratos de las órdenes', function () {
    $mail = new OrdenesAsignadasResumen($this->personal, $this->ordenes);
    $rendered = $mail->render();

    foreach ($this->ordenes as $orden) {
        expect($rendered)->toContain($orden->contrato);
    }
});
```

- [ ] **Step 6.2: Correr test para verificar que falla**

```bash
php artisan test --filter=OrdenesAsignadasResumenTest
```

Esperado: FAIL.

- [ ] **Step 6.3: Crear el Mailable**

```bash
php artisan make:mail OrdenesAsignadasResumen
```

Contenido de `app/Mail/OrdenesAsignadasResumen.php`:

```php
<?php

namespace App\Mail;

use App\Models\personals;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class OrdenesAsignadasResumen extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public personals $investigador,
        public Collection $ordenes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->ordenes->count()} Órdenes Asignadas — Revisión de Medidores",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ordenes-asignadas-resumen',
            with: [
                'investigador' => $this->investigador,
                'ordenes'      => $this->ordenes,
            ],
        );
    }

    /** @return array<int, \Illuminate\Mail\Mailables\Attachment> */
    public function attachments(): array
    {
        return [];
    }
}
```

- [ ] **Step 6.4: Crear la vista del correo**

Crear `resources/views/emails/ordenes-asignadas-resumen.blade.php`:

```blade
@extends('emails.layout')

@section('title', $ordenes->count() . ' Órdenes Asignadas — Revisión de Medidores')

@section('content')
    <div class="greeting">¡Hola {{ $investigador->nombres }} {{ $investigador->apellidos }}!</div>

    <div class="alert info">
        Se te han asignado <strong>{{ $ordenes->count() }} órdenes</strong> de Revisión de Medidores.
        A continuación encontrarás el listado completo para atenderlas.
    </div>

    <div class="section">
        <div class="section-title">Órdenes Asignadas ({{ $ordenes->count() }})</div>

        <table style="width:100%; border-collapse: collapse; font-size: 8.5pt;">
            <thead>
                <tr style="background:#003E72; color:white;">
                    <th style="padding:6px 8px; text-align:left;">N° Orden</th>
                    <th style="padding:6px 8px; text-align:left;">Contrato</th>
                    <th style="padding:6px 8px; text-align:left;">Cliente</th>
                    <th style="padding:6px 8px; text-align:left;">Dirección</th>
                    <th style="padding:6px 8px; text-align:left;">Medidor</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($ordenes as $index => $orden)
                    <tr style="background: {{ $index % 2 === 0 ? '#f9fafb' : '#ffffff' }};">
                        <td style="padding:5px 8px; border-bottom:1px solid #e5e7eb;">{{ $orden->numero_orden }}</td>
                        <td style="padding:5px 8px; border-bottom:1px solid #e5e7eb;">{{ $orden->contrato }}</td>
                        <td style="padding:5px 8px; border-bottom:1px solid #e5e7eb;">{{ $orden->cliente }}</td>
                        <td style="padding:5px 8px; border-bottom:1px solid #e5e7eb;">{{ $orden->direccion }}, {{ $orden->barrio }}</td>
                        <td style="padding:5px 8px; border-bottom:1px solid #e5e7eb;">{{ $orden->medidor }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="note">Puedes ingresar al sistema para ver el detalle completo de cada orden y comenzar a reportar tus resultados.</p>
@endsection
```

- [ ] **Step 6.5: Correr el test**

```bash
php artisan test --filter=OrdenesAsignadasResumenTest
```

Esperado: PASS 3 tests.

- [ ] **Step 6.6: Commit**

```bash
git add app/Mail/OrdenesAsignadasResumen.php \
        resources/views/emails/ordenes-asignadas-resumen.blade.php \
        tests/Feature/RevisionMedidores/OrdenesAsignadasResumenTest.php
git commit -m "feat: agregar mailable OrdenesAsignadasResumen para asignación masiva"
```

---

## Task 7: Componente ImportarRevisionMedidores

**Files:**
- Create: `app/Livewire/Coordinador/RevisionMedidores/ImportarRevisionMedidores.php`
- Create: `resources/views/livewire/coordinador/revision-medidores/importar-revision-medidores.blade.php`
- Create: `tests/Feature/RevisionMedidores/ImportarComponenteTest.php`

- [ ] **Step 7.1: Crear el test**

```bash
php artisan make:test --pest RevisionMedidores/ImportarComponenteTest
```

```php
<?php

use App\Enums\TipoServicio;
use App\Models\Company;
use App\Models\OrdenJob;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionsSeeder::class);
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->assignRole('Coordinador');
});

it('el componente renderiza correctamente', function () {
    $this->actingAs($this->user);

    Livewire::test(\App\Livewire\Coordinador\RevisionMedidores\ImportarRevisionMedidores::class)
        ->assertOk()
        ->assertSee('Importar Órdenes')
        ->assertSee('Descargar Plantilla');
});

it('técnico no puede acceder al componente', function () {
    $this->user->syncRoles(['Lector']);
    $this->actingAs($this->user);

    $this->get(route('operaciones.revision-medidores.importar'))
        ->assertForbidden();
});

it('importa un archivo CSV válido y crea las órdenes', function () {
    $this->actingAs($this->user);

    $csv = "contrato,cliente,direccion,barrio,medidor,tipo_medidor,ciclo,descripcion\n"
         . "CONT-A,Cliente A,Calle 1,Centro,MED-A,digital,C1,Verificar\n"
         . "CONT-B,Cliente B,Calle 2,Norte,MED-B,,,\n";

    $file = UploadedFile::fake()->createWithContent('ordenes.csv', $csv);

    Livewire::test(\App\Livewire\Coordinador\RevisionMedidores\ImportarRevisionMedidores::class)
        ->set('archivo', $file)
        ->call('importar')
        ->assertHasNoErrors()
        ->assertSee('2 órdenes importadas')
        ->assertSee('0 errores');

    expect(OrdenJob::where('tipo_servicio', TipoServicio::RevisionMedidores->value)->count())->toBe(2);
});

it('muestra errores de filas inválidas y sigue importando las válidas', function () {
    $this->actingAs($this->user);

    $csv = "contrato,cliente,direccion,barrio,medidor,tipo_medidor,ciclo,descripcion\n"
         . "CONT-OK,Cliente OK,Calle 1,Centro,MED-OK,,,\n"
         . ",Sin Contrato,Calle 2,Norte,MED-X,,,\n";

    $file = UploadedFile::fake()->createWithContent('ordenes.csv', $csv);

    Livewire::test(\App\Livewire\Coordinador\RevisionMedidores\ImportarRevisionMedidores::class)
        ->set('archivo', $file)
        ->call('importar')
        ->assertSee('1 órdenes importadas')
        ->assertSee('1 errores');

    expect(OrdenJob::where('tipo_servicio', TipoServicio::RevisionMedidores->value)->count())->toBe(1);
});

it('muestra error de validación si no se sube ningún archivo', function () {
    $this->actingAs($this->user);

    Livewire::test(\App\Livewire\Coordinador\RevisionMedidores\ImportarRevisionMedidores::class)
        ->call('importar')
        ->assertHasErrors(['archivo']);
});
```

- [ ] **Step 7.2: Correr test para verificar que falla**

```bash
php artisan test --filter=ImportarComponenteTest
```

Esperado: FAIL.

- [ ] **Step 7.3: Crear el componente Livewire**

```bash
php artisan make:livewire "Coordinador/RevisionMedidores/ImportarRevisionMedidores"
```

Contenido de `app/Livewire/Coordinador/RevisionMedidores/ImportarRevisionMedidores.php`:

```php
<?php

namespace App\Livewire\Coordinador\RevisionMedidores;

use App\Imports\RevisionMedidoresImport;
use App\Services\TenantService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

#[Layout('layouts.app')]
#[Title('Importar Revisión de Medidores')]
class ImportarRevisionMedidores extends Component
{
    use WithFileUploads;

    #[Validate('required|file|mimes:csv,xlsx,xls|max:10240')]
    public mixed $archivo = null;

    public ?int $importadas = null;

    /** @var array<int, array{fila: int, campo: string, motivo: string}> */
    public array $errores = [];

    public bool $importacionRealizada = false;

    public function importar(): void
    {
        $this->validate();

        $companyId = app(TenantService::class)->id();
        $userId = auth()->id();

        $import = new RevisionMedidoresImport($companyId, $userId);

        Excel::import($import, $this->archivo->getRealPath(), null, \Maatwebsite\Excel\Excel::CSV);

        $this->importadas = \App\Models\OrdenJob::where('tipo_servicio', \App\Enums\TipoServicio::RevisionMedidores->value)
            ->where('creado_por_id', $userId)
            ->whereDate('created_at', today())
            ->count();

        // Recalcular: total filas - errores = importadas
        $this->errores = $import->errores();
        $this->importacionRealizada = true;
        $this->archivo = null;
    }

    public function render(): View
    {
        return view('livewire.coordinador.revision-medidores.importar-revision-medidores');
    }
}
```

- [ ] **Step 7.4: Crear la vista**

Crear `resources/views/livewire/coordinador/revision-medidores/importar-revision-medidores.blade.php`:

```blade
<div class="max-w-4xl mx-auto py-8 px-4">
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">Importar Revisión de Medidores</flux:heading>
            <flux:subheading>Carga masiva de órdenes desde archivo CSV o Excel.</flux:subheading>
        </div>
        <div class="flex gap-3">
            <flux:button href="{{ route('operaciones.revision-medidores.plantilla') }}" icon="arrow-down-tray" variant="ghost">
                Descargar Plantilla
            </flux:button>
            <flux:button href="{{ route('operaciones.revision-medidores') }}" icon="arrow-left" variant="ghost">
                Volver al listado
            </flux:button>
        </div>
    </div>

    @if ($importacionRealizada)
        <div class="mb-6 p-4 rounded-lg border {{ count($errores) === 0 ? 'bg-green-50 border-green-200' : 'bg-yellow-50 border-yellow-200' }}">
            <p class="font-semibold text-sm">
                {{ $importadas }} órdenes importadas
                &bull;
                {{ count($errores) }} errores
            </p>
        </div>

        @if (count($errores) > 0)
            <div class="mb-6">
                <flux:heading size="sm" class="mb-3">Filas con errores</flux:heading>
                <table class="w-full text-sm border border-gray-200 rounded">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left">Fila</th>
                            <th class="px-3 py-2 text-left">Campo</th>
                            <th class="px-3 py-2 text-left">Motivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($errores as $error)
                            <tr class="border-t border-gray-100">
                                <td class="px-3 py-2">{{ $error['fila'] }}</td>
                                <td class="px-3 py-2 font-mono text-xs">{{ $error['campo'] }}</td>
                                <td class="px-3 py-2 text-red-600">{{ $error['motivo'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <flux:button wire:click="$set('importacionRealizada', false)" variant="outline">
            Importar otro archivo
        </flux:button>
    @else
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <flux:heading size="sm" class="mb-4">Cargar archivo</flux:heading>

            <div class="mb-4 text-sm text-gray-600">
                <p>Formatos aceptados: <strong>CSV</strong> y <strong>Excel (.xlsx)</strong>. Tamaño máximo: 10 MB.</p>
                <p class="mt-1">Columnas requeridas: <code>contrato, cliente, direccion, barrio, medidor</code></p>
            </div>

            <form wire:submit="importar">
                <div class="mb-4">
                    <input type="file" wire:model="archivo" accept=".csv,.xlsx,.xls"
                        class="block w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                    @error('archivo')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <flux:button type="submit" variant="primary" icon="arrow-up-tray" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="importar">Importar Órdenes</span>
                    <span wire:loading wire:target="importar">Procesando...</span>
                </flux:button>
            </form>
        </div>
    @endif
</div>
```

- [ ] **Step 7.5: Ajustar el método importar para manejar tanto CSV como XLSX**

Actualizar el método `importar` en `ImportarRevisionMedidores.php` para detectar el tipo de archivo:

```php
public function importar(): void
{
    $this->validate();

    $companyId = app(TenantService::class)->id();
    $userId = auth()->id();

    $import = new RevisionMedidoresImport($companyId, $userId);

    $extension = strtolower($this->archivo->getClientOriginalExtension());
    $readerType = in_array($extension, ['xlsx', 'xls'])
        ? \Maatwebsite\Excel\Excel::XLSX
        : \Maatwebsite\Excel\Excel::CSV;

    Excel::import($import, $this->archivo->getRealPath(), null, $readerType);

    $this->errores = $import->errores();
    $this->importadas = \App\Models\OrdenJob::where('tipo_servicio', \App\Enums\TipoServicio::RevisionMedidores->value)
        ->where('creado_por_id', $userId)
        ->whereDate('created_at', today())
        ->count();

    $this->importacionRealizada = true;
    $this->archivo = null;
}
```

- [ ] **Step 7.6: Correr el test**

```bash
php artisan test --filter=ImportarComponenteTest
```

Esperado: PASS 4 tests.

- [ ] **Step 7.7: Commit**

```bash
git add app/Livewire/Coordinador/RevisionMedidores/ImportarRevisionMedidores.php \
        resources/views/livewire/coordinador/revision-medidores/importar-revision-medidores.blade.php \
        tests/Feature/RevisionMedidores/ImportarComponenteTest.php
git commit -m "feat: agregar componente ImportarRevisionMedidores con upload CSV/XLSX"
```

---

## Task 8: Componente RevisionMedidoresIndex

**Files:**
- Create: `app/Livewire/Coordinador/RevisionMedidores/RevisionMedidoresIndex.php`
- Create: `resources/views/livewire/coordinador/revision-medidores/revision-medidores-index.blade.php`
- Create: `tests/Feature/RevisionMedidores/IndexComponenteTest.php`

- [ ] **Step 8.1: Crear el test**

```bash
php artisan make:test --pest RevisionMedidores/IndexComponenteTest
```

```php
<?php

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Mail\OrdenAsignada;
use App\Mail\OrdenesAsignadasResumen;
use App\Models\Company;
use App\Models\OrdenJob;
use App\Models\personals;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionsSeeder::class);
    $this->company = Company::factory()->create();
    $this->coordinador = User::factory()->create(['company_id' => $this->company->id]);
    $this->coordinador->assignRole('Coordinador');
    $this->personal = personals::factory()->create(['company_id' => $this->company->id]);
    User::factory()->create([
        'personals_id' => $this->personal->id,
        'company_id'   => $this->company->id,
        'email'        => 'investigador@test.com',
    ])->assignRole('Lector');
});

it('lista solo órdenes de tipo RevisionMedidores', function () {
    OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::RevisionMedidores->value,
        'cliente'       => 'Cliente Revisión',
        'company_id'    => $this->company->id,
    ]);
    OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::Fraudes->value,
        'cliente'       => 'Cliente Fraude',
        'company_id'    => $this->company->id,
    ]);

    $this->actingAs($this->coordinador);

    Livewire::test(\App\Livewire\Coordinador\RevisionMedidores\RevisionMedidoresIndex::class)
        ->assertSee('Cliente Revisión')
        ->assertDontSee('Cliente Fraude');
});

it('asignación individual cambia estado a Asignada y envía OrdenAsignada', function () {
    Mail::fake();

    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::RevisionMedidores->value,
        'estado'        => EstadoOrden::Pendiente->value,
        'company_id'    => $this->company->id,
    ]);

    $this->actingAs($this->coordinador);

    Livewire::test(\App\Livewire\Coordinador\RevisionMedidores\RevisionMedidoresIndex::class)
        ->callAction('asignar', data: [
            'personals_id'    => $this->personal->id,
            'fecha_asignacion' => now()->format('Y-m-d H:i:s'),
            'fecha_limite'    => now()->addDays(7)->format('Y-m-d H:i:s'),
        ], arguments: ['record' => $orden->id]);

    expect($orden->fresh()->estado)->toBe(EstadoOrden::Asignada)
        ->and($orden->fresh()->personals_id)->toBe($this->personal->id);

    Mail::assertSent(OrdenAsignada::class, 1);
    Mail::assertNotSent(OrdenesAsignadasResumen::class);
});

it('asignación masiva envía solo un OrdenesAsignadasResumen y no envía OrdenAsignada', function () {
    Mail::fake();

    $ordenes = OrdenJob::factory()->count(3)->create([
        'tipo_servicio' => TipoServicio::RevisionMedidores->value,
        'estado'        => EstadoOrden::Pendiente->value,
        'company_id'    => $this->company->id,
    ]);

    $this->actingAs($this->coordinador);

    Livewire::test(\App\Livewire\Coordinador\RevisionMedidores\RevisionMedidoresIndex::class)
        ->callBulkAction('asignar_masivo', records: $ordenes->pluck('id')->toArray(), data: [
            'personals_id'    => $this->personal->id,
            'fecha_asignacion' => now()->format('Y-m-d H:i:s'),
            'fecha_limite'    => now()->addDays(7)->format('Y-m-d H:i:s'),
        ]);

    foreach ($ordenes as $orden) {
        expect($orden->fresh()->estado)->toBe(EstadoOrden::Asignada);
    }

    Mail::assertSent(OrdenesAsignadasResumen::class, 1);
    Mail::assertNotSent(OrdenAsignada::class);
});
```

- [ ] **Step 8.2: Correr test para verificar que falla**

```bash
php artisan test --filter=IndexComponenteTest
```

Esperado: FAIL.

- [ ] **Step 8.3: Crear el componente**

```bash
php artisan make:livewire "Coordinador/RevisionMedidores/RevisionMedidoresIndex"
```

Contenido de `app/Livewire/Coordinador/RevisionMedidores/RevisionMedidoresIndex.php`:

```php
<?php

namespace App\Livewire\Coordinador\RevisionMedidores;

use App\Enums\EstadoOrden;
use App\Enums\PrioridadOrden;
use App\Enums\TipoServicio;
use App\Mail\OrdenAsignada;
use App\Mail\OrdenesAsignadasResumen;
use App\Models\OrdenJob;
use App\Models\personals;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Revisión de Medidores')]
class RevisionMedidoresIndex extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        $tecnicos = personals::query()
            ->select(['id', 'nombres', 'apellidos'])
            ->get()
            ->mapWithKeys(fn ($p) => [$p->id => "{$p->nombres} {$p->apellidos}"]);

        $asignacionSchema = [
            Forms\Components\Select::make('personals_id')
                ->label('Investigador')
                ->options($tecnicos)
                ->searchable()
                ->required(),
            Forms\Components\DateTimePicker::make('fecha_asignacion')
                ->label('Fecha de Asignación')
                ->default(now())
                ->required(),
            Forms\Components\DateTimePicker::make('fecha_limite')
                ->label('Fecha Límite')
                ->nullable(),
        ];

        return $table
            ->query(OrdenJob::query()
                ->where('tipo_servicio', TipoServicio::RevisionMedidores->value)
                ->with(['personal'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('numero_orden')
                    ->label('N° Orden')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('contrato')
                    ->label('Contrato')
                    ->searchable(),
                Tables\Columns\TextColumn::make('cliente')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('direccion')
                    ->label('Dirección')
                    ->searchable()
                    ->formatStateUsing(fn ($state, OrdenJob $record): string => "{$record->direccion}, {$record->barrio}"),
                Tables\Columns\TextColumn::make('medidor')
                    ->label('N° Medidor')
                    ->searchable(),
                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof EstadoOrden ? $state->label() : $state)
                    ->color(fn ($state) => $state instanceof EstadoOrden ? $state->color() : 'gray'),
                Tables\Columns\TextColumn::make('personal.nombres')
                    ->label('Investigador')
                    ->formatStateUsing(fn ($state, OrdenJob $record): string => $record->personal
                        ? "{$record->personal->nombres} {$record->personal->apellidos}"
                        : '—'
                    ),
                Tables\Columns\TextColumn::make('fecha_limite')
                    ->label('Fecha Límite')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->label('Estado')
                    ->options(
                        collect(EstadoOrden::cases())
                            ->mapWithKeys(fn (EstadoOrden $e) => [$e->value => $e->label()])
                    ),
            ])
            ->headerActions([
                Action::make('importar')
                    ->label('Importar Órdenes')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->url(route('operaciones.revision-medidores.importar')),
            ])
            ->actions([
                Action::make('asignar')
                    ->label('Asignar')
                    ->icon('heroicon-o-user-plus')
                    ->color('primary')
                    ->visible(fn (OrdenJob $record): bool => $record->estado === EstadoOrden::Pendiente)
                    ->form($asignacionSchema)
                    ->action(function (OrdenJob $record, array $data): void {
                        $record->update([
                            'personals_id'     => $data['personals_id'],
                            'estado'           => EstadoOrden::Asignada->value,
                            'fecha_asignacion' => $data['fecha_asignacion'],
                            'fecha_limite'     => $data['fecha_limite'] ?? null,
                        ]);
                        $this->notificarIndividual($record);
                    }),
                Action::make('descargar_pdf')
                    ->label('')
                    ->tooltip('Descargar Informe PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->visible(fn (OrdenJob $record): bool => $record->estado === EstadoOrden::Completada
                        && auth()->user()->can('informes')
                    )
                    ->url(fn (OrdenJob $record): string => route(
                        'operaciones.revision-medidores.reporte',
                        [$record, 'reporte' => $record->reportes->sortByDesc('created_at')->first()?->id]
                    ))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('asignar_masivo')
                        ->label('Asignar seleccionadas')
                        ->icon('heroicon-o-user-group')
                        ->color('primary')
                        ->form($asignacionSchema)
                        ->action(function (\Illuminate\Support\Collection $records, array $data): void {
                            $records->each(function (OrdenJob $record) use ($data): void {
                                $record->update([
                                    'personals_id'     => $data['personals_id'],
                                    'estado'           => EstadoOrden::Asignada->value,
                                    'fecha_asignacion' => $data['fecha_asignacion'],
                                    'fecha_limite'     => $data['fecha_limite'] ?? null,
                                ]);
                            });
                            $this->notificarMasivo($records, $data['personals_id']);
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->emptyStateHeading('Sin órdenes de revisión')
            ->emptyStateDescription('Importa un archivo para comenzar.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->defaultSort('created_at', 'desc');
    }

    private function notificarIndividual(OrdenJob $orden): void
    {
        $email = $orden->personal?->usuario?->email;
        if (! $email) {
            return;
        }
        try {
            Mail::to($email)->send(new OrdenAsignada($orden, $orden->personal));
        } catch (\Exception $e) {
            Log::error('Error enviando OrdenAsignada.', ['orden' => $orden->numero_orden, 'error' => $e->getMessage()]);
        }
    }

    private function notificarMasivo(\Illuminate\Support\Collection $ordenes, string $personalsId): void
    {
        $personal = personals::find($personalsId);
        $email = $personal?->usuario?->email;
        if (! $email || ! $personal) {
            return;
        }
        try {
            Mail::to($email)->send(new OrdenesAsignadasResumen($personal, $ordenes));
        } catch (\Exception $e) {
            Log::error('Error enviando OrdenesAsignadasResumen.', ['error' => $e->getMessage()]);
        }
    }

    public function render(): View
    {
        return view('livewire.coordinador.revision-medidores.revision-medidores-index');
    }
}
```

- [ ] **Step 8.4: Crear la vista**

Crear `resources/views/livewire/coordinador/revision-medidores/revision-medidores-index.blade.php`:

```blade
<div>
    {{ $this->table }}
</div>
```

- [ ] **Step 8.5: Correr el test**

```bash
php artisan test --filter=IndexComponenteTest
```

Esperado: PASS 3 tests.

- [ ] **Step 8.6: Commit**

```bash
git add app/Livewire/Coordinador/RevisionMedidores/RevisionMedidoresIndex.php \
        resources/views/livewire/coordinador/revision-medidores/revision-medidores-index.blade.php \
        tests/Feature/RevisionMedidores/IndexComponenteTest.php
git commit -m "feat: agregar RevisionMedidoresIndex con asignación individual y masiva"
```

---

## Task 9: Rutas Completas y Sidebar

**Files:**
- Modify: `routes/revision-medidores.php`
- Modify: `resources/views/layouts/app/sidebar.blade.php`
- Create: `tests/Feature/RevisionMedidores/RutasSidebarTest.php`

- [ ] **Step 9.1: Crear el test**

```bash
php artisan make:test --pest RevisionMedidores/RutasSidebarTest
```

```php
<?php

use App\Models\Company;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionsSeeder::class);
    $company = Company::factory()->create();
    $this->coordinador = User::factory()->create(['company_id' => $company->id]);
    $this->coordinador->assignRole('Coordinador');
    $this->tecnico = User::factory()->create(['company_id' => $company->id]);
    $this->tecnico->assignRole('Lector');
});

it('coordinador puede acceder al listado de revisión de medidores', function () {
    $this->actingAs($this->coordinador)
        ->get(route('operaciones.revision-medidores.index'))
        ->assertOk();
});

it('coordinador puede acceder a la pantalla de importar', function () {
    $this->actingAs($this->coordinador)
        ->get(route('operaciones.revision-medidores.importar'))
        ->assertOk();
});

it('técnico no puede acceder al listado de revisión de medidores', function () {
    $this->actingAs($this->tecnico)
        ->get(route('operaciones.revision-medidores.index'))
        ->assertForbidden();
});

it('el sidebar muestra Revisión de Medidores para el coordinador', function () {
    $this->actingAs($this->coordinador)
        ->get(route('dashboard'))
        ->assertSee('Revisión de Medidores');
});

it('el sidebar no muestra Revisión de Medidores para el técnico', function () {
    $this->actingAs($this->tecnico)
        ->get(route('dashboard'))
        ->assertDontSee('Revisión de Medidores');
});
```

- [ ] **Step 9.2: Correr test para verificar que falla**

```bash
php artisan test --filter=RutasSidebarTest
```

Esperado: FAIL — rutas no registradas.

- [ ] **Step 9.3: Completar el archivo de rutas**

Reemplazar el contenido de `routes/revision-medidores.php` con todas las rutas:

```php
<?php

use App\Http\Controllers\ReportePdfController;
use App\Http\Controllers\RevisionMedidoresPlantillaController;
use App\Livewire\Coordinador\RevisionMedidores\ImportarRevisionMedidores;
use App\Livewire\Coordinador\RevisionMedidores\RevisionMedidoresIndex;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:ordenes.ver'])
    ->prefix('operaciones/revision-medidores')
    ->name('operaciones.revision-medidores.')
    ->group(function () {
        Route::get('/', RevisionMedidoresIndex::class)->name('index');

        Route::middleware('permission:ordenes.crear')->group(function () {
            Route::get('importar', ImportarRevisionMedidores::class)->name('importar');
            Route::get('plantilla', [RevisionMedidoresPlantillaController::class, 'download'])->name('plantilla');
        });

        Route::get('{orden}/reporte', [ReportePdfController::class, 'DownLoadInformeRevisionMedidores'])
            ->middleware('permission:informes')
            ->name('reporte');
    });
```

- [ ] **Step 9.4: Agregar entrada al sidebar**

En `resources/views/layouts/app/sidebar.blade.php`, dentro del bloque `@can('ordenes.ver')`, agregar el nuevo ítem después de "Órdenes de Trabajo":

```blade
@can('ordenes.ver')
<flux:sidebar.group :heading="__('Operaciones')" class="grid">
    <flux:sidebar.item icon="clipboard-document-list"
        :href="route('operaciones.ordenes-trabajo')"
        :current="request()->routeIs('operaciones.ordenes-trabajo') || request()->routeIs('operaciones.ordenes-trabajo.show') || request()->routeIs('operaciones.ordenes-trabajo.revisar')"
        wire:navigate>
        {{ __('Órdenes de Trabajo') }}
    </flux:sidebar.item>
    <flux:sidebar.item icon="magnifying-glass-circle"
        :href="route('operaciones.revision-medidores.index')"
        :current="request()->routeIs('operaciones.revision-medidores.*')"
        wire:navigate>
        {{ __('Revisión de Medidores') }}
    </flux:sidebar.item>
</flux:sidebar.group>
@endcan
```

- [ ] **Step 9.5: Correr el test**

```bash
php artisan test --filter=RutasSidebarTest
```

Esperado: PASS 5 tests.

- [ ] **Step 9.6: Commit**

```bash
git add routes/revision-medidores.php \
        resources/views/layouts/app/sidebar.blade.php \
        tests/Feature/RevisionMedidores/RutasSidebarTest.php
git commit -m "feat: registrar rutas y agregar Revisión de Medidores al sidebar"
```

---

## Task 10: Auditoría — UsuarioServicioAuditable

**Files:**
- Verify: `app/Livewire/Admin/ManageUsers.php` (auto-manejado, solo verificar)
- Verify: `app/Livewire/Auditor/AuditoriasIndex.php` (auto-manejado, solo verificar)
- Create: `tests/Feature/RevisionMedidores/AuditoriaTest.php`

- [ ] **Step 10.1: Crear el test**

```bash
php artisan make:test --pest RevisionMedidores/AuditoriaTest
```

```php
<?php

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Models\auditoria;
use App\Models\Company;
use App\Models\OrdenJob;
use App\Models\reportes;
use App\Models\User;
use App\Models\UsuarioServicioAuditable;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionsSeeder::class);
    $this->company = Company::factory()->create();
});

it('auditor con servicio revision_medidores ve las auditorías del tipo', function () {
    $auditor = User::factory()->create(['company_id' => $this->company->id]);
    $auditor->assignRole('Auditor');
    UsuarioServicioAuditable::create([
        'user_id'       => $auditor->id,
        'tipo_servicio' => TipoServicio::RevisionMedidores->value,
    ]);

    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::RevisionMedidores->value,
        'estado'        => EstadoOrden::Completada->value,
        'company_id'    => $this->company->id,
    ]);
    $reporte = reportes::factory()->create(['orden_job_id' => $orden->id]);
    auditoria::factory()->create(['reporte_id' => $reporte->id]);

    $this->actingAs($auditor);

    Livewire::test(\App\Livewire\Auditor\AuditoriasIndex::class)
        ->assertSee($orden->numero_orden);
});

it('auditor sin servicio revision_medidores no ve las auditorías del tipo', function () {
    $auditor = User::factory()->create(['company_id' => $this->company->id]);
    $auditor->assignRole('Auditor');
    // No se asigna UsuarioServicioAuditable

    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::RevisionMedidores->value,
        'estado'        => EstadoOrden::Completada->value,
        'company_id'    => $this->company->id,
    ]);
    $reporte = reportes::factory()->create(['orden_job_id' => $orden->id]);
    auditoria::factory()->create(['reporte_id' => $reporte->id]);

    $this->actingAs($auditor);

    Livewire::test(\App\Livewire\Auditor\AuditoriasIndex::class)
        ->assertDontSee($orden->numero_orden);
});

it('ManageUsers muestra RevisionMedidores en el checkbox de servicios auditables', function () {
    $admin = User::factory()->create(['company_id' => $this->company->id]);
    $admin->assignRole('Administrador');

    $this->actingAs($admin);

    Livewire::test(\App\Livewire\Admin\ManageUsers::class)
        ->assertSee('Revisión de Medidores');
});
```

- [ ] **Step 10.2: Correr el test**

```bash
php artisan test --filter=AuditoriaTest
```

Esperado: PASS 3 tests (el enum ya está — ManageUsers y AuditoriasIndex usan `TipoServicio::cases()` automáticamente).

Si algún test falla por factories faltantes (`auditoria::factory()`, `reportes::factory()`), verificar que existan en `database/factories/` antes de continuar.

- [ ] **Step 10.3: Commit**

```bash
git add tests/Feature/RevisionMedidores/AuditoriaTest.php
git commit -m "test: verificar integración de auditoría para RevisionMedidores"
```

---

## Task 11: Informe PDF

**Files:**
- Modify: `app/Http/Controllers/ReportePdfController.php`
- Create: `resources/views/pdf/reporte-revision-medidores.blade.php`
- Create: `tests/Feature/RevisionMedidores/PdfInformeTest.php`

- [ ] **Step 11.1: Crear el test**

```bash
php artisan make:test --pest RevisionMedidores/PdfInformeTest
```

```php
<?php

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Models\Company;
use App\Models\OrdenJob;
use App\Models\reportes;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionsSeeder::class);
    $company = Company::factory()->create();
    $this->user = User::factory()->create(['company_id' => $company->id]);
    $this->user->assignRole('Coordinador');

    $this->orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::RevisionMedidores->value,
        'estado'        => EstadoOrden::Completada->value,
        'company_id'    => $company->id,
    ]);
    reportes::factory()->create(['orden_job_id' => $this->orden->id]);
});

it('coordinador con permiso informes puede descargar el PDF de revisión de medidores', function () {
    $this->actingAs($this->user)
        ->get(route('operaciones.revision-medidores.reporte', $this->orden))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('retorna 403 si la orden no es de tipo RevisionMedidores', function () {
    $ordenFraude = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::Fraudes->value,
        'estado'        => EstadoOrden::Completada->value,
        'company_id'    => $this->orden->company_id,
    ]);
    reportes::factory()->create(['orden_job_id' => $ordenFraude->id]);

    $this->actingAs($this->user)
        ->get(route('operaciones.revision-medidores.reporte', $ordenFraude))
        ->assertForbidden();
});

it('retorna 403 si el usuario no tiene permiso informes', function () {
    $sinPermiso = User::factory()->create(['company_id' => $this->orden->company_id]);
    $sinPermiso->assignRole('Lector'); // Lector no tiene permiso 'informes'

    $this->actingAs($sinPermiso)
        ->get(route('operaciones.revision-medidores.reporte', $this->orden))
        ->assertForbidden();
});
```

- [ ] **Step 11.2: Correr test para verificar que falla**

```bash
php artisan test --filter=PdfInformeTest
```

Esperado: FAIL — método no existe.

- [ ] **Step 11.3: Agregar el método al ReportePdfController**

En `app/Http/Controllers/ReportePdfController.php`, agregar antes del método `fileToBase64`:

```php
public function DownLoadInformeRevisionMedidores(OrdenJob $orden): Response
{
    abort_unless($orden->tipo_servicio === TipoServicio::RevisionMedidores, 403);
    abort_unless(auth()->user()->can('informes'), 403);

    ini_set('memory_limit', '256M');

    $reporteId = request()->query('reporte');

    $orden->load(['personal', 'creadoPor']);

    $query = $orden->reportes()->with([
        'comercio.tipoComercio',
        'tipoImposibilidad',
        'tipoRegulador',
        'marcaMedidor',
        'marcaRegulador',
        'tipoNotificacionAlerta',
        'auditoria.auditadoPor.personal',
    ]);

    $reporte = $reporteId
        ? $query->where('id', $reporteId)->firstOrFail()
        : $query->latest()->firstOrFail();

    $anomalias = collect($reporte->anomalia ?? [])
        ->map(fn (string|int $id) => TipoAnomalia::find($id)?->nombre)
        ->filter()
        ->implode(', ');

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

    $objetivos = $this->resolveObjetivos(TipoServicio::RevisionMedidores->value);

    $pdf = Pdf::loadView('pdf.reporte-revision-medidores', [
        'orden'           => $orden,
        'reporte'         => $reporte,
        'anomalias'       => $anomalias,
        'logoProderi'     => $logoProderi,
        'imagenes'        => $imagenes,
        'fechaGeneracion' => now()->format('d-m-Y'),
        'firma'           => $firma,
        'objetivos'       => $objetivos,
    ]);

    $pdf->setPaper('A4', 'portrait');

    return $pdf->download("reporte-revision-medidores-{$orden->numero_orden}.pdf");
}
```

- [ ] **Step 11.4: Crear la vista PDF**

Copiar `resources/views/pdf/reporte-fraude.blade.php` a `resources/views/pdf/reporte-revision-medidores.blade.php`:

```bash
cp resources/views/pdf/reporte-fraude.blade.php resources/views/pdf/reporte-revision-medidores.blade.php
```

En `reporte-revision-medidores.blade.php`, cambiar únicamente el título:

```html
<!-- ANTES: -->
<title>Informe de Inspección por Fraude - {{ $orden->numero_orden }}</title>
<!-- y en el h1 del header: -->
<h1>INFORME DE INSPECCIÓN POR FRAUDE</h1>

<!-- DESPUÉS: -->
<title>Informe de Revisión de Medidores - {{ $orden->numero_orden }}</title>
<!-- y en el h1 del header: -->
<h1>INFORME DE REVISIÓN DE MEDIDORES / LECTURAS</h1>
```

- [ ] **Step 11.5: Agregar acción PDF en OrdenesTrabajoIndex**

En `app/Livewire/Coordinador/OrdenesTrabajoIndex.php`, agregar la nueva acción en el bloque `->actions([...])`  después de la última acción de PDF existente:

```php
Action::make('descargar_pdf_revision_medidores')
    ->label('')
    ->tooltip('Descargar Informe de Revisión de Medidores')
    ->icon('heroicon-o-arrow-down-tray')
    ->color('info')
    ->visible(fn (OrdenJob $record): bool => $record->tipo_servicio === TipoServicio::RevisionMedidores
        && $record->estado === EstadoOrden::Completada
        && auth()->user()->can('informes')
    )
    ->url(fn (OrdenJob $record): string => route(
        'operaciones.revision-medidores.reporte',
        [$record, 'reporte' => $record->reportes->sortByDesc('created_at')->first()?->id]
    ))
    ->openUrlInNewTab(),
```

- [ ] **Step 11.6: Correr el test**

```bash
php artisan test --filter=PdfInformeTest
```

Esperado: PASS 3 tests.

- [ ] **Step 11.7: Commit**

```bash
git add app/Http/Controllers/ReportePdfController.php \
        resources/views/pdf/reporte-revision-medidores.blade.php \
        app/Livewire/Coordinador/OrdenesTrabajoIndex.php \
        tests/Feature/RevisionMedidores/PdfInformeTest.php
git commit -m "feat: agregar informe PDF para revisión de medidores"
```

---

## Task 12: Pint y Suite Completa de Tests

- [ ] **Step 12.1: Ejecutar Pint para formatear todos los archivos modificados**

```bash
vendor/bin/pint --dirty --format agent
```

Revisar y aplicar todos los cambios sugeridos.

- [ ] **Step 12.2: Correr la suite completa del módulo**

```bash
php artisan test --filter=RevisionMedidores
```

Esperado: todos los tests del módulo pasan.

- [ ] **Step 12.3: Correr los tests existentes para verificar que no regresen**

```bash
php artisan test
```

Esperado: ningún test previamente verde se rompió.

- [ ] **Step 12.4: Commit final**

```bash
git add -A
git commit -m "style: aplicar pint al módulo de revisión de medidores"
```

---

## Task 13: Queue Infrastructure + ZIP Job + Notification

**Files:**
- Create: `app/Jobs/GenerarZipReportesRevisionMedidoresJob.php`
- Create: `app/Notifications/ZipReportesListoNotification.php`
- Create: `tests/Feature/RevisionMedidores/ZipReportesJobTest.php`
- DB: tabla `notifications` (nueva tabla, no toca datos existentes — seguro en producción)
- DB: tabla `jobs` si `QUEUE_CONNECTION=database` (también nueva)

- [ ] **Step 13.1: Verificar e instalar tablas de queue y notifications**

```bash
php artisan tinker --execute 'echo Schema::hasTable("notifications") ? "exists" : "missing";'
```

Si devuelve `missing`:
```bash
php artisan notifications:table
php artisan migrate
```

```bash
php artisan tinker --execute 'echo Schema::hasTable("jobs") ? "exists" : "missing";'
```

Si devuelve `missing`:
```bash
php artisan queue:table
php artisan migrate
```

- [ ] **Step 13.2: Verificar QUEUE_CONNECTION en .env**

Abrir `.env`. Si tiene `QUEUE_CONNECTION=sync`, cambiarlo a `database`:
```
QUEUE_CONNECTION=database
```

Para producción con Redis (opcional):
```
QUEUE_CONNECTION=redis
```

**Nota:** Con `sync` el job se ejecuta inmediatamente (no en background). Para testing funciona igual. En producción se necesita `php artisan queue:work` corriendo como proceso daemon.

- [ ] **Step 13.3: Crear el test**

```bash
php artisan make:test --pest RevisionMedidores/ZipReportesJobTest
```

```php
<?php

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Jobs\GenerarZipReportesRevisionMedidoresJob;
use App\Models\Company;
use App\Models\OrdenJob;
use App\Models\User;
use App\Notifications\ZipReportesListoNotification;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionsSeeder::class);
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->assignRole('Coordinador');
});

it('el job se puede despachar con ids de órdenes', function () {
    Queue::fake();

    $ordenes = OrdenJob::factory()->count(3)->create([
        'tipo_servicio' => TipoServicio::RevisionMedidores->value,
        'estado'        => EstadoOrden::Completada->value,
        'company_id'    => $this->company->id,
    ]);

    GenerarZipReportesRevisionMedidoresJob::dispatch(
        $ordenes->pluck('id')->toArray(),
        $this->user->id
    );

    Queue::assertPushed(GenerarZipReportesRevisionMedidoresJob::class, function ($job) {
        return count($job->ordenIds) === 3;
    });
});

it('la notificación ZipReportesListo usa canales database y mail', function () {
    $notif = new ZipReportesListoNotification(5, 'https://example.com/zip');
    expect($notif->via($this->user))->toBe(['database', 'mail']);
});

it('la notificación tiene el asunto correcto en mail', function () {
    $notif = new ZipReportesListoNotification(3, 'https://example.com/zip');
    $mail = $notif->toMail($this->user);
    expect($mail->subject)->toContain('ZIP de Reportes Listo');
});

it('la notificación almacena tipo y url en database', function () {
    $notif = new ZipReportesListoNotification(2, 'https://example.com/zip');
    $data = $notif->toDatabase($this->user);
    expect($data['type'])->toBe('zip_reportes_listo')
        ->and($data['url'])->toBe('https://example.com/zip');
});
```

- [ ] **Step 13.4: Correr test para verificar que falla**

```bash
php artisan test --filter=ZipReportesJobTest
```

Esperado: FAIL — clases no existen.

- [ ] **Step 13.5: Crear el Job**

```bash
php artisan make:job GenerarZipReportesRevisionMedidoresJob
```

Contenido de `app/Jobs/GenerarZipReportesRevisionMedidoresJob.php`:

```php
<?php

namespace App\Jobs;

use App\Models\OrdenJob;
use App\Models\User;
use App\Notifications\ZipReportesListoNotification;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use ZipArchive;

class GenerarZipReportesRevisionMedidoresJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(
        public readonly array $ordenIds,
        private readonly string $userId,
    ) {}

    public function handle(): void
    {
        ini_set('memory_limit', '512M');

        $ordenes = OrdenJob::whereIn('id', $this->ordenIds)
            ->with(['personal', 'creadoPor', 'reportes' => fn ($q) => $q->latest()->with([
                'tipoImposibilidad',
                'tipoRegulador',
                'marcaMedidor',
                'marcaRegulador',
                'tipoNotificacionAlerta',
                'auditoria.auditadoPor.personal',
            ])])
            ->get();

        $zipFilename = 'zips/revision-medidores-' . Str::uuid() . '.zip';
        $zipFullPath = storage_path('app/private/' . $zipFilename);

        if (! is_dir(dirname($zipFullPath))) {
            mkdir(dirname($zipFullPath), 0755, true);
        }

        $zip = new ZipArchive;
        $zip->open($zipFullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $firmante = User::find($this->userId);
        $logoProderi = $this->fileToBase64(public_path('assets/image/LOGO PRODERI CON SLOGAN.png'));
        $objetivos = $this->resolveObjetivos();

        foreach ($ordenes as $orden) {
            $reporte = $orden->reportes->first();
            if (! $reporte) {
                continue;
            }

            $anomalias = collect($reporte->anomalia ?? [])
                ->map(fn (string|int $id) => \App\Models\TipoAnomalia::find($id)?->nombre)
                ->filter()
                ->implode(', ');

            $firma = [
                'nombre' => $firmante?->fullName() ?? '—',
                'cargo'  => $firmante?->personal?->cargo ?? '—',
                'email'  => $firmante?->email ?? '—',
                'ip'     => '—',
                'agente' => 'Descarga masiva programada',
                'fecha'  => now()->format('d/m/Y H:i:s'),
            ];

            $pdf = Pdf::loadView('pdf.reporte-revision-medidores', [
                'orden'           => $orden,
                'reporte'         => $reporte,
                'anomalias'       => $anomalias,
                'logoProderi'     => $logoProderi,
                'imagenes'        => $this->resolveImagenes($reporte->imagenes ?? []),
                'fechaGeneracion' => now()->format('d-m-Y'),
                'firma'           => $firma,
                'objetivos'       => $objetivos,
            ]);

            $pdf->setPaper('A4', 'portrait');

            $zip->addFromString("reporte-{$orden->numero_orden}.pdf", $pdf->output());
        }

        $zip->close();

        $url = URL::temporarySignedRoute(
            'operaciones.revision-medidores.descargar-zip',
            now()->addHours(24),
            ['path' => $zipFilename]
        );

        User::find($this->userId)?->notify(new ZipReportesListoNotification(count($this->ordenIds), $url));
    }

    private function fileToBase64(string $path): string
    {
        if (! file_exists($path)) {
            return '';
        }

        return 'data:image/png;base64,' . base64_encode(file_get_contents($path));
    }

    private function resolveObjetivos(): array
    {
        $json = file_get_contents(resource_path('data/objetivos-pdf.json'));
        $data = json_decode($json, true);

        return $data['revision_medidores'] ?? [];
    }

    private function resolveImagenes(array $imagenes): array
    {
        return collect($imagenes)->map(function (string $path): array {
            $fullPath = storage_path('app/private/' . $path);
            if (! file_exists($fullPath)) {
                return ['base64' => null, 'mime' => null];
            }
            $mime = mime_content_type($fullPath);

            return ['base64' => 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($fullPath)), 'mime' => $mime];
        })->toArray();
    }
}
```

- [ ] **Step 13.6: Crear la Notification**

```bash
php artisan make:notification ZipReportesListoNotification
```

Contenido de `app/Notifications/ZipReportesListoNotification.php`:

```php
<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ZipReportesListoNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly int $count,
        public readonly string $url,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('ZIP de Reportes Listo — Revisión de Medidores')
            ->greeting("¡Hola {$notifiable->name}!")
            ->line("Tu exportación de **{$this->count} reportes PDF** de Revisión de Medidores está lista.")
            ->action('Descargar ZIP', $this->url)
            ->line('Este enlace expira en **24 horas**. Descárgalo antes de que caduque.')
            ->salutation('Saludos, ' . config('app.name'));
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type'    => 'zip_reportes_listo',
            'message' => "Tu ZIP con {$this->count} reportes está listo para descargar.",
            'url'     => $this->url,
            'count'   => $this->count,
        ];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
```

- [ ] **Step 13.7: Correr el test**

```bash
php artisan test --filter=ZipReportesJobTest
```

Esperado: PASS 4 tests.

- [ ] **Step 13.8: Commit**

```bash
git add app/Jobs/GenerarZipReportesRevisionMedidoresJob.php \
        app/Notifications/ZipReportesListoNotification.php \
        tests/Feature/RevisionMedidores/ZipReportesJobTest.php
git commit -m "feat: agregar job y notificación para descarga masiva de PDFs en ZIP"
```

---

## Task 14: Bulk Download Action + Polling en RevisionMedidoresIndex

**Files:**
- Modify: `app/Livewire/Coordinador/RevisionMedidores/RevisionMedidoresIndex.php`
- Modify: `resources/views/livewire/coordinador/revision-medidores/revision-medidores-index.blade.php`
- Create: `tests/Feature/RevisionMedidores/DescargaMasivaTest.php`

- [ ] **Step 14.1: Crear el test**

```bash
php artisan make:test --pest RevisionMedidores/DescargaMasivaTest
```

```php
<?php

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Jobs\GenerarZipReportesRevisionMedidoresJob;
use App\Models\Company;
use App\Models\OrdenJob;
use App\Models\User;
use App\Notifications\ZipReportesListoNotification;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionsSeeder::class);
    $this->company = Company::factory()->create();
    $this->coordinador = User::factory()->create(['company_id' => $this->company->id]);
    $this->coordinador->assignRole('Coordinador');
});

it('bulk action descargar_pdfs despacha el job con los ids de órdenes completadas', function () {
    Bus::fake();

    $ordenes = OrdenJob::factory()->count(3)->create([
        'tipo_servicio' => TipoServicio::RevisionMedidores->value,
        'estado'        => EstadoOrden::Completada->value,
        'company_id'    => $this->company->id,
    ]);

    $this->actingAs($this->coordinador);

    Livewire::test(\App\Livewire\Coordinador\RevisionMedidores\RevisionMedidoresIndex::class)
        ->callBulkAction('descargar_pdfs', records: $ordenes->pluck('id')->toArray());

    Bus::assertDispatched(GenerarZipReportesRevisionMedidoresJob::class, function ($job) {
        return count($job->ordenIds) === 3;
    });
});

it('bulk action no despacha el job si ninguna orden está Completada', function () {
    Bus::fake();

    $ordenes = OrdenJob::factory()->count(2)->create([
        'tipo_servicio' => TipoServicio::RevisionMedidores->value,
        'estado'        => EstadoOrden::Pendiente->value,
        'company_id'    => $this->company->id,
    ]);

    $this->actingAs($this->coordinador);

    Livewire::test(\App\Livewire\Coordinador\RevisionMedidores\RevisionMedidoresIndex::class)
        ->callBulkAction('descargar_pdfs', records: $ordenes->pluck('id')->toArray());

    Bus::assertNotDispatched(GenerarZipReportesRevisionMedidoresJob::class);
});

it('checkZipNotificacion marca la notificación como leída y actualiza zipListo', function () {
    $url = URL::temporarySignedRoute(
        'operaciones.revision-medidores.descargar-zip',
        now()->addHours(24),
        ['path' => 'zips/test.zip']
    );

    $this->coordinador->notify(new ZipReportesListoNotification(5, $url));

    $this->actingAs($this->coordinador);

    Livewire::test(\App\Livewire\Coordinador\RevisionMedidores\RevisionMedidoresIndex::class)
        ->assertSet('zipListo', false)
        ->call('checkZipNotificacion')
        ->assertSet('zipListo', true);

    expect($this->coordinador->fresh()->unreadNotifications->count())->toBe(0);
});
```

- [ ] **Step 14.2: Correr test para verificar que falla**

```bash
php artisan test --filter=DescargaMasivaTest
```

Esperado: FAIL.

- [ ] **Step 14.3: Actualizar RevisionMedidoresIndex — propiedades, método de polling y bulk action**

En `app/Livewire/Coordinador/RevisionMedidores/RevisionMedidoresIndex.php`:

Agregar el use al inicio de los imports:
```php
use App\Jobs\GenerarZipReportesRevisionMedidoresJob;
```

Agregar las propiedades públicas antes del método `table()`:
```php
public bool $zipListo = false;
public string $zipUrl = '';
public string $zipMensaje = '';

public function checkZipNotificacion(): void
{
    $notification = auth()->user()
        ->unreadNotifications
        ->where('type', \App\Notifications\ZipReportesListoNotification::class)
        ->first();

    if ($notification) {
        $this->zipListo = true;
        $this->zipUrl = $notification->data['url'];
        $this->zipMensaje = $notification->data['message'];
        $notification->markAsRead();
    }
}
```

Dentro de `BulkActionGroup::make([...])` en el método `table()`, agregar **antes** de la acción `asignar_masivo`:
```php
BulkAction::make('descargar_pdfs')
    ->label('Descargar PDFs')
    ->icon('heroicon-o-archive-box-arrow-down')
    ->color('info')
    ->requiresConfirmation()
    ->modalHeading('Descargar PDFs masivamente')
    ->modalDescription('Se generará un archivo ZIP con los informes PDF de las órdenes completadas seleccionadas. Recibirás una notificación en el panel y por correo cuando esté listo.')
    ->modalSubmitActionLabel('Iniciar descarga')
    ->action(function (\Illuminate\Support\Collection $records): void {
        $completadas = $records->filter(
            fn (OrdenJob $o) => $o->estado === EstadoOrden::Completada
        );

        if ($completadas->isEmpty()) {
            $this->dispatch('notify', type: 'warning', message: 'Ninguna orden seleccionada tiene estado Completada.');

            return;
        }

        GenerarZipReportesRevisionMedidoresJob::dispatch(
            $completadas->pluck('id')->toArray(),
            auth()->id()
        );

        $this->dispatch('notify', type: 'info', message: "Generando ZIP para {$completadas->count()} reportes. Te notificaremos cuando esté listo.");
    })
    ->deselectRecordsAfterCompletion(),
```

- [ ] **Step 14.4: Actualizar la vista para agregar polling y banner de ZIP**

Reemplazar el contenido de `resources/views/livewire/coordinador/revision-medidores/revision-medidores-index.blade.php`:

```blade
<div wire:poll.5s="checkZipNotificacion">
    @if ($zipListo)
        <div class="mb-4 mx-4 mt-4 flex items-center gap-3 rounded-xl border border-green-200 bg-green-50 p-4">
            <div class="flex-1">
                <p class="text-sm font-medium text-green-800">{{ $zipMensaje }}</p>
                <p class="text-xs text-green-600 mt-0.5">El enlace expira en 24 horas.</p>
            </div>
            <a href="{{ $zipUrl }}"
               class="inline-flex items-center gap-1.5 rounded-lg bg-green-700 px-4 py-2 text-sm font-medium text-white hover:bg-green-800">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                Descargar ZIP
            </a>
            <button wire:click="$set('zipListo', false)" class="text-green-500 hover:text-green-700">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    @endif

    {{ $this->table }}
</div>
```

- [ ] **Step 14.5: Correr el test**

```bash
php artisan test --filter=DescargaMasivaTest
```

Esperado: PASS 3 tests.

- [ ] **Step 14.6: Commit**

```bash
git add app/Livewire/Coordinador/RevisionMedidores/RevisionMedidoresIndex.php \
        resources/views/livewire/coordinador/revision-medidores/revision-medidores-index.blade.php \
        tests/Feature/RevisionMedidores/DescargaMasivaTest.php
git commit -m "feat: agregar descarga masiva de PDFs en ZIP con polling en tiempo real"
```

---

## Task 15: Ruta de Descarga Firmada + Método Controller

**Files:**
- Modify: `routes/revision-medidores.php`
- Modify: `app/Http/Controllers/ReportePdfController.php`
- Create: `tests/Feature/RevisionMedidores/ZipDescargaRutaTest.php`

- [ ] **Step 15.1: Crear el test**

```bash
php artisan make:test --pest RevisionMedidores/ZipDescargaRutaTest
```

```php
<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionsSeeder::class);
    $company = Company::factory()->create();
    $this->user = User::factory()->create(['company_id' => $company->id]);
    $this->user->assignRole('Coordinador');
});

it('descarga el ZIP con URL firmada válida', function () {
    $zipPath = 'zips/test-' . Str::uuid() . '.zip';
    $fullPath = storage_path('app/private/' . $zipPath);

    if (! is_dir(dirname($fullPath))) {
        mkdir(dirname($fullPath), 0755, true);
    }
    file_put_contents($fullPath, 'fake zip content');

    $url = URL::temporarySignedRoute(
        'operaciones.revision-medidores.descargar-zip',
        now()->addHours(24),
        ['path' => $zipPath]
    );

    $this->actingAs($this->user)
        ->get($url)
        ->assertOk()
        ->assertHeader('content-disposition');
});

it('retorna 403 si la firma es inválida', function () {
    $url = route('operaciones.revision-medidores.descargar-zip', ['path' => 'zips/fake.zip']);

    $this->actingAs($this->user)
        ->get($url)
        ->assertForbidden();
});

it('retorna 404 si el archivo ZIP no existe en storage', function () {
    $url = URL::temporarySignedRoute(
        'operaciones.revision-medidores.descargar-zip',
        now()->addHours(24),
        ['path' => 'zips/archivo-que-no-existe.zip']
    );

    $this->actingAs($this->user)
        ->get($url)
        ->assertNotFound();
});
```

- [ ] **Step 15.2: Correr test para verificar que falla**

```bash
php artisan test --filter=ZipDescargaRutaTest
```

Esperado: FAIL — ruta no existe.

- [ ] **Step 15.3: Agregar la ruta firmada**

En `routes/revision-medidores.php`, dentro del grupo principal (fuera del subgrupo `ordenes.crear`), agregar:

```php
Route::get('descargar-zip', [ReportePdfController::class, 'descargarZipRevisionMedidores'])
    ->name('descargar-zip')
    ->middleware('signed');
```

El archivo completo quedaría así:

```php
<?php

use App\Http\Controllers\ReportePdfController;
use App\Http\Controllers\RevisionMedidoresPlantillaController;
use App\Livewire\Coordinador\RevisionMedidores\ImportarRevisionMedidores;
use App\Livewire\Coordinador\RevisionMedidores\RevisionMedidoresIndex;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:ordenes.ver'])
    ->prefix('operaciones/revision-medidores')
    ->name('operaciones.revision-medidores.')
    ->group(function () {
        Route::get('/', RevisionMedidoresIndex::class)->name('index');

        Route::middleware('permission:ordenes.crear')->group(function () {
            Route::get('importar', ImportarRevisionMedidores::class)->name('importar');
            Route::get('plantilla', [RevisionMedidoresPlantillaController::class, 'download'])->name('plantilla');
        });

        Route::get('{orden}/reporte', [ReportePdfController::class, 'DownLoadInformeRevisionMedidores'])
            ->middleware('permission:informes')
            ->name('reporte');

        Route::get('descargar-zip', [ReportePdfController::class, 'descargarZipRevisionMedidores'])
            ->name('descargar-zip')
            ->middleware('signed');
    });
```

- [ ] **Step 15.4: Agregar el método al ReportePdfController**

En `app/Http/Controllers/ReportePdfController.php`, agregar antes del método `fileToBase64`:

```php
public function descargarZipRevisionMedidores(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
{
    abort_unless($request->hasValidSignature(), 403);

    $zipPath = $request->query('path');
    $fullPath = storage_path('app/private/' . $zipPath);

    abort_unless(file_exists($fullPath), 404);

    return response()
        ->download($fullPath, 'reportes-revision-medidores.zip')
        ->deleteFileAfterSend(true);
}
```

- [ ] **Step 15.5: Correr el test**

```bash
php artisan test --filter=ZipDescargaRutaTest
```

Esperado: PASS 3 tests.

- [ ] **Step 15.6: Commit**

```bash
git add routes/revision-medidores.php \
        app/Http/Controllers/ReportePdfController.php \
        tests/Feature/RevisionMedidores/ZipDescargaRutaTest.php
git commit -m "feat: agregar ruta de descarga firmada para ZIP de revisión de medidores"
```

---

## Resumen de Archivos por Task

| Task | Nuevos | Modificados |
|---|---|---|
| 1 — Enum | `EnumRevisionMedidoresTest.php` | `TipoServicio.php` |
| 2 — Parchear existentes | `ComponentesExistentesTest.php`, `objetivos-pdf.json` | `ReporteCreate.php`, `ReporteEdit.php`, `OrdenCreate.php`, `OrdenesTrabajoIndex.php` |
| 3 — Excel | — | `composer.json`, `config/excel.php` |
| 4 — Plantilla | `RevisionMedidoresPlantillaExport.php`, `RevisionMedidoresPlantillaController.php`, `revision-medidores.php`, `PlantillaExportTest.php` | `web.php` |
| 5 — Import class | `RevisionMedidoresImport.php`, `ImportTest.php` | — |
| 6 — Mailable | `OrdenesAsignadasResumen.php`, `ordenes-asignadas-resumen.blade.php`, `OrdenesAsignadasResumenTest.php` | — |
| 7 — ImportarComponente | `ImportarRevisionMedidores.php`, `importar-revision-medidores.blade.php`, `ImportarComponenteTest.php` | — |
| 8 — Index | `RevisionMedidoresIndex.php`, `revision-medidores-index.blade.php`, `IndexComponenteTest.php` | — |
| 9 — Rutas/Sidebar | `RutasSidebarTest.php` | `revision-medidores.php`, `sidebar.blade.php` |
| 10 — Auditoría | `AuditoriaTest.php` | — |
| 11 — PDF | `reporte-revision-medidores.blade.php`, `PdfInformeTest.php` | `ReportePdfController.php`, `OrdenesTrabajoIndex.php` |
| 13 — ZIP Job + Notification | `GenerarZipReportesRevisionMedidoresJob.php`, `ZipReportesListoNotification.php`, `ZipReportesJobTest.php` | — |
| 14 — Bulk Download + Polling | `DescargaMasivaTest.php` | `RevisionMedidoresIndex.php`, `revision-medidores-index.blade.php` |
| 15 — Ruta descarga firmada | `ZipDescargaRutaTest.php` | `revision-medidores.php`, `ReportePdfController.php` |
| 12 — Pint + Suite final | — | varios (format only) |
