# Catalog Tenancy Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Scope all 11 catalog tables to their owning company so each tenant manages their own catalog data independently.

**Architecture:** Add `company_id` to all 11 catalog migrations and models. Each model gets `CompanyScope` (already used by User and OrdenJob) plus a `creating` boot hook that auto-assigns `company_id` from `TenantService`. A new `CatalogDefaultsService` holds all default seed data and is called by a `CompanyObserver::created()` hook so every new company gets default catalogs automatically. The existing catalog seeders are replaced by a single call to this service.

**Tech Stack:** Laravel 13, Eloquent global scopes, model observers, `App\Models\Scopes\CompanyScope`, `App\Services\TenantService`

---

## File Map

| File | Action | Purpose |
|------|--------|---------|
| `database/migrations/2024_03_22_000001_create_tipos_anomalia_table.php` | Modify | Add `company_id` |
| `database/migrations/2024_03_22_000002_create_tipos_comercio_table.php` | Modify | Add `company_id` |
| `database/migrations/2024_03_22_000003_create_tipos_imposibilidad_table.php` | Modify | Add `company_id` |
| `database/migrations/2024_03_22_000004_create_tipos_regulador_table.php` | Modify | Add `company_id` |
| `database/migrations/2024_03_22_000005_create_marcas_medidor_table.php` | Modify | Add `company_id` |
| `database/migrations/2024_03_22_000005_create_tipos_notificacion_alerta_table.php` | Modify | Add `company_id` |
| `database/migrations/2024_03_22_000006_create_marcas_regulador_table.php` | Modify | Add `company_id` |
| `database/migrations/2024_03_22_000006_create_correos_alerta_table.php` | Modify | Add `company_id` |
| `database/migrations/2024_03_22_000007_create_convenciones_table.php` | Modify | Add `company_id` |
| `database/migrations/2024_03_22_000008_create_tipologias_orden_table.php` | Modify | Add `company_id` |
| `database/migrations/2024_03_22_000009_create_resultados_reporte_table.php` | Modify | Add `company_id` |
| `app/Models/TipoAnomalia.php` | Modify | CompanyScope + creating hook |
| `app/Models/TipoComercio.php` | Modify | CompanyScope + creating hook |
| `app/Models/TipoImposibilidad.php` | Modify | CompanyScope + creating hook |
| `app/Models/TipoRegulador.php` | Modify | CompanyScope + creating hook |
| `app/Models/MarcaMedidor.php` | Modify | CompanyScope + creating hook |
| `app/Models/MarcaRegulador.php` | Modify | CompanyScope + creating hook |
| `app/Models/Convencion.php` | Modify | CompanyScope + creating hook |
| `app/Models/TipologiaOrden.php` | Modify | CompanyScope + creating hook |
| `app/Models/ResultadoReporte.php` | Modify | CompanyScope + creating hook |
| `app/Models/TipoNotificacionAlerta.php` | Modify | CompanyScope + creating hook |
| `app/Models/CorreoAlerta.php` | Modify | CompanyScope + creating hook |
| `app/Services/CatalogDefaultsService.php` | **Create** | All default catalog data + `seedForCompany()` |
| `app/Observers/CompanyObserver.php` | **Create** | Calls service on company created |
| `app/Providers/AppServiceProvider.php` | Modify | Register CompanyObserver |
| `database/seeders/DatabaseSeeder.php` | Modify | Remove individual catalog seeders; Company seeds catalogs via observer |
| `database/seeders/CatalogosSeeder.php` | Delete or empty | Replaced by CatalogDefaultsService |
| `database/seeders/TipoNotificacionAlertaSeeder.php` | Delete or empty | Replaced by CatalogDefaultsService |
| `database/seeders/ConvencionSeeder.php` | Delete or empty | Replaced by CatalogDefaultsService |
| `database/seeders/TipologiaOrdenSeeder.php` | Delete or empty | Replaced by CatalogDefaultsService |
| `database/seeders/ResultadoReporteSeeder.php` | Delete or empty | Replaced by CatalogDefaultsService |
| `tests/Feature/Multitenancy/CatalogTenancyTest.php` | **Create** | Verify isolation between companies |

---

### Task 1: Edit all 11 catalog migrations to add company_id

**Files:**
- Modify: all 11 `database/migrations/2024_03_22_*.php` files listed above

