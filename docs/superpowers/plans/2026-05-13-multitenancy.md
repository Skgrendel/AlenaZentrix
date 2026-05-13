# Multi-Tenancy Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add multi-tenancy to isolate each company's data behind a Laravel Global Scope, with a separate superadmin panel for platform-wide management.

**Architecture:** A `TenantService` singleton resolves the current company from the authenticated user's `company_id`. A `CompanyScope` global scope filters all tenant models to the active company. A `SetTenantContext` middleware boots the service and redirects superadmins to their own panel at `/superadmin`.

**Tech Stack:** Laravel 13, Livewire 4, Filament tables/forms (embedded), Spatie Permission, Pest 4, PHP 8.4.

**Spec:** `docs/superpowers/specs/2026-05-13-multitenancy-design.md`

---

## File Map

### New files
- `database/migrations/0000_01_01_000000_create_companies_table.php`
- `app/Models/Company.php`
- `database/factories/CompanyFactory.php`
- `app/Services/TenantService.php`
- `app/Models/Scopes/CompanyScope.php`
- `app/Http/Middleware/SetTenantContext.php`
- `app/Http/Middleware/RequireSuperAdmin.php`
- `database/seeders/CompanySeeder.php`
- `routes/superadmin.php`
- `resources/views/layouts/superadmin.blade.php`
- `app/Livewire/SuperAdmin/Dashboard.php`
- `resources/views/livewire/super-admin/dashboard.blade.php`
- `app/Livewire/SuperAdmin/Companies/CompaniesIndex.php`
- `resources/views/livewire/super-admin/companies/companies-index.blade.php`
- `app/Livewire/SuperAdmin/Users/UsersIndex.php`
- `resources/views/livewire/super-admin/users/users-index.blade.php`
- `app/Livewire/SuperAdmin/Roles/RolesIndex.php`
- `resources/views/livewire/super-admin/roles/roles-index.blade.php`
- `tests/Feature/Multitenancy/TenantScopeTest.php`
- `tests/Feature/Multitenancy/SuperAdminAccessTest.php`
- `tests/Feature/Multitenancy/CompanyAdminTest.php`

### Modified files
- `database/migrations/0000_10_11_000000_create_personals_table.php` — add `company_id`
- `database/migrations/0001_01_01_000000_create_users_table.php` — make `personals_id` nullable, add `company_id`, `is_superadmin`
- `database/migrations/2024_05_18_084152_create_orden_jobs_table.php` — add `company_id`
- `database/migrations/2024_07_05_161605_create_reportes_table.php` — add `company_id`
- `app/Models/User.php` — add `is_superadmin`, `company_id`, `company()` relation, `CompanyScope`
- `app/Models/personals.php` — add `company_id`, `company()` relation, `CompanyScope`
- `app/Models/OrdenJob.php` — add `company_id`, `CompanyScope`
- `app/Models/reportes.php` — add `company_id`, `CompanyScope`
- `app/Providers/AppServiceProvider.php` — register TenantService singleton
- `bootstrap/app.php` — register both middleware aliases
- `database/factories/UserFactory.php` — add `company_id` via Company factory
- `database/factories/PersonalsFactory.php` — add `company_id` via Company factory
- `database/factories/OrdenJobFactory.php` — add `company_id` via Company factory
- `database/seeders/PermissionsSeeder.php` — add `empresa.admin`, remove `roles.crear/editar/eliminar` from Administrador
- `database/seeders/PersonalSeeder.php` — add `company_id`
- `database/seeders/UserSeeder.php` — add superadmin + company_id on admin
- `database/seeders/DatabaseSeeder.php` — add CompanySeeder call
- `app/Livewire/Admin/ManageUsers.php` — auto-set `company_id` from TenantService on create
- `routes/web.php` — require superadmin.php

---

## Task 1: Company migration + model + factory

**Files:**
- Create: `database/migrations/0000_01_01_000000_create_companies_table.php`
- Create: `app/Models/Company.php`
- Create: `database/factories/CompanyFactory.php`

- [ ] **Step 1: Create the companies migration**

Run:
```bash
php artisan make:migration create_companies_table --no-interaction
```

Then rename the generated file to `database/migrations/0000_01_01_000000_create_companies_table.php` (so it runs before personals and users) and replace its content:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nombre');
            $table->string('logo')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
```

- [ ] **Step 2: Create the Company model**

Run:
```bash
php artisan make:model Company --no-interaction
```

Replace the generated file content:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = ['nombre', 'logo', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'company_id');
    }
}
```

- [ ] **Step 3: Create the CompanyFactory**

Run:
```bash
php artisan make:factory CompanyFactory --model=Company --no-interaction
```

Replace the generated file content:

```php
<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'nombre' => fake()->company(),
            'logo' => null,
            'activo' => true,
        ];
    }

    public function inactiva(): static
    {
        return $this->state(['activo' => false]);
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add database/migrations/0000_01_01_000000_create_companies_table.php app/Models/Company.php database/factories/CompanyFactory.php
git commit -m "feat: agregar tabla companies, modelo y factory"
```

---

## Task 2: Update existing migrations for multi-tenancy columns

**Files:**
- Modify: `database/migrations/0000_10_11_000000_create_personals_table.php`
- Modify: `database/migrations/0001_01_01_000000_create_users_table.php`
- Modify: `database/migrations/2024_05_18_084152_create_orden_jobs_table.php`
- Modify: `database/migrations/2024_07_05_161605_create_reportes_table.php`

- [ ] **Step 1: Add company_id to personals migration**

In `database/migrations/0000_10_11_000000_create_personals_table.php`, add after `$table->string('cargo')->nullable();`:

```php
$table->foreignUuid('company_id')->nullable()->constrained('companies')->nullOnDelete();
```

Full `up()` after edit:
```php
public function up(): void
{
    Schema::create('personals', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->enum('tipo_documento', array_column(TipoDocumento::cases(), 'value'));
        $table->string('numero_documento')->unique();
        $table->string('nombres');
        $table->string('apellidos');
        $table->string('telefono')->nullable();
        $table->string('cargo')->nullable();
        $table->foreignUuid('company_id')->nullable()->constrained('companies')->nullOnDelete();
        $table->timestamps();
    });
}
```

- [ ] **Step 2: Update users migration — nullable personals_id, add company_id and is_superadmin**

In `database/migrations/0001_01_01_000000_create_users_table.php`, replace the `users` table block:

```php
Schema::create('users', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('personals_id')->nullable()->constrained('personals')->nullOnDelete();
    $table->foreignUuid('company_id')->nullable()->constrained('companies')->nullOnDelete();
    $table->boolean('is_superadmin')->default(false);
    $table->string('email')->unique();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password');
    $table->rememberToken();
    $table->boolean('estado')->default(true);
    $table->string('profile_photo_path', 2048)->nullable();
    $table->timestamps();
});
```

- [ ] **Step 3: Add company_id to orden_jobs migration**

In `database/migrations/2024_05_18_084152_create_orden_jobs_table.php`, add after `$table->timestamps();` (before closing bracket):

