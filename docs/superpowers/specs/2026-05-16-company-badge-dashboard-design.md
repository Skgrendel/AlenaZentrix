# Company Badge en Dashboard — Diseño

**Fecha:** 2026-05-16

## Objetivo

Mostrar el logo y nombre de la empresa activa en la sección de saludo del dashboard, para que el usuario identifique visualmente en qué espacio de trabajo está operando.

## Alcance

Un solo archivo: `resources/views/dashboard.blade.php`.

## Comportamiento

- La empresa se obtiene con `auth()->user()->company` (relación BelongsTo ya definida en `User`).
- **Con logo:** renderizar `<img src="{{ $company->logo }}">` (la URL ya está almacenada en `Company::logo`).
- **Sin logo:** renderizar `<flux:avatar :name="$company->nombre" />` con las iniciales automáticas.
- **Sin empresa** (superadmin u otro caso sin `company_id`): no se renderiza nada.

## Estructura visual

La sección de saludo pasa de columna simple a fila `flex justify-between`:

- **Izquierda:** `h1` con saludo + `p` con fecha (sin cambios)
- **Derecha:** logo o avatar + nombre de empresa en texto pequeño

## Datos

| Campo   | Modelo   | Tipo   | Notas                            |
|---------|----------|--------|----------------------------------|
| `logo`  | Company  | string | URL externa, puede ser null      |
| `nombre`| Company  | string | Siempre presente si hay empresa  |

## Fuera de alcance

- Sidebar, header móvil u otras vistas.
- Subida de archivos de logo (ya existe, se usa la URL almacenada).
- Caché de la consulta (la relación Eloquent ya es eficiente).
