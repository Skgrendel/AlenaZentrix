# HANDOFF TEMPORAL — estado final de la sesión (borrar cuando ya no haga falta)

Actualizado: 2026-10-29. Dos proyectos hermanos:

| Proyecto | Ruta | Stack |
|---|---|---|
| WEB + API | `E:\www\AlenaZentrix` | Laravel 13, Livewire 4, Filament forms, Sanctum, Pest |
| APP MÓVIL | `E:\www\AlenaZentrix_Mobile` | Laravel 12, NativePHP, Filament 5, Livewire 4, Pest |

Cada repo tiene su `CLAUDE.md` (reglas Boost): leerlo primero. Reglas clave: seguir convenciones, test Pest por cada cambio, `vendor/bin/pint --dirty --format agent` al cerrar, no crear documentación salvo petición, no tocar dependencias, **no tocar `composer.json` de la móvil (cambio ajeno del usuario)**.

## Cómo ejecutar cosas (Windows)
- `php` NO está en el PATH de bash: usar **PowerShell**.
- Tests: `php -d memory_limit=1G artisan test --compact tests/Feature/<Archivo>.php`, un archivo por vez (la suite entera se queda sin memoria con 128 MB). Un `|` en `--filter` se interpreta como pipe en PowerShell.
- Web: la BD de pruebas MySQL `alenacentrix_test` es compartida; con agentes en paralelo usar una BD propia (`$env:DB_DATABASE='...'`).
- Móvil: no hay `.env` en esta máquina; `phpunit.xml` trae `APP_KEY` de prueba; los warnings `file_get_contents(.env)` son del entorno. Los tests que simulan `TokenService` con Mockery deben incluir `shouldReceive('getOfflineOwnerId')->andReturn(null)`.
- Para comparar contra el código original: `git worktree` del commit `420cab4` NECESITA copia real de `vendor` y de `public/build` (con junction carga las clases del proyecto actual). Ya se hizo y se eliminó.

## Estado: TODO LO PLANEADO ESTÁ IMPLEMENTADO Y VERIFICADO
**Web (66 archivos de test)**: pasan todos, salvo 6 archivos con fallos que YA EXISTÍAN en el código original (verificado contra `420cab4`, mismo resultado): `ExampleTest` (GET `/` da 302), `OrdenesTrabajoIndexTest` (1 test), `Auth/AuthenticationTest` (1), `Auth/TwoFactorChallengeTest` (1), `Settings/ProfileUpdateTest` (2), `Settings/SecurityTest` (5). Son restos del starter kit (2FA/perfil) y no se tocaron.
**Móvil (24 archivos de test)**: pasan todos (con warnings del `.env` ausente). Los 2 que fallaban por permisos en Windows ahora pasan.

### Funcionalidades (web + API)
- Subtipo de Auditoría de Campo `visita_calidad_suspensiones`: crear orden, formularios técnico crear/editar, revisión coordinador/auditor, PDF, informe Excel, API. Datos en `reportes.datos_servicio` (`categoria_campo`, `suspension_ejecutada`, `metodo_suspension`, `serie_sonda`, `serie_caps`, `lectura_medidor`, `anomalias_medidor` [ids de `TipoAnomalia`], `condicion_sellos`, `elementos_rotos`, `motivo_no_ejecucion`, `justificacion_no_ejecucion`, fotos en `fotos_fachada|fotos_suspension|fotos_medidor|fotos_no_ejecucion`).
- `orden_jobs.lectura_referencia` + `OrdenJob::lecturaCoincide()`; Coincide/No coincide en revisión/PDF/informe.
- Botones Sí/No (macro `ToggleButtons::siNo()` en `AppServiceProvider`) en lugar de interruptores; campo `geo_referencia` eliminado de formularios.
- Vigilancia: el rechazo (por turno o masivo) marca `requiere_correccion` y la orden sigue EnProgreso; API `GET/PUT /api/tecnico/ordenes/{orden}/reportes/{reporte}`; migración `2026_10_29_120000_normalizar_vigilancia_rechazada` reabre las rechazadas del flujo anterior; `ReporteEdit` ya no da 404 sin turnos.
- Endurecimiento de la API: tenant fail-closed (403 si empresa inactiva/sin empresa; el tenant se fija ANTES del route-model-binding, lo que corrigió que las pantallas web resolvieran órdenes de otras empresas), usuario desactivado ⇒ tokens revocados y rechazados, propiedad de orden en show/iniciar/cerrar, auditorías con permiso `auditorias.registrar`/sin autoauditoría/solo pendientes, rutas de fotos estrictas (sin `..`), escritura de reportes solo dueño o `ordenes.editar`, idempotencia y bloqueo transaccional en reportes, catálogos y anomalías validados por empresa, firmas validadas, límites de uso (throttle), `mis-ordenes` con Completadas de últimos 90 días, versión mínima de app (`X-App-Version` ≥ 1.1.0 para subtipos nuevos; 426 si no), login devuelve `user.permissions`.
- Revisión/PDF/Informes: actividades vacías no revientan, comentarios con fallback a la orden, rechazo valida estado y notifica al autor del turno, Excel con columnas de Investigación/Legal/Riesgo y todos los turnos de Vigilancia.

