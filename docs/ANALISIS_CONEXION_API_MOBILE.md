# Análisis de conexión API ↔ App Móvil (AlenaZentrix_Mobile)

**Fecha:** 2026-08-10
**Alcance:** Verificar si la API de `AlenaZentrix` (este repo) y el cliente `AlenaZentrix_Mobile` (proyecto local NativePHP en `d:\www\AlenaZentrix_Mobile`) se conectan correctamente, y dejar construido el endpoint de revisión de Auditoría que faltaba.

---

## Resumen ejecutivo

| Flujo | Estado | Detalle |
|---|---|---|
| Reportes del técnico (incluye `auditoria_campo`) | ✅ **Conectado y funcional** | Verificado campo a campo entre el contrato documentado en el móvil (`API_TECNICO.md`) y las reglas de validación reales del backend. Sin discrepancias. |
| Órdenes, catálogos, subida/lectura de archivos, auth | ✅ **Conectado y funcional** | Mismo resultado: contrato documentado y código real coinciden. |
| Revisión de Auditoría (Confirmado/Negativo de hallazgos de Fraudes) | 🆕 **Construido hoy, aún no consumido** | No existía en la API. Se construyó `GET/PUT /api/auditorias/{auditoria}`, probado con 8 tests. La app móvil **no tiene todavía** pantalla ni servicio que lo llame (no aparece en ninguna fase de sus docs). Queda listo para cuando se implemente esa pantalla. |

**Conclusión:** todo lo que la app móvil ya usa hoy funciona correctamente contra esta API. Lo único que no estaba conectado (porque no existía) era la revisión de auditorías, y ya quedó construido y probado del lado del backend — falta la parte cliente en el proyecto móvil.

---

## 1. Metodología

Se comparó, archivo contra archivo:

- El contrato documentado por el propio equipo móvil: `AlenaZentrix_Mobile/API_TECNICO.md`.
- El código cliente real que consume la API: `AlenaZentrix_Mobile/app/Services/Api/*` (`OrdenService`, `AuthService`, `UploadService`, `ArchivoService`, `CatalogoService`).
- El código servidor real: `AlenaZentrix/app/Http/Controllers/Api/**`, `app/Http/Resources/Api/**`, `routes/api.php`.
- Los enums compartidos (`TipoServicio`, `EstadoOrden`, `EstadoAuditoria`) en ambos lados.

No se hicieron llamadas HTTP reales entre los dos proyectos (no se levantó la app móvil); el análisis es de código estático + tests automatizados del lado del backend.

---

## 2. Flujo de Reportes (técnico) — verificado sin cambios

### 2.1 Autenticación

- `POST /api/auth/login` (`AuthController::login`) devuelve `token`, y `user.{id,email,nombre,iniciales,personals_id,company_id}`.
- `AuthService::validateLoginResponse()` en el móvil exige explícitamente `personals_id` y `company_id` en la respuesta — **ambos están presentes** en la respuesta real (el ejemplo en `API_TECNICO.md` §2.1 no muestra `company_id` en el JSON de ejemplo, pero sí lo documenta en la sección de Postman; es un detalle menor de documentación, no un bug de la API).

### 2.2 Tipos de servicio (`TipoServicio`)

Se compararon los 7 valores del enum `App\Enums\TipoServicio` contra la tabla de `API_TECNICO.md` y contra `OrdenService::normalizarTipoServicio()` en el móvil:

| Valor backend | Doc móvil | Cliente móvil | Coincide |
|---|---|---|---|
| `fraudes` | ✅ | ✅ | ✅ |
| `revision_medidores` | ✅ | ✅ | ✅ |
| `investigacion` | ✅ | ✅ | ✅ |
| `gestion_legal` | ✅ | ✅ | ✅ |
| `vigilancia` | ✅ | ✅ | ✅ |
| `Gestión de Riesgo` (con tilde y espacio, literal en BD) | ✅ advertido explícitamente | ✅ el cliente normaliza tanto `"Gestión de Riesgo"` como `"gestion_de_riesgo"` a una clave interna estable | ✅ |
| `auditoria_campo` | ✅ | ✅ | ✅ |

### 2.3 Reporte — campos por tipo de servicio

Se comparó `ReporteController::validar()` / `reglasFraudes()` / `reglasVigilancia()` / `reglasInvestigacion()` / `reglasGestionLegal()` / `reglasAuditoriaCampo()` contra la sección 7 de `API_TECNICO.md` (tabla de campos por tipo de servicio). **Coinciden exactamente** en nombres, tipos y obligatoriedad, incluyendo los casos condicionales:
- `foto_detector_fuga` requerido solo si `hay_fuga_gas = true` (Fraudes).
- Campos de acompañante requeridos solo si `acompanamiento_tecnico = true` (Investigación).
- `descripcion_novedad` requerido solo si `hay_novedad = true` (Vigilancia).

### 2.4 Idempotencia / offline

- El campo `sincronizado` en `OrdenResource` y la lógica de "reporte ya sincronizado" / "turno ya sincronizado" en `ReporteController::store()` coinciden con lo que `OrdenService::sendOrQueueReport()` y `aplicarReportesPendientes()` esperan del lado móvil (incluyendo el manejo especial de Vigilancia, que siempre usa POST y nunca cierra vía PUT).

