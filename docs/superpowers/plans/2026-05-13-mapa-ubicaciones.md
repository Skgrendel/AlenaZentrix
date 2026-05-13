# Mapa de Ubicaciones — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Agregar un módulo de mapa interactivo al sidebar que permite a coordinadores y administradores visualizar con filtros las ubicaciones GPS desde donde los investigadores enviaron sus reportes de campo.

**Architecture:** Livewire 4 component `MapaIndex` gestiona los filtros y la consulta de marcadores; despacha el evento de browser `markers-updated` cada vez que cambia un filtro. Leaflet.js + MarkerCluster (CDN) se inicializan en `@script` al montar la página y escuchan ese evento para actualizar el mapa sin recargar. Acceso restringido por el permiso `mapa.ver` vía middleware Spatie Permission.

**Tech Stack:** Laravel 13, Livewire 4, Flux UI 2, Spatie Permission, Leaflet 1.9.4 (CDN), Leaflet.markercluster 1.5.3 (CDN), Pest 4.

---

### Task 1: Agregar `@stack` al layout para habilitar CSS/JS por página

**Files:**
- Modify: `resources/views/partials/head.blade.php`
- Modify: `resources/views/layouts/app/sidebar.blade.php`

- [ ] **Step 1: Agregar `@stack('styles')` al final de head.blade.php**

Abrir `resources/views/partials/head.blade.php`. Agregar al final del archivo:

```blade
@stack('styles')
```

El archivo completo quedará:

```blade
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@filamentStyles
@stack('styles')
```

- [ ] **Step 2: Agregar `@stack('scripts')` en sidebar.blade.php antes de `@fluxScripts`**

Abrir `resources/views/layouts/app/sidebar.blade.php`. Localizar el bloque final del body:

```blade
    @persist('toast')
        <flux:toast.group>
            <flux:toast />
        </flux:toast.group>
    @endpersist

    @fluxScripts
    @filamentScripts
</body>
```

Reemplazarlo por:

```blade
    @persist('toast')
        <flux:toast.group>
            <flux:toast />
        </flux:toast.group>
    @endpersist

    @stack('scripts')
    @fluxScripts
    @filamentScripts
</body>
```

- [ ] **Step 3: Commit**

```bash
git add resources/views/partials/head.blade.php resources/views/layouts/app/sidebar.blade.php
git commit -m "feat: agregar stacks de estilos y scripts al layout para soporte de CDN por página"
```

---

### Task 2: Crear factories para `ubicacion` y `reportes`

**Files:**
- Create: `database/factories/UbicacionFactory.php`
- Create: `database/factories/ReportesFactory.php`
- Modify: `app/Models/ubicacion.php`
- Modify: `app/Models/reportes.php`

- [ ] **Step 1: Crear `UbicacionFactory`**

Ejecutar:

```bash
php artisan make:factory UbicacionFactory --model=ubicacion --no-interaction
```

Reemplazar el contenido de `database/factories/UbicacionFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\ubicacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ubicacion>
 */
class UbicacionFactory extends Factory
{
    protected $model = ubicacion::class;

    public function definition(): array
    {
        return [
            'direccion' => fake()->streetAddress(),
            'latitud' => (string) fake()->latitude(-4.2, 12.5),
            'longitud' => (string) fake()->longitude(-79.0, -66.8),
        ];
    }

    public function sinCoordenadas(): static
    {
        return $this->state(['latitud' => null, 'longitud' => null]);
    }
}
```

- [ ] **Step 2: Crear `ReportesFactory`**

Ejecutar:

```bash
php artisan make:factory ReportesFactory --model=reportes --no-interaction
```