### Funcionalidades (app móvil)
- Páginas nuevas `ReporteAuditoriaSuspensiones` y `ReporteAuditoriaMedidoresDigitales`; enrutado por subtipo (`RedirigeAFormularioReporte`); grupos de fotos independientes (`EvidenciasField->grupo()`); corrección de turnos de Vigilancia.
- Cola sin conexión: ítems con 4xx permanente pasan a `needs_attention` (no se reintentan solos), backoff exponencial, UI "Envíos pendientes con problemas" (reintentar/descartar; ancla `#envios-con-problemas` conectada al aviso de la orden), reconciliación de reintentos ya aplicados (422/409), limpieza de evidencias huérfanas, candado de sync 900 s, escritura serializada, token cifrado, cuota de caché de medios, purga de copias descifradas al arrancar y en logout.
- Cliente API: `X-App-Version`, 426/403-empresa-inactiva/401 con mensajes en español, timeout de subida de 120 s, directorio de subida saneado.
- Login: "modo auditor" para usuarios sin técnico con permisos de auditoría; se revoca el token si el login se rechaza. **Decisión tomada por un agente (revisar):** la sesión local ahora dura mínimo 7 días (`config/session.php`) para no dejar sin cola a un técnico sin red.
- Cerrar Vigilancia sincroniza antes y se bloquea si quedan turnos pendientes o bloqueados; aviso "Envío con problema" en Mis Órdenes y detalle; Auditorías según `acciones.puede_auditar` y permisos del usuario.

## Pendiente (solo el usuario puede hacerlo / decidir)
1. **Desplegar y correr `php artisan migrate`** en la web (producción): migraciones `2026_10_28_222035_add_lectura_referencia...`, `2026_10_29_120000_normalizar_vigilancia_rechazada` (CAMBIA DATOS: respaldo antes), `2026_10_29_133922_reiniciar_sincronizado_en_ordenes_rechazadas`.
2. **Orden de despliegue**: publicar primero la API/web y luego la app móvil nueva (la API oculta los subtipos nuevos a apps sin `X-App-Version`≥1.1.0). Subir la versión de la app móvil (`ALENA_APP_VERSION`, default 1.1.0).
3. **Probar en celular real** (nada de esto se pudo verificar sin dispositivo): cámara/galería con varios grupos de fotos, GPS, cola offline real, barra de "Envíos con problemas" (no tape la navegación), render de botones Sí/No, `TimePicker` con turnos creados en web, sesión de 7 días sin red.
4. Confirmar `APP_LOCALE=es` en el `.env` de producción (el default del código es `en`).
5. Decidir: ¿la sesión local móvil de 7 días es aceptable? ¿Se hace commit (ninguno pendiente de pedir; el usuario/otra sesión ya hizo `a7dbd08`, `39b3768` en la web y `77d6235` en la móvil, y quedan cambios sin commit en ambos repos)?
6. Opcional no hecho: Sí/No como botones en pantallas MÓVILES que aún usan `Toggle` (`ReporteAuditoriaCampo` general, etc.); actualizar `API_TECNICO.md` (web y móvil) con endpoints/campos nuevos y quitar `geo_referencia`; PWA/offline para la web (solo se discutió); arreglar los 6 archivos web con fallos viejos del starter kit; `composer audit` (no ejecutado; `league/flysystem-aws-s3-v3` muy desactualizado).
7. Limpieza: BDs temporales `alenacentrix_test_w1|w2|w3|susp` si quedaron en MySQL; este archivo.