The pattern is the same for every migration. Add `$table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();` **after the last data column, before softDeletes/timestamps**. The `cascadeOnDelete` means deleting a company also deletes its catalog data.

- [ ] **Step 1: Edit each migration**

For every migration, add the `company_id` line. Here is the complete updated version for each:

`2024_03_22_000001_create_tipos_anomalia_table.php`:
```php
Schema::create('tipos_anomalia', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('nombre');
    $table->string('nomenclatura')->nullable();
    $table->boolean('activo')->default(true);
    $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
    $table->softDeletes();
    $table->timestamps();
});
```

`2024_03_22_000002_create_tipos_comercio_table.php` (same structure, table `tipos_comercio`):
```php
Schema::create('tipos_comercio', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('nombre');
    $table->string('nomenclatura')->nullable();
    $table->boolean('activo')->default(true);
    $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
    $table->softDeletes();
    $table->timestamps();
});
```

`2024_03_22_000003_create_tipos_imposibilidad_table.php`:
```php
Schema::create('tipos_imposibilidad', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('nombre');
    $table->string('nomenclatura')->nullable();
    $table->boolean('activo')->default(true);
    $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
    $table->softDeletes();
    $table->timestamps();
});
```

`2024_03_22_000004_create_tipos_regulador_table.php`:
```php
Schema::create('tipos_regulador', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('nombre');
    $table->string('nomenclatura')->nullable();
    $table->boolean('activo')->default(true);
    $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
    $table->softDeletes();
    $table->timestamps();
});
```

`2024_03_22_000005_create_marcas_medidor_table.php` — check what columns it has first, then add company_id the same way.

`2024_03_22_000005_create_tipos_notificacion_alerta_table.php`:
```php
Schema::create('tipos_notificacion_alerta', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('nombre');
    $table->boolean('activo')->default(true);
    $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
    $table->softDeletes();
    $table->timestamps();
});
```

`2024_03_22_000006_create_marcas_regulador_table.php` — check structure, add company_id the same way.

`2024_03_22_000006_create_correos_alerta_table.php`:
```php
Schema::create('correos_alerta', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('nombre');
    $table->string('email');
    $table->boolean('activo')->default(true);
    $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
    $table->timestamps();
});
```

`2024_03_22_000007_create_convenciones_table.php` — check structure, add company_id. Note: Convencion only has `nombre` and `activo`, no `nomenclatura`.

`2024_03_22_000008_create_tipologias_orden_table.php` — same, check structure.

`2024_03_22_000009_create_resultados_reporte_table.php` — same, check structure.

- [ ] **Step 2: Verify migrations parse**

```bash
php artisan migrate:fresh --seed 2>&1 | head -20
```
Expected: should fail (seeders not yet updated) — but migrations should run without error.

---

### Task 2: Update all 11 catalog models

**Files:** All 11 model files listed in File Map

The pattern is identical for every model. Apply it to all 11.

- [ ] **Step 1: Write failing test**

Create `tests/Feature/Multitenancy/CatalogTenancyTest.php`:
```php
<?php

use App\Models\Company;
use App\Models\TipoAnomalia;
use App\Services\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('catalog items are scoped to their company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    TipoAnomalia::withoutGlobalScopes()->create([
        'nombre' => 'Anomalia A',
        'nomenclatura' => 'AA',
        'activo' => true,
        'company_id' => $companyA->id,
    ]);

    app(TenantService::class)->set($companyB);

    expect(TipoAnomalia::count())->toBe(0);
});

test('catalog items are auto-assigned company_id from tenant context', function () {
    $company = Company::factory()->create();
    app(TenantService::class)->set($company);

    TipoAnomalia::create(['nombre' => 'Test', 'nomenclatura' => 'T', 'activo' => true]);

    expect(TipoAnomalia::first()->company_id)->toBe($company->id);
});
```

- [ ] **Step 2: Run test — expect FAIL**

```bash
php artisan test --compact --filter=CatalogTenancy
```
Expected: FAIL — `company_id column not found` or similar.

- [ ] **Step 3: Apply the model pattern to all 11 models**

For every model, add `company_id` to `$fillable`, add `use` statements, and add `booted()`. The pattern for `TipoAnomalia`:

```php
<?php

namespace App\Models;

use App\Models\Scopes\CompanyScope;
use App\Services\TenantService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TipoAnomalia extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'tipos_anomalia';

    protected $fillable = ['nombre', 'nomenclatura', 'activo', 'company_id'];

    protected $casts = ['activo' => 'boolean'];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function (self $model): void {
            if (empty($model->company_id)) {
                $model->company_id = app(TenantService::class)->id();
            }
        });
    }
}
```

Apply the **exact same pattern** to:
- `TipoComercio` (table: `tipos_comercio`, fillable includes `nomenclatura`)
- `TipoImposibilidad` (table: `tipos_imposibilidad`, fillable includes `nomenclatura`)
- `TipoRegulador` (table: `tipos_regulador`, fillable includes `nomenclatura`)
- `MarcaMedidor` (table: `marcas_medidor`, fillable includes `nomenclatura`)
- `MarcaRegulador` (table: `marcas_regulador`, fillable includes `nomenclatura`)
- `Convencion` (table: `convenciones`, NO `nomenclatura` in fillable)
- `TipologiaOrden` (table: `tipologias_orden`, NO `nomenclatura`)
- `ResultadoReporte` (table: `resultados_reporte`, NO `nomenclatura`)
- `TipoNotificacionAlerta` (table: `tipos_notificacion_alerta`, NO `nomenclatura`)
- `CorreoAlerta` (table: `correos_alerta`, fillable: `['nombre', 'email', 'activo', 'company_id']`, NO SoftDeletes, NO `nomenclatura`)

Note: `CorreoAlerta` does NOT use `SoftDeletes` — keep it without.

- [ ] **Step 4: Run migrate:fresh (no seed yet)**

```bash
php artisan migrate:fresh
```
Expected: all migrations run successfully.

- [ ] **Step 5: Run the failing test — expect FAIL still (no seed data)**

```bash
php artisan test --compact --filter=CatalogTenancy
```
Expected: FAIL because `Company::factory()` doesn't exist yet or tables don't have data — we'll make it pass in Task 5 after seeds are fixed.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/ app/Models/
git commit -m "feat: add company_id to all catalog models and migrations"
```

---

### Task 3: Create CatalogDefaultsService

**Files:**
- Create: `app/Services/CatalogDefaultsService.php`

This service centralises all default catalog data. It replaces the 5 individual seeders.

- [ ] **Step 1: Create the service**

```php
<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Convencion;
use App\Models\MarcaMedidor;
use App\Models\MarcaRegulador;
use App\Models\ResultadoReporte;
use App\Models\TipoAnomalia;
use App\Models\TipoComercio;
use App\Models\TipoImposibilidad;
use App\Models\TipoNotificacionAlerta;
use App\Models\TipoRegulador;
use App\Models\TipologiaOrden;

