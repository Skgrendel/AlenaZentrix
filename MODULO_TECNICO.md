# Módulo Técnico — Documentación para Practicante

**Aplicación:** Alena Zentrix  
**Módulo:** Técnico / Investigador de campo  
**Destinatario:** Practicante de desarrollo — app PHP nativa  
**Objetivo:** Entender cómo funciona este módulo para replicarlo en una app PHP nativa que se conectará mediante API a Alena Zentrix.

---

## Tabla de Contenido

1. [¿Qué hace el técnico en el sistema?](#1-qué-hace-el-técnico-en-el-sistema)
2. [Roles involucrados](#2-roles-involucrados)
3. [Entidades de datos clave](#3-entidades-de-datos-clave)
4. [Ciclo de vida de una orden (máquina de estados)](#4-ciclo-de-vida-de-una-orden-máquina-de-estados)
5. [Tipos de servicio](#5-tipos-de-servicio)
6. [Las 4 pantallas del técnico](#6-las-4-pantallas-del-técnico)
7. [Formulario de reporte por tipo de servicio](#7-formulario-de-reporte-por-tipo-de-servicio)
8. [Reglas de negocio importantes](#8-reglas-de-negocio-importantes)
9. [Notificaciones por correo](#9-notificaciones-por-correo)
10. [Endpoints de API que deberás consumir](#10-endpoints-de-api-que-deberás-consumir)

---

## 1. ¿Qué hace el técnico en el sistema?

El técnico (o investigador) es el usuario de campo. Su flujo completo es:

1. **Ver** sus órdenes asignadas
2. **Iniciar** una orden cuando sale a campo
3. **Registrar un reporte** con los hallazgos en sitio (formulario varía según el tipo de servicio)
4. **Corregir** el reporte si el coordinador lo rechaza

El sistema actual es una app web Laravel + Livewire. Tu misión es construir una app PHP nativa separada que replique exactamente este flujo, comunicándose con Alena Zentrix a través de una API REST que se creará en esta app.

---

## 2. Roles involucrados

| Rol | Descripción |
|---|---|
| **Técnico** | Ve órdenes asignadas a él. Puede ser de cualquier tipo de servicio excepto Vigilancia |
| **Investigador** | Igual que técnico pero principalmente para órdenes de Investigación y Vigilancia |
| **Coordinador** | Crea las órdenes y las asigna. Revisa/aprueba/rechaza los reportes enviados por el técnico |

> La distinción Técnico vs Investigador en el sistema está dada por los permisos (`roles y permisos con Spatie`), no por una columna separada en la base de datos. A efectos del módulo técnico, funcionan igual.

---

## 3. Entidades de datos clave

### 3.1 `orden_jobs` — Tabla de órdenes de trabajo

Esta es la tabla central. Cada fila es una orden de trabajo.

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | UUID | Clave primaria |
| `numero_orden` | string | Número legible ej: `ODT-000001` |
| `tipo_servicio` | string (enum) | Ver [tipos de servicio](#5-tipos-de-servicio) |
| `estado` | string (enum) | Ver [estados](#4-ciclo-de-vida-de-una-orden-máquina-de-estados) |
| `prioridad` | string | `baja` / `media` / `alta` / `urgente` |
| `personals_id` | UUID (FK) | Técnico asignado (nullable — Vigilancia no tiene asignado fijo) |
| `creado_por_id` | UUID (FK) | Usuario coordinador que creó la orden |
| `contrato` | string | Número de contrato del cliente (nullable) |
| `cliente` | string | Nombre del cliente (nullable) |
| `direccion` | string | Dirección del punto a visitar |
| `barrio` | string | Barrio |
| `medidor` | string | Número de medidor asignado (nullable) |
| `antecedente` | text | Historial del punto (nullable) |
| `objetivo` | text | Objetivo de la visita (nullable) |
| `actividades` | JSON | Lista de actividades predefinidas por el coordinador (nullable) |
| `tipo_gestion_legal` | string | Sub-tipo para Gestión Legal |
| `tipo_investigacion` | string | Sub-tipo para Investigación (`interna` / `contratista`) |
| `tipo_auditoria_campo` | string | Sub-tipo para Auditoría de Campo |
| `documentos` | JSON | Rutas a archivos en S3 adjuntos por el coordinador |
| `fecha_asignacion` | timestamp | Cuándo fue asignada |
| `fecha_limite` | timestamp | Fecha tope para ejecutarla |
| `fecha_completado` | timestamp | Se setea automáticamente al completar |
| `company_id` | UUID (FK) | Empresa (multi-tenant) |

### 3.2 `reportes` — Tabla de reportes de campo

Un reporte es lo que registra el técnico al ejecutar la orden. Una orden puede tener varios reportes solo en Vigilancia; en los demás tipos, tiene uno (o uno corregido).

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | UUID | Clave primaria |
| `orden_job_id` | UUID (FK) | Orden a la que pertenece |
| `personals_id` | UUID (FK) | Técnico que hizo el reporte |
| `numero_orden` | string | Copia desnormalizada del número de orden |
| `datos_servicio` | JSON | **Todos los campos específicos del tipo de servicio** van aquí (excepto Fraudes) |
| `actividades` | JSON | Lista de actividades con respuesta del técnico |
| `imagenes` | JSON | Array de rutas S3 de las fotos |
| `video` | text | URL a un video externo |
| `geo_referencia` | text | URL a mapa/geolocalización |
| `observaciones` | text | Comentarios generales del técnico |
| `ubicacions_id` | UUID (FK) | GPS del técnico en el momento del reporte |
| `comercios_id` | UUID (FK) | Comercio encontrado (solo Fraudes) |
| `numero_medidor` | string | Número del medidor encontrado (solo Fraudes) |
| `lectura` | string | Lectura del medidor (solo Fraudes) |
| `coincide_medidor` | boolean | Si coincide con el medidor asignado (solo Fraudes) |
| `anomalia` | JSON | Array de IDs de anomalías detectadas (solo Fraudes) |
| `hay_fuga_gas` | boolean | Solo Fraudes |
| `excede_capacidad` | boolean | Solo Fraudes |
| `tipo_imposibilidad_id` | UUID (FK) | Si hubo imposibilidad de atender (solo Fraudes) |
| `tipo_regulador_id` | UUID (FK) | Tipo de presión/regulador (solo Fraudes) |
| `marca_medidor_id` | UUID (FK) | Marca del medidor (solo Fraudes) |
| `marca_regulador_id` | UUID (FK) | Descripción del medidor (solo Fraudes) |
| `tipo_notificacion_alerta_id` | UUID (FK) | Tipo de alerta (solo Fraudes) |
| `revisado` | boolean | El coordinador lo revisó |
| `confirmado` | boolean | El coordinador lo aprobó |

### 3.3 `personals` — Datos personales del técnico

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | UUID | Clave primaria |
| `nombres` | string | Nombres |
| `apellidos` | string | Apellidos |
| `tipo_documento` | string | CC, CE, Pasaporte, etc. |
| `numero_documento` | string | Número del documento |
| `telefono` | string | Teléfono |
| `cargo` | string | Cargo del técnico |

El usuario (`users`) tiene una FK `personals_id` que lo vincula con su registro de personal.

### 3.4 `ubicacions` — Coordenadas GPS

| Columna | Tipo |
|---|---|
| `id` | UUID |
| `latitud` | string |
| `longitud` | string |
| `direccion` | string |

Cuando el técnico envía un reporte, el sistema intenta capturar su posición GPS (el navegador la solicita) y crea automáticamente un registro en esta tabla. Si el usuario niega el permiso de geolocalización, `latitud` y `longitud` quedan en `null`.

### 3.5 `comercios` — Solo para Fraudes

| Columna | Tipo |
|---|---|
| `id` | UUID |
| `nombre_comercio` | string |
| `tipo_comercio_id` | UUID (FK) |

Se crea al guardar el reporte de Fraudes. Guarda el comercio encontrado en el sitio.

---

## 4. Ciclo de vida de una orden (máquina de estados)

```
                        [COORDINADOR CREA]
                               │
                               ▼
                          PENDIENTE ──(sin técnico asignado)
                               │
                    (asigna técnico)
                               │
                               ▼
                           ASIGNADA
                               │
                      (técnico presiona "Iniciar")
                               │
                               ▼
                          EN PROGRESO ◄──────────────────────────┐
                               │                                 │
          ┌────────────────────┤                                 │
          │ (Vigilancia: el    │ (técnico envía reporte          │
          │  técnico agrega    │  en otros tipos)                │
          │  turnos repetidos) │                                 │
          └────────────────────►                                 │
                               │                                 │
                               ▼                                 │
                          EN REVISIÓN                            │
                               │                                 │
              ┌────────────────┴───────────────────┐            │
              │ (coordinador aprueba)               │ (coordinador rechaza)
              ▼                                     │            │
          COMPLETADA                                ▼            │
              │                               RECHAZADA ─────────┘
              │                         (técnico edita reporte
              │                          y vuelve a enviar)
              ▼
    [Auditoría creada automáticamente]
```

**Reglas especiales de Vigilancia:**
- La orden entra directamente en `EN PROGRESO` al crearse (sin pasar por Asignada).
- El técnico puede agregar múltiples reportes de turno mientras la orden esté `EN PROGRESO`.
- El estado NO cambia a `EN REVISIÓN` al guardar un reporte — la orden sigue abierta.
- El técnico tiene el botón "Cerrar Vigilancia" que la pasa a `EN REVISIÓN`.
- No hay técnico fijo: cualquier investigador puede añadir turnos.

**Transiciones que hace el técnico (tú necesitas implementarlas):**

| Acción del técnico | Estado anterior | Estado nuevo |
|---|---|---|
| Presionar "Iniciar" | `asignada` | `en_progreso` |
| Enviar reporte (no Vigilancia) | `en_progreso` | `en_revision` |
| Enviar reporte (Vigilancia) | `en_progreso` | `en_progreso` (sin cambio) |
| Cerrar Vigilancia | `en_progreso` | `en_revision` |
| Editar reporte rechazado y reenviar | `rechazada` | `en_revision` |

---

## 5. Tipos de servicio

Hay 7 tipos. El tipo determina qué campos tiene el formulario de reporte.

| Valor en BD | Etiqueta visible | Tiene técnico fijo | Tiene contrato/cliente | Tiene datos medidor |
|---|---|---|---|---|
| `fraudes` | Fraudes | Sí | Sí | Sí |
| `revision_medidores` | Revisión de Medidores | Sí | Sí | Sí |
| `investigacion` | Investigación | Sí | Sí | No |
| `gestion_legal` | Gestión Legal | Sí | No | No |
| `vigilancia` | Dispositivos de Vigilancia | **No** | Sí | No |
| `Gestión de Riesgo` | Gestión de Riesgo | Sí | No | No |
| `auditoria_campo` | Auditorías de Campo | Sí | Sí | No |

> Nota: `Gestión de Riesgo` tiene un espacio en el valor en base de datos. Esto no es un error — así está definido en el sistema actual.

---

## 6. Las 4 pantallas del técnico

### 6.1 Mis Órdenes (`/tecnico/mis-ordenes`)

**¿Qué muestra?** Una tabla con todas las órdenes relevantes para el técnico.

**¿Qué órdenes ve?**
- Órdenes asignadas a su `personals_id` (en cualquier estado activo)
- **Todas** las órdenes de tipo `vigilancia` sin importar a quién estén asignadas (porque Vigilancia no tiene técnico fijo)

**Columnas visibles:**
- N° Orden (ej: `ODT-000045`)
- Servicio (tipo de servicio con color)
- Contrato (oculto si ninguna orden tiene contrato)
- Cliente/Referencia (oculto si ninguna orden tiene cliente)
- Dirección (oculto si ninguna orden tiene dirección)
- Estado (con colores: gris=pendiente, azul=asignada, amarillo=en progreso, morado=en revisión, verde=completada, rojo=rechazada)
- Prioridad (baja/media/alta/urgente)
- Fecha Límite

**Acciones disponibles por fila (dependen del estado y tipo):**

| Botón | Visible cuando | Qué hace |
|---|---|---|
| **Ver** | Estado ≠ `en_progreso` | Abre la vista de detalle de la orden |
| **Iniciar** | Estado = `asignada` Y tipo ≠ `vigilancia` | Cambia estado a `en_progreso`, notifica al coordinador |
| **Agregar Reporte** | Estado = `en_progreso` Y (tipo = `vigilancia` O la orden no tiene reportes aún) | Abre el formulario de creación del reporte |
| **Cerrar Vigilancia** | Tipo = `vigilancia` Y estado = `en_progreso` | Cambia estado a `en_revision` |
| **Editar Reporte** | Estado = `rechazada` Y tipo ≠ `vigilancia` | Abre el formulario de edición del reporte rechazado |

**Ordenamiento por defecto:** Las órdenes `en_revision` aparecen primero. El resto por fecha de creación descendente.

---

### 6.2 Ver Orden (`/tecnico/ordenes/{id}`)

Vista de **solo lectura** que muestra los detalles completos de la orden.

**Contenido:**
- Todos los campos del `orden_jobs` (número, tipo, estado, prioridad, contrato, cliente, dirección, medidor, antecedente, objetivo, actividades, etc.)
- Los documentos adjuntos por el coordinador (links seguros desde S3)

No tiene formularios ni botones de acción — es puramente informativa.

---

### 6.3 Crear Reporte (`/tecnico/ordenes/{id}/reportar`)

Formulario de campo. **El esquema del formulario cambia completamente según el `tipo_servicio` de la orden.**

Ver detalle completo en la sección [7. Formulario de reporte por tipo de servicio](#7-formulario-de-reporte-por-tipo-de-servicio).

**Al enviar el formulario, el sistema:**
1. Crea un registro en `ubicacions` con las coordenadas GPS del técnico
2. Crea el registro en `reportes`
3. Para Fraudes: además crea un registro en `comercios`
4. Actualiza el estado de la orden a `en_revision` (excepto Vigilancia)
5. Envía correo al coordinador (excepto Vigilancia)
6. Si hay fuga de gas, exceso de capacidad o anomalía "Bypass": envía alertas a correos de alerta configurados (solo Fraudes)
7. Redirige a "Mis Órdenes"

---

### 6.4 Editar Reporte (`/tecnico/ordenes/{id}/reporte/editar`)

Solo accesible cuando la orden está en estado `rechazada` y el tipo de servicio **no** es Vigilancia.

Carga el formulario pre-rellenado con los datos del último reporte de la orden. El técnico corrige lo que sea necesario según las observaciones del coordinador.

**Al guardar:**
1. Actualiza el reporte existente (no crea uno nuevo)
2. Las imágenes nuevas se **acumulan** a las existentes (no las reemplaza)
3. Actualiza el estado de la orden a `en_revision`
4. Envía correo al coordinador
5. Redirige a "Mis Órdenes"

---

## 7. Formulario de reporte por tipo de servicio

### 7.1 Fraudes y Revisión de Medidores

Secciones del formulario:

**1. Datos de la Orden**
- `nombre_comercio` (texto, requerido) — nombre del comercio encontrado
- `tipo_comercio_id` (select de catálogo, requerido)

**2. Información del Medidor**
- `numero_medidor` (texto)
- `coincide_medidor` (boolean — ¿el medidor encontrado coincide con el asignado?)
- `lectura` (texto)
- `marca_regulador_id` (select de catálogo — descripción del medidor)

**3. Configuración Técnica**
- `tipo_regulador_id` (select de catálogo — tipo de presión)
- `marca_medidor_id` (select de catálogo — marca del medidor)
- `tipo_notificacion_alerta_id` (select de catálogo — tipo de notificación)

**4. Detección de Anomalías**
- `anomalia` (select múltiple de catálogo)
- `tipo_imposibilidad_id` (select de catálogo — si no se pudo atender)

**5. Incidencias Detectadas**
- `hay_fuga_gas` (boolean) — si es true, aparece campo de foto del detector
- `foto_detector_fuga` (foto, requerida si hay_fuga_gas = true)
- `excede_capacidad` (boolean)

**6. Observaciones**
- `observaciones` (texto largo)

**7. Fotos y Evidencias**
- `imagenes` (múltiples fotos, máx 10, máx 5MB c/u, van a S3)
- `video` (URL)
- `geo_referencia` (URL)

> Los campos `nombre_comercio` y `tipo_comercio_id` se guardan en la tabla `comercios`, no en `reportes`. El resto va directo en columnas de `reportes`.

---

### 7.2 Investigación

Secciones del formulario:

**1. Datos del Reporte**
- `convencion_id` (select de catálogo, requerido)
- `acompanamiento_tecnico` (boolean) — si es true, aparece la sección 1b

**1b. Datos del Acompañante Técnico** (solo si hay acompañamiento)
- `acompanante_nombre`, `acompanante_identificacion`, `acompanante_cargo`, `acompanante_cuadrilla`

**2. Persona o Comercio Investigado**
- `tipo_investigado` (select: `persona` / `comercio`)
- Si persona: `nombre_investigado`, `identificacion`, `descripcion_fisica`
- Si comercio: `nombre_comercio_inv`, `tipo_comercio_inv_id`

**3. Actividad Realizada**
- `actividades` (lista no editable de actividades asignadas por el coordinador, con campo de respuesta por cada una)

**4. Resultado**
- `resultado_reporte_id` (select de catálogo)

**5. Observaciones**
- `observaciones` (texto largo)

**6. Evidencias**
- `imagenes` (múltiples archivos: imágenes, PDF, Word, Excel, PowerPoint)
- `video` (URL)
- `geo_referencia` (URL)

> Todos estos campos se guardan en `datos_servicio` (JSON) en `reportes`, excepto `observaciones`, `imagenes`, `video`, `geo_referencia` y `actividades` que tienen columnas propias.

---

### 7.3 Gestión Legal

Secciones del formulario:

**1. Datos de la Actividad**
- `tipo_gestion_legal` (solo lectura, viene de la orden)
- `fecha_ejecucion` (fecha, requerido)
- `departamento` (texto)
- `municipio` (texto)
- `radio_objetivo` (texto)

**2. Actividad Realizada**
- `actividades` (lista con respuesta por cada actividad asignada)

**3. Observaciones**
- `observaciones` (texto largo)

**4. Evidencias**
- `imagenes` (archivos)
- `video` (URL)
- `geo_referencia` (URL)

---

### 7.4 Vigilancia

Secciones del formulario:

**1. Turno**
- `fecha_turno` (fecha, requerido)
- `hora_inicio` (hora, requerido)
- `hora_fin` (hora, requerido)

**2. Novedades**
- `hay_novedad` (boolean) — si true, aparecen campos extra
- `descripcion_novedad` (texto, requerido si hay novedad)
- `hay_reincidencia` (boolean, visible si hay novedad)

**3. Resultado**
- `resultado_reporte_id` (select de catálogo)

**4. Observaciones**
- `observaciones` (texto)

**5. Evidencias**
- `imagenes` (archivos)
- `video` (URL)
- `geo_referencia` (URL)

> Al guardar, el estado de la orden NO cambia. La orden sigue en `en_progreso` y el técnico puede agregar otro turno.

---

### 7.5 Gestión de Riesgo

Secciones del formulario:

**1. Actividad Realizada**
- `actividades` (lista con respuesta por cada actividad)

**2. Observaciones**
- `observaciones` (texto)

**3. Evidencias**
- `imagenes` (archivos: imágenes, PDF, Word, Excel, PowerPoint, máx 10MB c/u)
- `video` (URL)
- `geo_referencia` (URL)

---

### 7.6 Auditoría de Campo

El más extenso. Secciones del formulario:

**Información General** (solo lectura: subtipo, OT, contrato, cliente, dirección, barrio, comentarios)

**Datos de Quién Atiende**
- `nombre_atiende` (requerido)
- `vinculo_usuario` (titular/familiar/vecino/otro)
- `celular_atiende`
- `fecha_hora_atencion` (fecha+hora, requerido)

**Validación de Atención y Ejecución**
- `recibio_visita` (boolean, requerido)
- `se_realizo_revision` (boolean, requerido)
- `fecha_ejecucion`
- `tiempo_ejecucion`
- `descripcion_trabajo`

**Hallazgos Técnicos**
- `se_detecto_fuga` (boolean) — si true: `ppm`, `vol_porcentaje`
- `problema_persiste` (boolean)
- `evidencia_reparacion` (boolean)
- `servicio_correcto` (boolean)

**Materiales y Cobros**
- `se_usaron_materiales` → `quien_suministro_materiales`
- `usuario_asumio_costo` → `valor_pagado`, `medio_pago`
- `entrego_factura` (boolean)

**Trazabilidad**
- `tecnico_dejo_documento`
- `informaron_no_estaba` (boolean)

**Autorización y Firmas**
- `autoriza` (boolean, requerido)
- `cedula_usuario` (requerido)
- `cedula_auditor` (requerido)
- `firma_usuario` (firma digital — imagen base64 o SVG)
- `firma_auditor` (firma digital)

**Observaciones**
- `observaciones`

**Evidencias**
- `imagenes` (fotos)
- `urls_evidencia` (array de URLs, ej: links a Google Drive)
- `geo_referencia`

---

## 8. Reglas de negocio importantes

### Autorización
- El técnico solo puede acceder al formulario de reporte si la orden le está asignada **o** si tiene el permiso `ordenes.ver` (que tienen coordinadores y admins).
- El formulario de edición solo es accesible si la orden está en estado `rechazada`.

### Actividades predefinidas
Si la orden tiene actividades en el campo `actividades` (array JSON del coordinador), el formulario las precarga. Cada actividad tiene:
```json
[
  { "descripcion": "Verificar medidor" },
  { "descripcion": "Revisar instalación" }
]
```
El técnico responde cada una. Al guardar, el array de actividades en el reporte queda:
```json
[
  { "uuid": "...", "descripcion_orden": "Verificar medidor", "actividades": "El medidor estaba..." },
  { "uuid": "...", "descripcion_orden": "Revisar instalación", "actividades": "La instalación..." }
]
```

### Manejo de imágenes
- Las fotos se suben directamente a **AWS S3**.
- En la base de datos se guarda un array JSON con las rutas/keys de S3, no los archivos.
- Ejemplo: `["reportes/ODT-000045/foto1.jpg", "reportes/ODT-000045/foto2.jpg"]`
- Al editar un reporte, las imágenes nuevas se agregan a las existentes (no las borran).

### GPS
- Al abrir el formulario de reporte, el navegador solicita permiso de geolocalización.
- Si el usuario lo concede: `latitud` y `longitud` se capturan y se envían junto con el formulario.
- Si lo niega o no está disponible: se guardan como `null`.

### Datos del servicio (campo `datos_servicio`)
Para Investigación, Gestión Legal, Gestión de Riesgo, Vigilancia y Auditoría de Campo, todos los campos específicos del formulario (excepto `observaciones`, `imagenes`, `video`, `geo_referencia`, `actividades`) se guardan como un objeto JSON en la columna `datos_servicio` del reporte.

Para **Fraudes** y **Revisión de Medidores**, los campos tienen columnas propias en `reportes` (no usan `datos_servicio`).

---

## 9. Notificaciones por correo

| Evento | Destinatario | Cuándo |
|---|---|---|
| Técnico inicia orden | Coordinador (creador de la orden) | Al presionar "Iniciar" |
| Técnico envía reporte | Coordinador (creador de la orden) | Al guardar el reporte (no en Vigilancia) |
| Técnico edita reporte rechazado | Coordinador | Al guardar la edición |
| Incidencia detectada | Todos los correos en tabla `correo_alertas` | Al guardar reporte de Fraudes si hay fuga de gas, exceso de capacidad o anomalía "Bypass" |

La alerta de incidencia incluye adjunta la foto del detector de fuga si existe.

---

## 10. Endpoints de API que deberás consumir

Esta sección es una **guía preliminar** — la API aún no está construida en Alena Zentrix. Cuando se construya, debería exponer al menos los siguientes endpoints para que tu app PHP nativa funcione:

### Autenticación
```
POST /api/auth/login
POST /api/auth/logout
```

### Órdenes del técnico
```
GET  /api/tecnico/mis-ordenes          → lista paginada de órdenes visibles para el técnico
GET  /api/tecnico/ordenes/{id}         → detalle de una orden
POST /api/tecnico/ordenes/{id}/iniciar → cambiar estado a en_progreso
```

### Reportes
```
POST /api/tecnico/ordenes/{id}/reportar          → crear reporte + cambiar estado
PUT  /api/tecnico/ordenes/{id}/reporte           → editar reporte rechazado + cambiar estado
```

### Vigilancia
```
POST /api/tecnico/ordenes/{id}/cerrar-vigilancia → cambiar estado a en_revision
```

### Catálogos (datos para los selects del formulario)
```
GET  /api/catalogos/tipos-comercio
GET  /api/catalogos/marcas-medidor
GET  /api/catalogos/marcas-regulador
GET  /api/catalogos/tipos-regulador
GET  /api/catalogos/tipos-anomalia
GET  /api/catalogos/tipos-imposibilidad
GET  /api/catalogos/tipos-notificacion-alerta
GET  /api/catalogos/convenciones
GET  /api/catalogos/resultados-reporte
```

### Archivos
```
POST /api/archivos/upload              → subir foto/archivo a S3, recibe la ruta guardada
GET  /api/archivos/{path}             → proxy seguro para ver un archivo de S3
```

---

## Ejemplo de flujo completo

1. Técnico abre tu app PHP nativa e inicia sesión → `POST /api/auth/login`
2. Ve la lista de sus órdenes → `GET /api/tecnico/mis-ordenes`
3. Toca una orden con estado `asignada` → `GET /api/tecnico/ordenes/{id}`
4. Presiona "Iniciar" → `POST /api/tecnico/ordenes/{id}/iniciar`
5. La app muestra el formulario de reporte según el `tipo_servicio` de la orden
6. El técnico toma fotos → cada foto se sube con `POST /api/archivos/upload`, la app guarda las rutas retornadas
7. Completa el formulario y envía → `POST /api/tecnico/ordenes/{id}/reportar` con todos los datos + rutas de fotos
8. La app redirige a la lista de órdenes

---

## Estructura de carpetas del módulo en Alena Zentrix (para referencia)

```
app/
├── Enums/
│   ├── EstadoOrden.php          ← estados posibles de la orden
│   ├── TipoServicio.php         ← tipos de servicio
│   └── PrioridadOrden.php
├── Livewire/Tecnico/
│   ├── MisOrdenesIndex.php      ← pantalla 1: lista de órdenes
│   ├── OrdenShow.php            ← pantalla 2: ver detalle
│   ├── ReporteCreate.php        ← pantalla 3: crear reporte (lógica principal)
│   └── ReporteEdit.php          ← pantalla 4: editar reporte rechazado
├── Models/
│   ├── OrdenJob.php             ← tabla orden_jobs
│   ├── reportes.php             ← tabla reportes
│   ├── personals.php            ← tabla personals
│   ├── ubicacion.php            ← tabla ubicacions
│   └── comercio.php             ← tabla comercios
routes/
└── tecnico.php                  ← rutas web del módulo técnico
```

---

*Este documento describe el comportamiento actual del módulo en la app Laravel. Tu app PHP nativa debe replicar exactamente este flujo consumiendo los endpoints de la API que se construirá sobre este mismo código.*
