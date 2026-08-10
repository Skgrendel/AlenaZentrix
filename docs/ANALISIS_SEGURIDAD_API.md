# Análisis de Seguridad — Conexión API ↔ App Móvil

**Fecha:** 2026-08-10 (actualizado tras aplicar las correcciones)
**Alcance:** Revisión de seguridad del código real de `AlenaZentrix` (API) y de cómo lo consume `AlenaZentrix_Mobile` (cliente), enfocada en autenticación, autorización, aislamiento multi-tenant y manejo de archivos. Es un análisis de código estático (lectura de fuente, sin pruebas de penetración activas) — no se ejecutó ningún ataque real, todos los hallazgos se verificaron trazando el código.

> Este documento complementa a `docs/ANALISIS_CONEXION_API_MOBILE.md` (funcionalidad) y `docs/API_AUDITORIAS.md` (contrato del nuevo endpoint). Aquí el foco es exclusivamente seguridad.
>
> **Estado: las 9 correcciones de este documento ya fueron aplicadas.** Ver `docs/AJUSTES_APP_MOBILE.md` para lo que el equipo móvil necesita ajustar como consecuencia.

---

## Resumen ejecutivo

| # | Hallazgo | Severidad | Estado |
|---|---|---|---|
| 1 | Path traversal entre empresas en `GET /api/archivos/{path}` | **Alto** | ✅ Corregido |
| 2 | La URL "pre-firmada" que devuelve `/api/archivos/{path}` en realidad apuntaba a una ruta web con sesión, no accesible para el cliente móvil | **Alto** | ✅ Corregido |
| 3 | La firma de la URL de `operaciones/foto` nunca se validaba en el servidor (faltaba middleware `signed`) | Medio | ✅ Corregido |
| 4 | `POST /api/auth/login` sin límite de intentos dedicado a fuerza bruta | Medio | ✅ Corregido |
| 5 | El login de la API no exige 2FA aunque el usuario lo tenga activado | Medio | 📝 Documentado (decisión: no bloquear, ver detalle) |
| 6 | Tokens Sanctum sin expiración | Bajo-Medio | ✅ Corregido (90 días) |
| 7 | `evidencias`/`imagenes` aceptaban cualquier string sin verificar pertenencia | Bajo | ✅ Corregido |
| 8 | La "ability" del token (`tecnico`) se otorgaba pero nunca se revisaba | Info | ✅ Corregido (removida) |
| 9 | No había `config/cors.php` en el proyecto | Info | ✅ Corregido (publicado con defaults) |