Reemplazar el contenido de `database/factories/ReportesFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\TipoServicio;
use App\Models\OrdenJob;
use App\Models\reportes;
use App\Models\ubicacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<reportes>
 */
class ReportesFactory extends Factory
{
    protected $model = reportes::class;

    public function definition(): array
    {
        return [
            'orden_job_id' => OrdenJob::factory()->state(['tipo_servicio' => TipoServicio::Fraudes->value]),
            'ubicacions_id' => ubicacion::factory(),
            'personals_id' => null,
            'numero_orden' => fake()->bothify('ORD-####'),
            'estado' => 'pendiente',
            'revisado' => false,
            'confirmado' => false,
            'hay_fuga_gas' => false,
            'excede_capacidad' => false,
        ];
    }

    public function sinUbicacion(): static
    {
        return $this->state(['ubicacions_id' => null]);
    }

    public function conUbicacionSinCoordenadas(): static
    {
        return $this->state(['ubicacions_id' => ubicacion::factory()->sinCoordenadas()]);
    }

    public function estado(string $estado): static
    {
        return $this->state(['estado' => $estado]);
    }
}
```

- [ ] **Step 3: Agregar `HasFactory` a los modelos `ubicacion` y `reportes`**

Verificar que `app/Models/ubicacion.php` ya tiene `use HasFactory` — lo tiene (ya está en el modelo).
Verificar que `app/Models/reportes.php` ya tiene `use HasFactory` — lo tiene.

No hay cambios necesarios en los modelos.

- [ ] **Step 4: Commit**

```bash
git add database/factories/UbicacionFactory.php database/factories/ReportesFactory.php
git commit -m "feat: agregar factories para ubicacion y reportes"
```

---

### Task 3: Escribir los tests del módulo Mapa (TDD — primero fallando)

**Files:**
- Create: `tests/Feature/Mapa/MapaIndexTest.php`

- [ ] **Step 1: Generar el archivo de test**

```bash
php artisan make:test --pest MapaIndexTest --no-interaction
```

Esto crea `tests/Feature/MapaIndexTest.php`. Moverlo a la subcarpeta `Mapa`:

```bash
mkdir -p tests/Feature/Mapa
mv tests/Feature/MapaIndexTest.php tests/Feature/Mapa/MapaIndexTest.php
```

- [ ] **Step 2: Reemplazar el contenido de `tests/Feature/Mapa/MapaIndexTest.php`**

