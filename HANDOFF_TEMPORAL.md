# HANDOFF TEMPORAL — estado de la sesión (borrar al terminar)

Creado: 2026-10-29. Dos proyectos hermanos, sin NINGÚN commit hecho en ninguno:

| Proyecto | Ruta | Stack |
|---|---|---|
| WEB + API | `E:\www\AlenaZentrix` | Laravel 13, Livewire 4, Filament forms, Sanctum, Pest |
| APP MÓVIL | `E:\www\AlenaZentrix_Mobile` | Laravel 12, NativePHP, Filament 5, Livewire 4, Pest |

Cada repo tiene su `CLAUDE.md` (reglas Boost): leerlo primero. Reglas clave: seguir convenciones existentes, tests Pest por cada cambio, correr `vendor/bin/pint --dirty --format agent` antes de cerrar, no crear documentación salvo petición, no tocar dependencias, **no tocar `composer.json` de la móvil (tiene un cambio ajeno del usuario)**.

## Cómo ejecutar cosas (Windows)
- `php` NO está en el PATH de bash: usar **PowerShell**.
- Tests: `php -d memory_limit=1G artisan test --compact tests/Feature/<Archivo>.php` (un archivo por vez; la suite completa se queda sin memoria con 128 MB).
- En PowerShell un `|` dentro de `--filter` se interpreta como pipe: correr archivo por archivo.
- Web: la BD de pruebas MySQL `alenacentrix_test` se comparte; si hay agentes en paralelo usar una BD propia (`$env:DB_DATABASE='alenacentrix_test_xx'`).
- Móvil: no hay `.env` en esta máquina; `phpunit.xml` ya trae `APP_KEY` de prueba, los warnings `file_get_contents(.env)` son del entorno.
- Si el usuario no ve cambios visuales: `npm run build` o `composer run dev`.

## Qué se hizo (todo con tests en verde al cierre de cada bloque)
1. **Subtipo `visita_calidad_suspensiones`** (Auditoría de Campo) de punta a punta en la web: crear orden, formularios técnico crear/editar (`ReporteCreate`/`ReporteEdit`), revisión coordinador/auditor, PDF, informe Excel, API móvil. Datos en `reportes.datos_servicio`:
   `categoria_campo`, `suspension_ejecutada`, `metodo_suspension`, `serie_sonda`, `serie_caps`, `lectura_medidor`, `anomalias_medidor` (array de ids de `TipoAnomalia`), `condicion_sellos`, `elementos_rotos`, `motivo_no_ejecucion`, `justificacion_no_ejecucion`, fotos en `fotos_fachada|fotos_suspension|fotos_medidor|fotos_no_ejecucion`.
2. **`orden_jobs.lectura_referencia`** (migración `2026_10_28_222035_...`): la pide quien crea la orden; `OrdenJob::lecturaCoincide()` compara. Se muestra al técnico y se marca Coincide/No coincide en revisión/PDF/informe.
3. **Botones Sí/No** (macro `ToggleButtons::siNo()` en `AppServiceProvider`) reemplazan los interruptores en `ReporteCreate/Edit`; guardan siempre booleano. `suspension_ejecutada` es caso aparte (obligatorio, sin preselección).
4. **Campo `geo_referencia` eliminado** de los formularios (web y móvil); la API lo acepta opcional y conserva el valor previo al editar. La ubicación sale de latitud/longitud (GPS).
5. **API** (`E:\www\AlenaZentrix`): `OrdenResource` expone `tipo_auditoria_campo(+_label)`, `tecnico`, `comentario_tecnico`, `lectura_referencia`, `turnos_por_corregir`; `ReporteResource` expone ids de catálogo y `requiere_correccion`; nuevas rutas `GET/PUT /api/tecnico/ordenes/{orden}/reportes/{reporte}` para corregir un turno de Vigilancia sin cambiar el estado de la orden.
6. **App móvil**: `EvidenciasUploader`/`EvidenciasField`/`WithReportEvidences` con grupos de fotos (`->grupo()`), guardas en eventos nativos; páginas nuevas `ReporteAuditoriaSuspensiones` y `ReporteAuditoriaMedidoresDigitales`; enrutado por subtipo en `RedirigeAFormularioReporte`; corrección de turnos de Vigilancia; endurecimiento de la capa de servicios/offline (401/422/429, timeouts de subida, etc.).
7. **Corregido tras auditoría**: PDF de Gestión Legal (500 por variables de fotos mal pegadas) y de Suspensiones (sin fotos); `requiere_correccion=false` en `actualizarFraudes`.