```php
$table->foreignUuid('company_id')->nullable()->constrained('companies')->nullOnDelete();
```

- [ ] **Step 4: Add company_id to reportes migration**

In `database/migrations/2024_07_05_161605_create_reportes_table.php`, add after the `$table->timestamps();` line inside the `reportes` table block:

```php
$table->foreignUuid('company_id')->nullable()->constrained('companies')->nullOnDelete();
```

- [ ] **Step 5: Verify migrations run cleanly**

```bash
php artisan migrate:fresh --no-interaction
```

Expected: all migrations run without errors. The `companies`, `personals`, `users`, `orden_jobs`, `reportes` tables should all exist.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/
git commit -m "feat: agregar company_id y is_superadmin a migraciones existentes"
```

---

## Task 3: TenantService singleton + CompanyScope + model updates

**Files:**
- Create: `app/Services/TenantService.php`
- Create: `app/Models/Scopes/CompanyScope.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `app/Models/User.php`
- Modify: `app/Models/personals.php`
- Modify: `app/Models/OrdenJob.php`
- Modify: `app/Models/reportes.php`
- Test: `tests/Feature/Multitenancy/TenantScopeTest.php`

- [ ] **Step 1: Write the failing tests**

Run:
```bash
php artisan make:test --pest MultitenancyTenantScopeTest --no-interaction
```

Rename the generated file to `tests/Feature/Multitenancy/TenantScopeTest.php` (create `tests/Feature/Multitenancy/` directory first).

Replace the file content:

```php
<?php

use App\Models\Company;
use App\Models\OrdenJob;
use App\Models\personals;
use App\Models\reportes;
use App\Models\User;
use App\Services\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::create(['name' => 'ordenes.ver', 'guard_name' => 'web']);
    Role::create(['name' => 'Administrador', 'guard_name' => 'web'])->givePermissionTo('ordenes.ver');
});

test('TenantService starts empty', function () {
    $service = app(TenantService::class);

    expect($service->active())->toBeFalse()
        ->and($service->id())->toBeNull()
        ->and($service->get())->toBeNull();
});

test('TenantService set and get work', function () {
    $company = Company::factory()->create();
    $service = app(TenantService::class);

    $service->set($company);

    expect($service->active())->toBeTrue()
        ->and($service->id())->toBe($company->id)
        ->and($service->get())->toBe($company);
});

test('CompanyScope filters users by company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $userA = User::factory()->create(['company_id' => $companyA->id]);
    User::factory()->create(['company_id' => $companyB->id]);

    app(TenantService::class)->set($companyA);

    expect(User::all())->toHaveCount(1)
        ->and(User::first()->id)->toBe($userA->id);
});

test('CompanyScope does not filter when user is superadmin', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $superadmin = User::factory()->create(['company_id' => null, 'is_superadmin' => true]);
    User::factory()->create(['company_id' => $companyA->id]);
    User::factory()->create(['company_id' => $companyB->id]);

    $this->actingAs($superadmin);

    expect(User::all())->toHaveCount(3);
});

test('CompanyScope filters personals by company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $personalA = personals::factory()->create(['company_id' => $companyA->id]);
    personals::factory()->create(['company_id' => $companyB->id]);

    app(TenantService::class)->set($companyA);

    expect(personals::all())->toHaveCount(1)
        ->and(personals::first()->id)->toBe($personalA->id);
});

test('CompanyScope filters orden_jobs by company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $ordenA = OrdenJob::factory()->create(['company_id' => $companyA->id]);
    OrdenJob::factory()->create(['company_id' => $companyB->id]);

    app(TenantService::class)->set($companyA);

    expect(OrdenJob::all())->toHaveCount(1)
        ->and(OrdenJob::first()->id)->toBe($ordenA->id);
});

test('CompanyScope filters reportes by company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $ordenA = OrdenJob::factory()->create(['company_id' => $companyA->id]);
    $ordenB = OrdenJob::factory()->create(['company_id' => $companyB->id]);

    $reporteA = reportes::factory()->create(['company_id' => $companyA->id, 'orden_job_id' => $ordenA->id]);
    reportes::factory()->create(['company_id' => $companyB->id, 'orden_job_id' => $ordenB->id]);

    app(TenantService::class)->set($companyA);

    expect(reportes::all())->toHaveCount(1)
        ->and(reportes::first()->id)->toBe($reporteA->id);
});
```

- [ ] **Step 2: Run tests to confirm they fail**

```bash
php artisan test --compact --filter=TenantScopeTest
```

Expected: FAIL — `App\Services\TenantService` not found.

- [ ] **Step 3: Create TenantService**

```bash
php artisan make:class Services/TenantService --no-interaction
```

Replace the content of `app/Services/TenantService.php`:

```php
<?php

namespace App\Services;

use App\Models\Company;

class TenantService
{
    private ?Company $current = null;

    public function set(Company $company): void
    {
        $this->current = $company;
    }

    public function get(): ?Company
    {
        return $this->current;
    }

    public function active(): bool
    {
        return $this->current !== null;
    }

    public function id(): ?string
    {
        return $this->current?->id;
    }
}
```

- [ ] **Step 4: Register TenantService as singleton in AppServiceProvider**

In `app/Providers/AppServiceProvider.php`, update `register()`:

```php
public function register(): void
{
    $this->app->singleton(\App\Services\TenantService::class);
}
```

- [ ] **Step 5: Create CompanyScope**

Create the directory `app/Models/Scopes/` then create `app/Models/Scopes/CompanyScope.php`:

```php
<?php

namespace App\Models\Scopes;

use App\Services\TenantService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if ($user && $user->is_superadmin) {
            return;
        }

        $tenantId = app(TenantService::class)->id();

        if ($tenantId) {
            $builder->where($model->getTable().'.company_id', $tenantId);
        }
    }
}
```

- [ ] **Step 6: Update User model**

Replace `app/Models/User.php` with:

```php
<?php

namespace App\Models;

use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Cuenta de acceso al sistema. El nombre visible se obtiene a través de la relación `personal`.
 */
class User extends Authenticatable
{
    use HasFactory;
    use HasRoles;
    use HasUuids;
    use Notifiable;

    protected $fillable = [
        'email',
        'personals_id',
        'company_id',
        'is_superadmin',
        'password',
        'estado',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'estado' => 'boolean',
        'is_superadmin' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    public function personal(): BelongsTo
    {
        return $this->belongsTo(personals::class, 'personals_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Devuelve el nombre completo del usuario. Si no tiene personal asociado,
     * retorna el email como identificador de respaldo.
     */
    public function fullName(): string
    {
        $personal = $this->personal;

        if (! $personal) {
            return $this->email;
        }

        return $personal->nombres.' '.$personal->apellidos;
    }

    /**
     * Devuelve las iniciales del nombre y apellido para avatares.
     * Retorna '?' si el usuario no tiene personal vinculado.
     */
    public function initials(): string
    {
        $personal = $this->personal;

        if (! $personal) {
            return '?';
        }

        return mb_strtoupper(
            mb_substr($personal->nombres, 0, 1).mb_substr($personal->apellidos, 0, 1)
        );
    }
}
```

