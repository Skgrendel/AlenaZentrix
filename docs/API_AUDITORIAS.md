# API de Auditorías — Nuevo módulo para el Desarrollador Móvil

**Base URL:** `https://alenazentrix.test` (local Herd) / `https://tu-dominio.com` (producción)
**Prefijo de todas las rutas:** `/api`
**Formato:** JSON
**Autenticación:** Bearer token (Laravel Sanctum) — el mismo token que ya usas para `/api/tecnico/*`

> Este documento cubre **únicamente** los endpoints nuevos de Auditoría. Para todo lo demás (login, mis-ordenes, reportes, catálogos, upload de archivos) sigue usando `API_TECNICO.md`, que no cambió.

---

## Tabla de contenido

1. [¿Qué es una Auditoría?](#1-qué-es-una-auditoría)
2. [Quién puede acceder](#2-quién-puede-acceder)
3. [GET /api/auditorias — Listar](#3-get-apiauditorias--listar)
4. [GET /api/auditorias/{id} — Ver detalle](#4-get-apiauditoriasid--ver-detalle)
5. [PUT /api/auditorias/{id} — Registrar resultado](#5-put-apiauditoriasid--registrar-resultado)
6. [Campos de referencia](#6-campos-de-referencia)
7. [Flujo completo de ejemplo](#7-flujo-completo-de-ejemplo)
8. [Códigos de error](#8-códigos-de-error)
9. [Notas de implementación para la app](#9-notas-de-implementación-para-la-app)

---

## 1. ¿Qué es una Auditoría?

Cuando una orden se completa (`estado = "completada"`) con `resultado = "positivo"`, el sistema crea **automáticamente** un registro de `Auditoria` asociado al reporte más reciente de esa orden, en estado `pendiente_auditoria` (ver `OrdenJob::booted()`, hook `updated`). No depende del `tipo_servicio` de la orden — depende únicamente del `resultado`.

Alguien —un Auditor, o el propio técnico dueño de la orden— debe revisar el hallazgo, adjuntar evidencias (fotos/PDFs) y marcar si el hallazgo se **confirma** o si resulta **negativo** (falso positivo).

Esto **no es lo mismo** que el reporte de tipo `auditoria_campo` (que ya conoces y ya está integrado). Son dos cosas distintas con el mismo nombre:

| | `auditoria_campo` (ya existe) | `Auditoria` (nuevo, este documento) |
|---|---|---|
| Qué es | Un tipo de reporte que el técnico llena en una visita de campo | Un registro que confirma/rechaza un hallazgo de Fraudes |
| Quién lo crea | El técnico, al enviar `POST /tecnico/ordenes/{id}/reporte` | El sistema, automáticamente, al completar una orden de Fraudes con resultado positivo |
| Endpoint | `POST/PUT /api/tecnico/ordenes/{id}/reporte` | `GET/PUT /api/auditorias/{id}` (este documento) |

## 2. Quién puede acceder

Cualquiera de estas dos condiciones habilita el acceso a una auditoría concreta:

1. El usuario tiene el permiso `auditorias.ver` (rol **Auditor**) → puede ver/editar **cualquier** auditoría de su empresa.
2. El usuario **es el técnico dueño de la orden** asociada al reporte de esa auditoría (`orden.personals_id === user.personals_id`, el mismo `personals_id` que ya guardas del login) → puede ver/editar **solo sus propias** auditorías, aunque no tenga el permiso `auditorias.ver`.

Si ninguna de las dos se cumple, la API responde `403`.

`GET /api/auditorias` (sección siguiente) aplica exactamente este mismo criterio para decidir qué lista de auditorías devuelve — no hace falta enviar ningún flag para indicar "modo técnico" o "modo auditor", la API lo decide sola según el usuario autenticado.

---

## 3. GET /api/auditorias — Listar

```
GET /api/auditorias
GET /api/auditorias?estado=pendiente_auditoria
GET /api/auditorias?tipo_servicio=fraudes
```

Devuelve la lista de auditorías visibles para el usuario autenticado — mismo criterio de acceso que la sección 2:

- **Auditor** (`auditorias.ver`): todas las auditorías cuya orden asociada tenga un `tipo_servicio` dentro de sus **servicios auditables** configurados (los mismos que filtran el listado del panel web en `/auditor/auditorias`). Si el usuario no tiene ningún servicio auditable asignado, la lista viene vacía.
- **Técnico** (sin `auditorias.ver`): solo las auditorías cuya orden le pertenece (`orden.personals_id === personals_id` del usuario).

**Query params (opcionales):**

| Param | Tipo | Descripción |
|---|---|---|
| `estado` | string | Filtra por `pendiente_auditoria`, `confirmado` o `negativo`. Valor inválido → `422`. |
| `tipo_servicio` | string | Filtra además por el tipo de servicio de la orden asociada (ej. `fraudes`, `revision_medidores`). |

**Headers:** los de siempre (`Authorization: Bearer {token}`, `Accept: application/json`).

**Respuesta exitosa (200):**

```json
{
  "data": [
    {
      "id": "uuid-auditoria-1",
      "estado": { "value": "pendiente_auditoria", "label": "Pendiente por auditar", "color": "warning" },
      "evidencias": [],
      "observaciones": null,
      "tipo_anomalia_id": null,
      "metros_cubicos_recuperados": null,
      "fecha_auditoria": null,
      "reporte": { "...": "igual que en el detalle, ver sección 4" },
      "orden": { "...": "igual que en el detalle, ver sección 4" }
    },
    { "...": "siguiente auditoría" }
  ]
}
```

No está paginado — devuelve la lista completa en `data` (igual que `GET /api/tecnico/mis-ordenes`, no como un `paginate()` con `links`/`meta`). Cada elemento tiene exactamente la misma forma que la respuesta de `GET /api/auditorias/{id}` (sección 4).

**Errores posibles:**
- `401` — no autenticado.
- `422` — `estado` con un valor que no es `pendiente_auditoria`/`confirmado`/`negativo`.

---

## 4. GET /api/auditorias/{id} — Ver detalle

```
GET /api/auditorias/{id}
```

**Parámetros de ruta:**
- `{id}` — UUID de la auditoría (no es el mismo UUID que la orden ni que el reporte).

**Headers:** los de siempre (`Authorization: Bearer {token}`, `Accept: application/json`).

**Respuesta exitosa (200):**

```json
{
  "data": {
    "id": "uuid-auditoria",
    "estado": {
      "value": "pendiente_auditoria",
      "label": "Pendiente por auditar",
      "color": "warning"
    },
    "evidencias": [],
    "observaciones": null,
    "tipo_anomalia_id": null,
    "metros_cubicos_recuperados": null,
    "fecha_auditoria": null,
    "reporte": {
      "id": "uuid-reporte",
      "orden_job_id": "uuid-orden",
      "numero_orden": "ODT-000045",
      "numero_medidor": "MED-001",
      "coincide_medidor": false,
      "lectura": "1234",
      "anomalia": ["uuid-anomalia-bypass"],
      "hay_fuga_gas": true,
      "excede_capacidad": false,
      "imagenes": ["reportes/ODT-000045/foto1.jpg", "reportes/ODT-000045/detector.jpg"],
      "video": null,
      "geo_referencia": null,
      "observaciones": "Medidor no coincide. Se detectó fuga menor.",
      "comentarios": null,
      "conclusion": null,
      "actividades": [],
      "datos_servicio": [],
      "estado": null,
      "revisado": false,
      "confirmado": false,
      "ubicacion": {
        "latitud": "4.6097",
        "longitud": "-74.0817",
        "direccion": "Calle 10 # 5-20"
      },
      "comercio": {
        "nombre_comercio": "Panadería El Trigal",
        "tipo_comercio": "Panadería"
      },
      "created_at": "2026-06-26T10:30:00.000000Z"
    },
    "orden": {
      "id": "uuid-orden",
      "numero_orden": "ODT-000045",
      "tipo_servicio": { "value": "fraudes", "label": "Fraudes", "color": "danger" },
      "estado": { "value": "completada", "label": "Completada", "color": "success" },
      "prioridad": { "value": "alta", "label": "Alta", "color": "warning" },
      "contrato": "12345678",
      "cliente": "Pedro López",
      "direccion": "Calle 10 # 5-20",
      "barrio": "Centro",
      "medidor": "MED-001",
      "...": "mismos campos que ya conoces de /api/tecnico/ordenes/{id}"
    }
  }
}
```

**Notas sobre la respuesta:**
- `reporte` y `orden` son exactamente los mismos objetos que ya recibes en `/api/tecnico/ordenes/{id}/reporte` y `/api/tecnico/mis-ordenes` respectivamente — **reutiliza el mismo código de parseo que ya tienes** para esos dos objetos (`normalizarOrden()`, el parser de reporte), no hace falta escribir uno nuevo.
- `reporte.comercio` es un campo **nuevo** que se agregó a `ReporteResource` (antes no venía). Solo aparece cuando el reporte tiene comercio asociado (Fraudes/Revisión de Medidores). Es la información del hallazgo que el auditor necesita para decidir.
- `evidencias` son rutas S3 (el mismo formato que `imagenes` en el reporte). Para mostrarlas usa `GET /api/archivos/{path}` igual que con cualquier otra imagen.

**Errores posibles:**
- `401` — no autenticado.
- `403` — no eres Auditor ni el técnico dueño de la orden.
- `404` — el UUID no corresponde a ninguna auditoría.

---

## 5. PUT /api/auditorias/{id} — Registrar resultado

```
PUT /api/auditorias/{id}
```

Guarda las evidencias y el resultado de la revisión. Se puede llamar más de una vez sobre la misma auditoría (por ejemplo, para corregir observaciones) — cada llamada sobrescribe `evidencias`, `estado`, `observaciones`, `tipo_anomalia_id` y `metros_cubicos_recuperados`, y actualiza `auditado_por_id` y `fecha_auditoria` al usuario/momento de la última llamada.

**Body (JSON):**

| Campo | Tipo | Obligatorio | Descripción |
|---|---|---|---|
| `evidencias` | array de strings (paths S3) | **Sí** (mínimo 1) | Rutas devueltas por `POST /api/upload`, igual que `imagenes` en el reporte. Recomendado: `directorio=auditorias` al subirlas. **Cada path debe empezar exactamente con `{company_id}/`** del usuario autenticado (ver nota de seguridad abajo). |
| `resultado` | string | **Sí** | Solo acepta `"confirmado"` o `"negativo"`. **No** se puede volver a poner `"pendiente_auditoria"` vía API. |
| `tipo_anomalia_id` | UUID | No | De catálogo — usa el mismo `GET /api/catalogos/tipos-anomalia` que ya conoces. |
| `metros_cubicos_recuperados` | numérico (string o número) | No | Cantidad recuperada, si aplica. |
| `contrato` | string | No | Si se envía, además actualiza el campo `contrato` de la **orden** asociada (no solo de la auditoría). |
| `observaciones` | string | No | Máximo 5000 caracteres. |

**Ejemplo de body:**
```json
{
  "evidencias": [
    "f47ac10b-58cc-4372-a567-0e02b2c3d479/auditorias/evidencia1.jpg",
    "f47ac10b-58cc-4372-a567-0e02b2c3d479/auditorias/evidencia2.pdf"
  ],
  "resultado": "confirmado",
  "tipo_anomalia_id": "uuid-anomalia-bypass",
  "metros_cubicos_recuperados": "12.5",
  "contrato": "12345678",
  "observaciones": "Se confirma bypass en la instalación. Evidencia fotográfica adjunta."
}
```

**Respuesta exitosa (200):**
```json
{
  "message": "Auditoría registrada correctamente.",
  "auditoria": {
    "id": "uuid-auditoria",
    "estado": {
      "value": "confirmado",
      "label": "Confirmado",
      "color": "success"
    },
    "evidencias": [
      "f47ac10b-.../auditorias/evidencia1.jpg",
      "f47ac10b-.../auditorias/evidencia2.pdf"
    ],
    "observaciones": "Se confirma bypass en la instalación. Evidencia fotográfica adjunta.",
    "tipo_anomalia_id": "uuid-anomalia-bypass",
    "metros_cubicos_recuperados": "12.5",
    "fecha_auditoria": "2026-08-10T15:20:00.000000Z",
    "reporte": { "...": "igual que en el GET" },
    "orden": { "...": "igual que en el GET, con contrato ya actualizado si lo enviaste" }
  }
}
```

> ⚠️ **Importante — a diferencia del endpoint de reporte:** aquí la respuesta **NO** envuelve el objeto `auditoria` en una clave `data` (no hay doble anidamiento `auditoria.data.estado`). Es `auditoria.estado`, directo. Esto es igual al patrón que ya usas para `orden` en las respuestas de `/iniciar` y `/cerrar-vigilancia` — el wrap `data` solo aparece en el `GET` de arriba.

**Errores posibles:**
- `401` — no autenticado.
- `403` — no eres Auditor ni el técnico dueño de la orden.
- `422` — validación fallida. Ejemplos:
  - `evidencias` vacío o ausente → `"The evidencias field is required."`
  - `resultado` con un valor distinto de `confirmado`/`negativo` (incluido `pendiente_auditoria`) → `"The selected resultado is invalid."`
  - un path en `evidencias` no empieza con `{company_id}/` del usuario → `"La evidencia debe pertenecer a tu empresa."` (en `errors.evidencias.0`, ver nota de seguridad abajo)
- `404` — el UUID no corresponde a ninguna auditoría.

> ⚠️ **Nota de seguridad — paths de `evidencias`:** el backend valida que cada string en `evidencias` empiece exactamente con el `company_id` del usuario autenticado, seguido de `/`. Si tu app siempre reenvía tal cual el `path` que devolvió `POST /api/upload` (sin recortarlo ni reconstruirlo), esto nunca falla — ese `path` ya viene con el `company_id` como prefijo por diseño. Solo revisa este punto si en algún lugar del código móvil se guarda/normaliza el path antes de mandarlo de vuelta.

---

## 6. Campos de referencia

### 6.1 Estados de una Auditoría (`estado.value`)

| Valor | Etiqueta | Color sugerido | ¿Se puede enviar por API? |
|---|---|---|---|
| `pendiente_auditoria` | Pendiente por auditar | warning (amarillo) | No — es el estado inicial automático, no se puede volver a este valor vía `PUT`. |
| `confirmado` | Confirmado | success (verde) | Sí |
| `negativo` | Negativo | danger (rojo) | Sí |

### 6.2 Tipos de archivo aceptados en `evidencias`

Los mismos que ya conoces de `/api/upload`: imágenes (`jpg`, `jpeg`, `png`, `gif`, `webp`, `heic`, `heif`), video (`mp4`, `mov`), documentos (`pdf`, `doc`, `docx`, `xls`, `xlsx`, `ppt`, `pptx`). Máximo 10 MB por archivo. Recomendado usar `directorio=auditorias` al subir para mantener organizado el bucket, aunque no es obligatorio.

---

## 7. Flujo completo de ejemplo

```
1. Login (igual que siempre)
   POST /api/auth/login
   → Guardar token y personals_id

2. Listar las auditorías pendientes del usuario
   GET /api/auditorias?estado=pendiente_auditoria
   → Mostrar la lista (funciona igual para técnico o auditor, ver sección 2)

3. Ver el detalle para mostrar el hallazgo que se va a auditar
   GET /api/auditorias/{id}
   → Mostrar reporte.comercio, reporte.imagenes, reporte.observaciones, orden.numero_orden, etc.

4. Subir las evidencias de la revisión (fotos/PDFs)
   POST /api/upload  (directorio: "auditorias")  → { "path": "COMPANY_ID/auditorias/evidencia1.jpg" }
   POST /api/upload  (directorio: "auditorias")  → { "path": "COMPANY_ID/auditorias/evidencia2.pdf" }

5. Registrar el resultado
   PUT /api/auditorias/{id}
   Body: {
     "evidencias": [
       "COMPANY_ID/auditorias/evidencia1.jpg",
       "COMPANY_ID/auditorias/evidencia2.pdf"
     ],
     "resultado": "confirmado",
     "tipo_anomalia_id": "uuid-anomalia",
     "metros_cubicos_recuperados": "12.5",
     "observaciones": "Hallazgo confirmado en sitio."
   }
   → 200 con la auditoría actualizada

6. Mostrar confirmación al usuario con response.message
```

---

## 8. Códigos de error

Mismos códigos y formato que el resto de la API (ver `API_TECNICO.md` §9): `401`, `403`, `404`, `422` (con `errors` por campo), `500`.

---

## 9. Notas de implementación para la app

- **Reutiliza tus parsers existentes.** `reporte` y `orden` dentro de la respuesta de auditoría tienen exactamente la misma forma que ya manejas en `OrdenService` — no crees modelos/parsers nuevos para ellos, solo agrega el objeto `auditoria` (estado, evidencias, observaciones, etc.) como una entidad nueva.
- **El campo `reporte.comercio` es nuevo** en `ReporteResource` — si en algún momento tu parser de reporte usa un `array_replace`/whitelist estricto de claves conocidas, revisa que no descarte esta clave nueva quando la reciba desde el endpoint de reporte normal (`/tecnico/ordenes/{id}/reporte`), donde también empezará a aparecer cuando el reporte tenga comercio.
- **No hay soporte offline/cola todavía** para este endpoint (a diferencia de `crearReporte`/`actualizarReporte`, que sí tienen cola offline vía `OfflineSyncService`). Si la pantalla de auditoría necesita funcionar sin conexión, hay que diseñarlo — avísame cuando lleguen a esa parte.
- **`GET /api/auditorias` no está paginado.** Si con el tiempo la lista crece mucho (muchas auditorías históricas), avisen para agregar paginación — hoy devuelve todo en un solo `data`, igual que `mis-ordenes`.
- **Autorización:** si tu app distingue entre "modo técnico" y "modo auditor" en la UI, recuerda que el mismo endpoint sirve para ambos casos — la API decide el acceso según `auditorias.ver` o dueño de la orden, no hace falta que la app mande ningún flag adicional. Esto aplica también al nuevo listado.
