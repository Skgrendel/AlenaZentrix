# Ajustes en la API por correcciones de seguridad — qué necesita el equipo móvil

**Fecha:** 2026-08-10
**Para:** equipo de desarrollo de `AlenaZentrix_Mobile`
**De:** revisión de seguridad de la API `AlenaZentrix`

Se aplicaron 9 correcciones de seguridad en el backend (`docs/ANALISIS_SEGURIDAD_API.md` tiene el detalle técnico completo de cada una). Este documento es la versión "qué me toca ajustar a mí" para el equipo móvil: de las 9, **solo 2 tienen algún efecto sobre la app móvil**, y una de ellas es en realidad una corrección de algo que probablemente ya estaba roto, no una funcionalidad nueva que rompimos.

---

## Resumen para los impacientes

| # | Corrección | ¿Afecta al móvil? | Acción requerida |
|---|---|---|---|
| 1 | Path traversal en `/api/archivos/{path}` | No | Ninguna |
| 2 | `/api/archivos/{path}` ahora devuelve una URL S3 real | **Sí — probablemente en positivo** | Probar que las fotos/evidencias cargan (ver detalle) |
| 3 | Firma validada en `operaciones/foto` (ruta solo del panel web) | No | Ninguna |
| 4 | Rate limit en `/api/auth/login` (5 intentos/min) | **Sí, menor** | Manejar `429` en el login (ver detalle) |
| 5 | Decisión sobre 2FA en login | No (se documentó, no se bloqueó) | Ninguna por ahora |
| 6 | Tokens expiran a los 90 días de inactividad | **Sí, menor** | Confirmar que ya manejan `401` como "hay que volver a loguear" |
| 7 | `evidencias`/`imagenes` deben pertenecer a tu empresa | **Sí, pero solo si envían algo fuera de lo normal** | Ver detalle — probablemente cero impacto real |
| 8 | Se quitó la ability `tecnico` del token | No | Ninguna |
| 9 | Se publicó `config/cors.php` | No | Ninguna |

---

## 1. `GET /api/archivos/{path}` ahora devuelve una URL S3 real (antes probablemente no funcionaba)

### Qué cambió

Antes, el campo `url` que devolvía este endpoint apuntaba a una ruta interna del panel web (`/operaciones/foto?path=...&signature=...&expires=...`), protegida por sesión de navegador. Su app móvil (cliente HTTP con Bearer token, sin sesión de navegador) **muy probablemente no podía cargar esa URL** — al pedirla, el servidor la redirigía a la pantalla de login del panel web en vez de servir la imagen.

Ahora `GET /api/archivos/{path}` devuelve una URL real y firmada del bucket S3/DigitalOcean Spaces (formato estándar `X-Amz-*`), consumible directamente con un `GET` normal, sin necesidad de sesión ni headers especiales adicionales.

### Formato de la URL — antes vs. ahora

**Antes** (no funcional para ustedes):
```
https://alenazentrix.test/operaciones/foto?path=...&expires=1234567890&signature=abc123...
```

**Ahora:**
```
https://<bucket>.<region>.digitaloceanspaces.com/<path>?X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Credential=...&X-Amz-Date=...&X-Amz-Expires=900&X-Amz-SignedHeaders=host&X-Amz-Signature=...
```

Sigue expirando en 15 minutos, igual que antes (mismo `now()->addMinutes(15)` en el backend). El resto del contrato del endpoint **no cambió**: mismo método (`GET /api/archivos/{path}`), misma respuesta `{ "url": "..." }`, mismos códigos de error (401/403/404).

### Por qué probablemente no necesitan cambiar código

Revisamos `app/Services/Api/ArchivoService.php` de su proyecto, específicamente el método `esUrlFirmada()`:

```php
private function esUrlFirmada(string $url): bool
{
    $query = strtolower((string) parse_url($url, PHP_URL_QUERY));

    return str_contains($query, 'x-amz-signature=')
        || str_contains($query, 'x-amz-credential=')
        || str_contains($query, 'x-amz-security-token=');
}
```

Este método **ya estaba escrito esperando exactamente el formato que ahora sí llega** (`X-Amz-*`). Antes, con la URL de `operaciones/foto`, `esUrlFirmada()` devolvía `false` porque esos parámetros nunca estaban presentes — es decir, su propio código ya anticipaba correctamente el formato correcto de la API; era la API la que no lo cumplía. `urlTemporalDesdeApi()` y `validarUrlRemota()` tampoco necesitan cambios: siguen aceptando cualquier URL `https://` válida.

### Lo que sí les pedimos que hagan

1. **Probar en un entorno real** (no solo revisar código) que las fotos/evidencias de una orden cargan correctamente ahora — es la validación end-to-end que nosotros no podemos hacer desde este lado sin levantar la app.
2. Si `validarUrlRemota()` exige HTTPS fuera de `local` (`app()->environment('local')`), confirmar que el `AWS_URL`/`AWS_ENDPOINT` configurado en el backend realmente usa `https://` en el entorno donde prueben (debería, pero es la única variable externa a nuestro código que no controlamos desde aquí).
3. Si tenían algún workaround/parche ya implementado para lidiar con el problema de "las fotos no cargan" (caché local, reintento, fallback a mostrar solo el nombre del archivo sin preview, etc.), pueden revisar si ya no es necesario.