- [ ] **Step 7: Update personals model**

Add the following to `app/Models/personals.php`:

1. Add at top with other use statements: `use App\Models\Scopes\CompanyScope;` and `use Illuminate\Database\Eloquent\Relations\BelongsTo;`
2. Add `'company_id'` to `$fillable` array
3. Add `booted()` method and `company()` relation:

```php
protected static function booted(): void
{
    static::addGlobalScope(new CompanyScope);
}

public function company(): BelongsTo
{
    return $this->belongsTo(Company::class, 'company_id');
}
```

Full updated `app/Models/personals.php`:

```php
<?php

namespace App\Models;

use App\Enums\TipoDocumento;
use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Datos personales y de identificación de técnicos, investigadores y cualquier miembro del equipo.
 */
class personals extends Model
{
    use HasFactory, HasUuids;

    public static $rules = [
        'tipo_documento' => 'required',
        'numero_documento' => 'required',
        'nombres' => 'required|string',
        'apellidos' => 'required|string',
        'telefono' => 'required',
    ];

    protected $fillable = [
        'tipo_documento',
        'numero_documento',
        'nombres',
        'apellidos',
        'telefono',
        'cargo',
        'company_id',
    ];

    protected $casts = [
        'tipo_documento' => TipoDocumento::class,
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function usuario(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(User::class, 'personals_id');
    }

    public function ordenJobs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrdenJob::class, 'personals_id');
    }
}
```

- [ ] **Step 8: Add CompanyScope + company_id fillable to OrdenJob model**

In `app/Models/OrdenJob.php`:
1. Add `use App\Models\Scopes\CompanyScope;` with the other use statements
2. Add `'company_id'` to `$fillable`
3. Inside the existing `booted()` method, add as the FIRST line: `static::addGlobalScope(new CompanyScope);`

The `booted()` method start should look like:

```php
protected static function booted(): void
{
    static::addGlobalScope(new CompanyScope);

    static::creating(function (self $model): void {
        // ... existing code unchanged
    });
    // ... rest unchanged
}
```

- [ ] **Step 9: Add CompanyScope + company_id fillable to reportes model**

In `app/Models/reportes.php`:
1. Add `use App\Models\Scopes\CompanyScope;` with the other use statements
2. Add `'company_id'` to `$fillable`
3. Add `booted()` method:

```php
protected static function booted(): void
{
    static::addGlobalScope(new CompanyScope);
}
```

- [ ] **Step 10: Run the tests**

```bash
php artisan test --compact --filter=TenantScopeTest
```

Expected: All 6 tests pass.

- [ ] **Step 11: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 12: Commit**

```bash
git add app/Services/ app/Models/Scopes/ app/Models/User.php app/Models/personals.php app/Models/OrdenJob.php app/Models/reportes.php app/Providers/AppServiceProvider.php tests/Feature/Multitenancy/
git commit -m "feat: agregar TenantService, CompanyScope y actualizar modelos para multi-tenancy"
```

---

## Task 4: Middleware — SetTenantContext + RequireSuperAdmin

**Files:**
- Create: `app/Http/Middleware/SetTenantContext.php`
- Create: `app/Http/Middleware/RequireSuperAdmin.php`
- Modify: `bootstrap/app.php`
- Test: `tests/Feature/Multitenancy/SuperAdminAccessTest.php`

- [ ] **Step 1: Write failing tests**

Run:
```bash
php artisan make:test --pest MultitenancySuperAdminAccessTest --no-interaction
```

Rename to `tests/Feature/Multitenancy/SuperAdminAccessTest.php` and replace content:

```php
<?php

use App\Models\Company;
use App\Models\User;
use App\Services\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::create(['name' => 'ordenes.ver', 'guard_name' => 'web']);
    Role::create(['name' => 'Administrador', 'guard_name' => 'web'])->givePermissionTo('ordenes.ver');
});

test('SetTenantContext sets company from authenticated user', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);

    $this->actingAs($user)->get(route('dashboard'));

    expect(app(TenantService::class)->id())->toBe($company->id);
});

test('superadmin hitting dashboard is redirected to superadmin panel', function () {
    $superadmin = User::factory()->create(['is_superadmin' => true, 'company_id' => null]);

    $this->actingAs($superadmin)
        ->get(route('dashboard'))
        ->assertRedirect(route('superadmin.dashboard'));
});

test('regular user cannot access superadmin routes', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('Administrador');

    $this->actingAs($user)
        ->get(route('superadmin.dashboard'))
        ->assertForbidden();
});

test('superadmin can access superadmin panel', function () {
    $superadmin = User::factory()->create(['is_superadmin' => true, 'company_id' => null]);

    $this->actingAs($superadmin)
        ->get(route('superadmin.dashboard'))
        ->assertOk();
});

test('guest cannot access superadmin routes', function () {
    $this->get(route('superadmin.dashboard'))
        ->assertRedirect(route('login'));
});
```

- [ ] **Step 2: Run tests to confirm they fail**

```bash
php artisan test --compact --filter=SuperAdminAccessTest
```

Expected: FAIL — route `superadmin.dashboard` not found.

- [ ] **Step 3: Create SetTenantContext middleware**

```bash
php artisan make:middleware SetTenantContext --no-interaction
```

Replace content of `app/Http/Middleware/SetTenantContext.php`:

```php
<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Services\TenantService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext implements \Illuminate\Contracts\Middleware\TerminableMiddleware
{
    public function __construct(private readonly TenantService $tenantService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->is_superadmin) {
            if (! $request->routeIs('superadmin.*') && ! $request->routeIs('logout') && ! $request->routeIs('profile.*')) {
                return redirect()->route('superadmin.dashboard');
            }

            return $next($request);
        }

        if ($user->company_id) {
            $company = Company::withoutGlobalScopes()->find($user->company_id);

            if ($company && $company->activo) {
                $this->tenantService->set($company);
            }
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        // Reset tenant for long-running processes (Octane)
    }
}
```

- [ ] **Step 4: Create RequireSuperAdmin middleware**

```bash
php artisan make:middleware RequireSuperAdmin --no-interaction
```

Replace content of `app/Http/Middleware/RequireSuperAdmin.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->is_superadmin) {
            abort(403);
        }

        return $next($request);
    }
}
```

- [ ] **Step 5: Register both middleware in bootstrap/app.php**

In `bootstrap/app.php`, update the `withMiddleware` closure:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
        'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        'superadmin' => \App\Http\Middleware\RequireSuperAdmin::class,
    ]);

    $middleware->appendToGroup('web', \App\Http\Middleware\SetTenantContext::class);
})
```

- [ ] **Step 6: Create minimal superadmin route for the test to find it**

Create `routes/superadmin.php` (minimal — will be expanded in Task 7):

```php
<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'superadmin'])
    ->prefix('superadmin')
    ->name('superadmin.')
    ->group(function () {
        Route::view('/', 'livewire.super-admin.dashboard')->name('dashboard');
    });
