# Manual de Uso — Alena Zentrix

**Sistema de Gestión de Órdenes de Trabajo**
Versión 1.0 · Uso interno

---

## Tabla de Contenido

1. [Introducción](#1-introducción)
2. [Acceso al Sistema](#2-acceso-al-sistema)
3. [Roles y Permisos](#3-roles-y-permisos)
4. [Manual por Rol](#4-manual-por-rol)
   - 4.1 [Administrador](#41-administrador)
   - 4.2 [Coordinador](#42-coordinador)
   - 4.3 [Técnico / Investigador](#43-técnico--investigador)
   - 4.4 [Auditor](#44-auditor)
5. [Módulos Generales](#5-módulos-generales)
   - 5.1 [Dashboard](#51-dashboard)
   - 5.2 [Mapa de Operaciones](#52-mapa-de-operaciones)
   - 5.3 [Informes](#53-informes)
   - 5.4 [Configuración de Perfil](#54-configuración-de-perfil)
6. [Tipos de Servicio](#6-tipos-de-servicio)
7. [Flujo de Estados de una Orden](#7-flujo-de-estados-de-una-orden)
8. [Documentación Técnica](#8-documentación-técnica)
   - 8.1 [Stack Tecnológico](#81-stack-tecnológico)
   - 8.2 [Estructura del Proyecto](#82-estructura-del-proyecto)
   - 8.3 [Arquitectura de Base de Datos](#83-arquitectura-de-base-de-datos)
   - 8.4 [Sistema de Roles y Permisos](#84-sistema-de-roles-y-permisos)
   - 8.5 [Rutas del Sistema](#85-rutas-del-sistema)
   - 8.6 [Módulos Livewire](#86-módulos-livewire)
   - 8.7 [Instalación y Puesta en Marcha](#87-instalación-y-puesta-en-marcha)

---

## 1. Introducción

**Alena Zentrix** es un sistema de gestión de órdenes de trabajo diseñado para equipos de campo en el sector energético. Centraliza el ciclo completo de una orden: desde su creación y asignación hasta la ejecución en campo, revisión por coordinadores y auditoría independiente.

### Funcionalidades principales

- Creación y asignación de órdenes de trabajo por tipo de servicio
- Ejecución y reporte en campo por parte de técnicos e investigadores
- Revisión y aprobación de reportes por coordinadores
- Auditoría independiente de órdenes completadas
- Generación y descarga de informes en PDF por tipo de servicio
- Consulta GIS integrada para verificación de datos del servicio
- Revisión masiva de medidores mediante importación de archivos
- Mapa interactivo de operaciones
- Notificaciones por correo en cada cambio de estado relevante

---

## 2. Acceso al Sistema

### Inicio de sesión

1. Ingrese a la URL del sistema proporcionada por el administrador.
2. En la pantalla de inicio de sesión, ingrese su **correo electrónico** y **contraseña**.
3. Haga clic en **Iniciar sesión**.

> Al acceder por primera vez, debe verificar su correo electrónico. Recibirá un mensaje con un enlace de verificación.

### Autenticación de dos factores (2FA)

Si el administrador ha habilitado la autenticación de dos factores para su cuenta:

1. Tras ingresar sus credenciales, el sistema solicitará un **código de verificación**.
2. Abra su aplicación de autenticación (Google Authenticator, Authy, etc.) y copie el código de 6 dígitos.
3. Ingrese el código y haga clic en **Verificar**.

Si pierde acceso a su aplicación de autenticación, puede usar uno de los **códigos de recuperación** que se generaron al activar el 2FA (disponibles en Configuración → Seguridad).

### Recuperación de contraseña

1. En la pantalla de inicio de sesión, haga clic en **¿Olvidaste tu contraseña?**
2. Ingrese su correo electrónico registrado.
3. Recibirá un correo con un enlace para crear una nueva contraseña.

---

## 3. Roles y Permisos

El sistema maneja los siguientes roles, cada uno con acceso diferenciado a los módulos:

| Rol | Descripción | Acceso principal |
|---|---|---|
| **Administrador** | Control total del sistema | Usuarios, roles, configuración, todos los módulos |
| **Coordinador** | Gestión operativa diaria | Órdenes de trabajo, consulta GIS, revisión de medidores, mapa |
| **Técnico** | Ejecución en campo | Sus órdenes asignadas, registro de reportes |
| **Investigador** | Ejecución en órdenes de vigilancia | Sus órdenes asignadas, reportes de turno sucesivos |
| **Auditor** | Control de calidad | Revisión de órdenes completadas, auditorías |

---

## 4. Manual por Rol

### 4.1 Administrador

El administrador gestiona la configuración general del sistema: usuarios, roles, permisos y catálogos.

---

#### Gestión de Usuarios (`admin/usuarios`)

Permite crear, editar, activar/desactivar y eliminar usuarios del sistema.

**Crear un usuario:**

1. Acceda a **Administración → Usuarios**.
2. Haga clic en **Nuevo Usuario**.
3. Complete los datos personales: tipo de documento, número de documento, nombres, apellidos, teléfono y cargo.
4. En la sección **Acceso al Sistema**, ingrese el correo electrónico y una contraseña inicial.
5. Asigne el rol correspondiente (Coordinador, Técnico, Auditor, etc.).
6. Si el rol es **Auditor**, seleccione los tipos de servicio que podrá auditar.
7. Haga clic en **Crear**.

> El usuario recibirá un correo de bienvenida con sus credenciales de acceso.

**Editar un usuario:**

1. En la tabla de usuarios, haga clic en el ícono de edición (lápiz) de la fila correspondiente.
2. Modifique los campos necesarios.
3. Si desea cambiar la contraseña, ingrese la nueva contraseña. Si lo deja vacío, se conserva la contraseña actual.
4. Haga clic en **Guardar**.

**Activar / Desactivar un usuario:**

- En la tabla de usuarios, use el toggle de la columna **Estado** para activar o desactivar el acceso del usuario. Un usuario desactivado no puede iniciar sesión.

**Desactivar un usuario:**

Los usuarios no se eliminan del sistema para preservar la trazabilidad de órdenes, reportes y auditorías. Para bloquear el acceso de un usuario:

1. Abra el formulario de edición del usuario (ícono de lápiz).
2. En la sección **Acceso al Sistema**, desactive el toggle **Activo**.
3. Haga clic en **Guardar**.

> Un usuario desactivado no puede iniciar sesión. Todos sus datos históricos (órdenes, reportes, auditorías) se conservan intactos en el sistema.

---

#### Gestión de Roles (`admin/roles`)

Permite administrar los roles del sistema y sus permisos asociados.

**Ver roles:**

1. Acceda a **Administración → Roles**.
2. Se muestra la lista de roles disponibles con el número de usuarios asignados a cada uno.

**Editar permisos de un rol:**

1. Haga clic en el ícono de edición (lápiz) del rol que desea modificar.
2. En la pantalla de edición, marque o desmarque los permisos disponibles para ese rol.
3. Haga clic en **Guardar cambios**.

---

#### Configuración del Sistema (`admin/configuracion`)

Administra los catálogos técnicos utilizados en los formularios de reportes y órdenes de trabajo.

| Catálogo | Descripción |
|---|---|
| Tipos de Anomalía | Irregularidades detectables en campo |
| Tipos de Imposibilidad | Razones por las que no se pudo ejecutar la visita |
| Tipos de Comercio | Clasificación del tipo de establecimiento |
| Marcas de Medidor | Fabricantes de medidores registrados |
| Marcas de Regulador | Fabricantes de reguladores registrados |
| Tipos de Regulador | Categorías de reguladores |
| Tipologías de Orden | Subclasificaciones internas de órdenes |
| Convenciones | Leyendas del mapa de operaciones |
| Resultados de Reporte | Clasificación del resultado de cada reporte |
| Correos de Alerta | Correos que reciben notificaciones del sistema |
| Tipos de Notificación | Configuración de eventos que generan alertas |

**Agregar un ítem a un catálogo:**

1. Acceda a **Administración → Configuración**.
2. Haga clic en el catálogo que desea administrar.
3. Haga clic en **Nuevo** y complete el nombre del ítem.
4. Haga clic en **Crear**.

**Editar o desactivar un ítem:**

- Use el ícono de edición para modificar el nombre.
- Use el toggle **Activo** para desactivar ítems sin eliminarlos. Los ítems inactivos no aparecen en los formularios.

---

### 4.2 Coordinador

El coordinador gestiona el ciclo de vida de las órdenes de trabajo: creación, seguimiento y aprobación de reportes.

---

#### Órdenes de Trabajo (`operaciones/ordenes-trabajo`)

Vista principal de todas las órdenes del sistema con filtros por estado, tipo de servicio, técnico y fechas.

**Crear una orden de trabajo:**

1. Acceda a **Operaciones → Órdenes de Trabajo**.
2. Haga clic en **Nueva Orden**.
3. Complete los campos requeridos:
   - **Tipo de servicio**: Fraudes, Investigación, Gestión Legal, Vigilancia, Gestión de Riesgo, Auditorías de Campo o Revisión de Medidores.
   - **Prioridad**: Baja, Media, Alta o Urgente.
   - **Técnico asignado** (si aplica según el tipo de servicio).
   - **Datos del punto de servicio**: dirección, contrato (si aplica), datos del medidor (si aplica).
   - **Descripción** de la orden.
4. Haga clic en **Crear Orden**.

> Una vez creada, la orden pasa automáticamente a estado **Asignada** y el técnico recibe una notificación por correo.

**Ver detalle de una orden:**

1. En la tabla de órdenes, haga clic en el número de orden o en el ícono de vista.
2. Se muestra el detalle completo: datos del punto, historial de cambios, reporte adjunto y acciones disponibles.

**Revisar un reporte:**

Cuando un técnico entrega su reporte, la orden pasa a estado **En Revisión**. El coordinador puede:

1. En la tabla de órdenes, haga clic en **Revisar** en la fila de la orden.
2. Revise los datos del reporte: fotos, datos técnicos, anomalías registradas.
3. Elija una de las acciones:
   - **Aprobar**: la orden pasa a estado **Completada**.
   - **Rechazar**: la orden vuelve a estado **En Progreso** y el técnico debe corregir el reporte. Ingrese el motivo del rechazo.

**Órdenes de Vigilancia — Reportes de Turno:**

Las órdenes de tipo Vigilancia permiten múltiples reportes de turno sucesivos.

1. Acceda al detalle de la orden de vigilancia.
2. Haga clic en **Ver Reportes de Turno** para revisar todos los registros enviados.
3. Para cerrar la orden manualmente, haga clic en **Cerrar Orden**.

**Descargar informe PDF:**

En el detalle de una orden completada, haga clic en **Descargar Informe** para obtener el PDF correspondiente al tipo de servicio.

---

#### Consulta GIS (`operaciones/consulta-gis`)

Permite verificar datos de un punto de servicio directamente desde el sistema GIS integrado.

1. Acceda a **Operaciones → Consulta GIS**.
2. Ingrese el número de servicio o datos del punto a consultar.
3. El sistema retornará información actualizada sobre la ubicación, tipo de medidor y estado del servicio.

---

#### Revisión de Medidores (`operaciones/revision-medidores`)

Módulo para gestión masiva de revisiones de medidores.

**Importar órdenes de revisión:**

1. Acceda a **Operaciones → Revisión de Medidores → Importar**.
2. Descargue la plantilla en formato Excel haciendo clic en **Descargar Plantilla**.
3. Complete la plantilla con los datos de los puntos a revisar.
4. Haga clic en **Importar Archivo** y seleccione el archivo completado.
5. El sistema creará automáticamente las órdenes de revisión.

**Seguimiento de revisiones:**

1. Acceda a **Operaciones → Revisión de Medidores**.
2. Se muestra la lista de órdenes de revisión con su estado de avance.
3. Puede descargar el informe PDF de cada revisión o un ZIP con todos los informes.

---

### 4.3 Técnico / Investigador

El técnico accede únicamente a sus órdenes asignadas y registra el reporte al finalizar la atención.

---

#### Mis Órdenes (`tecnico/mis-ordenes`)

Vista de todas las órdenes asignadas al técnico que ha iniciado sesión.

**Filtros disponibles:** estado de la orden, tipo de servicio, rango de fechas.

**Ver una orden:**

1. Haga clic en el número de orden o en el ícono de detalle.
2. Se muestra la información completa del punto de servicio: dirección, contrato, datos del medidor y descripción de la tarea.

---

#### Registrar un Reporte

Una vez que el técnico ha completado la visita al punto de servicio:

1. En el detalle de la orden, haga clic en **Registrar Reporte**.
2. Complete el formulario según el tipo de servicio:

**Datos comunes a todos los tipos:**

- **Ubicación GPS**: ingrese las coordenadas o use la detección automática.
- **Datos del comercio**: tipo de comercio, nombre y datos del establecimiento.
- **Resultado del reporte**: seleccione el resultado de la visita.
- **Observaciones**: texto libre con detalles adicionales.
- **Fotos**: adjunte las fotografías de evidencia. Se pueden subir múltiples imágenes.
- **Video** (opcional): adjunte un video de evidencia.

**Campos adicionales según tipo de servicio:**

| Campo | Fraudes | Investigación | Revisión Medidores |
|---|---|---|---|
| Datos del medidor | ✓ | — | ✓ |
| Marca de medidor | ✓ | — | ✓ |
| Tipo de anomalía | ✓ | ✓ | ✓ |
| Tipo de imposibilidad | Si aplica | Si aplica | Si aplica |
| Datos del regulador | ✓ | — | — |
| Número de CAU | ✓ | — | ✓ |

3. Haga clic en **Enviar Reporte**.

> La orden pasa automáticamente a estado **En Revisión** y el coordinador recibe una notificación.

**Editar un reporte rechazado:**

Si el coordinador rechazó el reporte, el técnico puede corregirlo:

1. En el detalle de la orden (estado **En Progreso**), haga clic en **Editar Reporte**.
2. Corrija los campos indicados en el motivo de rechazo.
3. Haga clic en **Actualizar Reporte**.

---

#### Reportes de Turno (Investigadores — Vigilancia)

Los investigadores asignados a órdenes de tipo Vigilancia registran reportes de turno sucesivos:

1. En el detalle de la orden, haga clic en **Registrar Turno**.
2. Complete los datos del turno: hora de inicio, hora de fin, novedades observadas y fotos.
3. Haga clic en **Guardar Turno**.

La orden permanece activa y permite agregar más turnos hasta que el coordinador la cierre manualmente.

---

### 4.4 Auditor

El auditor revisa órdenes completadas de los tipos de servicio para los cuales tiene permisos, y emite un resultado de auditoría.

---

#### Auditorías (`auditor/auditorias`)

Vista de todas las órdenes disponibles para auditar.

**Filtros disponibles:** tipo de servicio, estado de auditoría, técnico, rango de fechas.

**Estados de auditoría:**

| Estado | Descripción |
|---|---|
| **Pendiente por auditar** | La orden está completada pero no ha sido revisada por el auditor |
| **Confirmado** | El auditor validó el reporte como correcto |
| **Negativo** | El auditor encontró irregularidades o el reporte no es válido |

**Revisar una auditoría:**

1. En la lista de auditorías, haga clic en **Revisar** en la fila correspondiente.
2. Revise el detalle completo del reporte: fotos, datos técnicos, resultado registrado por el técnico.
3. Registre sus observaciones en el campo de texto libre.
4. Seleccione el resultado de la auditoría: **Confirmado** o **Negativo**.
5. Haga clic en **Guardar Auditoría**.

> La auditoría queda registrada con fecha, hora y firma del auditor. No modifica el estado original de la orden.

---

## 5. Módulos Generales

### 5.1 Dashboard

Página principal al iniciar sesión. Muestra un resumen del estado operativo del sistema adaptado al rol del usuario:

- Contadores de órdenes por estado (Pendientes, En Progreso, En Revisión, Completadas)
- Indicadores de actividad reciente
- Accesos directos a los módulos principales

### 5.2 Mapa de Operaciones (`mapa/`)

Vista geográfica de las órdenes de trabajo activas. Permite visualizar la distribución de puntos de servicio en el mapa con filtros por tipo de servicio y estado.

- Haga clic sobre un punto del mapa para ver el resumen de la orden asociada.
- Use los filtros superiores para segmentar la vista por tipo de servicio, estado o rango de fechas.
- Las convenciones del mapa (íconos y colores) se configuran en **Administración → Configuración → Convenciones**.

### 5.3 Informes (`informes/`)

Generación de informes tabulares exportables con filtros avanzados.

**Filtros disponibles:**

- Técnico
- Tipo de servicio
- Estado de la orden
- Rango de fechas (inicio y fin)

**Descargar un informe:**

1. Acceda a **Informes**.
2. Aplique los filtros deseados.
3. Haga clic en **Descargar** para obtener el archivo en el formato disponible.

> El acceso a informes está restringido por rol. Los técnicos no tienen acceso a este módulo.

### 5.4 Configuración de Perfil (`settings/`)

Cada usuario puede gestionar su propia configuración personal:

**Perfil (`settings/profile`):**
- Nombre y datos personales básicos.
- Foto de perfil.
- Guardar cambios con el botón **Actualizar Perfil**.

**Apariencia (`settings/appearance`):**
- Tema visual: claro, oscuro o según el sistema operativo.

**Seguridad (`settings/security`):**
- **Cambiar contraseña**: ingrese la contraseña actual y la nueva (debe confirmarse).
- **Autenticación de dos factores (2FA)**: active el 2FA escaneando el código QR con su aplicación de autenticación. Guarde los códigos de recuperación en un lugar seguro.
- **Eliminar cuenta**: acción irreversible. Requiere confirmación con contraseña.

---

## 6. Tipos de Servicio

El tipo de servicio determina el flujo de la orden, los campos del formulario de reporte y los permisos del auditor.

| Tipo de Servicio | Técnico Fijo | Contrato | Datos Medidor | Descripción |
|---|---|---|---|---|
| **Fraudes** | Sí | Sí | Sí | Detección de irregularidades en el uso del servicio energético |
| **Investigación** | Sí | No | No | Investigación de situaciones reportadas en campo |
| **Gestión Legal** | Sí | No | No | Seguimiento de procesos jurídicos en puntos de servicio |
| **Vigilancia** | No | Sí | No | Monitoreo continuo con múltiples turnos de vigilancia |
| **Gestión de Riesgo** | Sí | No | No | Evaluación y gestión de riesgos operativos |
| **Auditorías de Campo** | Sí | No | No | Auditoría directa en punto de servicio |
| **Revisión de Medidores** | Sí | Sí | Sí | Inspección técnica del estado del medidor |

> Las órdenes de **Vigilancia** son el único tipo sin técnico fijo asignado y tienen un flujo de estados especial (ver sección 7).

---

## 7. Flujo de Estados de una Orden

### Flujo estándar

```
[Pendiente] → [Asignada] → [En Progreso] → [En Revisión] → [Completada]
                                ↑                 |
                                └── [Rechazada] ←─┘
```

| Estado | Descripción | Quién actúa |
|---|---|---|
| **Pendiente** | Orden creada, sin asignar | Sistema |
| **Asignada** | Técnico notificado, pendiente de ejecución | Sistema (al crear la orden) |
| **En Progreso** | El técnico está trabajando en la orden | Técnico |
| **En Revisión** | El técnico envió el reporte, espera aprobación | Técnico (al enviar reporte) |
| **Completada** | Coordinador aprobó el reporte | Coordinador |
| **Rechazada** | Coordinador rechazó; el técnico debe corregir | Coordinador |
| **Cancelada** | Orden cancelada por el coordinador | Coordinador |

### Flujo especial — Vigilancia

```
[Pendiente] → [En Progreso] ←── (múltiples turnos sucesivos) ──→ [Completada]
```

Las órdenes de Vigilancia pasan directamente a **En Progreso** y permanecen en ese estado mientras los investigadores registran turnos. El coordinador cierra la orden manualmente cuando termina el operativo.

---

## 8. Documentación Técnica

### 8.1 Stack Tecnológico

| Capa | Tecnología | Versión |
|---|---|---|
| Lenguaje backend | PHP | 8.3 |
| Framework backend | Laravel | 13.x |
| Frontend reactivo | Livewire | 4.x |
| Componentes UI | Flux UI (free) | 2.x |
| Estilos | Tailwind CSS | 4.x |
| Tablas / Forms / Actions | Filament (standalone) | 5.x |
| Autenticación | Laravel Fortify | 1.x |
| Roles y permisos | Spatie Laravel Permission | 7.x |
| Almacenamiento de archivos | AWS S3 | — |
| Base de datos | MySQL | 8+ |
| Testing | Pest | 4.x |
| Servidor de desarrollo | Laravel Herd | — |

### 8.2 Estructura del Proyecto

```
app/
├── Enums/                  # Enumeraciones de negocio
│   ├── EstadoOrden.php     # pendiente, asignada, en_progreso, en_revision, completada, rechazada, cancelada
│   ├── EstadoAuditoria.php # pendiente_auditoria, confirmado, negativo
│   ├── PrioridadOrden.php  # baja, media, alta, urgente
│   ├── TipoServicio.php    # fraudes, investigacion, gestion_legal, vigilancia, etc.
│   └── TipoDocumento.php   # CC, NIT, CE, TI, PAS
├── Livewire/
│   ├── Admin/              # Usuarios, roles, configuración, catálogos
│   ├── Auditor/            # Lista y revisión de auditorías
│   ├── Coordinador/        # Órdenes, GIS, revisión de medidores
│   ├── Informes/           # Generación de informes
│   ├── Mapa/               # Mapa interactivo de operaciones
│   ├── Settings/           # Perfil, seguridad, apariencia
│   ├── SuperAdmin/         # Gestión multi-empresa
│   └── Tecnico/            # Mis órdenes y reportes
├── Mail/                   # Notificaciones por correo (implementan ShouldQueue)
├── Models/                 # Modelos Eloquent
├── Providers/              # AppServiceProvider, FortifyServiceProvider
└── Services/               # TenantService (contexto de empresa activa)

routes/
├── web.php                 # Rutas base y dashboard
├── admin.php               # Módulo administración
├── auditor.php             # Módulo auditor
├── tecnico.php             # Módulo técnico
├── operaciones.php         # Módulo coordinador
├── revision-medidores.php  # Sub-módulo revisión de medidores
├── informes.php            # Módulo informes
├── mapa.php                # Módulo mapa
├── settings.php            # Configuración de perfil
└── superadmin.php          # Panel superadministrador

resources/
├── views/
│   ├── emails/             # Templates de correo HTML
│   ├── layouts/            # Layouts principales (app, auth)
│   └── livewire/           # Vistas de componentes Livewire
docs/                       # Documentación del proyecto
```

### 8.3 Arquitectura de Base de Datos

#### Sistema de identificadores

Todas las tablas de entidades de negocio usan UUID (`char(36)`) como clave primaria.

**Tablas con UUID:**
`users`, `personals`, `orden_jobs`, `reportes`, `auditorias`, `ubicacions`, `comercios`, `gis_tokens`

**Tablas sin UUID (catálogos y sistema):**
`roles`, `permissions`, `configuraciones`, `encabezados`, `encabezados_dets` (usan `id` integer)

#### Patrones de clave foránea

```php
// Entre tablas UUID — con constraint
foreignUuid('user_id')->constrained('users')->cascadeOnDelete()

// Entre tablas UUID — sin constraint (referencia blanda)
uuid('personals_id')

// Hacia catálogo integer — se guarda como string
string('tipo_comercio')  // guarda el id integer del catálogo como string
```

#### Modelo de multi-tenancy

El sistema tiene soporte multi-empresa vía `Company` y `TenantService`. Un `CompanyScope` global en algunos modelos filtra automáticamente los registros por la empresa del usuario autenticado.

#### Relaciones principales

```
Company ──< User >── personals (1:1)
User ──< OrdenJob >── reportes (1:1 por orden estándar, 1:N para vigilancia)
OrdenJob ──< auditoria (1:1)
OrdenJob ── ubicacion (N:1)
OrdenJob ── comercio (N:1)
reportes ── comercio (N:1)
reportes ── ubicacion (N:1)
```

### 8.4 Sistema de Roles y Permisos

Implementado con **Spatie Laravel Permission v7**.

**Roles disponibles:**

| Rol | Descripción |
|---|---|
| `Admin` | Acceso completo |
| `Coordinador` | Gestión de órdenes y revisión |
| `Tecnico` | Ejecución y reporte |
| `Investigador` | Como Técnico, para órdenes de Vigilancia |
| `Auditor` | Revisión y auditoría |
| `SuperAdmin` | Panel multi-empresa (acceso especial) |

**Protección de rutas:**

```php
// Middleware personalizado — verifica $user->active + hasAnyRole()
Route::middleware(['auth', 'role:admin'])->group(function () { ... });
Route::middleware(['auth', 'role:admin,audit'])->group(function () { ... });
```

**Protección en componentes Livewire:**

```php
// Los componentes usan un método interno (no authorize() que colisiona con la clase base)
protected function requireAdminRole(): void
{
    abort_unless(auth()->user()->hasRole('Admin'), 403);
}
```

**Auditor con permisos por tipo de servicio:**

Los auditores tienen una relación adicional en `usuario_servicio_auditables` que define qué tipos de servicio pueden auditar. Se gestiona al crear o editar el usuario (campo `servicios_auditables`).

### 8.5 Rutas del Sistema

#### Módulo Administración (`/admin`)

| Método | URL | Nombre | Componente |
|---|---|---|---|
| GET | `/admin/usuarios` | `admin.users` | `ManageUsers` |
| GET | `/admin/roles` | `admin.roles` | `ManageRoles` |
| GET | `/admin/roles/{role}/editar` | `admin.roles.editar` | `EditRolePermisos` |
| GET | `/admin/configuracion` | `admin.configuracion` | `Configuracion` |

#### Módulo Operaciones (`/operaciones`)

| Método | URL | Nombre | Componente |
|---|---|---|---|
| GET | `/operaciones/ordenes-trabajo` | `operaciones.ordenes-trabajo` | `OrdenesTrabajoIndex` |
| GET | `/operaciones/ordenes-trabajo/crear` | `operaciones.ordenes-trabajo.crear` | `OrdenCreate` |
| GET | `/operaciones/ordenes-trabajo/{orden}` | `operaciones.ordenes-trabajo.show` | `OrdenJobShow` |
| GET | `/operaciones/ordenes-trabajo/{orden}/revisar` | `operaciones.ordenes-trabajo.revisar` | `ReporteRevisar` |
| GET | `/operaciones/ordenes-trabajo/{orden}/reportes` | `operaciones.ordenes-trabajo.reportes` | `ReportesVigilanciaIndex` |
| GET | `/operaciones/consulta-gis` | `operaciones.consulta-gis` | `ConsultaGisIndex` |
| GET | `/operaciones/revision-medidores` | `operaciones.revision-medidores.index` | `RevisionMedidoresIndex` |
| GET | `/operaciones/revision-medidores/importar` | `operaciones.revision-medidores.importar` | `ImportarRevisionMedidores` |

#### Descarga de Informes PDF

| Método | URL | Nombre |
|---|---|---|
| GET | `/operaciones/ordenes-trabajo/{orden}/reporte-fraude` | `operaciones.ordenes-trabajo.reporte-fraude` |
| GET | `/operaciones/ordenes-trabajo/{orden}/reporte-investigacion` | `operaciones.ordenes-trabajo.reporte-investigacion` |
| GET | `/operaciones/ordenes-trabajo/{orden}/reporte-gestion-legal` | `operaciones.ordenes-trabajo.reporte-gestion-legal` |
| GET | `/operaciones/ordenes-trabajo/{orden}/reporte-auditoria-campo` | `operaciones.ordenes-trabajo.reporte-auditoria-campo` |
| GET | `/operaciones/ordenes-trabajo/{orden}/reporte-gestion-riesgo` | `operaciones.ordenes-trabajo.reporte-gestion-riesgo` |
| GET | `/operaciones/revision-medidores/{orden}/reporte` | `operaciones.revision-medidores.reporte` |
| GET | `/operaciones/revision-medidores/descargar-zip` | `operaciones.revision-medidores.descargar-zip` (firmada) |

#### Módulo Técnico (`/tecnico`)

| Método | URL | Nombre | Componente |
|---|---|---|---|
| GET | `/tecnico/mis-ordenes` | `tecnico.mis-ordenes` | `MisOrdenesIndex` |
| GET | `/tecnico/ordenes/{orden}` | `tecnico.ordenes.show` | `OrdenShow` |
| GET | `/tecnico/ordenes/{orden}/reportar` | `tecnico.ordenes.reportar` | `ReporteCreate` |
| GET | `/tecnico/ordenes/{orden}/reporte/editar` | `tecnico.ordenes.reporte.editar` | `ReporteEdit` |

#### Módulo Auditor (`/auditor`)

| Método | URL | Nombre | Componente |
|---|---|---|---|
| GET | `/auditor/auditorias` | `auditor.auditorias` | `AuditoriasIndex` |
| GET | `/auditor/auditorias/{auditoria}/revisar` | `auditor.auditorias.revisar` | `AuditoriaRevisar` |

#### Otros módulos

| Método | URL | Nombre | Componente |
|---|---|---|---|
| GET | `/informes` | `informes.index` | `InformesIndex` |
| GET | `/mapa` | `mapa.index` | `MapaIndex` |
| GET | `/settings/profile` | `profile.edit` | `Settings\Profile` |
| GET | `/settings/security` | `security.edit` | `Settings\Security` |
| GET | `/settings/appearance` | `appearance.edit` | `Settings\Appearance` |

### 8.6 Módulos Livewire

Todos los componentes Livewire de este proyecto utilizan Filament standalone para tablas, formularios y acciones. El patrón base es:

```php
class MiComponente extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;
}
```

**Patrones importantes:**

- `@js()` **no funciona** dentro de atributos de componentes Flux — usar `data-*` attributes con `$el.dataset` en Alpine.js.
- `flux:option` es Pro-only — usar `<option>` nativo dentro de `<flux:select>`.
- `authorize()` colisiona con la clase base de Livewire — usar nombres alternativos como `requireAdminRole()`.
- El campo de contraseña en edición de usuario usa `->dehydrated(fn ($state) => filled($state))` para no sobreescribir si se deja vacío.

### 8.7 Instalación y Puesta en Marcha

#### Requisitos del servidor

- PHP >= 8.3 con extensiones: `pdo_mysql`, `gd`, `zip`, `mbstring`, `openssl`
- Composer >= 2.x
- Node.js >= 18.x
- MySQL >= 8.0
- Cuenta AWS S3 (para almacenamiento de fotos y videos)

#### Pasos de instalación

```bash
# 1. Clonar el repositorio
git clone <url-del-repositorio>
cd alena-zentrix

# 2. Instalar dependencias PHP
composer install --no-dev --optimize-autoloader

# 3. Instalar dependencias JavaScript
npm install

# 4. Configurar el entorno
cp .env.example .env
php artisan key:generate
```

#### Variables de entorno requeridas

```env
APP_NAME="Alena Zentrix"
APP_ENV=production
APP_KEY=          # generada con php artisan key:generate
APP_URL=https://su-dominio.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=alenezentrix
DB_USERNAME=
DB_PASSWORD=

MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=notificaciones@alenaZentrix.com
MAIL_FROM_NAME="Alena Zentrix"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=
AWS_BUCKET=
AWS_URL=

QUEUE_CONNECTION=database
```

#### Migración y datos iniciales

```bash
# Ejecutar migraciones
php artisan migrate --force

# Ejecutar seeders (roles, permisos, catálogos iniciales)
php artisan db:seed

# Compilar assets para producción
npm run build

# Limpiar y optimizar caché
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

#### Cola de trabajos

El sistema usa `QUEUE_CONNECTION=database`. Para procesar los correos en cola:

```bash
# Iniciar el worker (producción — usar Supervisor o similar)
php artisan queue:work --tries=3 --timeout=90

# O usar el scheduler para procesamiento automático
php artisan schedule:work
```

#### Ejecutar pruebas

```bash
# Todas las pruebas
php artisan test --compact

# Pruebas de un módulo específico
php artisan test --compact --filter=ManageUsers
```

---

*Documento generado para uso interno · Alena Zentrix · Todos los derechos reservados*