---

## 2. Rate limit en `POST /api/auth/login`

### Qué cambió

Ahora el login rechaza con **`HTTP 429 Too Many Requests`** si se hacen más de **5 intentos por minuto** con el mismo email (combinado con la IP de origen). Antes no había límite específico de login (solo el límite genérico de toda la API, 60 peticiones/min).

### Impacto esperado

Bajo — en el uso normal (un técnico escribiendo mal su contraseña una o dos veces) nunca se llega a 5 intentos en un minuto. Solo importa si:
- Tienen algún flujo automatizado de pruebas que reintenta login en loop.
- Quieren mostrar un mensaje específico en vez del genérico "credenciales incorrectas" cuando reciban un `429`.

### Qué pedimos

Si su cliente HTTP (`AuthService::login()` / `ApiClient`) no distingue hoy entre `401` (credenciales malas) y otros códigos, sería buena idea agregar un manejo específico para `429`:
```php
// Ejemplo de mensaje sugerido
if ($response->status() === 429) {
    throw new ApiException('Demasiados intentos. Espera un minuto e intenta de nuevo.');
}
```
No es obligatorio si su manejo de errores genérico ya es aceptable para este caso — es una mejora de UX, no un requisito funcional.

---

## 3. Tokens Sanctum expiran a los 90 días de inactividad

### Qué cambió

Antes los tokens (`plainTextToken` que guardan tras el login) nunca expiraban. Ahora expiran a los 90 días.

### Impacto esperado

Ninguno para sesiones activas — un dispositivo que se usa regularmente nunca llega a 90 días de inactividad. Solo afecta a un dispositivo que quedó con la sesión guardada y no se abrió la app en 3+ meses: la próxima petición con ese token devolverá `401` en vez del comportamiento anterior (token válido para siempre).

### Qué pedimos

Confirmar que `ApiClient`/`AuthService` ya interpretan un `401` en cualquier endpoint autenticado como "sesión inválida, hay que volver a loguear" (limpiar el token guardado con `TokenService::clear()` y redirigir a login). Si ya lo hacen (es el comportamiento esperado normal de cualquier API con tokens), no hay nada que cambiar — este caso ya cae dentro de ese mismo manejo.

---

## 4. `evidencias`/`imagenes` deben pertenecer a la empresa del usuario

### Qué cambió

Los endpoints que reciben paths de archivos (`POST/PUT /api/tecnico/ordenes/{id}/reporte`, `PUT /api/auditorias/{id}`) ahora **rechazan con 422** cualquier path que no empiece exactamente con `{tu_company_id}/`.

### Impacto esperado

Ninguno, siempre que el flujo sea el documentado: subir cada archivo primero con `POST /api/upload` y enviar en `imagenes`/`evidencias`/`foto_detector_fuga` **exactamente el `path` que devolvió ese endpoint, sin modificarlo**. Ese `path` ya viene con el `company_id` como prefijo por diseño (así funcionaba desde antes, esto no es nuevo).

### Único punto de atención

Si en algún lugar del código móvil se **reconstruye o recorta** el path antes de enviarlo de vuelta al backend (por ejemplo, guardando localmente solo la parte después del `company_id/` y volviendo a armarlo distinto, o normalizando con alguna función propia antes de mandarlo), hay que asegurarse de que el resultado final siga empezando por el `company_id` real del usuario. Si siempre usan el `path` tal cual lo devuelve `/api/upload` (que es lo que documenta `API_TECNICO.md`), no hay nada que cambiar.

---

## Lo que NO les afecta (por completitud)

- **Path traversal en `/api/archivos`**: corrección puramente defensiva del lado del servidor, invisible para cualquier cliente que use paths normales (como ya hacen).
- **Firma en `operaciones/foto`**: esa ruta es exclusiva del panel web (sesión de navegador), la app móvil nunca la llama directamente.
- **2FA en login**: se decidió explícitamente NO exigirlo en la API — el login sigue funcionando exactamente igual que antes.
- **Ability `tecnico` del token**: nunca se usaba en ningún lado, quitarla no cambia nada observable.
- **`config/cors.php`**: solo relevante para clientes basados en navegador; su app hace peticiones servidor-a-servidor, no le aplica.

---

## Contacto / dudas

Si al probar el punto 1 (carga de fotos) encuentran algún caso donde la URL no carga, o el formato no es el esperado, avisen con: el `path` exacto que enviaron a `/api/archivos/{path}`, la URL completa que recibieron de vuelta, y el error específico (timeout, 403, certificado, etc.) — con eso se puede diagnosticar rápido si es algo del lado del backend o de configuración del bucket.