```php
<?php

use App\Enums\TipoServicio;
use App\Models\OrdenJob;
use App\Models\personals;
use App\Models\reportes;
use App\Models\ubicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Permission::create(['name' => 'mapa.ver', 'guard_name' => 'web']);

    Role::create(['name' => 'Administrador', 'guard_name' => 'web'])
        ->givePermissionTo('mapa.ver');
    Role::create(['name' => 'Coordinador', 'guard_name' => 'web'])
        ->givePermissionTo('mapa.ver');
    Role::create(['name' => 'Lector', 'guard_name' => 'web']);
});

test('guests are redirected to login', function () {
    $this->get(route('mapa.index'))
        ->assertRedirect(route('login'));
});

test('users without mapa.ver permission are forbidden', function () {
    $user = User::factory()->create();
    $user->assignRole('Lector');

    $this->actingAs($user)
        ->get(route('mapa.index'))
        ->assertForbidden();
});

test('coordinador with mapa.ver can access the map', function () {
    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $this->actingAs($user)
        ->get(route('mapa.index'))
        ->assertOk();
});

test('administrador with mapa.ver can access the map', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrador');

    $this->actingAs($user)
        ->get(route('mapa.index'))
        ->assertOk();
});

test('only reportes with non-null lat and lng are returned as marcadores', function () {
    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $orden = OrdenJob::factory()->create(['tipo_servicio' => TipoServicio::Fraudes->value]);

    $reporteConCoordenadas = reportes::factory()->create([
        'orden_job_id' => $orden->id,
        'ubicacions_id' => ubicacion::factory()->create([
            'latitud' => '4.7110',
            'longitud' => '-74.0721',
        ])->id,
        'estado' => 'pendiente',
    ]);

    $reporteSinUbicacion = reportes::factory()->sinUbicacion()->create([
        'orden_job_id' => $orden->id,
    ]);

    $reporteConCoordenadasNulas = reportes::factory()->conUbicacionSinCoordenadas()->create([
        'orden_job_id' => $orden->id,
    ]);

    $component = Livewire\Livewire::actingAs($user)
        ->test(\App\Livewire\Mapa\MapaIndex::class);

    $marcadores = $component->instance()->marcadores;

    expect($marcadores)->toHaveCount(1)
        ->and($marcadores[0]['numero_orden'])->toBe($reporteConCoordenadas->numero_orden);
});

test('filter by fecha reduces marcadores', function () {
    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $orden = OrdenJob::factory()->create(['tipo_servicio' => TipoServicio::Fraudes->value]);

    reportes::factory()->create([
        'orden_job_id' => $orden->id,
        'ubicacions_id' => ubicacion::factory()->create(['latitud' => '4.71', 'longitud' => '-74.07'])->id,
        'created_at' => now()->subDays(5),
    ]);

    reportes::factory()->create([
        'orden_job_id' => $orden->id,
        'ubicacions_id' => ubicacion::factory()->create(['latitud' => '4.71', 'longitud' => '-74.07'])->id,
        'created_at' => now()->subDays(60),
    ]);

    $component = Livewire\Livewire::actingAs($user)
        ->test(\App\Livewire\Mapa\MapaIndex::class)
        ->set('fechaDesde', now()->subDays(30)->toDateString())
        ->set('fechaHasta', now()->toDateString());

    expect($component->instance()->marcadores)->toHaveCount(1);
});

test('filter by tecnico reduces marcadores', function () {
    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $orden = OrdenJob::factory()->create(['tipo_servicio' => TipoServicio::Fraudes->value]);
    $tecnico = personals::factory()->create();

    reportes::factory()->create([
        'orden_job_id' => $orden->id,
        'ubicacions_id' => ubicacion::factory()->create(['latitud' => '4.71', 'longitud' => '-74.07'])->id,
        'personals_id' => $tecnico->id,
    ]);

    reportes::factory()->create([
        'orden_job_id' => $orden->id,
        'ubicacions_id' => ubicacion::factory()->create(['latitud' => '5.00', 'longitud' => '-75.00'])->id,
        'personals_id' => null,
    ]);

    $component = Livewire\Livewire::actingAs($user)
        ->test(\App\Livewire\Mapa\MapaIndex::class)
        ->set('tecnicoId', $tecnico->id);

    expect($component->instance()->marcadores)->toHaveCount(1);
});

test('filter by estado reduces marcadores', function () {
    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $orden = OrdenJob::factory()->create(['tipo_servicio' => TipoServicio::Fraudes->value]);

    reportes::factory()->estado('confirmado')->create([
        'orden_job_id' => $orden->id,
        'ubicacions_id' => ubicacion::factory()->create(['latitud' => '4.71', 'longitud' => '-74.07'])->id,
    ]);

    reportes::factory()->estado('pendiente')->create([
        'orden_job_id' => $orden->id,
        'ubicacions_id' => ubicacion::factory()->create(['latitud' => '5.00', 'longitud' => '-75.00'])->id,
    ]);

    $component = Livewire\Livewire::actingAs($user)
        ->test(\App\Livewire\Mapa\MapaIndex::class)
        ->set('estadoReporte', 'confirmado');

    expect($component->instance()->marcadores)->toHaveCount(1);
});

test('filter by numero_orden reduces marcadores', function () {
    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $orden = OrdenJob::factory()->create(['tipo_servicio' => TipoServicio::Fraudes->value]);

    reportes::factory()->create([
        'orden_job_id' => $orden->id,
        'ubicacions_id' => ubicacion::factory()->create(['latitud' => '4.71', 'longitud' => '-74.07'])->id,
        'numero_orden' => 'ORD-0001',
    ]);

    reportes::factory()->create([
        'orden_job_id' => $orden->id,
        'ubicacions_id' => ubicacion::factory()->create(['latitud' => '5.00', 'longitud' => '-75.00'])->id,
        'numero_orden' => 'ORD-9999',
    ]);

    $component = Livewire\Livewire::actingAs($user)
        ->test(\App\Livewire\Mapa\MapaIndex::class)
        ->set('ordenTrabajo', '0001');

    expect($component->instance()->marcadores)->toHaveCount(1);
});
```

- [ ] **Step 3: Verificar que los tests fallan**

```bash
php artisan test --compact --filter=MapaIndexTest
```

Resultado esperado: todos fallan con `Route [mapa.index] not defined` o `Class not found`.