class CatalogDefaultsService
{
    public function seedForCompany(Company $company): void
    {
        $id = $company->id;

        $this->seedMany(TipoAnomalia::class, $id, [
            ['nombre' => 'Sin Anomalias',                          'nomenclatura' => 'NA'],
            ['nombre' => 'Bypass',                                 'nomenclatura' => 'BY'],
            ['nombre' => 'Medidor con sellos manipulados',         'nomenclatura' => 'MCSM'],
            ['nombre' => 'Medidor con digitos desalineados',       'nomenclatura' => 'MCDD'],
            ['nombre' => 'Medidor sin talco',                      'nomenclatura' => 'MST'],
            ['nombre' => 'Medidor enterrado',                      'nomenclatura' => 'ME'],
            ['nombre' => 'Conexión directa',                       'nomenclatura' => 'CD'],
            ['nombre' => 'Medidor frenado',                        'nomenclatura' => 'MF'],
            ['nombre' => 'Medidor gira hacia atrás',               'nomenclatura' => 'MGHA'],
            ['nombre' => 'Medidor No encontrado',                  'nomenclatura' => 'MNE'],
            ['nombre' => 'Medidor No Concuerda con el contrato',   'nomenclatura' => 'MNC'],
            ['nombre' => 'Medidor trocado',                        'nomenclatura' => 'MT'],
            ['nombre' => 'Inactivo y en Consumo',                  'nomenclatura' => 'IC'],
            ['nombre' => 'Inspeccion y revision',                  'nomenclatura' => 'IR'],
            ['nombre' => 'Posible fuga',                           'nomenclatura' => 'PF'],
            ['nombre' => 'Pendiente de Retiro / Revision',         'nomenclatura' => 'PR'],
            ['nombre' => 'Medidor con doble local Comercial',      'nomenclatura' => 'MDL'],
        ]);

        $this->seedMany(TipoComercio::class, $id, [
            ['nombre' => 'Restaurante',                            'nomenclatura' => 'RE'],
            ['nombre' => 'Venta de Comidas Rapidas',               'nomenclatura' => 'VCR'],
            ['nombre' => 'Panaderia',                              'nomenclatura' => 'PA'],
            ['nombre' => 'Cafeteria',                              'nomenclatura' => 'CA'],
            ['nombre' => 'Bar / Discoteca',                        'nomenclatura' => 'BA'],
            ['nombre' => 'Lavanderia',                             'nomenclatura' => 'LA'],
            ['nombre' => 'Venta de Fritos',                        'nomenclatura' => 'VF'],
            ['nombre' => 'Asadero de Pollos',                      'nomenclatura' => 'AP'],
            ['nombre' => 'Industria',                              'nomenclatura' => 'IN'],
            ['nombre' => 'Productor de Quesos / leche',            'nomenclatura' => 'PQL'],
            ['nombre' => 'Hotel/Motel',                            'nomenclatura' => 'HM'],
            ['nombre' => 'Centro recreacional',                    'nomenclatura' => 'CR'],
            ['nombre' => 'Institucion educativa',                  'nomenclatura' => 'IE'],
            ['nombre' => 'Centro Comercial',                       'nomenclatura' => 'CC'],
            ['nombre' => 'Heladeria',                              'nomenclatura' => 'HE'],
            ['nombre' => 'Bodegas',                                'nomenclatura' => 'BO'],
            ['nombre' => 'Inmueble Desocupado / En Arriendo',      'nomenclatura' => 'IM'],
            ['nombre' => 'Tienda de Abarrotes',                    'nomenclatura' => 'TA'],
            ['nombre' => 'Ferreteria',                             'nomenclatura' => 'TF'],
            ['nombre' => 'Papeleria y Micelaneas',                 'nomenclatura' => 'TP'],
            ['nombre' => 'Tienda de Juguetes',                     'nomenclatura' => 'TJ'],
            ['nombre' => 'Veterinaria',                            'nomenclatura' => 'VE'],
            ['nombre' => 'Taller de Motos',                        'nomenclatura' => 'TM'],
            ['nombre' => 'Estacion de Servicio',                   'nomenclatura' => 'ES'],
            ['nombre' => 'Hospital / Clinica / IPS / Consultorio', 'nomenclatura' => 'HC'],
            ['nombre' => 'Terreno Baldio',                         'nomenclatura' => 'TB'],
            ['nombre' => 'Locales Comerciales',                    'nomenclatura' => 'LC'],
            ['nombre' => 'Drogueria',                              'nomenclatura' => 'DR'],
            ['nombre' => 'Edificio / En construccion',             'nomenclatura' => 'EC'],
            ['nombre' => 'Base Naval / Base Militar',              'nomenclatura' => 'BM'],
            ['nombre' => 'Comercio no encontrado',                 'nomenclatura' => 'CNE'],
        ]);

        $this->seedMany(TipoImposibilidad::class, $id, [
            ['nombre' => 'Ninguna',                                    'nomenclatura' => 'NI'],
            ['nombre' => 'Obstaculos (Poca Visibilidad)',               'nomenclatura' => 'OB'],
            ['nombre' => 'Falta de Acceso (Rejas, Cerraduras, Etc)',   'nomenclatura' => 'FA'],
            ['nombre' => 'Falta de Medidor',                           'nomenclatura' => 'FM'],
            ['nombre' => 'Usuario No Permite Lectura',                 'nomenclatura' => 'UNP'],
            ['nombre' => 'Lugar Deshabitado',                          'nomenclatura' => 'LD'],
        ]);

        $this->seedMany(TipoRegulador::class, $id, [
            ['nombre' => 'Baja presion',  'nomenclatura' => 'BP'],
            ['nombre' => 'Media presion', 'nomenclatura' => 'MP'],
            ['nombre' => 'Alta presion',  'nomenclatura' => 'AP'],
        ]);

        $this->seedMany(MarcaMedidor::class, $id, [
            ['nombre' => 'DAESUNG',              'nomenclatura' => 'DA'],
            ['nombre' => 'YAO',                  'nomenclatura' => 'YA'],
            ['nombre' => 'YAZAKI',               'nomenclatura' => 'YZ'],
            ['nombre' => 'GOLDSTAR',             'nomenclatura' => 'GO'],
            ['nombre' => 'SINGER',               'nomenclatura' => 'SI'],
            ['nombre' => 'RICOH',                'nomenclatura' => 'RI'],
            ['nombre' => 'KROHNSCHODER',         'nomenclatura' => 'KR'],
            ['nombre' => 'AMERICAN METER',       'nomenclatura' => 'AM'],
            ['nombre' => 'ROCKWELL',             'nomenclatura' => 'RO'],
            ['nombre' => 'KEUK',                 'nomenclatura' => 'KE'],
            ['nombre' => 'INVENSYS - SENSUS',    'nomenclatura' => 'IS'],
            ['nombre' => 'ABB ELSTER',           'nomenclatura' => 'AB'],
            ['nombre' => 'ROMET',                'nomenclatura' => 'RM'],
            ['nombre' => 'INSTROMET',            'nomenclatura' => 'IM'],
            ['nombre' => 'IM METER',             'nomenclatura' => 'IMM'],
            ['nombre' => 'ELSTER',               'nomenclatura' => 'EL'],
            ['nombre' => 'GALLUS',               'nomenclatura' => 'GA'],
            ['nombre' => 'EQUIMETER',            'nomenclatura' => 'EQ'],
            ['nombre' => 'ITRON',                'nomenclatura' => 'IT'],
            ['nombre' => 'ACTARIS',              'nomenclatura' => 'AC'],
            ['nombre' => 'WILSON',               'nomenclatura' => 'WI'],
            ['nombre' => 'KEUKDONG',             'nomenclatura' => 'KD'],
            ['nombre' => 'METREX',               'nomenclatura' => 'ME'],
            ['nombre' => 'SHINHAN',              'nomenclatura' => 'SH'],
            ['nombre' => 'GENERICA',             'nomenclatura' => 'GE'],
            ['nombre' => 'SENSUS',               'nomenclatura' => 'SE'],
            ['nombre' => 'DRESSER',              'nomenclatura' => 'DR'],
            ['nombre' => 'DUMIN',                'nomenclatura' => 'DU'],
            ['nombre' => 'WITECH',               'nomenclatura' => 'WT'],
            ['nombre' => 'CHINT',                'nomenclatura' => 'CH'],
            ['nombre' => 'FMG',                  'nomenclatura' => 'FM'],
            ['nombre' => 'TANCY',                'nomenclatura' => 'TA'],
            ['nombre' => 'MEDIDOR FICTICIO (CAMBIO)', 'nomenclatura' => 'MF'],
        ]);

        $this->seedMany(MarcaRegulador::class, $id, [
            ['nombre' => 'G-1.6',    'nomenclatura' => 'G16'],
            ['nombre' => 'G-2.5',    'nomenclatura' => 'G25'],
            ['nombre' => 'G-4',      'nomenclatura' => 'G4'],
            ['nombre' => 'G-6',      'nomenclatura' => 'G6'],
            ['nombre' => 'G10',      'nomenclatura' => 'G10'],
            ['nombre' => 'G16',      'nomenclatura' => 'G16B'],
            ['nombre' => 'AL-425',   'nomenclatura' => 'AL425'],
            ['nombre' => 'AL-800',   'nomenclatura' => 'AL800'],
            ['nombre' => 'AL-1000',  'nomenclatura' => 'AL1000'],
            ['nombre' => 'AC-250',   'nomenclatura' => 'AC250'],
            ['nombre' => 'AC-630',   'nomenclatura' => 'AC630'],
            ['nombre' => 'MR-8',     'nomenclatura' => 'MR8'],
            ['nombre' => 'MR-10',    'nomenclatura' => 'MR10'],
            ['nombre' => 'MR-12',    'nomenclatura' => 'MR12'],
        ]);

        $this->seedMany(TipoNotificacionAlerta::class, $id, [
            ['nombre' => 'Sin Alertas'],
            ['nombre' => 'Exceso de Capacidad'],
            ['nombre' => 'Retro Flujo'],
            ['nombre' => 'Flujo cero'],
            ['nombre' => 'Posible fuga interna'],
            ['nombre' => 'Fuga Pequeña Const 7 Días'],
            ['nombre' => 'Batería Baja'],
            ['nombre' => 'CAU 01'],
            ['nombre' => 'CAU 02'],
            ['nombre' => 'CAU 03'],
            ['nombre' => 'CAU 04'],
        ]);

        $this->seedMany(Convencion::class, $id, [
            ['nombre' => 'Seguimiento de Sospechoso'],
            ['nombre' => 'Vigilancia de Punto Fijo'],
            ['nombre' => 'Inteligencia de Fuentes Abiertas'],
            ['nombre' => 'Entrevista a Testigo'],
            ['nombre' => 'Revisión Documental'],
            ['nombre' => 'Verificación en Campo'],
            ['nombre' => 'Operación Encubierta'],
            ['nombre' => 'Coordinación con Autoridades'],
        ]);

        $this->seedMany(TipologiaOrden::class, $id, [
            ['nombre' => 'Entrevistas'],
            ['nombre' => 'Impulsos en Fiscalía'],
            ['nombre' => 'Capturas en Flagrancia'],
            ['nombre' => 'Allanamientos'],
            ['nombre' => 'Desarticulación de Red'],
            ['nombre' => 'Operación Conjunta'],
            ['nombre' => 'Orden Judicial'],
        ]);

        $this->seedMany(ResultadoReporte::class, $id, [
            ['nombre' => 'Positivo'],
            ['nombre' => 'Negativo'],
            ['nombre' => 'Pendiente'],
            ['nombre' => 'Sin resultado'],
        ]);
    }