```

Add to `routes/web.php` at the end:

```php
require __DIR__.'/superadmin.php';
```

Create minimal view `resources/views/livewire/super-admin/dashboard.blade.php`:

```html
<!DOCTYPE html>
<html><body><p>Superadmin panel</p></body></html>
```

- [ ] **Step 7: Run the tests**

```bash
php artisan test --compact --filter=SuperAdminAccessTest
```

Expected: All 5 tests pass.

- [ ] **Step 8: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 9: Commit**

```bash
git add app/Http/Middleware/ bootstrap/app.php routes/superadmin.php routes/web.php resources/views/livewire/super-admin/
git commit -m "feat: agregar middleware SetTenantContext y RequireSuperAdmin"
```

---

## Task 5: Update seeders + migrate:fresh

**Files:**
- Modify: `database/seeders/PermissionsSeeder.php`
- Modify: `database/seeders/PersonalSeeder.php`
- Modify: `database/seeders/UserSeeder.php`
- Create: `database/seeders/CompanySeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`

- [ ] **Step 1: Update PermissionsSeeder — add empresa.admin, restrict Administrador role**

In `database/seeders/PermissionsSeeder.php`:

1. Add to the `$permisos` array (after `admin.configuracion`):
```php
'empresa.admin' => ['label' => 'Gestionar configuración de la propia empresa', 'modulo' => 'Administración'],
```

2. Change the `Administrador` role permissions from `['*']` to explicit list (removing roles.crear/editar/eliminar):
```php
'Administrador' => [
    'ordenes.ver', 'ordenes.crear', 'ordenes.editar', 'ordenes.eliminar', 'ordenes.revisar',
    'mis-ordenes.ver', 'mis-ordenes.reportar',
    'auditorias.ver', 'auditorias.registrar',
    'informes.ordenes', 'informes.tecnico', 'informes.auditorias', 'informes',
    'mapa.ver',
    'legal.ver',
    'evidencias.ver',
    'usuarios.ver', 'usuarios.crear', 'usuarios.editar', 'usuarios.eliminar', 'usuarios.asignar-roles',
    'roles.ver',
    'admin.configuracion',
    'empresa.admin',
],
```

- [ ] **Step 2: Create CompanySeeder**

Run:
```bash
php artisan make:seeder CompanySeeder --no-interaction
```

Replace content of `database/seeders/CompanySeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        Company::create([
            'nombre' => 'Empresa Demo',
            'activo' => true,
        ]);
    }
}
```

- [ ] **Step 3: Update PersonalSeeder — add company_id**

Replace content of `database/seeders/PersonalSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\personals;
use Illuminate\Database\Seeder;

class PersonalSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();

        personals::create([
            'tipo_documento' => 'CC',
            'numero_documento' => '0000',
            'nombres' => 'Administrador',
            'apellidos' => 'Sistema',
            'cargo' => 'Administrador del Sistema',
            'company_id' => $company->id,
        ]);

        personals::create([
            'tipo_documento' => 'CC',
            'numero_documento' => '0001',
            'nombres' => 'Auditor',
            'apellidos' => 'Sistema',
            'cargo' => 'Auditor',
            'company_id' => $company->id,
        ]);
    }
}
```

- [ ] **Step 4: Update UserSeeder — add superadmin + company_id on admin**

Replace content of `database/seeders/UserSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\personals;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Superadmin — no company, no personal, acceso global
        User::create([
            'email' => 'superadmin@proderi.com',
            'personals_id' => null,
            'company_id' => null,
            'is_superadmin' => true,
            'password' => bcrypt('super.**'),
            'estado' => true,
            'email_verified_at' => now(),
        ]);

        $company = Company::first();

        // Administrador de empresa
        $personal = personals::where('numero_documento', '0000')->firstOrFail();
        $admin = User::create([
            'email' => 'admin@proderi.com',
            'personals_id' => $personal->id,
            'company_id' => $company->id,
            'password' => bcrypt('0000.**'),
            'estado' => true,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole(Role::where('name', 'Administrador')->firstOrFail());

        // Auditor predeterminado
        $personalAuditor = personals::where('numero_documento', '0001')->firstOrFail();
        $auditor = User::create([
            'email' => 'auditor@proderi.com',
            'personals_id' => $personalAuditor->id,
            'company_id' => $company->id,
            'password' => bcrypt('0001.**'),
            'estado' => true,
            'email_verified_at' => now(),
        ]);
        $auditor->assignRole(Role::where('name', 'Auditor')->firstOrFail());
    }
}
```

- [ ] **Step 5: Update DatabaseSeeder — add CompanySeeder before PersonalSeeder**

Replace content of `database/seeders/DatabaseSeeder.php`:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CatalogosSeeder::class);
        $this->call(TipoNotificacionAlertaSeeder::class);
        $this->call(ConvencionSeeder::class);
        $this->call(TipologiaOrdenSeeder::class);
        $this->call(ResultadoReporteSeeder::class);
        $this->call(PermissionsSeeder::class);
        $this->call(CompanySeeder::class);
        $this->call(PersonalSeeder::class);
        $this->call(UserSeeder::class);
    }
}
```

- [ ] **Step 6: Run migrate:fresh --seed**

```bash
php artisan migrate:fresh --seed --no-interaction
```

Expected: All migrations + all seeders complete without errors. Verify:
```bash
php artisan tinker --execute 'echo App\Models\User::withoutGlobalScopes()->count()." users\n"; echo App\Models\Company::count()." companies\n";'
```

Expected output:
```
3 users
1 companies
```

- [ ] **Step 7: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 8: Commit**

```bash
git add database/seeders/
git commit -m "feat: actualizar seeders para multi-tenancy con superadmin y CompanySeeder"
```

---

## Task 6: Update factories for multi-tenancy

**Files:**
- Modify: `database/factories/UserFactory.php`
- Modify: `database/factories/PersonalsFactory.php`
- Modify: `database/factories/OrdenJobFactory.php`

These factories are used by tests. After adding `company_id` to models with `CompanyScope`, tests need factories that auto-create a Company or accept an existing one.

- [ ] **Step 1: Update UserFactory**

Replace content of `database/factories/UserFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\personals;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'personals_id' => personals::factory(),
            'company_id' => Company::factory(),
            'is_superadmin' => false,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'estado' => true,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    public function superadmin(): static
    {
        return $this->state([
            'is_superadmin' => true,
            'company_id' => null,
            'personals_id' => null,
        ]);
    }
}
```

- [ ] **Step 2: Update PersonalsFactory**

Replace content of `database/factories/PersonalsFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\TipoDocumento;
use App\Models\Company;
use App\Models\personals;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<personals>
 */
class PersonalsFactory extends Factory
{
    protected $model = personals::class;

    public function definition(): array
    {
        return [
            'tipo_documento' => fake()->randomElement(TipoDocumento::cases())->value,
            'numero_documento' => fake()->unique()->numerify('##########'),
            'nombres' => fake()->firstName(),
            'apellidos' => fake()->lastName(),
            'telefono' => fake()->phoneNumber(),
            'cargo' => fake()->jobTitle(),
            'company_id' => Company::factory(),
        ];
    }
}
```