**Lectura rápida:** no había una vulnerabilidad crítica de "cualquiera sin login puede entrar", pero sí dos problemas de aislamiento entre empresas (#1) y una pieza de la arquitectura de archivos que no estaba terminada de conectar entre el backend y el cliente móvil (#2), que probablemente causaba que las fotos/evidencias no cargaran en la app. Ambos ya están corregidos. Ver la sección de cada hallazgo para el detalle de qué cambió.

---

## 1. Path traversal entre empresas en `GET /api/archivos/{path}`

**Severidad: Alto**

### Dónde

`app/Http/Controllers/Api/ArchivoController.php:16-23`

```php
public function show(string $path): JsonResponse
{
    $path = ltrim($path, '/');

    $companyId = auth()->user()->company_id;
    if (! str_starts_with($path, $companyId.'/')) {
        return response()->json(['message' => 'No autorizado.'], 403);
    }
    ...
    if (! Storage::disk('s3')->exists($path)) { ... }
    ...
    $url = Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(15));
```

### El problema

El control de acceso compara el `$path` **tal como llega en la URL**, sin normalizar. Pero `Storage::disk('s3')` usa Flysystem, cuyo normalizador de rutas por defecto (`vendor/league/flysystem/src/WhitespacePathNormalizer.php:24-48`) **resuelve `..` antes de pedirle nada a S3**:

```php
private function normalizeRelativePath(string $path): string
{
    $parts = [];
    foreach (explode('/', $path) as $part) {
        switch ($part) {
            case '': case '.': break;
            case '..':
                if (empty($parts)) { throw PathTraversalDetected::forPath($path); }
                array_pop($parts);   // <- aquí "sube" un nivel
                break;
            default: $parts[] = $part; break;
        }
    }
    return implode('/', $parts);
}
```

Y esta normalización ocurre dentro de `League\Flysystem\Filesystem` (`vendor/league/flysystem/src/Filesystem.php`), **antes** de llegar al adaptador de S3 — tanto para `exists()` como para `temporaryUrl()`. La ruta HTTP (`routes/api.php`, `->where('path', '.*')`) permite cualquier carácter, incluyendo `..` y `/`.

### Cómo se explota

Un usuario autenticado de la Empresa A (con su `company_id` real) hace:

```
GET /api/archivos/{miCompanyId}/../{otraCompanyId}/reportes/ODT-000099/foto.jpg
```

1. `str_starts_with($path, "{miCompanyId}/")` → **pasa** (el string literal empieza así).
2. `Storage::disk('s3')->exists($path)` → Flysystem normaliza `..` y consulta realmente `{otraCompanyId}/reportes/ODT-000099/foto.jpg`.
3. Si existe, el usuario recibe una URL para verlo — un archivo de **otra empresa**.

El único requisito para el atacante es adivinar o conocer el `company_id` (UUID) de la otra empresa y la ruta del archivo — ambos son datos que pueden filtrarse por otros canales (logs, mensajes de error, ingeniería social) o, en el peor caso, enumerarse si hay algún otro endpoint que liste `company_id`s.

### Por qué el test existente no lo detecta

`tests/Feature/ApiSecurityTest.php:53-60` prueba exactamente el caso directo:
```php
test('GET /api/archivos rechaza path de otro tenant', function () {
    ...
    ->getJson("/api/archivos/{$otroCompanyId}/foto.jpg")
    ->assertForbidden();
});
```
Esto sigue funcionando bien (un path que empieza directo con otro `company_id` se rechaza). Pero no hay ningún test con `..` en el path, que es justamente el caso que rompe el control.

### Sugerencia de corrección

Normalizar el path **antes** de comparar, y rechazar cualquier segmento `.`/`..` explícitamente (defensa en profundidad, no confiar solo en que Flysystem lance excepción):

```php
public function show(string $path): JsonResponse
{
    $path = ltrim($path, '/');

    // Rechazar cualquier intento de traversal antes de tocar el filesystem
    $segments = explode('/', $path);
    if (in_array('.', $segments, true) || in_array('..', $segments, true)) {
        return response()->json(['message' => 'Ruta inválida.'], 403);
    }

    $companyId = auth()->user()->company_id;
    if (! str_starts_with($path, $companyId.'/')) {
        return response()->json(['message' => 'No autorizado.'], 403);
    }
    // ...resto igual
}
```

Aplica el mismo fix a la ruta gemela `operaciones/foto` en `routes/web.php:17-29`, que tiene el mismo patrón de validación por `str_starts_with` sin normalizar previamente (ver hallazgo #2 para más detalle de esta ruta).

### ✅ Corrección aplicada

Se agregó el rechazo explícito de segmentos `.`/`..` en ambos lugares, exactamente como se sugería, antes del check de `company_id`. Cubierto por el test nuevo `tests/Feature/ApiSecurityTest.php`: `'GET /api/archivos rechaza traversal con .. para escapar al tenant propio'`.

---

## 2. La "URL temporal" de `/api/archivos/{path}` no es una URL de S3 — es una ruta web con sesión, inaccesible para el cliente móvil

**Severidad: Alto (funcional + diseño de seguridad)**

### Dónde

`app/Providers/AppServiceProvider.php:36-47`:
```php
protected function configureS3TemporaryUrls(): void
{
    Storage::disk('s3')->buildTemporaryUrlsUsing(
        function (string $path, \DateTime $expiration, array $options): string {
            return URL::temporarySignedRoute(
                'operaciones.foto',
                $expiration,
                ['path' => $path],
            );
        }
    );
}
```

Esto se registra en `boot()`, sin condicionarlo a ningún entorno — **afecta a toda llamada a `Storage::disk('s3')->temporaryUrl()` en toda la aplicación**, incluida la que hace `ArchivoController::show()` para la API.

La ruta a la que apunta, `routes/web.php:14-29`:
```php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::middleware('permission:evidencias.ver')
        ->get('operaciones/foto', function (Request $request) {
            ...
        })->name('operaciones.foto');
});
```

`auth` aquí usa el guard por defecto (`web`, basado en **sesión/cookie de navegador**), no `auth:sanctum`.

### El problema

`GET /api/archivos/{path}` (pensado para el cliente móvil, autenticado con **Bearer token**) responde con una URL que en realidad apunta a esta ruta protegida por **sesión de navegador**. El cliente móvil, del lado de `AlenaZentrix_Mobile`, hace exactamente esto (`app/Services/Api/ArchivoService.php:279-320`):

```php
$response = $this->apiClient->get('/archivos/' . $this->encodePath($path));
$url = data_get($response, 'url') ?? data_get($response, 'data.url');
// ...
return $this->validarUrlRemota($url);
```

Y ese `$url` se usa directamente (típicamente en un `<img src>` o similar, vía otra llamada `Http::get($url)` en `urlDisponible()`), **sin ninguna cookie de sesión ni header de autenticación de la app original** — es un cliente HTTP totalmente aparte, corriendo dentro de la app móvil (NativePHP), no un navegador con sesión abierta en `alenazentrix.test`.

**Resultado esperado:** la petición a `operaciones/foto` llega sin sesión → `auth` middleware la redirige a `/login` (HTML, no imagen) → la imagen/evidencia no carga en la app móvil.

Esto contradice el objetivo del endpoint `/api/archivos/{path}` documentado en `API_TECNICO.md` (§6.2), que promete una URL S3 directamente usable por el cliente.

### Sugerencia de corrección

Elegir una de estas dos opciones (no ambas):

**Opción A — la más simple:** que `ArchivoController::show()` NO use `Storage::disk('s3')->temporaryUrl()` (que está globalmente interceptado), sino que genere la URL real de S3 directamente, sin pasar por el override pensado para el panel web:

```php
// En vez de Storage::disk('s3')->temporaryUrl(...)
$adapter = Storage::disk('s3')->getAdapter(); // o construir un cliente S3 aparte
$url = $adapter->temporaryUrl($path, now()->addMinutes(15)); // URL real firmada por AWS/DO, sin pasar por buildTemporaryUrlsUsing
```
(en la práctica, esto puede requerir registrar un segundo "disk" en `config/filesystems.php`, ej. `s3_direct`, apuntando al mismo bucket pero sin el `buildTemporaryUrlsUsing` aplicado, y usarlo solo desde `ArchivoController`).

**Opción B:** mantener el proxy vía ruta propia (como hoy), pero que sea una ruta **API real** protegida por `auth:sanctum` en vez de una ruta web con sesión — es decir, mover la lógica de `operaciones/foto` a un método del propio `ArchivoController` que haga `stream()`/`fpassthru()` directamente, devolviendo el archivo (o los bytes) en la misma respuesta de la API, sin URL intermedia.

Cualquiera de las dos evita depender de una sesión de navegador que el cliente móvil no tiene.

### ✅ Corrección aplicada — Opción A

Se agregó un disco nuevo `s3_directo` en `config/filesystems.php` (mismas credenciales/bucket que `s3`, pero instancia independiente que **no** pasa por `AppServiceProvider::configureS3TemporaryUrls()`). `ArchivoController::show()` ahora genera la URL con `Storage::disk('s3_directo')->temporaryUrl(...)` — una URL real de S3/DigitalOcean Spaces, firmada con parámetros `X-Amz-*`, usable directamente por el cliente HTTP del móvil sin ninguna sesión.

**El panel web no se tocó**: sigue usando `Storage::disk('s3')` exactamente igual que antes (a través de `AppServiceProvider`, ahora usando el helper `ArchivoUrl` — ver hallazgo #3).

⚠️ **Esto SÍ es un cambio de contrato para el móvil** — antes la URL devuelta apuntaba a `operaciones/foto` con parámetros `signature`/`expires` (formato Laravel); ahora es una URL S3/DO Spaces real con parámetros `X-Amz-Signature`, `X-Amz-Credential`, etc. Ver `docs/AJUSTES_APP_MOBILE.md` para el detalle de impacto (en la práctica, es una mejora: el cliente móvil ya esperaba justamente este formato `X-Amz-*` en su lógica de detección de "URL firmada").

---

## 3. La URL firmada nunca valida su firma en el servidor

**Severidad: Medio**

### Dónde

`routes/web.php:17-29` — la ruta `operaciones.foto` no tiene el middleware `signed`.

Confirmado que el proyecto sí usa y conoce este middleware en otra parte: `routes/revision-medidores.php:25` tiene `->middleware('signed')` en otra ruta — aquí simplemente no se aplicó.

### El problema

`URL::temporarySignedRoute()` (usado en `AppServiceProvider.php:40`) genera una URL con parámetros `signature` y `expires`, pero **nada en el servidor los valida** si la ruta no lleva el middleware `signed`. Es decir, la promesa de "URL válida 15 minutos" que documenta `API_TECNICO.md` §6.2 no se cumple: la URL, si alguien logra usarla (con sesión válida), sigue funcionando después de que "expiró", y la firma tampoco se verifica — cualquiera con una sesión de navegador válida y el permiso `evidencias.ver` puede pedir `operaciones/foto?path=cualquier-cosa` sin firma alguna, porque la protección real que está operando es `auth + permission`, no la firma.

En la práctica esto es una capa de seguridad "decorativa" que no hace nada — no agrava el hallazgo #1/#2 directamente, pero es un control que se cree que existe y no existe.

### Sugerencia de corrección

Agregar el middleware:
```php
Route::middleware(['auth', 'verified', 'signed'])
    ->get('operaciones/foto', function (Request $request) { ... })
    ->name('operaciones.foto');
```

### ✅ Corrección aplicada

Se agregó `signed` al grupo de middleware de la ruta. Como esto rompía cualquier llamada existente que generara la URL sin firma, se creó un helper `App\Support\ArchivoUrl::temporal($path)` y se actualizaron **los 6 lugares** del panel web que construían la URL directo con `route('operaciones.foto', ...)` para que ahora usen el helper (que sí firma):
`OrdenShow.php`, `OrdenJobShow.php`, `ReporteRevisar.php` (×2), `AuditoriaRevisar.php` (×2), y la vista Blade `auditoria-revisar.blade.php`. El comportamiento visible para el usuario del panel web es idéntico — mismas fotos, mismos enlaces. Cubierto por los tests nuevos `'GET operaciones/foto sin firma válida devuelve 403'` y `'GET operaciones/foto con firma válida no devuelve 403 por firma'`.

---

## 4. `POST /api/auth/login` sin límite de intentos dedicado a fuerza bruta

**Severidad: Medio**

### Dónde

`routes/api.php:18` — `Route::post('/auth/login', [AuthController::class, 'login'])`, dentro del middleware group `api` (confirmado con `php artisan route:list`: la ruta solo tiene el middleware `["api"]`).

### El problema

El proyecto **sí** tiene un limiter pensado exactamente para esto — `app/Providers/FortifyServiceProvider.php:78-82`:
```php
RateLimiter::for('login', function (Request $request) {
    $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());
    return Limit::perMinute(5)->by($throttleKey);
});
```
5 intentos por minuto por email+IP — razonable contra fuerza bruta/credential stuffing. Pero este limiter solo se aplica a las rutas que registra Fortify (`/login` del panel web), **no** a `AuthController::login`, que es lo que usa la app móvil. La API solo hereda el limiter genérico `throttle:api` (60 peticiones/minuto por usuario o IP, pensado para uso normal de la API, no para proteger un endpoint de credenciales).

Un atacante sin cuenta podría probar hasta 60 combinaciones de contraseña por minuto contra un email conocido antes de que el limiter genérico lo frene — mucho más permisivo que las 5/min que ya existen para el mismo propósito en el panel web.

### Sugerencia de corrección

Aplicar el mismo limiter `login` (o uno equivalente) a la ruta de la API:
```php
Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:login')
    ->name('api.auth.login');
```
(El limiter `login` usa `$request->input(Fortify::username())`, hay que confirmar que ese nombre de campo coincide con `email`, que es lo que manda `AuthController::login`; si no, definir un limiter específico para la API con la misma lógica de 5/min por email+IP.)

### ✅ Corrección aplicada

Se confirmó que `Fortify::username()` = `'email'` (`config/fortify.php:48`), igual que el campo que ya usa `AuthController::login`, así que se reutilizó directamente el limiter `login` existente: `->middleware('throttle:login')` en `routes/api.php`. Cubierto por el test nuevo `'POST /api/auth/login bloquea después de 5 intentos fallidos en el mismo minuto'` (el 6to intento devuelve 429).

---

## 5. El login de la API no exige 2FA

**Severidad: Medio**

### Dónde

`app/Http/Controllers/Api/AuthController.php:14-27`:
```php
public function login(LoginRequest $request): JsonResponse
{
    if (! Auth::attempt($request->only('email', 'password'))) {
        return response()->json(['message' => 'Credenciales incorrectas.'], 401);
    }
    $user = Auth::user();
    if (! $user->estado) { ... }
    $token = $user->createToken('nativephp', ['tecnico'])->plainTextToken;
    ...
}
```

### El problema

No hay ninguna verificación de `$user->two_factor_confirmed_at` ni de un segundo factor. Si un usuario tiene 2FA activo en el panel web (protegido por Fortify), ese mismo usuario puede autenticarse **completamente** vía `/api/auth/login` con solo email+contraseña — el 2FA queda reducido a una protección parcial que un atacante puede sortear simplemente usando la API en vez del panel web.

Esto es coherente con que la API está pensada para "primer factor únicamente" (dispositivo móvil de confianza), pero si esa es la decisión de producto, vale la pena que sea explícita y documentada, no un efecto colateral.

### Sugerencia de corrección

Decisión de producto a confirmar con el equipo, dos caminos razonables:
- **A)** Documentar explícitamente que el login móvil es un factor único y que 2FA solo protege el panel web (aceptar el riesgo conscientemente).
- **B)** Si se quiere paridad, exigir un segundo paso en el login de la API para usuarios con 2FA activo (ej. devolver un estado intermedio `requires_2fa` y un endpoint adicional para confirmar el código, similar a como Fortify lo hace en web).

### 📝 Decisión tomada — Opción A

Se optó por **no bloquear** el login de la API por 2FA: la app móvil no tiene pantalla para pedir el código, y bloquear habría dejado sin acceso a cualquier técnico/auditor con 2FA activo hoy. Se agregó un comentario explícito en `AuthController::login()` documentando esta decisión consciente. Si en el futuro se quiere paridad (Opción B), habrá que diseñar primero el paso de 2FA en la app móvil.

---

## 6. Tokens Sanctum sin expiración

**Severidad: Bajo-Medio**

### Dónde

`config/sanctum.php:53`:
```php
'expiration' => null,
```

### El problema

Los tokens emitidos (`$user->createToken('nativephp', ['tecnico'])`) nunca expiran automáticamente. Si un dispositivo móvil se pierde o es robado con la sesión activa, el token sigue siendo válido indefinidamente hasta que alguien lo revoque manualmente (`DELETE /api/auth/logout`, que requiere que **alguien tenga acceso a ese mismo token** para llamarlo — si el dispositivo está perdido, nadie puede hacer ese logout desde el dispositivo, y no existe hoy un endpoint de "revocar todos los tokens de este usuario" accesible desde otro dispositivo/el panel web).

### Sugerencia de corrección

- Configurar `'expiration' => <minutos>` (ej. 30 días) y renovar el token periódicamente desde la app, o
- Agregar en el panel web (donde el usuario sí tiene sesión) una acción de "cerrar sesión en todos los dispositivos" que revoque todos los `personal_access_tokens` del usuario (`$user->tokens()->delete()`), para cubrir el caso de dispositivo perdido.

### ✅ Corrección aplicada — 90 días

`config/sanctum.php`: `'expiration' => (int) env('SANCTUM_TOKEN_EXPIRATION_MINUTES', 60 * 24 * 90)`. Efecto real: las sesiones móviles activas hoy no se ven afectadas; solo un token que lleve más de 90 días sin usarse dejará de funcionar en su próxima petición, forzando un re-login único de ese dispositivo. Configurable por variable de entorno si se necesita ajustar la ventana sin tocar código. **Pendiente:** no se agregó la acción de "cerrar sesión en todos los dispositivos" para el caso de robo/pérdida — queda como mejora futura si se necesita.

---

## 7. `evidencias`/`imagenes` aceptan cualquier string sin verificar pertenencia

**Severidad: Bajo**

### Dónde

`app/Http/Requests/Api/AuditoriaGuardarRequest.php:26-27`:
```php
'evidencias' => ['required', 'array', 'min:1'],
'evidencias.*' => ['string', 'max:500'],
```
Mismo patrón en `ReporteController::validar()` para `imagenes.*`.

### El problema

Nada valida que el string enviado sea realmente un path devuelto por `/api/upload`, ni que pertenezca al `company_id` del usuario. Un cliente autenticado podría, en teoría, enviar como "evidencia" el path de un archivo de otra empresa (si lo conoce o lo adivina) sin que el sistema lo detecte al guardar — el dato quedaría persistido como evidencia legítima. Hoy el impacto práctico es bajo porque **leer** esos paths ya tiene los problemas de los hallazgos #1/#2, pero conviene cerrar esta entrada igual, especialmente si se corrigen esos hallazgos primero.

### Sugerencia de corrección

Validar con una regla `starts_with:<company_id>/` (usando el `company_id` del usuario autenticado) en el `evidencias.*`/`imagenes.*`, o, más robusto, verificar `Storage::disk('s3')->exists($path)` + prefijo de empresa antes de aceptar el dato.

### ✅ Corrección aplicada

Se agregó una regla de validación (closure) en `AuditoriaGuardarRequest.php` (`evidencias.*`) y en `ReporteController.php` (`imagenes.*` y `foto_detector_fuga`) que exige que el path empiece con `{company_id_del_usuario}/`. Como todos los paths legítimos ya vienen con ese prefijo (los pone `UploadController` al generarlos), esto no afecta a ningún cliente que use el flujo normal — solo rechaza paths ajenos a la empresa. Se actualizaron los tests existentes de `AuditoriaApiTest.php` para reflejar el prefijo requerido.

---

## 8. La ability del token (`tecnico`) se otorga pero nunca se revisa

**Severidad: Info**

### Dónde

`AuthController.php:29`: `$user->createToken('nativephp', ['tecnico'])`. Búsqueda de `tokenCan(` en todo `app/`: **cero resultados**.

### El problema

No es una vulnerabilidad en sí (todas las rutas están igualmente protegidas por `auth:sanctum` + permisos de Spatie), pero significa que el sistema de "abilities" de Sanctum no aporta ningún control real hoy — si en el futuro se emiten tokens con distintos alcances (ej. un token de "solo lectura" para integraciones externas), no hay ningún punto del código que respete esa restricción todavía.

### Sugerencia

Si no se va a usar el sistema de abilities, quitar el array `['tecnico']` de `createToken()` para no dar una falsa sensación de scoping. Si sí se planea usarlo, agregar `$request->user()->tokenCan('tecnico')` como verificación adicional en las rutas relevantes.

### ✅ Corrección aplicada

Se removió el array `['tecnico']` de `createToken()` en `AuthController::login()`. Sin efecto funcional (nada lo verificaba), solo evita la falsa sensación de scoping.

---

## 9. No hay `config/cors.php` en el proyecto

**Severidad: Info / a confirmar**

### El problema

No se encontró el archivo `config/cors.php` (Laravel normalmente lo incluye por defecto). Para el cliente móvil actual esto no importa — es un cliente PHP servidor-a-servidor (NativePHP), no un navegador, así que CORS no le aplica. Pero si en el futuro se agrega un cliente basado en navegador (una SPA, un panel externo) que consuma esta misma API vía `fetch()`/`XMLHttpRequest`, la ausencia de este archivo puede causar comportamientos inesperados (el middleware `HandleCors` de Laravel podría no tener configuración que leer).

### Sugerencia

Confirmar si la ausencia es intencional. Si se prevé algún cliente browser a futuro, publicar `config/cors.php` explícitamente con `allowed_origins` restringido a los dominios reales (no `*`) en producción.

### ✅ Corrección aplicada

Se publicó `config/cors.php` con los valores estándar de Laravel (`paths => ['api/*', 'sanctum/csrf-cookie']`, `allowed_origins => ['*']`), documentando explícitamente el comportamiento permisivo que ya ocurría de facto — sin restringir nada que hoy funcione. Si se agrega un cliente browser en el futuro, ajustar `allowed_origins` a los dominios reales en producción.

---

## Puntos positivos (para tener el panorama completo)

No todo son hallazgos — varias decisiones de seguridad ya están bien resueltas:

- **IDs no enumerables:** todos los recursos (`OrdenJob`, `reportes`, `auditoria`, `User`, etc.) usan UUID (`HasUuids`), no autoincrementales — dificulta ataques de enumeración de IDs.
- **Verificación de propiedad consistente:** todos los endpoints de técnico (`OrdenController`, `ReporteController`, y el nuevo `AuditoriaController`) siguen el mismo patrón `$user->personals_id === $orden->personals_id || $user->can(...)` — no hay inconsistencias entre endpoints.
- **Aislamiento multi-tenant real:** `CompanyScope` (global scope de Eloquent) + `SetTenantContext` (middleware) se aplican de forma amplia y consistente en los modelos principales — no es un parche puntual.
- **Sin mass assignment:** en ningún controlador se hace `Modelo::create($request->all())` directo; siempre se arma un array curado campo por campo antes de `create()`/`update()`.
- **Política de contraseñas fuerte en producción:** `AppServiceProvider::configureDefaults()` exige mínimo 12 caracteres, mayúsculas/minúsculas, números, símbolos y verificación contra contraseñas comprometidas (`uncompromised()`) cuando `app()->isProduction()`.
- **Suite de tests de seguridad ya existente:** `tests/Feature/ApiSecurityTest.php` cubre varios casos de IDOR y aislamiento de tenant (aunque, como se detalla en el hallazgo #1, no cubre el caso específico de `..`).
- **Subida de archivos con extensión/tamaño validados:** `UploadController` restringe extensiones y tamaño máximo (10MB), y siempre antepone el `company_id` del usuario autenticado al path — el cliente no puede elegir dónde escribir.

---

## Tabla priorizada de acción sugerida (histórico — todo ya aplicado)

| Prioridad | Acción | Hallazgo relacionado | Estado |
|---|---|---|---|
| 1 | Corregir el path traversal en `ArchivoController::show()` (y su gemelo `operaciones/foto`) | #1 | ✅ |
| 2 | Decidir y arreglar el mecanismo de entrega de archivos para que el cliente móvil pueda consumirlo (Opción A o B) | #2 | ✅ (Opción A) |
| 3 | Agregar `signed` a la ruta `operaciones.foto` | #3 | ✅ |
| 4 | Aplicar throttling dedicado a `/api/auth/login` | #4 | ✅ |
| 5 | Decidir postura sobre 2FA en login móvil (documentar o implementar) | #5 | 📝 (Opción A, documentado) |
| 6 | Configurar expiración de tokens o revocación remota | #6 | ✅ (90 días; revocación remota pendiente) |
| 7 | Validar pertenencia de `evidencias`/`imagenes` al tenant | #7 | ✅ |
| 8 | Limpiar o activar el uso de abilities de token | #8 | ✅ |
| 9 | Confirmar intención de la ausencia de `config/cors.php` | #9 | ✅ |

**Pendientes/decisiones abiertas para el futuro (no bloqueantes):**
- Acción de "cerrar sesión en todos los dispositivos" (hallazgo #6) para el caso de robo/pérdida de dispositivo.
- Paridad de 2FA en el login móvil (hallazgo #5), si el negocio lo requiere — necesita antes una pantalla de 2FA en la app móvil.
- Se detectó, al correr los tests, un bug **preexistente y no relacionado** con este análisis: `resources/views/livewire/coordinador/reporte-revisar.blade.php` llama a un método `evidenciasUrls()` que no existe en `ReporteRevisar.php` (el método real se llama `archivosUrls()`). No se corrigió aquí por estar fuera de alcance, pero vale la pena que el equipo lo revise.

---

## Metodología

Análisis estático de código (lectura de fuente), sin ejecución de exploits ni pruebas contra un entorno vivo. Se citan archivo y línea de cada hallazgo para que puedan verificarse directamente. No se modificó ningún archivo de código durante este análisis — es puramente informativo.