### 2.5 Archivos

- `POST /api/upload` devuelve `{ "path": "{company_id}/{directorio}/{archivo}" }` — coincide con lo documentado y con las extensiones permitidas en `UploadService::ALLOWED_EXTENSIONS` (idénticas a `UploadController`).
- `GET /api/archivos/{path}` (URL firmada, 15 min, verificación de `company_id`) coincide con `ArchivoService`.

**Conclusión de esta sección:** no se requiere ningún cambio. El flujo de reportes, incluyendo Auditoría de Campo, está completamente alineado entre ambos proyectos.

---

## 3. Flujo de Revisión de Auditoría — construido en esta sesión

### 3.1 Qué existía antes

Solo en el panel web (`App\Livewire\Auditor\AuditoriaRevisar`), accesible por:
- El rol **Auditor** (permiso `auditorias.ver`), para cualquier auditoría de su empresa.
- El propio **técnico** dueño de la orden, sin ese permiso (ruta `tecnico.mis-ordenes.auditar`).

No existía ningún endpoint API, ni ninguna pantalla/servicio del lado móvil (no aparece en `API_TECNICO.md` ni en ninguna fase de `AlenaZentrix_Mobile/docs/fases/`).

### 3.2 Qué se construyó

| Archivo | Propósito |
|---|---|
| `app/Http/Requests/Api/AuditoriaGuardarRequest.php` | Autorización (Auditor o técnico dueño de la orden) + validación (`evidencias`, `resultado`, `tipo_anomalia_id`, `metros_cubicos_recuperados`, `contrato`, `observaciones`). |
| `app/Http/Controllers/Api/AuditoriaController.php` | `show()` y `guardar()`, replicando exactamente la lógica de `AuditoriaRevisar`. |
| `app/Http/Resources/Api/AuditoriaResource.php` | Respuesta JSON, reutilizando `ReporteResource` y `OrdenResource` existentes. |
| `app/Http/Resources/Api/ReporteResource.php` (ajuste) | Se agregó el campo `comercio` (nombre y tipo) cuando la relación está cargada, necesario para que quien audite sepa a qué comercio corresponde el hallazgo de Fraudes. |
| `routes/api.php` | Nuevas rutas dentro del grupo `auth:sanctum` + `SetTenantContext`. |

### 3.3 Endpoints nuevos

```
GET  /api/auditorias/{auditoria}   → detalle de la auditoría (reporte + orden + evidencias)
PUT  /api/auditorias/{auditoria}   → registra evidencias + resultado (confirmado/negativo)
```

Autorización: mismo criterio que el panel web —
```php
$user->can('auditorias.ver') || $auditoria->reporte?->ordenJob?->personals_id === $user->personals_id
```

### 3.4 Pruebas

`tests/Feature/Api/AuditoriaApiTest.php` — 8 tests, todos en verde:
- Requiere autenticación (401).
- Técnico dueño de la orden puede ver su propia auditoría sin permiso `auditorias.ver`.
- Técnico que no es dueño y sin permiso recibe 403.
- Usuario con rol Auditor puede ver cualquier auditoría.
- `PUT` guarda evidencias/resultado y setea `auditado_por_id` + `fecha_auditoria`.
- `PUT` actualiza el contrato de la orden cuando se envía.
- `PUT` rechaza `resultado` distinto de `confirmado`/`negativo` (422).
- `PUT` devuelve 403 para usuario no autorizado.

```
tests: AuditoriaApiTest → 8 passed (18 assertions)
tests: ApiTecnicoTest (regresión del flujo existente) → 15 passed (45 assertions)
vendor/bin/pint --dirty → passed
```

### 3.5 Pendiente (fuera del alcance de esta sesión)

- El proyecto **móvil no tiene todavía pantalla ni servicio** que consuma `GET/PUT /api/auditorias/{auditoria}`. El endpoint queda listo, pero la conexión real de este flujo específico solo se podrá verificar cuando se implemente esa parte del lado móvil.

---

## 4. Hallazgos menores (no bloqueantes)

- `app/Http/Requests/Api/Tecnico/ReporteRequest.php` existe pero **no se usa**: `ReporteController` valida manualmente con un método privado `validar()`. El `FormRequest` no usado tiene además nombres de tabla desactualizados (`tipo_comercios` en vez de `tipos_comercio`, etc.) que no coinciden con las migraciones actuales. No afecta el funcionamiento real (nunca se ejecuta), pero es código muerto que vale la pena limpiar o eliminar en una futura sesión.

---

## 5. Archivos modificados/creados en esta sesión

**Nuevos:**
- `app/Http/Requests/Api/AuditoriaGuardarRequest.php`
- `app/Http/Controllers/Api/AuditoriaController.php`
- `app/Http/Resources/Api/AuditoriaResource.php`
- `tests/Feature/Api/AuditoriaApiTest.php`
- `docs/ANALISIS_CONEXION_API_MOBILE.md` (este documento)

**Modificados:**
- `app/Http/Resources/Api/ReporteResource.php` (campo `comercio` agregado)
- `routes/api.php` (rutas `api/auditorias/{auditoria}`)