## EN CURSO al momento de este traspaso (5 agentes en paralelo; resultados desconocidos)
Se pidió corregir TODO lo de la auditoría final, en orden de prioridad. Cada agente tenía archivos propios. **Verificar con `git status`/`git diff` en ambos repos qué alcanzó a hacerse y completar lo que falte.**

**WEB — Agente W1 (seguridad/API)**: `SetTenantContext`, `CompanyScope`, `AuthController`, `OrdenController`, `OrdenResource`, `AuditoriaController`, `AuditoriaGuardarRequest`, `auditoria` model, `routes/api.php`, `bootstrap/app.php`, `config/api.php`. Tareas: (1) fail-closed del tenant (empresa inactiva/sin company ⇒ 403 JSON; login rechaza empresa inactiva; superadmin con token API ⇒ 403 JSON no 302); (2) middleware que rechaza usuario desactivado + revocar tokens al desactivar (ya aparecen `UserObserver` y `ApiRateLimiters` registrados en `AppServiceProvider`); (3) `OrdenController::show/iniciar/cerrarVigilancia` verifican propiedad; (4) auditorías: 404 si no hay orden visible, exigir `auditorias.registrar`, sin autoauditoría, solo si pendiente, rutas de evidencias sin `..`; (5) versión mínima: cabecera `X-App-Version` (>= 1.1.0) para ver/operar subtipos nuevos, 426 si no; (6) `puede_editar_reporte` coherente; (7) `mis-ordenes`: Completadas últimos 90 días (`?desde=`), sin N+1; (8) throttles (upload más estricto); (9) `iniciar`/`cerrar` idempotentes.
Tests: `tests/Feature/ApiSeguridadW1Test.php`. BD de pruebas propia: `alenacentrix_test_w1`.