    /** @param class-string $model @param array<int, array<string, mixed>> $rows */
    private function seedMany(string $model, string $companyId, array $rows): void
    {
        foreach ($rows as $row) {
            $model::withoutGlobalScopes()->create(array_merge($row, [
                'company_id' => $companyId,
                'activo' => $row['activo'] ?? true,
            ]));
        }
    }
}
```

> **Note:** Check the current `ResultadoReporteSeeder` to verify the exact result names before writing them in the service — they may differ from the placeholder values above. Read `database/seeders/ResultadoReporteSeeder.php` first.

- [ ] **Step 2: Commit**

```bash
git add app/Services/CatalogDefaultsService.php
git commit -m "feat: add CatalogDefaultsService with default catalog data per company"
```

---

### Task 4: Create CompanyObserver and register it

**Files:**
- Create: `app/Observers/CompanyObserver.php`
- Modify: `app/Providers/AppServiceProvider.php`

- [ ] **Step 1: Write the failing test for observer behavior**

Add to `tests/Feature/Multitenancy/CatalogTenancyTest.php`:
```php
test('creating a company auto-seeds default catalogs', function () {
    $company = Company::factory()->create();

    expect(
        \App\Models\TipoAnomalia::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->count()
    )->toBeGreaterThan(0);

    expect(
        \App\Models\MarcaMedidor::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->count()
    )->toBeGreaterThan(0);
});
```

- [ ] **Step 2: Run — expect FAIL**

```bash
php artisan test --compact --filter="creating a company"
```
Expected: FAIL — count is 0 because observer doesn't exist yet.

- [ ] **Step 3: Create the observer**

```php
<?php