- [ ] **Step 4: Commit (tests en rojo)**

```bash
git add tests/Feature/Mapa/MapaIndexTest.php
git commit -m "test: agregar tests del módulo mapa (rojo)"
```

---

### Task 4: Crear la ruta y registrarla

**Files:**
- Create: `routes/mapa.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Crear `routes/mapa.php`**

```php
<?php

use App\Livewire\Mapa\MapaIndex;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:mapa.ver'])
    ->prefix('mapa')
    ->name('mapa.')
    ->group(function () {
        Route::get('/', MapaIndex::class)->name('index');
    });
```

- [ ] **Step 2: Registrar el archivo de rutas en `routes/web.php`**

Agregar al final de `routes/web.php` (antes de la última línea existente si hay comentarios, o después de las otras líneas `require`):

```php
require __DIR__.'/mapa.php';
```

El bloque de `require` al final de `web.php` quedará:

```php
require __DIR__.'/operaciones.php';
require __DIR__.'/tecnico.php';
require __DIR__.'/auditor.php';
require __DIR__.'/informes.php';
require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
require __DIR__.'/mapa.php';
```

- [ ] **Step 3: Verificar que la ruta existe**

```bash
php artisan route:list --name=mapa --except-vendor
```

Resultado esperado:
```
GET|HEAD  mapa  mapa.index  App\Livewire\Mapa\MapaIndex
```

- [ ] **Step 4: Commit**

```bash
git add routes/mapa.php routes/web.php
git commit -m "feat: agregar ruta mapa.index con middleware de permiso mapa.ver"
```

---

### Task 5: Crear el componente Livewire `MapaIndex`

**Files:**
- Create: `app/Livewire/Mapa/MapaIndex.php`

- [ ] **Step 1: Crear el componente**

```bash
php artisan make:livewire Mapa/MapaIndex --no-interaction
```

Esto crea `app/Livewire/Mapa/MapaIndex.php` y `resources/views/livewire/mapa/mapa-index.blade.php`.

- [ ] **Step 2: Reemplazar el contenido de `app/Livewire/Mapa/MapaIndex.php`**

```php
<?php

namespace App\Livewire\Mapa;

use App\Models\personals;
use App\Models\reportes;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Mapa de Ubicaciones')]
class MapaIndex extends Component
{
    public string $fechaDesde;
    public string $fechaHasta;
    public ?string $tecnicoId = null;
    public ?string $estadoReporte = null;
    public ?string $ordenTrabajo = null;

    public function mount(): void
    {
        $this->fechaDesde = now()->subDays(30)->toDateString();
        $this->fechaHasta = now()->toDateString();
    }

    /** @return array<string, string> */
    #[Computed]
    public function tecnicos(): array
    {
        return personals::query()
            ->select(['id', 'nombres', 'apellidos'])
            ->orderBy('nombres')
            ->get()
            ->mapWithKeys(fn (personals $p) => [$p->id => "{$p->nombres} {$p->apellidos}"])
            ->all();
    }

    /**
     * @return array<int, array{
     *   lat: float,
     *   lng: float,
     *   tecnico: string,
     *   fecha: string,
     *   numero_orden: string,
     *   estado: string,
     *   direccion: string,
     *   tipo_servicio: string,
     *   anomalias: array<int, mixed>,
     *   reporte_url: string
     * }>
     */
    #[Computed]
    public function marcadores(): array
    {
        return reportes::with(['ubicacion', 'personal', 'ordenJob'])
            ->whereHas('ubicacion', fn ($q) => $q->whereNotNull('latitud')->whereNotNull('longitud'))
            ->whereBetween('created_at', [
                $this->fechaDesde.' 00:00:00',
                $this->fechaHasta.' 23:59:59',
            ])
            ->when($this->tecnicoId, fn ($q) => $q->where('personals_id', $this->tecnicoId))
            ->when($this->estadoReporte, fn ($q) => $q->where('estado', $this->estadoReporte))
            ->when($this->ordenTrabajo, fn ($q) => $q->where('numero_orden', 'like', "%{$this->ordenTrabajo}%"))
            ->get()
            ->map(fn (reportes $reporte) => [
                'lat' => (float) $reporte->ubicacion->latitud,
                'lng' => (float) $reporte->ubicacion->longitud,
                'tecnico' => $reporte->personal
                    ? "{$reporte->personal->nombres} {$reporte->personal->apellidos}"
                    : '—',
                'fecha' => $reporte->created_at?->format('d/m/Y') ?? '—',
                'numero_orden' => $reporte->numero_orden,
                'estado' => $reporte->estado,
                'direccion' => $reporte->ubicacion->direccion ?? '—',
                'tipo_servicio' => $reporte->ordenJob?->tipo_servicio?->label() ?? '—',
                'anomalias' => is_array($reporte->anomalia) ? $reporte->anomalia : [],
                'reporte_url' => route('operaciones.ordenes-trabajo.show', $reporte->orden_job_id),
            ])
            ->values()
            ->all();
    }

