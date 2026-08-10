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
3. [GET /api/auditorias/{id} — Ver detalle](#3-get-apiauditoriasid--ver-detalle)
4. [PUT /api/auditorias/{id} — Registrar resultado](#4-put-apiauditoriasid--registrar-resultado)
5. [Campos de referencia](#5-campos-de-referencia)
6. [Flujo completo de ejemplo](#6-flujo-completo-de-ejemplo)
7. [Códigos de error](#7-códigos-de-error)
8. [Notas de implementación para la app](#8-notas-de-implementación-para-la-app)

---

## 1. ¿Qué es una Auditoría?

Cuando una orden de tipo `fraudes` o `revision_medidores` se completa con `resultado = "positivo"`, el sistema crea **automáticamente** un registro de `Auditoria` asociado al reporte de esa orden, en estado `pendiente_auditoria`.

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

> No hay un endpoint de "listar mis auditorías pendientes" todavía. Si lo necesitas para la pantalla de la app, avísame y lo agregamos — por ahora se asume que la app llega al `{id}` de la auditoría desde algún otro lugar (por ejemplo, si en el futuro se agrega el campo `auditoria_id` o similar a la orden en `/api/tecnico/mis-ordenes`).

---

## 3. GET /api/auditorias/{id} — Ver detalle

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

## 4. PUT /api/auditorias/{id} — Registrar resultado

```
PUT /api/auditorias/{id}
```

Guarda las evidencias y el resultado de la revisión. Se puede llamar más de una vez sobre la misma auditoría (por ejemplo, para corregir observaciones) — cada llamada sobrescribe `evidencias`, `estado`, `observaciones`, `tipo_anomalia_id` y `metros_cubicos_recuperados`, y actualiza `auditado_por_id` y `fecha_auditoria` al usuario/momento de la última llamada.

**Body (JSON):**

| Campo | Tipo | Obligatorio | Descripción |
|---|---|---|---|
| `evidencias` | array de strings (paths S3) | **Sí** (mínimo 1) | Rutas devueltas por `POST /api/upload`, igual que `imagenes` en el reporte. Recomendado: `directorio=auditorias` al subirlas. |
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
- `404` — el UUID no corresponde a ninguna auditoría.

---

## 5. Campos de referencia

### 5.1 Estados de una Auditoría (`estado.value`)

| Valor | Etiqueta | Color sugerido | ¿Se puede enviar por API? |
|---|---|---|---|
| `pendiente_auditoria` | Pendiente por auditar | warning (amarillo) | No — es el estado inicial automático, no se puede volver a este valor vía `PUT`. |
| `confirmado` | Confirmado | success (verde) | Sí |
| `negativo` | Negativo | danger (rojo) | Sí |

### 5.2 Tipos de archivo aceptados en `evidencias`

Los mismos que ya conoces de `/api/upload`: imágenes (`jpg`, `jpeg`, `png`, `gif`, `webp`, `heic`, `heif`), video (`mp4`, `mov`), documentos (`pdf`, `doc`, `docx`, `xls`, `xlsx`, `ppt`, `pptx`). Máximo 10 MB por archivo. Recomendado usar `directorio=auditorias` al subir para mantener organizado el bucket, aunque no es obligatorio.

---

## 6. Flujo completo de ejemplo

```
1. Login (igual que siempre)
   POST /api/auth/login
   → Guardar token y personals_id

2. Llegar a una auditoría pendiente
   (Todavía no hay endpoint de "listado de auditorías pendientes" — el id debe
   venir de otro lugar por ahora, ej. una notificación o un enlace directo)

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

## 7. Códigos de error

Mismos códigos y formato que el resto de la API (ver `API_TECNICO.md` §9): `401`, `403`, `404`, `422` (con `errors` por campo), `500`.

---

## 8. Notas de implementación para la app

- **Reutiliza tus parsers existentes.** `reporte` y `orden` dentro de la respuesta de auditoría tienen exactamente la misma forma que ya manejas en `OrdenService` — no crees modelos/parsers nuevos para ellos, solo agrega el objeto `auditoria` (estado, evidencias, observaciones, etc.) como una entidad nueva.
- **El campo `reporte.comercio` es nuevo** en `ReporteResource` — si en algún momento tu parser de reporte usa un `array_replace`/whitelist estricto de claves conocidas, revisa que no descarte esta clave nueva quando la reciba desde el endpoint de reporte normal (`/tecnico/ordenes/{id}/reporte`), donde también empezará a aparecer cuando el reporte tenga comercio.
- **No hay soporte offline/cola todavía** para este endpoint (a diferencia de `crearReporte`/`actualizarReporte`, que sí tienen cola offline vía `OfflineSyncService`). Si la pantalla de auditoría necesita funcionar sin conexión, hay que diseñarlo — avísame cuando lleguen a esa parte.
- **No existe todavía** un endpoint de listado ("mis auditorías pendientes"). Si la pantalla de la app necesita listar auditorías pendientes del técnico o del auditor (en vez de recibir el `id` desde otro lado), lo agregamos como un paso aparte — es rápido de construir siguiendo el mismo patrón de `GET /api/tecnico/mis-ordenes`.
- **Autorización:** si tu app distingue entre "modo técnico" y "modo auditor" en la UI, recuerda que el mismo endpoint sirve para ambos casos — la API decide el acceso según `auditorias.ver` o dueño de la orden, no hace falta que la app mande ningún flag adicional.