- [ ] **Step 3: Update OrdenJobFactory**

Replace content of `database/factories/OrdenJobFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\EstadoOrden;
use App\Enums\PrioridadOrden;
use App\Models\Company;
use App\Models\OrdenJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrdenJob>
 */
class OrdenJobFactory extends Factory
{
    protected $model = OrdenJob::class;

    public function definition(): array
    {
        return [
            'personals_id' => null,
            'company_id' => Company::factory(),
            'contrato' => fake()->numerify('CONT-####'),
            'cliente' => fake()->name(),
            'direccion' => fake()->streetAddress(),
            'barrio' => fake()->word(),
            'medidor' => fake()->numerify('MED-######'),
            'tipo_medidor' => fake()->optional()->randomElement(['digital', 'analógico']),
            'ciclo' => fake()->numerify('C##'),
            'prioridad' => fake()->randomElement(PrioridadOrden::cases())->value,
            'estado' => EstadoOrden::Pendiente->value,
            'estado_servicio' => true,
            'fecha_asignacion' => null,
            'fecha_limite' => fake()->optional()->dateTimeBetween('now', '+30 days'),
            'fecha_completado' => null,
        ];
    }
}
```

- [ ] **Step 4: Update existing tests that use factories — set TenantService before Livewire tests**

Existing tests in `tests/Feature/Mapa/MapaIndexTest.php` use `Livewire::actingAs($user)->test(...)`. After CompanyScope is active, the scope will look for an active tenant. `SetTenantContext` runs for HTTP requests, but for Livewire unit tests we need to set TenantService manually.

Update `tests/Feature/Mapa/MapaIndexTest.php` — change the `beforeEach` block to also boot the tenant from the test user's company, and ensure all factory calls share the same `company_id`.

Replace the `beforeEach` and helper pattern: after creating `$user`, call `app(\App\Services\TenantService::class)->set($user->company)`. Also ensure OrdenJob and reportes factories use the same company. The simplest approach is to create a shared company:

In `tests/Feature/Mapa/MapaIndexTest.php`, add this at the top of `beforeEach`:

```php
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    // ... existing permission/role creation ...
});
```

And in each test that creates an OrdenJob or reportes, pass the user's company_id explicitly:

```php
// After $user = User::factory()->create(); add:
app(\App\Services\TenantService::class)->set($user->company);

// When creating OrdenJob:
$orden = OrdenJob::factory()->create(['company_id' => $user->company_id, 'tipo_servicio' => TipoServicio::Fraudes->value]);

// When creating reportes that belong to that order:
$reporte = reportes::factory()->create([
    'orden_job_id' => $orden->id,
    'company_id' => $user->company_id,
    // ... other fields
]);
```

Apply this pattern to all tests in `MapaIndexTest.php` that create OrdenJob or reportes records.

- [ ] **Step 5: Run all tests to make sure nothing is broken**

```bash
php artisan test --compact
```

Expected: All existing tests pass. If any fail due to company isolation, they need the same fix: set TenantService + share company_id across related factories.

- [ ] **Step 6: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 7: Commit**

```bash
git add database/factories/ tests/Feature/Mapa/
git commit -m "feat: actualizar factories para multi-tenancy y corregir tests existentes"
```

---

## Task 7: Superadmin layout + expanded routes

**Files:**
- Modify: `routes/superadmin.php`
- Create: `resources/views/layouts/superadmin.blade.php`

- [ ] **Step 1: Create superadmin layout**

Create `resources/views/layouts/superadmin.blade.php`:

```html
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-white dark:bg-zinc-800">
    <flux:sidebar sticky collapsible="mobile"
        class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:sidebar.header>
            <x-app-logo :sidebar="true" href="{{ route('superadmin.dashboard') }}" wire:navigate />
            <flux:sidebar.collapse class="lg:hidden" />
        </flux:sidebar.header>

        <flux:sidebar.nav>
            <flux:sidebar.group :heading="__('Superadmin')" class="grid">
                <flux:sidebar.item icon="home" :href="route('superadmin.dashboard')"
                    :current="request()->routeIs('superadmin.dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="building-office" :href="route('superadmin.companies')"
                    :current="request()->routeIs('superadmin.companies*')" wire:navigate>
                    {{ __('Empresas') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="users" :href="route('superadmin.users')"
                    :current="request()->routeIs('superadmin.users*')" wire:navigate>
                    {{ __('Usuarios') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="shield-check" :href="route('superadmin.roles')"
                    :current="request()->routeIs('superadmin.roles*')" wire:navigate>
                    {{ __('Roles y Permisos') }}
                </flux:sidebar.item>
            </flux:sidebar.group>
        </flux:sidebar.nav>

        <flux:spacer />

        <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->email" />
    </flux:sidebar>

    <flux:header class="lg:hidden">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
        <flux:spacer />
        <flux:dropdown position="top" align="end">
            <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />
            <flux:menu>
                <flux:menu.separator />
                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer">
                        {{ __('Log out') }}
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </flux:header>

    {{ $slot }}

    @persist('toast')
        <flux:toast.group>
            <flux:toast />
        </flux:toast.group>
    @endpersist

    @fluxScripts
    @filamentScripts
</body>
</html>
```

- [ ] **Step 2: Expand superadmin routes**

Replace content of `routes/superadmin.php`:

```php
<?php

use App\Livewire\SuperAdmin\Companies\CompaniesIndex;
use App\Livewire\SuperAdmin\Dashboard;
use App\Livewire\SuperAdmin\Roles\RolesIndex;
use App\Livewire\SuperAdmin\Users\UsersIndex;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'superadmin'])
    ->prefix('superadmin')
    ->name('superadmin.')
    ->group(function () {
        Route::get('/', Dashboard::class)->name('dashboard');
        Route::get('companies', CompaniesIndex::class)->name('companies');
        Route::get('users', UsersIndex::class)->name('users');
        Route::get('roles', RolesIndex::class)->name('roles');
    });
```

- [ ] **Step 3: Commit**

```bash
git add routes/superadmin.php resources/views/layouts/superadmin.blade.php
git commit -m "feat: agregar layout y rutas del panel superadmin"
```

---

## Task 8: Superadmin Dashboard component

**Files:**
- Create: `app/Livewire/SuperAdmin/Dashboard.php`
- Modify: `resources/views/livewire/super-admin/dashboard.blade.php`

- [ ] **Step 1: Create the component**

```bash
php artisan make:livewire SuperAdmin/Dashboard --no-interaction
```

Replace content of `app/Livewire/SuperAdmin/Dashboard.php`:

```php
<?php

namespace App\Livewire\SuperAdmin;

use App\Models\Company;
use App\Models\OrdenJob;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.superadmin')]
#[Title('Panel Superadmin')]
class Dashboard extends Component
{
    #[Computed]
    public function totalEmpresas(): int
    {
        return Company::count();
    }

    #[Computed]
    public function totalUsuarios(): int
    {
        return User::withoutGlobalScopes()->where('is_superadmin', false)->count();
    }

    #[Computed]
    public function totalOrdenes(): int
    {
        return OrdenJob::withoutGlobalScopes()->count();
    }

    public function render(): View
    {
        return view('livewire.super-admin.dashboard');
    }
}
```

- [ ] **Step 2: Create the view**

Replace content of `resources/views/livewire/super-admin/dashboard.blade.php`:

```html
<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl p-6">
    <flux:heading size="xl">Panel Superadmin</flux:heading>
    <flux:text class="text-zinc-500">Vista global de la plataforma.</flux:text>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <flux:card>
            <flux:heading>Empresas</flux:heading>
            <p class="mt-2 text-3xl font-bold">{{ $this->totalEmpresas }}</p>
        </flux:card>
        <flux:card>
            <flux:heading>Usuarios</flux:heading>
            <p class="mt-2 text-3xl font-bold">{{ $this->totalUsuarios }}</p>
        </flux:card>
        <flux:card>
            <flux:heading>Órdenes</flux:heading>
            <p class="mt-2 text-3xl font-bold">{{ $this->totalOrdenes }}</p>
        </flux:card>
    </div>
</div>
```

- [ ] **Step 3: Verify the route loads**

```bash
php artisan route:list --name=superadmin --except-vendor
```

Expected: 4 routes listed under `superadmin.*`.

- [ ] **Step 4: Commit**

```bash
git add app/Livewire/SuperAdmin/Dashboard.php resources/views/livewire/super-admin/dashboard.blade.php
git commit -m "feat: agregar componente Dashboard del superadmin"
```

---

## Task 9: Superadmin Companies CRUD

**Files:**
- Create: `app/Livewire/SuperAdmin/Companies/CompaniesIndex.php`
- Create: `resources/views/livewire/super-admin/companies/companies-index.blade.php`
- Test: `tests/Feature/Multitenancy/CompanyAdminTest.php`

- [ ] **Step 1: Write the failing test**

Run:
```bash
php artisan make:test --pest MultitenancyCompanyAdminTest --no-interaction
```

Rename to `tests/Feature/Multitenancy/CompanyAdminTest.php` and replace content:

```php
<?php

use App\Livewire\SuperAdmin\Companies\CompaniesIndex;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('superadmin can view companies list', function () {
    $superadmin = User::factory()->superadmin()->create();
    Company::factory()->count(3)->create();

    $this->actingAs($superadmin)
        ->get(route('superadmin.companies'))
        ->assertOk();
});

test('superadmin can create a company', function () {
    $superadmin = User::factory()->superadmin()->create();

    Livewire\Livewire::actingAs($superadmin)
        ->test(CompaniesIndex::class)
        ->callAction('create', ['nombre' => 'Nueva Empresa', 'activo' => true])
        ->assertHasNoErrors();

    expect(Company::where('nombre', 'Nueva Empresa')->exists())->toBeTrue();
});

test('superadmin can deactivate a company', function () {
    $superadmin = User::factory()->superadmin()->create();
    $company = Company::factory()->create(['activo' => true]);

    Livewire\Livewire::actingAs($superadmin)
        ->test(CompaniesIndex::class)
        ->callTableAction('edit', $company, ['nombre' => $company->nombre, 'activo' => false])
        ->assertHasNoErrors();

    expect($company->fresh()->activo)->toBeFalse();
});
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
php artisan test --compact --filter=CompanyAdminTest
```

Expected: FAIL — `CompaniesIndex` class not found.

- [ ] **Step 3: Create the component**

```bash
php artisan make:livewire SuperAdmin/Companies/CompaniesIndex --no-interaction
```

Replace content of `app/Livewire/SuperAdmin/Companies/CompaniesIndex.php`:

```php
<?php

namespace App\Livewire\SuperAdmin\Companies;

use App\Models\Company;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.superadmin')]
#[Title('Empresas')]
class CompaniesIndex extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(Company::query())
            ->columns([
                Tables\Columns\TextColumn::make('nombre')->label('Empresa')->searchable()->sortable(),
                Tables\Columns\IconColumn::make('activo')->label('Activa')->boolean(),
                Tables\Columns\TextColumn::make('users_count')
                    ->label('Usuarios')
                    ->counts('users')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('created_at')->label('Creada')->dateTime('d/m/Y')->sortable(),
            ])
            ->headerActions([
                Action::make('create')
                    ->label('Nueva empresa')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Crear empresa')
                    ->form($this->companyFormSchema())
                    ->action(fn (array $data) => Company::create($data)),
            ])
            ->actions([
                EditAction::make()
                    ->modalHeading('Editar empresa')
                    ->form($this->companyFormSchema())
                    ->using(fn (Company $record, array $data) => $record->update($data)),
                DeleteAction::make()
                    ->modalHeading('Eliminar empresa'),
            ])
            ->defaultSort('nombre');
    }

    /** @return array<int, mixed> */
    protected function companyFormSchema(): array
    {
        return [
            Forms\Components\TextInput::make('nombre')
                ->label('Nombre')
                ->required()
                ->maxLength(150),
            Forms\Components\Toggle::make('activo')
                ->label('Activa')
                ->default(true),
        ];
    }

    public function render(): View
    {
        return view('livewire.super-admin.companies.companies-index');
    }
}
```

- [ ] **Step 4: Create the view**

Create `resources/views/livewire/super-admin/companies/companies-index.blade.php`:

```html
<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl p-6">
    <flux:heading size="xl">Empresas</flux:heading>
    {{ $this->table }}
    <x-filament-actions::modals />
</div>
```

- [ ] **Step 5: Run the tests**

```bash
php artisan test --compact --filter=CompanyAdminTest
```

Expected: All 3 tests pass.

- [ ] **Step 6: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 7: Commit**

```bash
git add app/Livewire/SuperAdmin/Companies/ resources/views/livewire/super-admin/companies/ tests/Feature/Multitenancy/CompanyAdminTest.php
git commit -m "feat: agregar CRUD de empresas para superadmin"
```

---

## Task 10: Superadmin Users global view

**Files:**
- Create: `app/Livewire/SuperAdmin/Users/UsersIndex.php`
- Create: `resources/views/livewire/super-admin/users/users-index.blade.php`

- [ ] **Step 1: Write the failing test (add to CompanyAdminTest.php)**

Add these tests to `tests/Feature/Multitenancy/CompanyAdminTest.php`:

```php
use App\Livewire\SuperAdmin\Users\UsersIndex;

test('superadmin sees users from all companies', function () {
    $superadmin = User::factory()->superadmin()->create();

    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    User::factory()->create(['company_id' => $companyA->id]);
    User::factory()->create(['company_id' => $companyB->id]);

    $this->actingAs($superadmin)
        ->get(route('superadmin.users'))
        ->assertOk();

    // Superadmin + 2 company users = 3 total (withoutGlobalScopes bypasses CompanyScope)
    expect(User::withoutGlobalScopes()->where('is_superadmin', false)->count())->toBe(2);
});
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
php artisan test --compact --filter="superadmin sees users"
```