    public function updated(): void
    {
        $this->dispatch('markers-updated', marcadores: $this->marcadores);
    }

    public function limpiarFiltros(): void
    {
        $this->fechaDesde = now()->subDays(30)->toDateString();
        $this->fechaHasta = now()->toDateString();
        $this->tecnicoId = null;
        $this->estadoReporte = null;
        $this->ordenTrabajo = null;

        $this->dispatch('markers-updated', marcadores: $this->marcadores);
    }

    public function render(): View
    {
        return view('livewire.mapa.mapa-index', [
            'marcadores' => $this->marcadores,
            'tecnicos' => $this->tecnicos,
        ]);
    }
}
```

- [ ] **Step 3: Ejecutar tests para ver cuántos pasan ahora**

```bash
php artisan test --compact --filter=MapaIndexTest
```

Los tests de acceso (guests, forbidden, coordinador, administrador) deben pasar. Los de filtros fallarán hasta que exista la vista.

- [ ] **Step 4: Commit**

```bash
git add app/Livewire/Mapa/MapaIndex.php
git commit -m "feat: agregar componente Livewire MapaIndex con filtros y marcadores"
```

---

### Task 6: Crear la vista Blade con el mapa Leaflet

**Files:**
- Modify: `resources/views/livewire/mapa/mapa-index.blade.php`

- [ ] **Step 1: Reemplazar el contenido de `resources/views/livewire/mapa/mapa-index.blade.php`**

```blade
@pushonce('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" crossorigin=""/>
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" crossorigin=""/>
    <style>
        #mapa-container { z-index: 1; }
        .leaflet-popup-content { margin: 10px 14px; }
        .mapa-popup-btn {
            display: block; margin-top: 8px; text-align: center;
            background: #3B82F6; color: white; padding: 5px 10px;
            border-radius: 6px; text-decoration: none; font-size: 12px;
        }
        .mapa-popup-btn:hover { background: #2563EB; }
        .mapa-popup-table td { padding: 2px 8px 2px 0; font-size: 13px; vertical-align: top; }
        .mapa-popup-table td:first-child { color: #6b7280; white-space: nowrap; }
    </style>
@endpushonce

@pushonce('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV/XN/WLs=" crossorigin=""></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js" crossorigin=""></script>
@endpushonce

<div class="space-y-4">
    <div>
        <flux:heading size="xl">Mapa de Ubicaciones</flux:heading>
        <flux:text class="mt-1 text-sm">Visualiza las ubicaciones desde donde los investigadores enviaron sus reportes.</flux:text>
    </div>

    {{-- Panel de filtros --}}
    <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">

            <div>
                <flux:label>Desde</flux:label>
                <flux:input type="date" wire:model.change="fechaDesde" />
            </div>

            <div>
                <flux:label>Hasta</flux:label>
                <flux:input type="date" wire:model.change="fechaHasta" />
            </div>

            <div>
                <flux:label>Investigador</flux:label>
                <flux:select wire:model.change="tecnicoId">
                    <flux:select.option value="">Todos</flux:select.option>
                    @foreach ($tecnicos as $id => $nombre)
                        <flux:select.option value="{{ $id }}">{{ $nombre }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div>
                <flux:label>Estado</flux:label>
                <flux:select wire:model.change="estadoReporte">
                    <flux:select.option value="">Todos</flux:select.option>
                    <flux:select.option value="pendiente">Pendiente</flux:select.option>
                    <flux:select.option value="revisado">Revisado</flux:select.option>
                    <flux:select.option value="confirmado">Confirmado</flux:select.option>
                </flux:select>
            </div>

            <div>
                <flux:label>N° Orden</flux:label>
                <flux:input type="text" wire:model.change="ordenTrabajo" placeholder="ORD-..." />
            </div>
        </div>

        <div class="mt-3 flex items-center justify-between">
            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                {{ count($marcadores) }} {{ count($marcadores) === 1 ? 'ubicación encontrada' : 'ubicaciones encontradas' }}
            </flux:text>
            <flux:button wire:click="limpiarFiltros" variant="ghost" size="sm" icon="x-mark">
                Limpiar filtros
            </flux:button>
        </div>
    </div>

    {{-- Leyenda --}}
    <div class="flex flex-wrap gap-4 text-xs text-zinc-600 dark:text-zinc-400">
        <span class="flex items-center gap-1.5">
            <span class="inline-block h-3 w-3 rounded-full border-2 border-white shadow" style="background:#EAB308"></span>
            Pendiente
        </span>
        <span class="flex items-center gap-1.5">
            <span class="inline-block h-3 w-3 rounded-full border-2 border-white shadow" style="background:#3B82F6"></span>
            Revisado
        </span>
        <span class="flex items-center gap-1.5">
            <span class="inline-block h-3 w-3 rounded-full border-2 border-white shadow" style="background:#22C55E"></span>
            Confirmado
        </span>
    </div>

    {{-- Contenedor del mapa --}}
    <div class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
        <div id="mapa-container" class="h-[calc(100vh-18rem)] w-full min-h-96"></div>
    </div>
</div>

@script
<script>
    const COLORES = {
        pendiente: '#EAB308',
        revisado:  '#3B82F6',
        confirmado: '#22C55E',
    };

    function crearIcono(estado) {
        const color = COLORES[estado] || '#6B7280';
        return L.divIcon({
            className: '',
            html: `<div style="background:${color};width:13px;height:13px;border-radius:50%;border:2px solid white;box-shadow:0 1px 4px rgba(0,0,0,0.4)"></div>`,
            iconSize: [13, 13],
            iconAnchor: [6, 6],
            popupAnchor: [0, -8],
        });
    }

    function buildPopup(m) {
        const anomalias = Array.isArray(m.anomalias) && m.anomalias.length
            ? m.anomalias.join(', ')
            : '—';
        return `
            <div style="min-width:230px">
                <div style="font-weight:600;font-size:14px;margin-bottom:6px">${m.numero_orden}</div>
                <table class="mapa-popup-table">
                    <tr><td>Técnico</td><td>${m.tecnico}</td></tr>
                    <tr><td>Fecha</td><td>${m.fecha}</td></tr>
                    <tr><td>Estado</td><td>${m.estado}</td></tr>
                    <tr><td>Dirección</td><td>${m.direccion}</td></tr>
                    <tr><td>Servicio</td><td>${m.tipo_servicio}</td></tr>
                    <tr><td>Anomalías</td><td>${anomalias}</td></tr>
                </table>
                <a href="${m.reporte_url}" class="mapa-popup-btn">Ver reporte →</a>
            </div>
        `;
    }

    let map = L.map('mapa-container').setView([4.7110, -74.0721], 6);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19,
    }).addTo(map);

    let clusterGroup = L.markerClusterGroup({ chunkedLoading: true });
    map.addLayer(clusterGroup);

    function renderMarcadores(marcadores) {
        clusterGroup.clearLayers();

        if (!marcadores.length) { return; }

        const bounds = [];

        marcadores.forEach(m => {
            const marker = L.marker([m.lat, m.lng], { icon: crearIcono(m.estado) });
            marker.bindPopup(buildPopup(m));
            clusterGroup.addLayer(marker);
            bounds.push([m.lat, m.lng]);
        });

        map.fitBounds(bounds, { padding: [40, 40], maxZoom: 14 });
    }

    renderMarcadores(@json($marcadores));

    $wire.on('markers-updated', ({ marcadores }) => {
        renderMarcadores(marcadores);
    });
</script>
@endscript
```

- [ ] **Step 2: Verificar que la página carga**

Abrir en el navegador: `https://alenaZentrix.test/mapa` (como coordinador o admin).

Debe aparecer el panel de filtros, la leyenda de colores y el mapa centrado en Colombia.

- [ ] **Step 3: Ejecutar todos los tests**

```bash
php artisan test --compact --filter=MapaIndexTest
```

Resultado esperado: todos los tests pasan (verde).

- [ ] **Step 4: Commit**

```bash
git add resources/views/livewire/mapa/mapa-index.blade.php
git commit -m "feat: agregar vista del mapa con Leaflet, MarkerCluster y panel de filtros"
```

---

### Task 7: Agregar entrada al sidebar

**Files:**
- Modify: `resources/views/layouts/app/sidebar.blade.php`

- [ ] **Step 1: Agregar el item del mapa al sidebar**

Abrir `resources/views/layouts/app/sidebar.blade.php`. Localizar el bloque del grupo Informes:

```blade
            @canany(['informes.ordenes', 'informes.tecnico', 'informes.auditorias'])
            <flux:sidebar.group :heading="__('Informes')" class="grid">
                <flux:sidebar.item icon="document-chart-bar"
                    :href="route('informes.index')"
                    :current="request()->routeIs('informes.*')"
                    wire:navigate>
                    {{ __('Informes') }}
                </flux:sidebar.item>
            </flux:sidebar.group>
            @endcanany
```

Agregar el bloque del Mapa **después** de ese bloque:

```blade
            @can('mapa.ver')
            <flux:sidebar.group :heading="__('Mapa')" class="grid">
                <flux:sidebar.item icon="map"
                    :href="route('mapa.index')"
                    :current="request()->routeIs('mapa.*')"
                    wire:navigate>
                    {{ __('Mapa de Ubicaciones') }}
                </flux:sidebar.item>
            </flux:sidebar.group>
            @endcan
```

- [ ] **Step 2: Verificar visualmente**

Iniciar sesión como coordinador o administrador. El sidebar debe mostrar "Mapa de Ubicaciones" con el ícono de mapa. Iniciar sesión como técnico/auditor/lector: el ítem NO debe aparecer.

- [ ] **Step 3: Commit**

```bash
git add resources/views/layouts/app/sidebar.blade.php
git commit -m "feat: agregar ítem de Mapa de Ubicaciones al sidebar"
```

---

### Task 8: Formatear código y ejecutar todos los tests

**Files:** (sin cambios nuevos — solo validación final)

- [ ] **Step 1: Ejecutar Pint sobre todos los archivos modificados**

```bash
vendor/bin/pint --dirty --format agent
```

Corregir cualquier problema de estilo que reporte.

- [ ] **Step 2: Ejecutar el conjunto completo de tests del módulo mapa**

```bash
php artisan test --compact --filter=MapaIndexTest
```

Resultado esperado: 8 tests, todos en verde.

- [ ] **Step 3: Ejecutar la suite completa para detectar regresiones**

```bash
php artisan test --compact
```

Resultado esperado: toda la suite pasa sin errores nuevos.

- [ ] **Step 4: Commit final si Pint hizo cambios**

```bash
git add -p
git commit -m "style: aplicar formato pint al módulo mapa"
```

---

## Resumen de archivos

| Acción | Archivo |
|--------|---------|
| Crear | `routes/mapa.php` |
| Crear | `app/Livewire/Mapa/MapaIndex.php` |
| Crear | `resources/views/livewire/mapa/mapa-index.blade.php` |
| Crear | `database/factories/UbicacionFactory.php` |
| Crear | `database/factories/ReportesFactory.php` |
| Crear | `tests/Feature/Mapa/MapaIndexTest.php` |
| Modificar | `routes/web.php` |
| Modificar | `resources/views/partials/head.blade.php` |
| Modificar | `resources/views/layouts/app/sidebar.blade.php` |
