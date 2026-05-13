# Multi-Tenancy Design

**Date:** 2026-05-13
**Status:** Approved

## Overview

Make the application multi-tenant to handle multiple companies in a single instance. Each company operates in isolation. A superadmin (root user) manages the entire platform — companies, users, and roles — from a separate panel. Each company has its own admin who can manage that company's users and assign pre-existing roles.

---

## Section 1: Architecture & Structure

### Tenant Detection

Tenant is resolved from the authenticated user's `company_id`. No subdomains or separate databases. A `TenantService` singleton holds the current company in the request lifecycle.

```
app/Services/TenantService.php
```

```php
class TenantService
{
    private ?Company $current = null;

    public function set(Company $company): void { $this->current = $company; }
    public function get(): ?Company             { return $this->current; }
    public function active(): bool              { return $this->current !== null; }
    public function id(): ?string               { return $this->current?->id; }
}
```

Registered as a singleton in `AppServiceProvider`.

### Global Scope

`CompanyScope` is applied to all tenant-scoped models. Superadmin users bypass it automatically.

```
app/Models/Scopes/CompanyScope.php
```

Applied to: `User`, `personals`, `orden_jobs`, `reportes`, `Ubicacion`.

Scope logic:
- If `is_superadmin = true` on the authenticated user → no filter applied.
- Otherwise → `WHERE company_id = {TenantService::id()}`.

### Middleware

| Middleware | File | Purpose |
|---|---|---|
| `SetTenantContext` | `app/Http/Middleware/SetTenantContext.php` | Runs after auth; resolves company from user and calls `TenantService::set()`. Redirects superadmin to `/superadmin` if they hit the regular panel. |
| `RequireSuperAdmin` | `app/Http/Middleware/RequireSuperAdmin.php` | Guards `/superadmin/*` routes. Returns 403 for non-superadmin users. |

### Route Structure

```
routes/
  web.php           — imports regular routes (includes superadmin.php and existing modules)
  superadmin.php    — prefix: /superadmin, middleware: [auth, RequireSuperAdmin]
```

### Directory Structure

```
app/
  Models/
    Company.php
    Scopes/
      CompanyScope.php
  Services/
    TenantService.php
  Http/Middleware/
    SetTenantContext.php
    RequireSuperAdmin.php
  Livewire/
    SuperAdmin/
      Dashboard.php
      Companies/
        CompaniesIndex.php
        CompanyForm.php
      Users/
        UsersIndex.php
        UserForm.php
      Roles/
        RolesIndex.php

resources/views/
  layouts/
    superadmin.blade.php
  livewire/
    super-admin/
      dashboard.blade.php
      companies/
        companies-index.blade.php
        company-form.blade.php
      users/
        users-index.blade.php
        user-form.blade.php
      roles/
        roles-index.blade.php
```

---

## Section 2: Data Layer

### Migrations

New migration files to formalize existing DB columns (DB already has these; migrations are needed for `migrate:fresh`):

| Migration | Purpose |
|---|---|
| `create_companies_table` | `id` (UUID), `nombre`, `logo` (nullable), `activo` (bool), timestamps |
| `add_company_id_to_users_table` | `company_id` UUID nullable FK → companies |
| `add_company_id_to_personals_table` | `company_id` UUID nullable FK → companies |
| `add_company_id_to_orden_jobs_table` | `company_id` UUID nullable FK → companies |
| `add_company_id_to_reportes_table` | `company_id` UUID nullable FK → companies |
| `add_is_superadmin_to_users_table` | `is_superadmin` bool default false |

### Company Model

```php
// app/Models/Company.php
class Company extends Model
{
    use HasUuids;

    protected $fillable = ['nombre', 'logo', 'activo'];
    protected $casts = ['activo' => 'boolean'];

    public function users(): HasMany { ... }
}
```

### CompanyScope Implementation