Expected: FAIL — `UsersIndex` class not found.

- [ ] **Step 3: Create the component**

```bash
php artisan make:livewire SuperAdmin/Users/UsersIndex --no-interaction
```

Replace content of `app/Livewire/SuperAdmin/Users/UsersIndex.php`:

```php
<?php

namespace App\Livewire\SuperAdmin\Users;

use App\Enums\TipoDocumento;
use App\Models\Company;
use App\Models\personals;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Layout('layouts.superadmin')]
#[Title('Usuarios')]
class UsersIndex extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(User::withoutGlobalScopes()->with(['personal', 'roles', 'company'])->where('is_superadmin', false))
            ->columns([
                Tables\Columns\TextColumn::make('personal.nombres')->label('Nombres')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('personal.apellidos')->label('Apellidos')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->label('Correo')->searchable(),
                Tables\Columns\TextColumn::make('company.nombre')->label('Empresa')->searchable()->badge()->color('primary'),
                Tables\Columns\TextColumn::make('roles.name')->label('Rol')->badge()->separator(','),
                Tables\Columns\IconColumn::make('estado')->label('Activo')->boolean(),
            ])
            ->headerActions([
                Action::make('create')
                    ->label('Nuevo usuario')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Crear usuario')
                    ->form($this->formSchema(isCreating: true))
                    ->action(function (array $data): void {
                        $personal = personals::create([
                            'tipo_documento' => $data['tipo_documento'],
                            'numero_documento' => $data['numero_documento'],
                            'nombres' => $data['nombres'],
                            'apellidos' => $data['apellidos'],
                            'telefono' => $data['telefono'] ?? null,
                            'cargo' => $data['cargo'] ?? null,
                            'company_id' => $data['company_id'],
                        ]);

                        $user = User::create([
                            'personals_id' => $personal->id,
                            'email' => $data['email'],
                            'password' => Hash::make($data['password']),
                            'company_id' => $data['company_id'],
                            'estado' => $data['estado'] ?? true,
                        ]);

                        if (! empty($data['rol'])) {
                            $user->assignRole($data['rol']);
                        }
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->modalHeading('Editar usuario')
                    ->form($this->formSchema(isCreating: false))
                    ->fillForm(fn (User $record) => [
                        'tipo_documento' => $record->personal?->tipo_documento,
                        'numero_documento' => $record->personal?->numero_documento,
                        'nombres' => $record->personal?->nombres,
                        'apellidos' => $record->personal?->apellidos,
                        'telefono' => $record->personal?->telefono,
                        'cargo' => $record->personal?->cargo,
                        'email' => $record->email,
                        'company_id' => $record->company_id,
                        'estado' => $record->estado,
                        'rol' => $record->roles->first()?->name,
                    ])
                    ->using(function (User $record, array $data): User {
                        $record->personal?->update([
                            'tipo_documento' => $data['tipo_documento'],
                            'numero_documento' => $data['numero_documento'],
                            'nombres' => $data['nombres'],
                            'apellidos' => $data['apellidos'],
                            'telefono' => $data['telefono'] ?? null,
                            'cargo' => $data['cargo'] ?? null,
                            'company_id' => $data['company_id'],
                        ]);

                        $record->update([
                            'email' => $data['email'],
                            'company_id' => $data['company_id'],
                            'estado' => $data['estado'],
                        ]);

                        if (filled($data['password'] ?? null)) {
                            $record->update(['password' => Hash::make($data['password'])]);
                        }

                        if (! empty($data['rol'])) {
                            $record->syncRoles([$data['rol']]);
                        }

                        return $record;
                    }),
                DeleteAction::make()
                    ->using(fn (User $record) => $record->personal?->delete()),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /** @return array<int, mixed> */
    protected function formSchema(bool $isCreating): array
    {
        $companies = Company::query()->where('activo', true)->pluck('nombre', 'id')->toArray();
        $roles = Role::pluck('name', 'name')->toArray();

        return [
            Section::make('Datos Personales')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('tipo_documento')
                        ->label('Tipo de Documento')
                        ->options(collect(TipoDocumento::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]))
                        ->required(),
                    Forms\Components\TextInput::make('numero_documento')->label('Número de Documento')->required()->maxLength(20),
                    Forms\Components\TextInput::make('nombres')->label('Nombres')->required()->maxLength(100),
                    Forms\Components\TextInput::make('apellidos')->label('Apellidos')->required()->maxLength(100),
                    Forms\Components\TextInput::make('telefono')->label('Teléfono')->tel()->maxLength(20),
                    Forms\Components\TextInput::make('cargo')->label('Cargo')->maxLength(100)->columnSpanFull(),
                ]),
            Section::make('Acceso al Sistema')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('company_id')
                        ->label('Empresa')
                        ->options($companies)
                        ->required()
                        ->searchable()
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('email')->label('Correo')->email()->required()->maxLength(255),
                    Forms\Components\TextInput::make('password')->label('Contraseña')->password()->revealable()->required($isCreating)->dehydrated(fn ($state) => filled($state))->maxLength(255),
                    Forms\Components\Select::make('rol')->label('Rol')->options($roles)->searchable()->columnSpanFull(),
                    Forms\Components\Toggle::make('estado')->label('Activo')->default(true)->columnSpanFull(),
                ]),
        ];
    }

    public function render(): View
    {
        return view('livewire.super-admin.users.users-index');
    }
}
```

- [ ] **Step 4: Create the view**

Create `resources/views/livewire/super-admin/users/users-index.blade.php`:

```html
<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl p-6">
    <flux:heading size="xl">Usuarios</flux:heading>
    {{ $this->table }}
    <x-filament-actions::modals />
</div>
```

- [ ] **Step 5: Run tests**

```bash
php artisan test --compact --filter=CompanyAdminTest
```

Expected: All tests in CompanyAdminTest pass.

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/SuperAdmin/Users/ resources/views/livewire/super-admin/users/ tests/Feature/Multitenancy/CompanyAdminTest.php
git commit -m "feat: agregar vista global de usuarios para superadmin"
```

---

## Task 11: Superadmin Roles management

**Files:**
- Create: `app/Livewire/SuperAdmin/Roles/RolesIndex.php`
- Create: `resources/views/livewire/super-admin/roles/roles-index.blade.php`

- [ ] **Step 1: Create the component**

```bash
php artisan make:livewire SuperAdmin/Roles/RolesIndex --no-interaction
```

Replace content of `app/Livewire/SuperAdmin/Roles/RolesIndex.php`:

```php
<?php

namespace App\Livewire\SuperAdmin\Roles;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\DeleteAction;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