**WEB — Agente W2 (ReporteController)**: `ReporteController.php`, `ReporteResource.php`, migración nueva. Tareas: (1) `reglaPathDeLaEmpresa` estricta (sin `..`, `//`, `\`); (2) aceptar al editar las rutas ya guardadas del reporte (fotos web sin prefijo company); (3) escritura de reportes solo dueño (no `ordenes.ver`); (4) Vigilancia: idempotencia por `fecha_turno` antes del chequeo de estado, permitir turno con orden EnRevision, PUT idempotente; (5) `DB::transaction` + `lockForUpdate`; (6) `requiere_correccion=false` en `actualizarFraudes`; (7) foto del detector solo obligatoria en `store`; (8) `Rule::exists` con `company_id` en catálogos; (9) validar formato/tamaño de firmas; (10) normalizar `actividades[*].actividades` ausente a `''`; (11) migración: `sincronizado=false` en órdenes rechazadas con `sincronizado=true`.
Tests: `tests/Feature/ApiReporteW2Test.php`. BD: `alenacentrix_test_w2`.

**WEB — Agente W3 (revisión/PDF/informes)**: `ReportesVigilanciaIndex`, `ReporteRevisar` (+blade), `AuditoriaRevisar` (+blade), `ReportePdfController` + `resources/views/pdf/*`, `InformesIndex`, `ReporteCreate/Edit` (+blades). Tareas: (1) rechazo masivo de Vigilancia marca todos los turnos `requiere_correccion` y deja la orden EnProgreso; (2) blades con `?? '—'` en actividades; (3) fallback a `$orden->comentario_usuario/tecnico` en revisión/PDF de Auditoría de Campo; (4) `rechazar` valida estado y notifica al autor del turno; (5) foto detector solo requerida si no existe; (6) `actualizarUbicacion` no pisa con null, Bypass en `submitFraudes`; (7) Informes: columnas de Investigación/Legal/Riesgo y todos los turnos de Vigilancia; (8) limpieza. Tests: `tests/Feature/WebRevisionW3Test.php`. BD: `alenacentrix_test_w3`. También debía arreglar los 2 tests web que ya fallaban (`AuditoriaCampoTest` "evidenciasUrls…" y `Tecnico/ReporteEditTest` "muestra archivos sin previsualizacion").

**MÓVIL — Agente M1 (offline/sesión/cliente)**: `app/Services/Offline/**`, `OfflineSyncManager`, `ApiClient`, `TokenService`, `AuthService`, `UploadService`, `ArchivoService`. Tareas: ítems de cola con 4xx permanente ⇒ `needs_attention` + backoff + `retryItem/discardItem` + UI mínima; reconciliar reintentos con 422 "ya aplicado"; enviar `X-App-Version` (config `api.app_version`, default 1.1.0), manejar 426 y 403-empresa-inactiva, traducir 401; login de auditor sin técnico y no dejar token huérfano; lock de sync (TTL 900 s) y escritura serializada de la cola; token cifrado; purga de `offline-secure` y cuota de caché de medios; sesión offline.

**MÓVIL — Agente M2 (órdenes/formularios)**: `OrdenService`, `AuditoriaService`, `CatalogoService`, `MisOrdenes`, `OrdenDetalle`, `EditarReportes`, `Auditorias`, `RegistrarAuditoria`, `RedirigeAFormularioReporte`, `EditarReporteExistente`, `ReporteFraudes`, `ReporteVigilancia`, `ReporteAuditoriaSuspensiones`, `ReporteAuditoriaMedidoresDigitales`. Tareas: cerrar Vigilancia sincroniza antes y bloquea si quedan pendientes; manejar 426 ("Actualiza la aplicación"); sin override optimista si el envío está bloqueado; `puede_auditar`/servicios auditables desde la API; foto del detector opcional al corregir; normalizar la orden tras `iniciar`; formatos de `fecha_turno`; limpiezas.

## Pasos de cierre (hacer en orden cuando los agentes terminen o se corten)
1. `git status` y `git diff --stat` en ambos repos; revisar que nada quedó a medias (archivos con sintaxis rota: `php -l`).
2. `vendor/bin/pint --dirty --format agent` en ambos repos.
3. Correr en solitario (sin otros procesos usando la BD) y archivo por archivo:
   - Web: `ToggleButtonsSiNoTest`, `Suspensiones*`, `ApiTecnicoTest`, `ApiSecurityTest`, `ApiSanctumTest`, `ApiVigilanciaCorreccionTest`, `ApiReporteMovilTest`, `ApiSeguridadW1Test`, `ApiReporteW2Test`, `WebRevisionW3Test`, `ReportePdfTest`, `InformesIndexTest`, `AuditoriaCampoTest`, `Tecnico/*`, `Api/AuditoriaApiTest`.
   - Móvil: todos los de `tests/Feature` y `tests/Unit`, uno por uno.
4. Borrar BDs temporales si sobraron: `alenacentrix_test_w1`, `_w2`, `_w3`, `_susp`.
5. Reportar al usuario qué se cumplió y qué no; avisar del impacto de los endurecimientos (qué usuarios pierden qué acceso).

## Fallos de tests CONOCIDOS que ya existían (no son de estos cambios)
- Web: `AuditoriaCampoTest` "evidenciasUrls clasifies images…" (método inexistente en `ReporteRevisar`), `Tecnico/ReporteEditTest` "muestra archivos sin previsualizacion" (monta `ReporteEdit` sin reporte; verificado que el código original ya tenía el `firstOrFail`). W3 tenía el encargo de arreglarlos.
- Móvil: `OfflineStoreTest` "uploads local evidence before sending its report" y `AuditoriaServiceTest` "sincroniza evidencias seguras…" (permisos de archivo temporal en Windows con el mock).

## Pendiente del usuario (no automatizable)
- `php artisan migrate` en la web (columna `lectura_referencia` y migraciones nuevas de esta ronda).
- Probar en un celular real: cámara/galería con varios grupos de fotos, GPS, cola offline real, render de botones Sí/No en el WebView.
- Confirmar `APP_LOCALE=es` en el `.env` de producción (en el código el default es `en`).
- Decidir: ¿es intencional que el login móvil exija técnico asociado (auditor sin técnico)? (M1 implementa un "modo auditor" condicional).
- Decidir: ¿PWA/offline en la web para Android o iPhone? (solo se discutió; nada implementado).
- Pendiente opcional no hecho: convertir a botones Sí/No los interruptores que aún queden en las pantallas MÓVILES (`ReporteAuditoriaCampo` general y otras usan `Toggle`); actualizar `API_TECNICO.md` (web y móvil) con los endpoints/campos nuevos y quitar `geo_referencia`; commits (el usuario no los pidió aún).
- Producción: `composer audit` no se ejecutó; `league/flysystem-aws-s3-v3` está muy desactualizado.