```php
class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if (!$user || $user->is_superadmin) {
            return;
        }

        $tenantId = app(TenantService::class)->id();

        if ($tenantId) {
            $builder->where($model->getTable().'.company_id', $tenantId);
        }
    }
}
```

### Seeders

| Seeder | Purpose |
|---|---|
| `CompanySeeder` | Creates one default company: "Empresa Demo" |
| `UserSeeder` | Creates superadmin (`is_superadmin=true`, no `company_id`) + company admin (`empresa.admin` role) |
| `PermissionsSeeder` | Adds `empresa.admin` permission under module "Administración"; assigns to `Administrador` role |

DatabaseSeeder order: `PermissionsSeeder` → `CompanySeeder` → `UserSeeder`.

---

## Section 3: Superadmin Panel

Separate panel at `/superadmin` with its own layout. Superadmin never enters the regular `/dashboard`.

### Components

| Component | Route | Purpose |
|---|---|---|
| `SuperAdmin\Dashboard` | `GET /superadmin` | Global metrics: companies count, users count, orders count |
| `SuperAdmin\Companies\CompaniesIndex` | `GET /superadmin/companies` | Table of companies with create/edit/delete |
| `SuperAdmin\Companies\CompanyForm` | Modal (inline) | Fields: nombre, logo, activo |
| `SuperAdmin\Users\UsersIndex` | `GET /superadmin/users` | All users across all companies, filterable by company |
| `SuperAdmin\Users\UserForm` | Modal (inline) | Fields: email, password, company_id, role |
| `SuperAdmin\Roles\RolesIndex` | `GET /superadmin/roles` | Full CRUD of roles and permissions (Spatie) |

### Layout

`resources/views/layouts/superadmin.blade.php` — standalone sidebar without field-work modules. Links: Dashboard, Companies, Users, Roles.

### Global Data Access

SuperAdmin components query without `CompanyScope` (scope is bypassed for `is_superadmin = true`). They see all rows across all companies.

### Redirect Logic

- Superadmin hits `/dashboard` → `SetTenantContext` redirects to `/superadmin`.
- Regular user hits `/superadmin/*` → `RequireSuperAdmin` returns 403.

---

## Section 4: Company Admin Panel

Company admin uses the regular panel (`/dashboard`) with the existing UI. No new panel is created.

### Capabilities

| Action | Permission | Notes |
|---|---|---|
| View company users | `usuarios.ver` | CompanyScope filters automatically |
| Create users | `usuarios.crear` | `company_id` set automatically from `TenantService` |
| Edit users | `usuarios.editar` | Only own company's users |
| Assign roles | `usuarios.asignar-roles` | Assigns globally defined roles (created by superadmin) |
| Edit company settings | `empresa.admin` | Edit name/logo of own company only |

### Restrictions

- Cannot create, edit, or delete roles (those routes are in `/superadmin/roles`).
- Cannot see users from other companies (CompanyScope enforces this).
- `company_id` field is not exposed in user forms — set server-side only.

### Auto-assignment

When a company admin creates a user, `company_id` is taken from `TenantService::id()` and written in the controller/Livewire action — never from user input.

---

## Section 5: Testing Strategy

### Unit Tests

- `TenantService`: set/get/active/id methods.
- `CompanyScope`: scope applies filter when tenant active, skips filter for superadmin.

### Feature Tests

| Scenario | Expected |
|---|---|
| Superadmin sees data from all companies | All rows returned |
| Company admin sees only own company's data | Filtered rows |
| Regular user hits `/superadmin` | 403 |
| Superadmin hits `/dashboard` | Redirects to `/superadmin` |
| Company admin creates user | `company_id` auto-assigned from tenant context |
| Company admin cannot access roles CRUD | 403 |
| `migrate:fresh` + seeders | Superadmin + company + company admin created correctly |

---

## Out of Scope

- Email-based tenant detection (subdomain routing).
- Per-company database isolation.
- Company self-registration (superadmin creates companies manually).
- Billing or subscription management.