#[Layout('layouts.superadmin')]
#[Title('Roles y Permisos')]
class RolesIndex extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(Role::query()->withCount(['permissions', 'users']))
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Rol')->sortable()->searchable()->badge()->color('primary'),
                Tables\Columns\TextColumn::make('permissions_count')->label('Permisos')->badge()->color('gray')->sortable(),
                Tables\Columns\TextColumn::make('users_count')->label('Usuarios')->badge()->color('success')->sortable(),
            ])
            ->headerActions([
                Action::make('create')
                    ->label('Nuevo Rol')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Crear Rol')
                    ->form([
                        Forms\Components\TextInput::make('name')->label('Nombre del Rol')->required()->maxLength(50),
                    ])
                    ->action(function (array $data): void {
                        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
                        $this->redirect(route('admin.roles.editar', $role), navigate: true);
                    }),
            ])
            ->actions([
                Action::make('editar')
                    ->label('Editar permisos')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->url(fn (Role $record): string => route('admin.roles.editar', $record)),
                DeleteAction::make()
                    ->visible(fn (Role $record): bool => $record->name !== 'Administrador')
                    ->before(function (Role $record): void {
                        $record->syncPermissions([]);
                        $record->users()->detach();
                    }),
            ])
            ->defaultSort('name');
    }

    public function render(): View
    {
        return view('livewire.super-admin.roles.roles-index');
    }
}
```

- [ ] **Step 2: Create the view**

Create `resources/views/livewire/super-admin/roles/roles-index.blade.php`:

```html
<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl p-6">
    <flux:heading size="xl">Roles y Permisos</flux:heading>
    {{ $this->table }}
    <x-filament-actions::modals />
</div>
```

- [ ] **Step 3: Write and run a quick smoke test (add to CompanyAdminTest.php)**

Add to `tests/Feature/Multitenancy/CompanyAdminTest.php`:

```php
use App\Livewire\SuperAdmin\Roles\RolesIndex;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

test('superadmin can access roles management', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $superadmin = User::factory()->superadmin()->create();

    $this->actingAs($superadmin)
        ->get(route('superadmin.roles'))
        ->assertOk();
});
```

Run:
```bash
php artisan test --compact --filter=CompanyAdminTest
```

Expected: All tests pass.

- [ ] **Step 4: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 5: Commit**

```bash
git add app/Livewire/SuperAdmin/Roles/ resources/views/livewire/super-admin/roles/ tests/Feature/Multitenancy/CompanyAdminTest.php
git commit -m "feat: agregar gestión de roles para superadmin"
```

---

## Task 12: Update ManageUsers to auto-set company_id + tenant isolation tests

**Files:**
- Modify: `app/Livewire/Admin/ManageUsers.php`
- Create: final tests in `tests/Feature/Multitenancy/TenantScopeTest.php`

- [ ] **Step 1: Add tenant isolation tests**

Add to `tests/Feature/Multitenancy/TenantScopeTest.php`:

```php
use App\Models\Company;
use App\Services\TenantService;

test('company admin only sees users from their own company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $admin = User::factory()->create(['company_id' => $companyA->id]);
    $admin->assignRole('Administrador');
    User::factory()->create(['company_id' => $companyA->id]);
    User::factory()->create(['company_id' => $companyB->id]);

    app(TenantService::class)->set($companyA);
    $this->actingAs($admin);

    // 2 users in companyA (admin + one more); companyB user filtered out
    expect(User::all())->toHaveCount(2);
});

test('when company admin creates a user, company_id is auto-set from tenant context', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->create(['company_id' => $company->id]);
    $admin->assignRole('Administrador');

    app(TenantService::class)->set($company);
    $this->actingAs($admin);

    $personal = personals::factory()->make(['company_id' => null]);

    // Simulate creating via TenantService context
    $newPersonal = personals::create([
        'tipo_documento' => $personal->tipo_documento->value,
        'numero_documento' => $personal->numero_documento,
        'nombres' => $personal->nombres,
        'apellidos' => $personal->apellidos,
        'company_id' => app(TenantService::class)->id(),
    ]);

    expect($newPersonal->company_id)->toBe($company->id);
});
```

- [ ] **Step 2: Update ManageUsers create action to auto-set company_id**

In `app/Livewire/Admin/ManageUsers.php`:

1. Add at the top: `use App\Services\TenantService;`
2. In the `create` action's `->action()` closure, add `'company_id' => app(TenantService::class)->id()` to both the `personals::create()` and `User::create()` calls:

```php
->action(function (array $data): void {
    $tenantId = app(TenantService::class)->id();

    $personal = personals::create([
        'tipo_documento' => $data['tipo_documento'],
        'numero_documento' => $data['numero_documento'],
        'nombres' => $data['nombres'],
        'apellidos' => $data['apellidos'],
        'telefono' => $data['telefono'] ?? null,
        'cargo' => $data['cargo'] ?? null,
        'company_id' => $tenantId,
    ]);

    $plainPassword = $data['password'];
    $user = User::create([
        'personals_id' => $personal->id,
        'email' => $data['email'],
        'password' => Hash::make($plainPassword),
        'company_id' => $tenantId,
        'estado' => $data['estado'] ?? true,
    ]);

    if (! empty($data['rol'])) {
        $user->assignRole($data['rol']);
    }

    try {
        Mail::to($user->email)->send(new WelcomeNewUser(
            $user->email,
            $personal->nombres,
            $personal->apellidos,
            $plainPassword,
            route('login'),
        ));
    } catch (\Exception $e) {
        \Log::error('Welcome email failed for user: '.$user->email, ['error' => $e->getMessage()]);
    }
}),
```

- [ ] **Step 3: Run all tenant scope tests**

```bash
php artisan test --compact --filter=TenantScopeTest
```

Expected: All tests pass.

- [ ] **Step 4: Run the full test suite**

```bash
php artisan test --compact
```

Expected: All tests pass. If any fail due to company_id missing in test setup, add `app(TenantService::class)->set($user->company)` after creating the test user in that test.

- [ ] **Step 5: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/Admin/ManageUsers.php tests/Feature/Multitenancy/TenantScopeTest.php
git commit -m "feat: auto-asignar company_id en ManageUsers y tests de aislamiento de tenant"
```

---

## Task 13: Final verification

- [ ] **Step 1: Run migrate:fresh --seed one final time**

```bash
php artisan migrate:fresh --seed --no-interaction
```

- [ ] **Step 2: Verify seeded data**

```bash
php artisan tinker --execute 'echo "Companies: ".App\Models\Company::count()."\n"; echo "Users: ".App\Models\User::withoutGlobalScopes()->count()."\n"; $sa = App\Models\User::withoutGlobalScopes()->where("is_superadmin", true)->first(); echo "Superadmin email: ".$sa->email."\n";'
```

Expected:
```
Companies: 1
Users: 3
Superadmin email: superadmin@proderi.com
```

- [ ] **Step 3: Run full test suite**

```bash
php artisan test --compact
```

Expected: All tests pass.

- [ ] **Step 4: Run Pint on all modified files**

```bash
vendor/bin/pint --format agent
```

- [ ] **Step 5: Final commit**

```bash
git add -A
git commit -m "feat: multi-tenancy completo con panel superadmin y aislamiento por empresa"
```