namespace App\Observers;

use App\Models\Company;
use App\Services\CatalogDefaultsService;

class CompanyObserver
{
    public function __construct(private readonly CatalogDefaultsService $catalogDefaults) {}

    public function created(Company $company): void
    {
        $this->catalogDefaults->seedForCompany($company);
    }
}
```

- [ ] **Step 4: Register observer in AppServiceProvider**

Read `app/Providers/AppServiceProvider.php` first, then add:

```php
use App\Models\Company;
use App\Observers\CompanyObserver;

public function boot(): void
{
    Company::observe(CompanyObserver::class);
    // ... any existing boot code
}
```

- [ ] **Step 5: Run the test — expect PASS**

```bash
php artisan test --compact --filter="creating a company"
```
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Observers/CompanyObserver.php app/Providers/AppServiceProvider.php
git commit -m "feat: add CompanyObserver to auto-seed default catalogs on company creation"
```

---

### Task 5: Refactor seeders and DatabaseSeeder

**Files:**
- Modify: `database/seeders/DatabaseSeeder.php`
- Modify: `database/seeders/CatalogosSeeder.php`
- Modify: `database/seeders/TipoNotificacionAlertaSeeder.php`
- Modify: `database/seeders/ConvencionSeeder.php`
- Modify: `database/seeders/TipologiaOrdenSeeder.php`
- Modify: `database/seeders/ResultadoReporteSeeder.php`

