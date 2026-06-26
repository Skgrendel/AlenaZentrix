# API del Módulo Técnico — Referencia para el Desarrollador

**Base URL:** `https://alenazentrix.test` (local Herd) / `https://tu-dominio.com` (producción)  
**Prefijo de todas las rutas:** `/api`  
**Formato:** JSON  
**Autenticación:** Bearer token (Laravel Sanctum)

---

## Tabla de Contenido

1. [Convenciones generales](#1-convenciones-generales)
2. [Autenticación](#2-autenticación)
3. [Órdenes del técnico](#3-órdenes-del-técnico)
4. [Reportes](#4-reportes)
5. [Catálogos](#5-catálogos)
6. [Archivos (subida y acceso)](#6-archivos-subida-y-acceso)
7. [Referencia de campos por tipo de servicio](#7-referencia-de-campos-por-tipo-de-servicio)
8. [Flujos completos de ejemplo](#8-flujos-completos-de-ejemplo)
9. [Códigos de error](#9-códigos-de-error)
10. [Usando con Postman y Bruno](#10-usando-con-postman-y-bruno)

---

## 1. Convenciones generales

### Headers requeridos en todas las peticiones autenticadas

```
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

> **Importante:** El header `Accept: application/json` es obligatorio. Sin él, Laravel puede devolver HTML en lugar de JSON cuando ocurra un error de validación.

### Formato de respuesta exitosa

Todas las respuestas exitosas devuelven JSON. Los endpoints de colección devuelven un array en `data`. Los endpoints de acción devuelven `message` y el recurso afectado.

### Formato de error de validación (HTTP 422)

```json
{
  "message": "The campo field is required.",
  "errors": {
    "campo": ["The campo field is required."]
  }
}
```

### Tipos de servicio — valores en base de datos

| Valor exacto en BD | Etiqueta visible |
|---|---|
| `fraudes` | Fraudes |
| `revision_medidores` | Revisión de Medidores |
| `investigacion` | Investigación |
| `gestion_legal` | Gestión Legal |
| `vigilancia` | Dispositivos de Vigilancia |
| `Gestión de Riesgo` | Gestión de Riesgo |
| `auditoria_campo` | Auditorías de Campo |

> ⚠️ `Gestión de Riesgo` tiene espacio y tilde en su valor — así está guardado en la BD. Compara con `===`.

### Estados de una orden

| Valor | Etiqueta | Color sugerido |
|---|---|---|
| `pendiente` | Pendiente | Gris |
| `asignada` | Asignada | Azul |
| `en_progreso` | En Progreso | Amarillo |
| `en_revision` | En Revisión | Morado |
| `completada` | Completada | Verde |
| `rechazada` | Rechazada | Rojo |

---

## 2. Autenticación

### 2.1 Iniciar sesión

```
POST /api/auth/login
```

No requiere token. Es la única ruta pública de la API.

**Body:**
```json
{
  "email": "tecnico@empresa.com",
  "password": "tu_contraseña"
}
```

**Respuesta exitosa (200):**
```json
{
  "token": "1|abc123xyz...",
  "user": {
    "id": "uuid-del-usuario",
    "email": "tecnico@empresa.com",
    "nombre": "Juan Pérez",
    "iniciales": "JP",
    "personals_id": "uuid-del-personal"
  }
}
```

> Guarda el `token` y el `personals_id`. El `personals_id` identifica al técnico en el sistema y se usa para determinar qué órdenes le pertenecen.

**Posibles errores:**
- `401` — Credenciales incorrectas
- `403` — La cuenta está desactivada

---

### 2.2 Cerrar sesión

```
DELETE /api/auth/logout
```

Revoca el token actual.

**Respuesta exitosa (200):**
```json
{
  "message": "Sesión cerrada correctamente."
}
```

---

## 3. Órdenes del técnico

### 3.1 Listar mis órdenes

```
GET /api/tecnico/mis-ordenes
```

Devuelve todas las órdenes relevantes para el técnico autenticado:
- Órdenes asignadas a su `personals_id` (en estado activo)
- **Todas** las órdenes de tipo `vigilancia` (sin importar el técnico asignado)

Las órdenes en estado `en_revision` aparecen primero. El resto en orden descendente de creación.

**No tiene parámetros.**

**Respuesta exitosa (200):**
```json
{
  "data": [
    {
      "id": "uuid-orden",
      "numero_orden": "ODT-000045",
      "tipo_servicio": {
        "value": "fraudes",
        "label": "Fraudes",
        "color": "danger"
      },
      "estado": {
        "value": "asignada",
        "label": "Asignada",
        "color": "info"
      },
      "prioridad": {
        "value": "alta",
        "label": "Alta",
        "color": "warning"
      },
      "contrato": "12345678",
      "cliente": "Pedro López",
      "direccion": "Calle 10 # 5-20",
      "barrio": "Centro",
      "medidor": "MED-001",
      "tipo_medidor": null,
      "ciclo": null,
      "objetivo": "Verificar consumo irregular",
      "antecedente": "Reportado en octubre 2024",
      "comentario_usuario": null,
      "actividades": [
        { "descripcion": "Verificar medidor" },
        { "descripcion": "Revisar instalación" }
      ],
      "documentos": [],
      "proceso": null,
      "fecha_asignacion": "2026-06-20T08:00:00.000000Z",
      "fecha_limite": "2026-06-30T23:59:59.000000Z",
      "fecha_completado": null,
      "acciones": {
        "puede_ver": true,
        "puede_iniciar": true,
        "puede_reportar": false,
        "puede_cerrar_vigilancia": false,
        "puede_editar_reporte": false
      },
      "created_at": "2026-06-20T07:45:00.000000Z"
    }
  ]
}
```

**El objeto `acciones` indica qué botones mostrar en la interfaz:**

| Campo | Cuándo es `true` | Botón a mostrar |
|---|---|---|
| `puede_ver` | Estado ≠ `en_progreso` | "Ver detalle" |
| `puede_iniciar` | Estado = `asignada` Y tipo ≠ `vigilancia` | "Iniciar" |
| `puede_reportar` | Estado = `en_progreso` Y (tipo = `vigilancia` O sin reportes aún) | "Agregar Reporte" |
| `puede_cerrar_vigilancia` | Tipo = `vigilancia` Y estado = `en_progreso` | "Cerrar Vigilancia" |
| `puede_editar_reporte` | Estado = `rechazada` Y tipo ≠ `vigilancia` | "Editar Reporte" |

---

### 3.2 Ver detalle de una orden

```
GET /api/tecnico/ordenes/{id}
```

**Parámetros de ruta:**
- `{id}` — UUID de la orden

**Respuesta exitosa (200):**

Misma estructura que cada ítem de la lista, con la adición del campo:
```json
{
  "data": {
    ...
    "tiene_reporte": true
  }
}
```

> `tiene_reporte` solo aparece en este endpoint (no en la lista). Úsalo para saber si ya existe un reporte antes de intentar crear uno.

---

### 3.3 Iniciar una orden

```
POST /api/tecnico/ordenes/{id}/iniciar
```

Cambia el estado de `asignada` → `en_progreso`. Envía correo al coordinador.

**Restricciones:**
- La orden debe estar en estado `asignada`
- No aplica a órdenes de tipo `vigilancia` (esas inician automáticamente)

**Body:** vacío `{}`

**Respuesta exitosa (200):**
```json
{
  "message": "Orden iniciada correctamente.",
  "orden": { ...objeto orden con estado actualizado... }
}
```

**Posibles errores:**
- `422` — La orden no está en estado `asignada`
- `422` — Es una orden de Vigilancia

---

### 3.4 Cerrar vigilancia

```
POST /api/tecnico/ordenes/{id}/cerrar-vigilancia
```

Cambia el estado de `en_progreso` → `en_revision` para órdenes de Vigilancia.

**Restricciones:**
- Solo para órdenes de tipo `vigilancia`
- La orden debe estar en estado `en_progreso`

**Body:** vacío `{}`

**Respuesta exitosa (200):**
```json
{
  "message": "Vigilancia cerrada correctamente.",
  "orden": { ...objeto orden con estado actualizado... }
}
```

---

## 4. Reportes

### 4.1 Crear reporte

```
POST /api/tecnico/ordenes/{id}/reporte
```

Registra el reporte de campo. Los campos varían según el `tipo_servicio` de la orden.

**Restricciones:**
- La orden debe estar en estado `en_progreso`
- Para tipos que no son `vigilancia`: solo se permite un reporte por orden
- Para `vigilancia`: se pueden crear múltiples reportes (turnos)

**Comportamiento al guardar:**
- Crea un registro de geolocalización (`latitud`, `longitud`)
- Para `fraudes` / `revision_medidores`: además crea un registro de comercio
- Para todos excepto `vigilancia`: cambia el estado de la orden a `en_revision`
- Para todos excepto `vigilancia`: envía correo de notificación al coordinador
- Para `fraudes`: si hay fuga de gas, exceso de capacidad o anomalía "Bypass" → envía alertas adicionales

**Campos comunes (todos los tipos):**

| Campo | Tipo | Obligatorio | Descripción |
|---|---|---|---|
| `latitud` | string | No | Coordenada GPS del técnico |
| `longitud` | string | No | Coordenada GPS del técnico |
| `observaciones` | string | No | Observaciones generales |
| `imagenes` | array de strings | No | Rutas S3 de archivos (subidos previamente con `/api/upload`) |
| `video` | string (URL) | No | URL a video externo |
| `geo_referencia` | string (URL) | No | URL a mapa o geolocalización |
| `actividades` | array de objetos | No | Ver estructura abajo |

**Estructura de `actividades`:**
```json
"actividades": [
  {
    "descripcion_orden": "Verificar medidor",
    "actividades": "El medidor estaba en buen estado..."
  },
  {
    "descripcion_orden": "Revisar instalación",
    "actividades": "La instalación presentaba..."
  }
]
```
> Los ítems en `actividades` deben corresponder con las actividades que vienen en el campo `actividades` del objeto orden. El sistema asignará un `uuid` automáticamente a cada uno.

**Respuesta exitosa (201):**
```json
{
  "message": "Reporte registrado correctamente.",
  "reporte": {
    "id": "uuid-reporte",
    "orden_job_id": "uuid-orden",
    "numero_orden": "ODT-000045",
    "numero_medidor": "MED-001",
    "coincide_medidor": true,
    "lectura": "1234",
    "anomalia": ["uuid-anomalia-1"],
    "hay_fuga_gas": false,
    "excede_capacidad": false,
    "imagenes": ["reportes/ODT-000045/foto1.jpg"],
    "video": null,
    "geo_referencia": null,
    "observaciones": "Sin novedad.",
    "comentarios": null,
    "conclusion": null,
    "actividades": [...],
    "datos_servicio": null,
    "estado": null,
    "revisado": false,
    "confirmado": false,
    "ubicacion": {
      "latitud": "4.6097",
      "longitud": "-74.0817",
      "direccion": "Calle 10 # 5-20"
    },
    "created_at": "2026-06-26T10:30:00.000000Z"
  }
}
```

---

### 4.2 Editar reporte rechazado

```
PUT /api/tecnico/ordenes/{id}/reporte
```

Corrige el reporte más reciente de una orden rechazada y lo reenvía a revisión.

**Restricciones:**
- La orden debe estar en estado `rechazada`
- No aplica a órdenes de tipo `vigilancia`

**Comportamiento al guardar:**
- Actualiza el reporte existente (no crea uno nuevo)
- Las imágenes nuevas se **acumulan** a las existentes (no las reemplaza)
- Cambia el estado de la orden a `en_revision`
- Envía correo de notificación al coordinador

**Body:** idéntico al de creación del reporte (con los campos del tipo de servicio correspondiente). Las `imagenes` que envíes se agregan a las ya guardadas — si no quieres agregar fotos nuevas, envía `"imagenes": []` o simplemente no incluyas el campo.

**Respuesta exitosa (200):**
```json
{
  "message": "Reporte actualizado correctamente.",
  "reporte": { ...objeto reporte actualizado... }
}
```

---

## 5. Catálogos

Todos los endpoints de catálogo devuelven la misma estructura:

```json
[
  { "id": "uuid", "nombre": "Nombre del ítem" },
  { "id": "uuid", "nombre": "Otro ítem" }
]
```

Los ítems están filtrados por `activo = true` y ordenados por nombre.

> **Cuándo cargarlos:** Carga todos los catálogos al iniciar sesión o al abrir el formulario de reporte. Guárdalos localmente (SQLite, SharedPreferences, etc.) para no depender de conexión en campo.

| Endpoint | Catálogo | Usado en tipos de servicio |
|---|---|---|
| `GET /api/catalogos/tipos-comercio` | Tipos de comercio | Fraudes, Revisión de Medidores, Investigación |
| `GET /api/catalogos/marcas-medidor` | Marcas de medidor | Fraudes, Revisión de Medidores |
| `GET /api/catalogos/marcas-regulador` | Descripción del medidor | Fraudes, Revisión de Medidores |
| `GET /api/catalogos/tipos-regulador` | Tipo de presión | Fraudes, Revisión de Medidores |
| `GET /api/catalogos/tipos-anomalia` | Anomalías detectables | Fraudes, Revisión de Medidores |
| `GET /api/catalogos/tipos-imposibilidad` | Razones de imposibilidad | Fraudes, Revisión de Medidores |
| `GET /api/catalogos/tipos-notificacion-alerta` | Tipos de alerta | Fraudes, Revisión de Medidores |
| `GET /api/catalogos/convenciones` | Convenciones | Investigación |
| `GET /api/catalogos/resultados-reporte` | Resultados posibles | Investigación, Vigilancia |

**Ejemplo de respuesta `GET /api/catalogos/tipos-comercio`:**
```json
[
  { "id": "uuid-1", "nombre": "Panadería" },
  { "id": "uuid-2", "nombre": "Restaurante" },
  { "id": "uuid-3", "nombre": "Taller" }
]
```

---

## 6. Archivos (subida y acceso)

### 6.1 Subir un archivo a S3

```
POST /api/upload
```

Sube un archivo a S3 y devuelve la ruta guardada. **Llama a este endpoint antes de enviar el reporte.** Acumula las rutas devueltas y envíalas en el campo `imagenes` del reporte.

**Body:** `multipart/form-data`

| Campo | Tipo | Obligatorio | Descripción |
|---|---|---|---|
| `archivo` | file | Sí | El archivo a subir |
| `directorio` | string | No | Subdirectorio relativo. Default: `reportes/varios`. Recomendado: `reportes/ODT-000045` |

> ⚠️ **Importante (seguridad):** El servidor antepone automáticamente el `company_id` del usuario autenticado al directorio. El `path` devuelto siempre tendrá el formato `{company_id}/{directorio}/{filename}`. No es necesario que la app lo construya manualmente — simplemente usa el `path` que devuelve la respuesta tal como viene.

**Tipos de archivo aceptados:**

| Categoría | Extensiones |
|---|---|
| Imágenes | `jpg`, `jpeg`, `png`, `gif`, `webp`, `heic`, `heif` |
| Video | `mp4`, `mov` |
| Documentos | `pdf`, `doc`, `docx`, `xls`, `xlsx`, `ppt`, `pptx` |

**Tamaño máximo:** 10 MB por archivo

**Respuesta exitosa (201):**
```json
{
  "path": "f47ac10b-58cc-4372-a567-0e02b2c3d479/reportes/ODT-000045/abc123.jpg"
}
```

> Guarda este `path` exactamente como viene. Es lo que debes incluir en el array `imagenes` (o en `foto_detector_fuga`) al crear el reporte.

**Ejemplo de flujo para subir 3 fotos** (donde `f47ac10b-...` es el `company_id` de tu empresa):
```
POST /api/upload  → { "path": "f47ac10b-.../reportes/ODT-000045/foto1.jpg" }
POST /api/upload  → { "path": "f47ac10b-.../reportes/ODT-000045/foto2.jpg" }
POST /api/upload  → { "path": "f47ac10b-.../reportes/ODT-000045/foto3.jpg" }

Luego en el reporte:
"imagenes": [
  "f47ac10b-.../reportes/ODT-000045/foto1.jpg",
  "f47ac10b-.../reportes/ODT-000045/foto2.jpg",
  "f47ac10b-.../reportes/ODT-000045/foto3.jpg"
]
```

---

### 6.2 Obtener URL de un archivo de S3

```
GET /api/archivos/{path}
```

Genera una URL temporal pre-firmada (válida 15 minutos) para acceder a un archivo almacenado en S3.

**Parámetros de ruta:**
- `{path}` — La ruta del archivo **exactamente como fue devuelta por `/api/upload`**. Incluye el `company_id` como prefijo.

> ⚠️ **Importante (seguridad):** El servidor verifica que el `path` solicitado comience con el `company_id` del usuario autenticado. Si intentas acceder a una ruta de otra empresa recibirás `403`. Usa siempre el `path` devuelto por `/api/upload` sin modificarlo.

**Ejemplo de petición:**
```
GET /api/archivos/f47ac10b-58cc-4372-a567-0e02b2c3d479/reportes/ODT-000045/foto1.jpg
```

**Respuesta exitosa (200):**
```json
{
  "url": "https://tu-bucket.s3.amazonaws.com/f47ac10b-.../reportes/ODT-000045/foto1.jpg?X-Amz-Signature=..."
}
```

> Úsalo para mostrar imágenes y documentos en la app. La URL expira en 15 minutos, así que no la guardes de forma permanente.

**Posibles errores:**
- `403` — El path no pertenece a la empresa del usuario autenticado
- `404` — Archivo no encontrado en S3

---

## 7. Referencia de campos por tipo de servicio

Esta sección detalla los campos adicionales que debe incluir el body del reporte según el `tipo_servicio` de la orden.

---

### 7.1 Fraudes y Revisión de Medidores

> Aplica cuando `tipo_servicio.value` es `fraudes` o `revision_medidores`.

Además de los campos comunes, envía:

| Campo | Tipo | Obligatorio | Descripción |
|---|---|---|---|
| `nombre_comercio` | string | **Sí** | Nombre del comercio encontrado |
| `tipo_comercio_id` | UUID | **Sí** | ID del tipo de comercio (de catálogo) |
| `numero_medidor` | string | No | Número del medidor encontrado en sitio |
| `coincide_medidor` | boolean | No | `true` si coincide con el medidor asignado en la orden |
| `lectura` | string | No | Lectura del medidor |
| `marca_regulador_id` | UUID | No | Descripción del medidor (de catálogo) |
| `tipo_regulador_id` | UUID | No | Tipo de presión (de catálogo) |
| `marca_medidor_id` | UUID | No | Marca del medidor (de catálogo) |
| `tipo_notificacion_alerta_id` | UUID | No | Tipo de alerta (de catálogo) |
| `anomalia` | array de UUIDs | No | IDs de anomalías detectadas (de catálogo) |
| `tipo_imposibilidad_id` | UUID | No | Si hubo imposibilidad de atender (de catálogo) |
| `hay_fuga_gas` | boolean | No | `true` si se detectó fuga de gas |
| `foto_detector_fuga` | string (path S3) | **Sí si `hay_fuga_gas`=true** | Path de la foto del detector (subida previamente) |
| `excede_capacidad` | boolean | No | `true` si la instalación excede capacidad |

**Ejemplo de body completo:**
```json
{
  "latitud": "4.6097",
  "longitud": "-74.0817",
  "nombre_comercio": "Panadería El Trigal",
  "tipo_comercio_id": "uuid-tipo-comercio",
  "numero_medidor": "MED-99821",
  "coincide_medidor": false,
  "lectura": "00234",
  "marca_regulador_id": "uuid-marca-reg",
  "tipo_regulador_id": "uuid-tipo-reg",
  "marca_medidor_id": "uuid-marca-med",
  "tipo_notificacion_alerta_id": null,
  "anomalia": ["uuid-anomalia-bypass"],
  "tipo_imposibilidad_id": null,
  "hay_fuga_gas": true,
  "foto_detector_fuga": "reportes/ODT-000045/detector.jpg",
  "excede_capacidad": false,
  "observaciones": "El medidor no coincide con el registrado en contrato.",
  "imagenes": [
    "reportes/ODT-000045/foto1.jpg",
    "reportes/ODT-000045/foto2.jpg"
  ],
  "video": null,
  "geo_referencia": "https://maps.google.com/?q=4.6097,-74.0817"
}
```

> ⚠️ Si `hay_fuga_gas` es `true` y `anomalia` contiene el ID de la anomalía "Bypass", el sistema enviará automáticamente alertas por correo a los responsables.

---

### 7.2 Investigación

> Aplica cuando `tipo_servicio.value` es `investigacion`.

| Campo | Tipo | Obligatorio | Descripción |
|---|---|---|---|
| `convencion_id` | UUID | **Sí** | Convención aplicada (de catálogo) |
| `tipo_investigado` | string | **Sí** | `persona` o `comercio` |
| `acompanamiento_tecnico` | boolean | No | Si hubo acompañante técnico |
| `acompanante_nombre` | string | Sí si `acompanamiento_tecnico`=true | Nombre del acompañante |
| `acompanante_identificacion` | string | Sí si `acompanamiento_tecnico`=true | Cédula del acompañante |
| `acompanante_cargo` | string | Sí si `acompanamiento_tecnico`=true | Cargo/empresa del acompañante |
| `acompanante_cuadrilla` | string | No | Código de cuadrilla |
| `nombre_investigado` | string | No | Nombre (si `tipo_investigado`=`persona`) |
| `identificacion` | string | No | Cédula (si `tipo_investigado`=`persona`) |
| `descripcion_fisica` | string | No | Descripción física (si `tipo_investigado`=`persona`) |
| `nombre_comercio_inv` | string | No | Nombre del comercio (si `tipo_investigado`=`comercio`) |
| `tipo_comercio_inv_id` | UUID | No | Tipo de comercio (si `tipo_investigado`=`comercio`) |
| `resultado_reporte_id` | UUID | No | Resultado de la investigación (de catálogo) |
| `actividades` | array | No | Actividades con respuesta del investigador |

**Ejemplo de body:**
```json
{
  "latitud": "4.6097",
  "longitud": "-74.0817",
  "convencion_id": "uuid-convencion",
  "tipo_investigado": "persona",
  "acompanamiento_tecnico": false,
  "nombre_investigado": "Carlos Pérez",
  "identificacion": "1020304050",
  "descripcion_fisica": "Hombre, 40 años, cabello negro",
  "resultado_reporte_id": "uuid-resultado",
  "observaciones": "El investigado reside en el predio.",
  "actividades": [
    {
      "descripcion_orden": "Verificar residencia",
      "actividades": "Se confirmó que el investigado habita el inmueble."
    }
  ],
  "imagenes": ["reportes/ODT-000046/foto1.jpg"],
  "video": null,
  "geo_referencia": null
}
```

---

### 7.3 Gestión Legal

> Aplica cuando `tipo_servicio.value` es `gestion_legal`.

| Campo | Tipo | Obligatorio | Descripción |
|---|---|---|---|
| `fecha_ejecucion` | date (`YYYY-MM-DD`) | **Sí** | Fecha en que se ejecutó la actividad |
| `departamento` | string | No | Departamento donde se realizó |
| `municipio` | string | No | Municipio |
| `radio_objetivo` | string | No | Radio del objetivo |
| `actividades` | array | No | Actividades con respuesta |

**Ejemplo de body:**
```json
{
  "latitud": "4.6097",
  "longitud": "-74.0817",
  "fecha_ejecucion": "2026-06-26",
  "departamento": "Cundinamarca",
  "municipio": "Bogotá",
  "radio_objetivo": "500m",
  "observaciones": "Diligencia realizada sin inconvenientes.",
  "actividades": [
    {
      "descripcion_orden": "Notificar al demandado",
      "actividades": "Se dejó aviso en la puerta del predio."
    }
  ],
  "imagenes": ["reportes/ODT-000047/soporte.pdf"],
  "video": null,
  "geo_referencia": null
}
```

---

### 7.4 Vigilancia

> Aplica cuando `tipo_servicio.value` es `vigilancia`.

Cada envío registra un turno. La orden **no** cambia de estado al guardar.

| Campo | Tipo | Obligatorio | Descripción |
|---|---|---|---|
| `fecha_turno` | date (`YYYY-MM-DD`) | **Sí** | Fecha del turno |
| `hora_inicio` | string (`HH:MM` o `HH:MM:SS`) | **Sí** | Hora de inicio del turno |
| `hora_fin` | string (`HH:MM` o `HH:MM:SS`) | **Sí** | Hora de fin del turno |
| `hay_novedad` | boolean | No | Si hubo novedades durante el turno |
| `descripcion_novedad` | string | Sí si `hay_novedad`=true | Detalle de las novedades |
| `hay_reincidencia` | boolean | No | Si hay reincidencia (solo si hay novedad) |
| `resultado_reporte_id` | UUID | No | Resultado del turno (de catálogo) |

**Ejemplo de body:**
```json
{
  "latitud": "4.6097",
  "longitud": "-74.0817",
  "fecha_turno": "2026-06-26",
  "hora_inicio": "08:00",
  "hora_fin": "16:00",
  "hay_novedad": true,
  "descripcion_novedad": "Se observó ingreso no autorizado al predio a las 11:30.",
  "hay_reincidencia": false,
  "resultado_reporte_id": "uuid-resultado",
  "observaciones": "Turno sin más incidentes.",
  "imagenes": ["reportes/ODT-000048/foto1.jpg"],
  "video": null,
  "geo_referencia": null
}
```

> Después de guardar el turno, la orden sigue en `en_progreso`. Para finalizarla, llama a `POST /api/tecnico/ordenes/{id}/cerrar-vigilancia`.

---

### 7.5 Gestión de Riesgo

> Aplica cuando `tipo_servicio.value` es `Gestión de Riesgo` (con tilde y espacio).

Solo se requieren los campos comunes. No tiene campos específicos obligatorios.

**Ejemplo de body:**
```json
{
  "latitud": "4.6097",
  "longitud": "-74.0817",
  "observaciones": "Se realizó la inspección del punto de riesgo.",
  "actividades": [
    {
      "descripcion_orden": "Evaluar riesgo estructural",
      "actividades": "Se identificaron grietas en la pared sur."
    }
  ],
  "imagenes": [
    "reportes/ODT-000049/foto1.jpg",
    "reportes/ODT-000049/informe.pdf"
  ],
  "video": null,
  "geo_referencia": "https://maps.google.com/?q=4.6097,-74.0817"
}
```

---

### 7.6 Auditoría de Campo

> Aplica cuando `tipo_servicio.value` es `auditoria_campo`.

El formulario más extenso. Todos los campos van en `datos_servicio` (el servidor los separa automáticamente).

| Campo | Tipo | Obligatorio | Descripción |
|---|---|---|---|
| `nombre_atiende` | string | **Sí** | Nombre de quien atendió la visita |
| `fecha_hora_atencion` | datetime (`YYYY-MM-DD HH:MM:SS`) | **Sí** | Fecha y hora de atención |
| `recibio_visita` | boolean | **Sí** | Si el usuario recibió al técnico |
| `se_realizo_revision` | boolean | **Sí** | Si se realizó revisión técnica |
| `autoriza` | boolean | **Sí** | Si el usuario autoriza la recolección de datos |
| `cedula_usuario` | string | **Sí** | Cédula del usuario que atendió |
| `cedula_auditor` | string | **Sí** | Cédula del auditor |
| `vinculo_usuario` | string | No | `titular`, `familiar`, `vecino` o `otro` |
| `celular_atiende` | string | No | Celular de quien atendió |
| `fecha_ejecucion` | date | No | Fecha de ejecución del trabajo |
| `tiempo_ejecucion` | string | No | Ej: "2 horas" |
| `descripcion_trabajo` | string | No | Descripción del trabajo realizado |
| `se_detecto_fuga` | boolean | No | Si se detectó fuga o falla |
| `ppm` | string | No | Medición PPM (si hay fuga) |
| `vol_porcentaje` | string | No | VOL % (si hay fuga) |
| `problema_persiste` | boolean | No | Si el problema sigue activo |
| `evidencia_reparacion` | boolean | No | Si se evidencia reparación |
| `servicio_correcto` | boolean | No | Si el servicio funciona correctamente |
| `se_usaron_materiales` | boolean | No | Si se usaron materiales |
| `quien_suministro_materiales` | string | No | `tecnico`, `usuario` o `empresa` |
| `usuario_asumio_costo` | boolean | No | Si el usuario pagó algo |
| `valor_pagado` | string | No | Valor pagado |
| `medio_pago` | string | No | `efectivo` o `transferencia` |
| `entrego_factura` | boolean | No | Si se entregó soporte de cobro |
| `tecnico_dejo_documento` | string | No | Documento que dejó el técnico (IVT, certificado) |
| `informaron_no_estaba` | boolean | No | Si le dijeron que el usuario no estaba (siendo falso) |
| `firma_usuario` | string | No | Firma digital en base64 o SVG |
| `firma_auditor` | string | No | Firma digital en base64 o SVG |
| `urls_evidencia` | array de URLs | No | Links a documentos externos (Google Drive, etc.) |

**Ejemplo de body:**
```json
{
  "latitud": "4.6097",
  "longitud": "-74.0817",
  "nombre_atiende": "María García",
  "vinculo_usuario": "titular",
  "celular_atiende": "3001234567",
  "fecha_hora_atencion": "2026-06-26 10:30:00",
  "recibio_visita": true,
  "se_realizo_revision": true,
  "fecha_ejecucion": "2026-06-26",
  "tiempo_ejecucion": "1 hora 30 minutos",
  "descripcion_trabajo": "Se revisaron todas las conexiones internas.",
  "se_detecto_fuga": false,
  "problema_persiste": false,
  "evidencia_reparacion": true,
  "servicio_correcto": true,
  "se_usaron_materiales": true,
  "quien_suministro_materiales": "tecnico",
  "usuario_asumio_costo": false,
  "entrego_factura": false,
  "tecnico_dejo_documento": "IVT-2026-001",
  "informaron_no_estaba": false,
  "autoriza": true,
  "cedula_usuario": "52012345",
  "cedula_auditor": "80123456",
  "firma_usuario": "data:image/png;base64,iVBORw0KGgo...",
  "firma_auditor": "data:image/png;base64,iVBORw0KGgo...",
  "observaciones": "Visita de auditoría realizada sin inconvenientes.",
  "imagenes": ["reportes/ODT-000050/foto1.jpg"],
  "urls_evidencia": ["https://drive.google.com/file/d/abc123"],
  "geo_referencia": null
}
```

---

## 8. Flujos completos de ejemplo

### Flujo 1: Orden de Fraudes (caso más completo)

```
1. Iniciar sesión
   POST /api/auth/login
   → Guardar token y personals_id

2. Cargar catálogos (una sola vez al iniciar sesión)
   GET /api/catalogos/tipos-comercio
   GET /api/catalogos/marcas-medidor
   GET /api/catalogos/marcas-regulador
   GET /api/catalogos/tipos-regulador
   GET /api/catalogos/tipos-anomalia
   GET /api/catalogos/tipos-imposibilidad
   GET /api/catalogos/tipos-notificacion-alerta

3. Listar órdenes
   GET /api/tecnico/mis-ordenes
   → Encontrar una orden con estado "asignada" y tipo "fraudes"

4. Ver detalle (opcional, para mostrar antecedentes y objetivo)
   GET /api/tecnico/ordenes/{id}

5. Iniciar la orden
   POST /api/tecnico/ordenes/{id}/iniciar
   → La orden pasa a "en_progreso"

6. El técnico llena el formulario. Sube fotos mientras trabaja:
   POST /api/upload  (foto del medidor)       → { "path": "COMPANY_ID/reportes/ODT-000045/foto1.jpg" }
   POST /api/upload  (foto de la instalación) → { "path": "COMPANY_ID/reportes/ODT-000045/foto2.jpg" }
   POST /api/upload  (foto del detector)      → { "path": "COMPANY_ID/reportes/ODT-000045/detector.jpg" }
   (guarda los paths exactamente como vienen)

7. Enviar el reporte
   POST /api/tecnico/ordenes/{id}/reporte
   Body: {
     "latitud": "4.6097",
     "longitud": "-74.0817",
     "nombre_comercio": "Panadería El Trigal",
     "tipo_comercio_id": "uuid",
     "numero_medidor": "MED-99821",
     "coincide_medidor": false,
     "lectura": "00234",
     "hay_fuga_gas": true,
     "foto_detector_fuga": "COMPANY_ID/reportes/ODT-000045/detector.jpg",
     "excede_capacidad": false,
     "anomalia": [],
     "observaciones": "Medidor no coincide. Se detectó fuga menor.",
     "imagenes": [
       "COMPANY_ID/reportes/ODT-000045/foto1.jpg",
       "COMPANY_ID/reportes/ODT-000045/foto2.jpg"
     ]
   }
   → La orden pasa a "en_revision"
   → Se envía correo al coordinador
   → Se envía alerta por fuga de gas

8. Mostrar pantalla de "mis órdenes" actualizada
   GET /api/tecnico/mis-ordenes
```

---

### Flujo 2: Orden de Vigilancia (múltiples turnos)

```
1. Listar órdenes → encontrar orden tipo "vigilancia" en estado "en_progreso"

2. Cargar catálogos:
   GET /api/catalogos/resultados-reporte

3. Registrar primer turno
   POST /api/tecnico/ordenes/{id}/reporte
   Body: {
     "fecha_turno": "2026-06-26",
     "hora_inicio": "08:00",
     "hora_fin": "16:00",
     "hay_novedad": false,
     "resultado_reporte_id": "uuid-resultado",
     "observaciones": "Turno sin novedad."
   }
   → La orden SIGUE en "en_progreso"

4. Registrar segundo turno (días después)
   POST /api/tecnico/ordenes/{id}/reporte
   Body: {
     "fecha_turno": "2026-06-27",
     "hora_inicio": "08:00",
     "hora_fin": "16:00",
     "hay_novedad": true,
     "descripcion_novedad": "Ingreso no autorizado detectado a las 11h.",
     "hay_reincidencia": false,
     "resultado_reporte_id": "uuid-resultado",
     "observaciones": "Se tomaron fotografías del incidente."
   }

5. Cuando el coordinador indique, cerrar la vigilancia
   POST /api/tecnico/ordenes/{id}/cerrar-vigilancia
   → La orden pasa a "en_revision"
```

---

### Flujo 3: Corregir reporte rechazado

```
1. Listar órdenes → encontrar orden en estado "rechazada"
   (el campo acciones.puede_editar_reporte será true)

2. Ver detalle para cargar los datos actuales del reporte
   GET /api/tecnico/ordenes/{id}

3. El técnico corrige los datos y sube fotos adicionales si aplica
   POST /api/upload → path_nueva_foto

4. Enviar corrección
   PUT /api/tecnico/ordenes/{id}/reporte
   Body: { ...todos los campos corregidos..., "imagenes": [path_nueva_foto] }
   → Las fotos nuevas SE ACUMULAN a las existentes
   → La orden pasa a "en_revision"
   → Se envía correo al coordinador
```

---

### Flujo 4: Mostrar una foto en la app

```
1. Tienes el path de una imagen guardado en el reporte
   (viene directamente de la respuesta de /api/upload, incluye el company_id):
   "f47ac10b-58cc-4372-a567-0e02b2c3d479/reportes/ODT-000045/foto1.jpg"

2. Solicitar URL temporal — usa el path tal como está, sin modificarlo
   GET /api/archivos/f47ac10b-58cc-4372-a567-0e02b2c3d479/reportes/ODT-000045/foto1.jpg
   → { "url": "https://bucket.s3.amazonaws.com/f47ac10b-.../reportes/ODT-000045/foto1.jpg?X-Amz-..." }

3. Cargar la imagen con esa URL (válida 15 minutos)

⚠️ Si el path no comienza con el company_id del usuario autenticado, el servidor retorna 403.
```

---

## 9. Códigos de error

| Código HTTP | Significado |
|---|---|
| `401` | No autenticado — el token es inválido o no se envió |
| `403` | No autorizado — sin permiso para el recurso solicitado (orden de otro técnico, archivo de otra empresa, etc.) |
| `404` | Recurso no encontrado |
| `422` | Error de validación — revisa el campo `errors` en la respuesta |
| `500` | Error interno del servidor |

### Errores de negocio comunes (422)

| Acción | Mensaje de error | Causa |
|---|---|---|
| Iniciar orden | `La orden no está en estado Asignada.` | El estado ya cambió |
| Iniciar orden | `Las órdenes de Vigilancia no requieren inicio manual.` | Tipo incorrecto |
| Crear reporte | `La orden no está En Progreso.` | Hay que iniciar primero |
| Crear reporte | `Esta orden ya tiene un reporte registrado.` | Solo uno por orden (excepto vigilancia) |
| Editar reporte | `Solo se pueden editar reportes de órdenes rechazadas.` | Estado incorrecto |
| Cerrar vigilancia | `Esta acción solo aplica a órdenes de Vigilancia.` | Tipo incorrecto |

---

---

## 10. Usando con Postman y Bruno

### ¿Cuál usar?

| | **Bruno** | **Postman** |
|---|---|---|
| Licencia | Open source, gratuito | Gratuito con límites |
| Colecciones | Archivos `.bru` en el repo | Exportación JSON |
| Entornos | Archivos `.env` locales | Variables de entorno online |
| Recomendado para | Equipos que usan Git | Uso individual / pruebas rápidas |

---

### 10.1 Bruno

**Instalación:** [usebruno.com](https://www.usebruno.com/) o `winget install Bruno.Bruno`

#### Estructura de colección sugerida

```
alena-api/
├── environments/
│   ├── local.env
│   └── produccion.env
├── auth/
│   ├── login.bru
│   └── logout.bru
├── ordenes/
│   ├── mis-ordenes.bru
│   ├── ver-orden.bru
│   ├── iniciar.bru
│   └── cerrar-vigilancia.bru
├── reportes/
│   ├── crear-reporte.bru
│   └── editar-reporte.bru
├── archivos/
│   ├── upload.bru
│   └── get-url.bru
└── catalogos/
    └── tipos-comercio.bru
```

#### Archivo de entorno `environments/local.env`

```
BASE_URL=https://alenazentrix.test
TOKEN=
ORDEN_ID=
```

> Después de hacer login, copia el token en la variable `TOKEN`. Bruno no tiene scripts automáticos en la versión gratuita, pero puedes hacerlo manualmente.

#### Ejemplo: `auth/login.bru`

```
meta {
  name: Login
  type: http
  seq: 1
}

post {
  url: {{BASE_URL}}/api/auth/login
  body: json
  auth: none
}

body:json {
  {
    "email": "tecnico@empresa.com",
    "password": "tu_contraseña"
  }
}
```

#### Ejemplo: `ordenes/mis-ordenes.bru`

```
meta {
  name: Mis Órdenes
  type: http
  seq: 1
}

get {
  url: {{BASE_URL}}/api/tecnico/mis-ordenes
  body: none
  auth: bearer
}

auth:bearer {
  token: {{TOKEN}}
}
```

#### Ejemplo: `archivos/upload.bru`

```
meta {
  name: Upload Archivo
  type: http
  seq: 1
}

post {
  url: {{BASE_URL}}/api/upload
  body: multipart-form
  auth: bearer
}

auth:bearer {
  token: {{TOKEN}}
}

body:multipart-form {
  archivo: @file(/ruta/a/tu/foto.jpg)
  directorio: reportes/ODT-000045
}
```

> El `path` devuelto incluirá el `company_id` como prefijo: `{company_id}/reportes/ODT-000045/foto.jpg`. Cópialo para usarlo en `/api/archivos/`.

#### Ejemplo: `archivos/get-url.bru`

```
meta {
  name: Obtener URL Archivo
  type: http
  seq: 2
}

get {
  url: {{BASE_URL}}/api/archivos/{{PATH_ARCHIVO}}
  body: none
  auth: bearer
}

auth:bearer {
  token: {{TOKEN}}
}
```

> Crea la variable `PATH_ARCHIVO` en tu entorno con el path completo devuelto por el upload (ej: `f47ac10b-.../reportes/ODT-000045/foto.jpg`).

#### Ejemplo: `reportes/crear-reporte-fraudes.bru`

```
meta {
  name: Crear Reporte - Fraudes
  type: http
  seq: 1
}

post {
  url: {{BASE_URL}}/api/tecnico/ordenes/{{ORDEN_ID}}/reporte
  body: json
  auth: bearer
}

auth:bearer {
  token: {{TOKEN}}
}

body:json {
  {
    "latitud": "4.6097",
    "longitud": "-74.0817",
    "nombre_comercio": "Panadería El Trigal",
    "tipo_comercio_id": "uuid-tipo-comercio",
    "numero_medidor": "MED-99821",
    "coincide_medidor": false,
    "lectura": "00234",
    "hay_fuga_gas": false,
    "excede_capacidad": false,
    "anomalia": [],
    "observaciones": "Sin novedad.",
    "imagenes": []
  }
}
```

---

### 10.2 Postman

**Instalación:** [postman.com/downloads](https://www.postman.com/downloads/)

#### Paso 1: Crear entorno

1. En Postman, abre el panel lateral → **Environments** → **+**
2. Nombre: `AlenaZentrix Local`
3. Agrega estas variables:

| Variable | Initial Value | Current Value |
|---|---|---|
| `BASE_URL` | `https://alenazentrix.test` | `https://alenazentrix.test` |
| `TOKEN` | *(vacío)* | *(se llena después del login)* |
| `ORDEN_ID` | *(vacío)* | *(UUID de la orden a probar)* |
| `PATH_ARCHIVO` | *(vacío)* | *(path devuelto por /api/upload)* |

4. Guarda y selecciona el entorno en el desplegable superior derecho.

#### Paso 2: Crear colección

1. **Collections** → **+** → **Blank Collection**
2. Nombre: `AlenaZentrix API`
3. En la pestaña **Authorization** de la colección elige `Bearer Token` y escribe `{{TOKEN}}`

> Así todas las carpetas heredan la autenticación automáticamente — no hay que configurarla en cada request.

#### Paso 3: Login y captura automática del token

Crea la request de login y agrega este script en la pestaña **Tests**:

```javascript
if (pm.response.code === 200) {
    const json = pm.response.json();
    pm.environment.set("TOKEN", json.token);
    pm.environment.set("COMPANY_ID", json.user.company_id ?? "");
    console.log("Token guardado:", json.token.substring(0, 20) + "...");
}
```

> Después de ejecutar el login, el token se guarda automáticamente en la variable de entorno y todas las demás requests lo usan sin que tengas que copiarlo a mano.

#### Requests clave

**Login**
```
POST {{BASE_URL}}/api/auth/login
Body (raw JSON):
{
  "email": "tecnico@empresa.com",
  "password": "tu_contraseña"
}
```

**Mis órdenes**
```
GET {{BASE_URL}}/api/tecnico/mis-ordenes
Authorization: Bearer {{TOKEN}}  (heredado de la colección)
```

**Iniciar orden**
```
POST {{BASE_URL}}/api/tecnico/ordenes/{{ORDEN_ID}}/iniciar
Body: {} (vacío)
```

**Upload de archivo**
```
POST {{BASE_URL}}/api/upload
Body: form-data
  archivo → tipo File → selecciona el archivo
  directorio → reportes/ODT-000045
```

Agrega este script en **Tests** para capturar el path:
```javascript
if (pm.response.code === 201) {
    pm.environment.set("PATH_ARCHIVO", pm.response.json().path);
}
```

**Obtener URL de archivo**
```
GET {{BASE_URL}}/api/archivos/{{PATH_ARCHIVO}}
```
> `{{PATH_ARCHIVO}}` ya contiene el `company_id` como prefijo si capturaste el valor del upload con el script anterior.

**Crear reporte (Fraudes)**
```
POST {{BASE_URL}}/api/tecnico/ordenes/{{ORDEN_ID}}/reporte
Body (raw JSON):
{
  "latitud": "4.6097",
  "longitud": "-74.0817",
  "nombre_comercio": "Panadería El Trigal",
  "tipo_comercio_id": "{{TIPO_COMERCIO_ID}}",
  "hay_fuga_gas": false,
  "excede_capacidad": false,
  "anomalia": [],
  "observaciones": "Sin novedad.",
  "imagenes": ["{{PATH_ARCHIVO}}"]
}
```

#### Paso 4: Ejecutar un flujo completo con Collection Runner

1. Click derecho en la colección → **Run collection**
2. Ordena las requests en el orden del flujo:
   - Login → Mis Órdenes → Ver Orden → Iniciar → Upload → Crear Reporte
3. Activa **Save responses** para revisar cada paso
4. Click **Run AlenaZentrix API**

---

### 10.3 Tips generales

**Certificado SSL local (Herd):** Si recibes error de SSL en Postman o Bruno con `https://alenazentrix.test`, desactiva temporalmente la verificación:
- **Postman:** Settings → General → SSL certificate verification → OFF
- **Bruno:** Preferences → Request → Disable SSL Verification

**Header obligatorio:** Siempre incluye `Accept: application/json`. Sin él, Laravel puede devolver HTML en lugar de JSON en los errores.

En Postman puedes configurarlo a nivel de colección: pestaña **Headers** → agrega `Accept: application/json`.

En Bruno, agrégalo en cada `.bru`:
```
headers {
  Accept: application/json
}
```

**Paths de archivos:** Recuerda que los paths devueltos por `/api/upload` siempre incluyen el `company_id` como prefijo. Úsalos directamente en el campo `imagenes` del reporte y en el endpoint `/api/archivos/` sin modificarlos.

---

*Documento generado para la app PHP nativa que se conecta con Alena Zentrix via API REST.*  
*Última actualización: 2026-06-26*
