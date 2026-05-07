# Alena Zentrix

Sistema de gestión de órdenes de trabajo para equipos de campo en el sector energético. Permite a coordinadores asignar, hacer seguimiento y aprobar órdenes de trabajo ejecutadas por técnicos e investigadores en campo.

---

## Tabla de contenido

- [Descripción](#descripción)
- [Tecnologías](#tecnologías)
- [Requisitos](#requisitos)
- [Instalación](#instalación)
- [Módulos](#módulos)
- [Roles del sistema](#roles-del-sistema)
- [Estructura del proyecto](#estructura-del-proyecto)

---

## Descripción

Alena Zentrix centraliza el ciclo de vida completo de las órdenes de trabajo:

- **Creación y asignación** de órdenes por tipo de servicio (Fraudes, Investigación, Capturas, Bandas, Vigilancia)
- **Ejecución en campo** por técnicos e investigadores con registro de reportes fotográficos y datos del punto de servicio
- **Revisión y aprobación** por coordinadores con posibilidad de rechazo y corrección
- **Auditorías** independientes sobre las órdenes completadas
- **Notificaciones por correo** en cada cambio de estado relevante
- **Informes** exportables por técnico, estado y fecha

---

## Tecnologías

| Capa | Tecnología | Versión |
|---|---|---|
| Backend | PHP / Laravel | 8.3 / 13.x |
| Frontend reactivo | Livewire | 4.x |
| Componentes UI | Flux UI | 2.x |
| Estilos | Tailwind CSS | 4.x |
| Tablas / Forms / Actions | Filament (standalone) | 5.x |
| Autenticación | Laravel Fortify | 1.x |
| Roles y permisos | Spatie Laravel Permission | — |
| Almacenamiento de archivos | AWS S3 | — |
| Base de datos | MySQL | — |
| Testing | Pest | 4.x |

---

## Requisitos

- PHP >= 8.3
- Composer
- Node.js >= 18
- MySQL >= 8
- Cuenta AWS S3 (para almacenamiento de fotos)
- [Laravel Herd](https://herd.laravel.com/) (recomendado para desarrollo local)

---

## Instalación

### 1. Clonar el repositorio

```bash
git clone https://github.com/tu-org/alena-zentrix.git
cd alena-zentrix
```

### 2. Instalar dependencias PHP

```bash
composer install
```

### 3. Instalar dependencias JavaScript

```bash
npm install
```

### 4. Configurar variables de entorno

```bash
cp .env.example .env
php artisan key:generate
```

Edita `.env` con tus valores:

```env
APP_NAME="Alena Zentrix"
APP_URL=https://alena-zentrix.test

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=alena_zentrix
DB_USERNAME=root
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
```

### 5. Migrar la base de datos y ejecutar seeders

```bash
php artisan migrate --seed
```

### 6. Compilar assets

```bash
npm run build
```

### 7. Levantar el servidor

Con Laravel Herd el sitio estará disponible automáticamente en `https://alena-zentrix.test`.

Sin Herd:

```bash
php artisan serve
```

---

## Módulos

### Órdenes de Trabajo
Módulo central del sistema. Los coordinadores crean órdenes con tipo de servicio, datos del punto, prioridad y técnico asignado. El flujo de estados es:

```
Pendiente → Asignada → En Progreso → En Revisión → Completada / Rechazada
```

> Excepción: Las órdenes de tipo **Vigilancia** pasan directamente a `En Progreso` y permiten múltiples reportes de turno sin cambiar de estado hasta que el coordinador las cierre.

### Reportes
Cada técnico registra su reporte al finalizar la atención: ubicación GPS, comercio, marca de medidor, tipo de irregularidad, fotos y resultado. El formulario varía según el tipo de servicio de la orden.

### Vigilancia
Las órdenes de vigilancia tienen un flujo especial: los investigadores registran reportes de turno sucesivos mientras la orden permanece activa. El coordinador puede revisar todos los turnos y cerrar la orden manualmente.

### Auditorías
Módulo independiente para auditores. Permite revisar órdenes completadas, registrar observaciones y emitir un resultado de auditoría sin interferir con el flujo de la orden.

### Informes
Generación y descarga de reportes en formato de tabla. Filtros por técnico, tipo de servicio, estado y rango de fechas. El acceso está restringido por rol.

### Usuarios y Roles
Gestión completa de usuarios con asignación de roles y permisos granulares. Cada usuario tiene un registro de personal vinculado con datos personales (nombre, documento, teléfono).

### Configuración
Administración de catálogos del sistema: tipos de anomalía, marcas de medidor, tipos de comercio, tipos de regulador, tipologías de orden, convenciones y resultados de reporte.

---

## Roles del sistema

| Rol | Descripción |
|---|---|
| **Administrador** | Acceso total: usuarios, roles, configuración e informes globales |
| **Coordinador** | Crea y gestiona órdenes de trabajo, revisa reportes y aprueba/rechaza |
| **Técnico** | Ve y ejecuta sus órdenes asignadas, registra reportes |
| **Auditor** | Revisa órdenes completadas y emite auditorías |
| **Investigador** | Como técnico pero para órdenes de Vigilancia |

---

## Estructura del proyecto

```
app/
├── Enums/              # EstadoOrden, TipoServicio, PrioridadOrden, etc.
├── Filament/
│   └── Widgets/        # Widgets del dashboard (stats, charts)
├── Livewire/
│   ├── Admin/          # Gestión de usuarios, roles, configuración
│   ├── Auditor/        # Módulo de auditorías
│   ├── Coordinador/    # Módulo de órdenes y revisión de reportes
│   ├── Informes/       # Generación de informes
│   ├── Settings/       # Perfil, seguridad, apariencia
│   └── Tecnico/        # Mis órdenes y registro de reportes
├── Mail/               # Notificaciones por correo
├── Models/             # Eloquent models
└── Providers/          # AppServiceProvider, FortifyServiceProvider

docs/                   # Documentación técnica del proyecto
resources/
├── views/
│   ├── emails/         # Templates de correo (extienden emails/layout.blade.php)
│   ├── layouts/        # Layouts de la app (sidebar, auth)
│   └── livewire/       # Vistas de componentes Livewire
routes/
├── admin.php
├── auditor.php
├── informes.php
├── operaciones.php
├── settings.php
├── tecnico.php
└── web.php
```

---

## Documentación técnica

La documentación detallada por módulo se encuentra en la carpeta [`docs/`](docs/README.md).

---

## Licencia

Propietario — todos los derechos reservados. Desarrollado para uso interno.