Since `CompanyObserver::created()` now seeds all catalogs when a company is created, the individual catalog seeders are no longer needed from DatabaseSeeder. The `CompanySeeder` calls `Company::create()` which fires the observer, which calls `CatalogDefaultsService::seedForCompany()` automatically.

- [ ] **Step 1: Update DatabaseSeeder**

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermissionsSeeder::class);
        $this->call(CompanySeeder::class);  // observer seeds catalogs automatically
        $this->call(PersonalSeeder::class);
        $this->call(UserSeeder::class);
    }
}
```

- [ ] **Step 2: Empty out the old catalog seeders**

Replace the body of each seeder with a no-op (keep the class so nothing breaks if called elsewhere):

```php
// CatalogosSeeder.php, TipoNotificacionAlertaSeeder.php, ConvencionSeeder.php,
// TipologiaOrdenSeeder.php, ResultadoReporteSeeder.php — all become:

public function run(): void
{
    // Replaced by CatalogDefaultsService — catalogs are seeded per company via CompanyObserver.
}
```

- [ ] **Step 3: Run migrate:fresh --seed**

```bash
php artisan migrate:fresh --seed
```
Expected: all migrations run, CompanySeeder creates company, observer seeds all catalogs for that company, PersonalSeeder and UserSeeder run successfully.

- [ ] **Step 4: Verify catalog data exists in DB**

```bash
php artisan tinker --execute "echo \App\Models\TipoAnomalia::withoutGlobalScopes()->count();"
```
Expected: 17 (the number of anomalias in CatalogDefaultsService).

- [ ] **Step 5: Commit**

```bash
git add database/seeders/
git commit -m "feat: replace catalog seeders with CatalogDefaultsService via CompanyObserver"
```

---

### Task 6: Run full test suite and fix failures

**Files:** any that break

- [ ] **Step 1: Run all tests**

```bash
php artisan test --compact
```
Expected: all previous tests still pass. The 2 new catalog tests pass. Note any new failures.

- [ ] **Step 2: Fix any test failures**

If multitenancy tests fail because `Company::factory()->create()` now triggers catalog seeding and tests check exact counts, update the assertions. The `CatalogTenancyTest` should now pass fully.

- [ ] **Step 3: Run final test suite**

```bash
php artisan test --compact tests/Feature/Multitenancy/
```
Expected: all 21 tests pass (19 existing + 2 new).

- [ ] **Step 4: Run pint**

```bash
vendor/bin/pint --format agent
```

- [ ] **Step 5: Commit**

```bash
git add .
git commit -m "test: add catalog tenancy isolation tests"
```

---

## Self-Review

**Spec coverage:**
- ✅ 11 migrations updated with company_id
- ✅ 11 models get CompanyScope + creating hook
- ✅ CatalogDefaultsService holds all default data
- ✅ CompanyObserver fires on company create → seeds catalogs
- ✅ DatabaseSeeder cleaned up
- ✅ Tests verify isolation and auto-seeding

**Placeholder scan:**
- Task 3 Step 1 notes: "Check ResultadoReporteSeeder for exact names" — implementer must read that file before writing the service. Not a placeholder, it's a deliberate instruction.
- Task 1 Step 1 notes: "check structure" for marcas_medidor and marcas_regulador — implementer must read those migration files first.

**Type consistency:**
- `seedMany()` takes `string $model` (FQCN), `string $companyId`, `array $rows` — used consistently.
- `seedForCompany(Company $company)` matches `CompanyObserver::created(Company $company)` argument.
- `withoutGlobalScopes()` used in `seedMany()` to bypass the scope during seeding — correct because TenantService isn't active during seeder execution.
