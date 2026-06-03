# GrowthOS

GrowthOS es una plataforma SaaS interna para gestión de contenido audiovisual, construida con **Laravel 12**, **Vue 3** e **Inertia.js**. Incluye autenticación, RBAC, planificación semanal de tareas de video, módulo de ideas, historial de cambios por tarea, reportes PDF con logo/color corporativo, dashboard con KPIs reales, integración con YouTube API, e importación desde proyecto Python legado.

---

## Stack tecnológico

### Backend
| Tecnología | Uso |
|------------|-----|
| PHP 8.2+ | Runtime |
| Laravel 12 | Framework |
| MySQL | Base de datos |
| [Spatie Laravel Permission](https://github.com/spatie/laravel-permission) | Roles y permisos |
| [Spatie Activity Log](https://github.com/spatie/laravel-activitylog) | Auditoría de cambios en usuarios y video tareas |
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
| Planificación (mes/semana) | `/planning` | `manage tasks` |
| Tareas extra (sidebar) | — | `manage tasks` |
| Ideas (CRUD + import/export txt) | `/ideas` | `manage tasks` |
| Historial de tareas (auditoría) | `/task-history` | `manage tasks` |
| YouTube (canales + API stats) | `/youtube` | `view youtube` |
| Reportes PDF (Dashboard / Planning) | `/dashboard`, `/planning` | `view reports` |
| Historial de reportes | `/report-history` | `view reports` |
| Empresa (nombre, logo, color, canales) | `/settings` | `manage tasks` |
| AI Generator (guiones, copy, frases, audio) | `/ai` | Usuario autenticado |
| Historial de generaciones AI | `/ai/history` | Usuario autenticado |
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
view youtube
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
- Componentes reutilizables (formularios, botones, paginación, modales)
- Soporte modo claro / oscuro (Tailwind `dark:`)
- Vista cards / lista toggleable en sección de videos recientes
- Auto-selección del primer bloque horario libre al crear tarea
- Botón "+" se oculta cuando el bloque o día están completos

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
│   ├── Controllers/         # Dashboard, Planning, VideoTask, ExtraTask, TaskReport, ReportHistory,
│   │                        # Settings, Users, Roles, Profile, Youtube, Idea, TaskHistory, AI
│   ├── Middleware/          # HandleInertiaRequests (auth + flash compartidos)
│   └── Requests/           # Validación (Store/Update User, Profile, etc.)
├── Models/                  # User, VideoTask, ExtraTask, ReportHistory, Organization, Channel, Idea, GeneratedVideo
├── Policies/                # UserPolicy
├── Services/
│   ├── AI/                  # GeminiService, ElevenLabsService, AIContentService, Prompts,
│   │                        # ScriptCleaner, CopyParser, PhraseCleaner
│   ├── PlanningCalendarService, DashboardService, UserService, IdeaService, YouTubeService
└── Support/                 # WorkBlocks, VideoTaskStatuses (enums planos con constantes)

database/
├── migrations/              # users, permissions, activity_log, video_tasks, extra_tasks, report_history,
│                           # organizations, channels, ideas
└── seeders/                 # RolesAndPermissionsSeeder, AdminUserSeeder

resources/views/pdf/         # report.blade.php (template PDF con logo, color empresa y footer)

resources/js/
├── Components/
│   ├── AI/                  # UseTaskModal (envío al planificador desde AI Generator)
│   ├── ExportPdfModal.vue   # Modal reutilizable para exportar PDF (Dashboard + Planning)
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
    ├── AI/                  # Index (generador 4 columnas) + History (historial con descarga TXT y envío a planificador)
    ├── Auth/
    ├── Dashboard/           # KPIs reales con statcards + círculo SVG rendimiento + Exportar PDF
    ├── Ideas/               # Index (tabs por canal, búsqueda, sort, CRUD, import/export txt)
    ├── Planning/            # Calendario mes/semana + sidebar tareas del día + extra tasks modal + Exportar PDF
    ├── Profile/
    ├── Reports/             # History.vue (historial de reportes PDF)
    ├── Roles/
    ├── Settings/            # Empresa (nombre, logo, color) + Canales (CRUD inline)
    ├── TaskHistory/         # Index (lista tareas con estado) + Show (timeline de cambios)
    ├── Users/
    ├── VideoTasks/          # Create, Edit, Show (3 columnas + video embed), VideoTaskForm
    └── Youtube/             # Index (tabs canal, estadísticas, cards/lista videos, estado videos)
```

---

## Arquitectura

- **Service Layer** — lógica de negocio desacoplada de controladores (`UserService`, `DashboardService`, `PlanningCalendarService`, `IdeaService`)
- **Support classes** — constantes y helpers sin enums nativos (`WorkBlocks`, `VideoTaskStatuses`)
- **Policies + middleware `can`** — autorización en backend (no solo en UI)
- **Inertia** — una sola app Vue sin API REST duplicada para el panel
- **Componentes Vue reutilizables** — DRY en formularios y UI
- **Activity Log** — `spatie/laravel-activitylog` registra automáticamente cambios en `User` y `VideoTask` (quién, qué, cuándo)
- **PDF generation** — `barryvdh/laravel-dompdf` con plantilla Blade agrupada por días, logo empresa (base64), color corporativo, links en cursiva y footer con nombre del sistema
- **Import Python** — comando `import:python-data` migra datos desde SQLite (tasks.db) a Laravel, con detección de duplicados
- **AI Generator** — Módulo de generación de contenido con **Google Gemini 2.5 Flash** (rotación de API keys, rate-limit handling) y **ElevenLabs** (TTS a MP3). Servicios: `GeminiService`, `ElevenLabsService`, `AIContentService`, `ScriptCleaner`, `CopyParser`, `PhraseCleaner`, `Prompts`. Persistencia en tabla `generated_videos`. Envío directo al planificador desde el generador y el historial.
- **Permisos por ruta** — `manage tasks` para planificación, tareas extra, ideas, historial de tareas y settings; `view reports` para reportes e historial; `view youtube` para sección YouTube; AI Generator accesible para cualquier usuario autenticado

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

### Importar datos desde Python

```bash
php artisan import:python-data "E:\Python\Git\GrowthOS\database\tasks.db"
```

Importa video tareas, tareas extra, ideas y canales desde la base SQLite del proyecto Python legado. Es idempotente (detecta duplicados por fecha+bloque y los salta).

---

## Variables de entorno relevantes

| Variable | Descripción |
|----------|-------------|
| `APP_URL` | URL base de la aplicación |
| `DB_*` | Conexión MySQL |
| `ADMIN_PASSWORD` | Contraseña del usuario admin al ejecutar seeders |
| `VITE_APP_NAME` | Nombre mostrado en el frontend |
| `YOUTUBE_API_KEY` | API Key de YouTube Data API v3 para estadisticas de canales |
| `GEMINI_API_KEY` | API Key de Google Gemini (o `GEMINI_KEY_1`, `GEMINI_KEY_2`... para rotación) |
| `ELEVENLABS_API_KEY` | API Key de ElevenLabs para generación de audio MP3 |
| `ELEVENLABS_VOICE_ID` | Voice ID de ElevenLabs para narración |
| `ELEVENLABS_MODEL_ID` | Modelo ElevenLabs (default: `eleven_multilingual_v2`) |
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
- [x] Activity log en usuarios y video tareas (spatie/laravel-activitylog)
- [x] CRUD completo Video Tasks (Create, Edit, Show, formulario reutilizable)
- [x] Planificación con calendario (vista mes/semana, sidebar de tareas por día, feriados Perú)
- [x] Tareas extra (CRUD inline en sidebar del calendario, indicador oficina/fuera)
- [x] Reportes PDF con dompdf (scope anual/mensual/semanal/día, agrupado por día, logo empresa base64, color corporativo, links cursiva, footer)
- [x] Historial de reportes (listado + re-descarga exacta)
- [x] Dashboard con KPIs reales + botón Exportar PDF
- [x] Configuración empresa (nombre, logo, color principal) y canales (CRUD inline)
- [x] YouTube section con estadísticas via API (suscriptores, vistas, videos recientes, toggle cards/lista)
- [x] Ideas (CRUD, tabs por canal, búsqueda, sort, import/export txt)
- [x] Historial de tareas (listado con filtros + timeline de cambios por tarea)
- [x] Import Python (comando `import:python-data` — migra tasks, extra_tasks, ideas desde SQLite)
- [x] Planning: botón "+" oculto cuando bloque/día completo
- [x] VideoTaskForm: auto-selección del primer bloque libre
- [x] Show: layout 3 columnas (guion, copy, video) + YouTube/TikTok embed
- [x] PDF mejorado: line-height 1.6, escalado proporcional, footer, color en links
- [x] AI Generator con Gemini 2.5 Flash (guion, copy, frases, audio ElevenLabs)
- [x] Historial de generaciones AI (descarga TXT, envío a planificador, cargar en editor)

### Pendiente
- [ ] Tests de autorización y CRUD
- [ ] Multi-tenancy y suscripciones

---

## Licencia

MIT
