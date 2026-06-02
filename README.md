# GrowthOS

GrowthOS es una plataforma SaaS interna para gestión operativa, construida con **Laravel 12**, **Vue 3** e **Inertia.js**. Incluye autenticación, RBAC, módulos de usuarios, roles, planificación de tareas (calendario), tareas extra, reportes PDF, histórico de reportes, dashboard con KPIs reales y configuración empresa/canales.

---

## Stack tecnológico

### Backend
| Tecnología | Uso |
|------------|-----|
| PHP 8.2+ | Runtime |
| Laravel 12 | Framework |
| MySQL | Base de datos |
| [Spatie Laravel Permission](https://github.com/spatie/laravel-permission) | Roles y permisos |
| [Spatie Activity Log](https://github.com/spatie/laravel-activitylog) | Auditoría de cambios en usuarios |
| Laravel Sanctum | API tokens (preparado) |
| Inertia Laravel 2 | SPA server-driven |

### Frontend
| Tecnología | Uso |
|------------|-----|
| Vue 3 | UI reactiva |
| Inertia.js 2 | Navegación SPA sin API REST propia |
| Tailwind CSS 3 | Estilos |
| Vite 6 | Bundler |
| Ziggy | Rutas Laravel en JavaScript |
| Lucide Vue | Iconos del sidebar |

### Entorno de desarrollo recomendado
- Windows + [Laragon](https://laragon.org/)
- Composer
- Node.js 18+ y npm

---

## Módulos implementados

| Módulo | Ruta | Permiso requerido |
|--------|------|-------------------|
| Dashboard | `/dashboard` | `view dashboard` |
| Usuarios | `/users` | `manage users` |
| Roles | `/roles` | `manage roles` |
| Planificación | `/planning` | `manage tasks` |
| Calendario (mes/semana) | — | `manage tasks` |
| Tareas extra (sidebar) | — | `manage tasks` |
| YouTube (canales + API) | `/youtube` | `view youtube` |
| Reportes PDF (Dashboard / Planning) | `/dashboard`, `/planning` | `view reports` |
| Historial de reportes | `/report-history` | `view reports` |
| Empresa (nombre, logo, color, canales) | `/settings` | `manage tasks` |
| Perfil | `/profile` | Usuario autenticado |

### Autenticación (Laravel Breeze + Inertia)
- Login / Logout
- Registro
- Recuperación de contraseña
- Verificación de email (rutas preparadas)

### RBAC
Roles por defecto tras `db:seed`:

| Rol | Permisos |
|-----|----------|
| **Super Admin** | Todos |
| **Employee** | Sin permisos asignados (extensible) |

Permisos del sistema:

```
view dashboard
manage users
manage roles
manage tasks
view reports
```

### CRUD de usuarios
- Listado paginado con búsqueda en tiempo real
- Crear, editar y eliminar
- Asignación de rol
- Validación vía Form Requests
- Capa de servicio (`UserService`)
- Policy basada en permisos (`UserPolicy`)

### Roles y permisos
- CRUD de roles
- Sincronización de permisos por rol
- Protección de rutas con middleware `can:*`

### UI / UX
- Layout con sidebar dinámico según permisos
- Topbar con perfil y logout
- Flash messages y toasts
- Modal de confirmación para eliminaciones
- Componentes reutilizables (formularios, botones, paginación)
- Soporte modo claro / oscuro (Tailwind `dark:`)

---

## Requisitos previos

- PHP >= 8.2 con extensiones: `mbstring`, `openssl`, `pdo`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`
- Composer
- Node.js >= 18 y npm
- MySQL 8+ (o MariaDB)
- Laragon u otro stack local

---

## Instalación

### 1. Clonar el repositorio

```bash
git clone https://github.com/TU-USUARIO/growthos.git
cd growthos
```

### 2. Dependencias

```bash
composer install
npm install
```

### 3. Entorno

```bash
cp .env.example .env
php artisan key:generate
```

Configurar base de datos en `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=growthos
DB_USERNAME=root
DB_PASSWORD=
```

Opcional — contraseña del administrador al sembrar datos:

```env
ADMIN_PASSWORD=tu_contraseña_segura
```

> En `local` y `testing`, si `ADMIN_PASSWORD` no está definida, se usa `password`.  
> En otros entornos se genera una contraseña aleatoria si no se configura.

### 4. Base de datos

```bash
php artisan migrate
php artisan db:seed
```

### 5. Ejecutar en desarrollo

**Opción A — dos terminales**

```bash
# Terminal 1
php artisan serve

# Terminal 2
npm run dev
```

**Opción B — un solo comando (Composer)**

```bash
composer dev
```

Levanta servidor Laravel, cola, logs (Pail) y Vite en paralelo.

### 6. Acceder

- URL: `http://127.0.0.1:8000`
- Admin por defecto (solo en local sin `ADMIN_PASSWORD`):

| Campo | Valor |
|-------|-------|
| Email | `admin@growthos.com` |
| Password | `password` |

---

## Estructura del proyecto

```
app/
├── Http/
│   ├── Controllers/         # Dashboard, Planning, VideoTask, ExtraTask, TaskReport, ReportHistory, Settings, Users, Roles, Profile
│   ├── Middleware/          # HandleInertiaRequests (auth + flash compartidos)
│   └── Requests/           # Validación (Store/Update User, Profile, etc.)
├── Models/                  # User, VideoTask, ExtraTask, ReportHistory, Organization, Channel
├── Policies/                # UserPolicy
└── Services/                # PlanningCalendarService, DashboardService, UserService

database/
├── migrations/              # users, permissions, activity_log, video_tasks, extra_tasks, report_history, organizations, channels
└── seeders/                 # RolesAndPermissionsSeeder, AdminUserSeeder

resources/views/pdf/         # report.blade.php (template PDF con logo, color empresa y footer)

resources/js/
├── Components/
│   ├── Forms/               # TextInput, SearchInput
│   ├── Modals/              # Modal, ConfirmDelete
│   ├── Navigation/          # Sidebar, Topbar, SidebarItem
│   ├── Notifications/       # Toast, ToastContainer
│   └── UI/                  # PrimaryButton, FlashMessage, Pagination, StatCard
├── config/
│   └── navigation.js        # Menú lateral filtrado por permiso
├── Layouts/
│   └── AppLayout.vue        # Layout principal autenticado
└── Pages/
    ├── Auth/
    ├── Dashboard/           # KPIs reales con statcards + círculo SVG rendimiento
    ├── Planning/            # Calendario mes/semana + sidebar tareas del día + extra tasks modal
    ├── Profile/
    ├── Reports/             # History.vue (historial de reportes PDF)
    ├── Roles/
    ├── Settings/            # Empresa (nombre, logo, color) + Canales (CRUD inline)
    ├── Users/
    └── VideoTasks/          # Create, Edit, Show, VideoTaskForm
```

---

## Arquitectura

- **Service Layer** — lógica de negocio desacoplada de controladores (`UserService`, `DashboardService`, `PlanningCalendarService`)
- **Form Requests** — validación centralizada
- **Policies + middleware `can`** — autorización en backend (no solo en UI)
- **Inertia** — una sola app Vue sin API REST duplicada para el panel
- **Componentes Vue reutilizables** — DRY en formularios y UI
- **Activity Log** — registro de cambios en `name` y `email` del modelo `User`
- **PDF generation** — `barryvdh/laravel-dompdf` con plantilla Blade agrupada por días, logo empresa, color corporativo y footer con nombre del sistema
- **Permisos por ruta** — `manage tasks` para planificación, tareas extra y settings; `view reports` para reportes e historial

---

## Comandos útiles

### Backend

```bash
php artisan serve
php artisan migrate
php artisan db:seed
php artisan optimize:clear
php artisan permission:cache-reset
php artisan tinker
```

### Frontend

```bash
npm run dev      # Desarrollo con HMR
npm run build    # Build de producción
```

### Calidad de código

```bash
./vendor/bin/pint    # Formateo PHP (Laravel Pint)
php artisan test     # Tests PHPUnit
```

---

## Variables de entorno relevantes

| Variable | Descripción |
|----------|-------------|
| `APP_URL` | URL base de la aplicación |
| `DB_*` | Conexión MySQL |
| `ADMIN_PASSWORD` | Contraseña del usuario admin al ejecutar seeders |
| `VITE_APP_NAME` | Nombre mostrado en el frontend |
| `YOUTUBE_API_KEY` | API Key de YouTube Data API v3 para estadisticas de canales |
| `IMPORT_SOURCE_PATH` | Ruta al archivo SQLite del proyecto Python (`database/tasks.db`) para migrar datos |

---

## Roadmap

### Completado
- [x] Autenticación Breeze + Inertia
- [x] RBAC con Spatie Permission
- [x] CRUD usuarios con búsqueda
- [x] CRUD roles y permisos
- [x] Sidebar dinámico por permisos
- [x] Toasts y flash messages
- [x] Modal de confirmación (delete)
- [x] Activity log en usuarios
- [x] CRUD completo Video Tasks (Create, Edit, Show, formulario reutilizable)
- [x] Planificación con calendario (vista mes/semana, sidebar de tareas por día, feriados Perú)
- [x] Tareas extra (CRUD inline en sidebar del calendario, indicador oficina/fuera)
- [x] Reportes PDF con dompdf (scope anual/mensual/semanal/día, agrupado por día, logo empresa, color corporativo)
- [x] Historial de reportes (listado + re-descarga exacta)
- [x] Dashboard con KPIs reales (tareas video/extra, progreso, rendimiento semanal, estadísticas hoy)
- [x] Configuración empresa (nombre, logo, color principal) y canales (CRUD)
- [x] YouTube section con estadisticas via API (suscriptores, vistas, videos recientes)

### Pendiente
- [ ] Paginación activa en listados
- [ ] Tests de autorización y CRUD
- [ ] Multi-tenancy y suscripciones

---

## Licencia

MIT
