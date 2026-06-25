# GrowthOS

GrowthOS es una plataforma SaaS interna para gestión de contenido audiovisual, construida con **Laravel 12**, **Vue 3** e **Inertia.js**. Incluye autenticación, RBAC, planificación semanal de tareas de video, módulo de ideas (con paginación, filtros y edición en masa), historial de cambios por tarea, reportes PDF con logo/color corporativo, dashboard con KPIs reales, módulo de vacaciones y permisos, respaldo de datos exportable, e integración con YouTube API.

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
| Usuarios (ver) | `/users` | `view users` |
| Usuarios (crear) | `/users/create` | `create users` |
| Usuarios (editar) | `/users/{id}/edit` | `edit users` |
| Usuarios (eliminar) | — | `delete users` |
| Roles (ver) | `/roles` | `view roles` |
| Roles (crear) | `/roles/create` | `create roles` |
| Roles (editar) | `/roles/{id}/edit` | `edit roles` |
| Roles (eliminar) | — | `delete roles` |
| Planificación (ver) | `/planning` | `view planning` |
| Planificación (crear) | — | `create planning` |
| Planificación (editar) | — | `edit planning` |
| Planificación (eliminar) | — | `delete planning` |
| Planificación (exportar PDF) | — | `export planning` |
| Tareas extra (ver) | — | `view extra tasks` |
| Tareas extra (crear) | — | `create extra tasks` |
| Tareas extra (editar) | — | `edit extra tasks` |
| Tareas extra (eliminar) | — | `delete extra tasks` |
| Ideas (ver) | `/ideas` | `view ideas` |
| Ideas (crear) | — | `create ideas` |
| Ideas (editar) | — | `edit ideas` |
| Ideas (eliminar) | — | `delete ideas` |
| Ideas (importar/exportar) | — | `import ideas`, `export ideas` |
| Historial de tareas (auditoría) | `/task-history` | `view task history` |
| Reportes PDF (ver) | `/dashboard`, `/planning` | `view reports` |
| Reportes PDF (exportar) | — | `download reports` |
| Historial de reportes (ver) | `/report-history` | `view reports` |
| Historial de reportes (descargar) | — | `download reports` |
| YouTube (canales + API stats) | `/youtube` | `view youtube` |
| Empresa (editar) | `/settings` | `edit company` |
| Canales (CRUD) | — | `create channels`, `edit channels`, `delete channels` |
| AI Generator | `/ai` | `view ai` |
| AI Historial (ver) | `/ai/history` | `view ai history` |
| AI Historial (descargar) | — | `download ai` |
| AI Historial (filtrar usadas en planner) | — | `view ai history` |
| Perfil | `/profile` | Usuario autenticado |
| Vacaciones (ver) | `/vacations` | `view vacations` |
| Vacaciones (solicitar) | — | `create vacations` |
| Vacaciones (editar) | — | `edit vacations` |
| Vacaciones (aprobar/rechazar) | — | `approve vacations`, `reject vacations` |
| Vacaciones (eliminar) | — | `delete vacations` |
| Permisos (ver) | `/time-off` | `view time off` |
| Permisos (solicitar) | — | `create time off` |
| Permisos (editar) | — | `edit time off` |
| Permisos (aprobar/rechazar) | — | `approve time off`, `reject time off` |
| Permisos (eliminar) | — | `delete time off` |
| Respaldo de datos | `/backup` | Super Admin |
| Manual | `/manual` | Todos los autenticados |

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

Permisos del sistema (34 en total, agrupados por módulo):

```
Dashboard:       view dashboard
Usuarios:        view users, create users, edit users, delete users
Roles:           view roles, create roles, edit roles, delete roles
Planificación:   view planning, create planning, edit planning, delete planning, export planning
Tareas extra:    view extra tasks, create extra tasks, edit extra tasks, delete extra tasks
Ideas:           view ideas, create ideas, edit ideas, delete ideas, import ideas, export ideas
Historial:       view task history
Reportes:        view reports, download reports, delete reports
YouTube:         view youtube
Empresa:         view empresa, create empresa, edit empresa, delete empresa
Canales:         create channels, edit channels, delete channels
AI:              view ai, view ai history, download ai
Config:          view configuracion, configure work hours, configure youtube, configure dashboard, configure backup
Vacaciones:      view vacations, create vacations, edit vacations, approve vacations, reject vacations, delete vacations
Permisos:        view time off, create time off, edit time off, approve time off, reject time off, delete time off
```

### CRUD de usuarios
- Listado paginado con búsqueda en tiempo real
- Crear, editar y eliminar
- Asignación de rol
- Validación vía Form Requests
- Capa de servicio (`UserService`)
- Policy basada en permisos (`UserPolicy`)
- Solo Super Admin puede editar/eliminar a otros Super Admins (bloqueado en Policy y en UI)

### Roles y permisos
- CRUD de roles
- Sincronización de permisos por rol
- Protección de rutas con middleware `can:*`

### PDF - Reportes
- Observaciones por día con subtítulo "Observaciones" en el color de la empresa y borde izquierdo coloreado
- Tareas extra con diseño de tarjeta y borde izquierdo gris oscuro (mismo estilo que observaciones)

### UI / UX
- Layout con sidebar dinámico según permisos
- Topbar fijo con perfil, logout y selector de empresa (Super Admin)
- Flash messages y toasts
- Modal centrado de error 403 para respuestas Inertia (con backdrop, icono, botón "Entendido")
- Modal de confirmación para eliminaciones
- Componentes reutilizables (formularios, botones, paginación, modales, StatCard con color)
- Soporte modo claro / oscuro (Tailwind `dark:`)
- Dark theme en Welcome, Login, Register y Forgot Password con diseño glassmorphism
- Vista cards / lista toggleable en sección de videos recientes
- Auto-selección del primer bloque horario libre al crear tarea (salta bloque ocupados)
- Botón "+" se oculta cuando el bloque o día están completos
- Selector de color con círculos blancos y círculo interior de color en formularios
- StatCards con borde superior y valor coloreados según métrica
- Dashboard responsivo: stats 2 columnas en mobile, 5 en desktop
- Modales con scroll interno y márgenes laterales en mobile
- Página /ideas con scroll solo en lista de ideas; sidebars y buscador estáticos
- Calendario mensual con cards independientes, días de otros meses visibles en baja opacidad
- Configuración en grid de 2 columnas en desktop
- Botón copiar con animación check (pop-in) en invitaciones
- Búsquedas con debounce (400ms) en usuarios y roles
- Paginación en tabla de roles
- Nombre de app desde variable de entorno (`VITE_APP_NAME`)
- DaySidebar: secciones colapsables (Tareas de video, Tareas extra, Observaciones) con iconos ChevronDown/ChevronRight
- DaySidebar: selectores de estado más anchos (`min-w-[130px]`)
- **Ideas**: paginación (50 por página), filtro Todas/Pendientes/Usadas, selección múltiple con checkboxes, barra de acciones masivas (Marcar como usadas, pendientes, eliminar, editar contenido)
- **Vacaciones y Permisos**: listado con mismo formato que Usuarios (tabla con búsqueda, paginación, hover), formulario modal, aprobación/rechazo
- **Respaldo de datos**: exportación de todas las tablas del sistema en JSON comprimido, restauración con validación por empresa, programación semanal desde Configuración
- **Dashboard adaptativo**: grid de columnas se ajusta dinámicamente si el usuario no tiene permiso `view users`
- **Sidebar reordenado**: por flujo de trabajo (Dashboard → Planificación → Tareas → YouTube → Ideas → AI → Historial → Usuarios → Roles → Vacaciones → Permisos → Empresa → Configuración → Manual)
- **Planning mes**: tareas extra visibles como barras ámbar individuales lado a lado
- **YouTube**: gráficos con Chart.js (vue-chartjs) en vez de SVG custom
- **403 personalizado**: página SPA con botones "Volver" e "Ir al Dashboard"
- **Configuración**: permisos granulares por sección (`configure work hours`, `configure youtube`, `configure dashboard`, `configure backup`), ya no existe permiso master `edit configuracion`
- **Respaldo en topbar**: icono `HardDrive` fijo en la topbar, eliminado del sidebar
- **Sesiones de trabajo multi-día**: tabla `work_sessions` permite continuar tareas en días posteriores. Las tareas se muestran en el calendario tanto en su fecha original como en las fechas de sesión, cada una con su propio bloque horario y estado.
- **Gestión de sesiones desde sidebar**: botón "+ Sesión" crea sesión en la fecha de hoy con el primer bloque libre disponible; botón "Completar" marca la sesión como completada; todo sin salir del calendario.
- **Edición/eliminación de sesiones**: desde el formulario de editar tarea, sección "Sesiones de trabajo" con opciones de editar fecha/bloque/estado y eliminar con confirmación modal.
- **Multi-idioma en VideoTasks**: columna `translations` JSON que almacena título/guion/copy en EN y PT. Pestañas de idioma en crear, editar y ver tarea con indicador visual del idioma activo.
- **Leyenda de colores en planificación**: todos los estados de tarea y sesión visibles con indicador de color, agrupados por sección (Tareas / Sesiones).

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
├── Enums/                   # VideoTaskStatus (backed string enum PHP 8)
├── Http/
│   ├── Controllers/         # Dashboard, Planning, VideoTask, ExtraTask, TaskReport, ReportHistory,
│   │                        # Settings, Users, Roles, Profile, Youtube, Idea, TaskHistory, AI,
│   │                        # Backup, Vacation, TimeOff, Manual
│   ├── Middleware/          # HandleInertiaRequests (auth + flash compartidos)
│   └── Requests/           # Validación (Store/Update User, Profile, etc.)
├── Models/                  # User, VideoTask, WorkSession, ExtraTask, ReportHistory, Organization, Channel,
│   │                        # Idea, GeneratedVideo, Vacation, TimeOff, DayObservation
├── Policies/                # UserPolicy, ChannelPolicy, VideoTaskPolicy, IdeaPolicy, ExtraTaskPolicy
├── Services/
│   ├── AI/                  # GeminiService, ElevenLabsService, AIContentService, Prompts,
│   │                        # ScriptCleaner, CopyParser, PhraseCleaner
│   └── BackupService, PlanningCalendarService, DashboardService, UserService, IdeaService,
│       YouTubeService, ReportService
├── Support/                 # WorkBlocks (lógica de bloques de trabajo)
└── Traits/                  # BelongsToOrganization, OwnedByUser (global scopes)

database/
├── migrations/              # users, permissions, activity_log, video_tasks, extra_tasks, report_history,
│                           # organizations, channels, ideas, generated_videos, day_observations,
│                           # vacations, time_offs
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
│   └── UI/                  # PrimaryButton, FlashMessage, Pagination, StatCard, ErrorModal
├── Composables/             # useTheme (modo claro/oscuro)
├── config/
│   └── navigation.js        # Menú lateral filtrado por permiso
├── Layouts/
│   └── AppLayout.vue        # Layout principal autenticado
└── Pages/
    ├── AI/                  # Index (generador 4 columnas) + History (historial con descarga TXT y envío a planificador)
    ├── Auth/
    ├── Backup/              # Index (exportación, restauración, programación, scope selector)
    ├── Dashboard/           # KPIs reales con statcards + gráficos Chart.js + Exportar PDF
    ├── Error/               # 403.vue (página personalizada SPA)
    ├── Ideas/               # Index (tabs por canal, búsqueda, sort, paginación, filtro estado, selección múltiple, edición en masa, CRUD, import/export txt)
    ├── Manual/              # Manual.vue (documentación del sistema)
    ├── Planning/            # Calendario mes/semana + sidebar tareas del día + extra tasks modal + Exportar PDF
    ├── Profile/
    ├── Reports/             # History.vue (historial de reportes PDF)
    ├── Roles/               # Index + Create + Edit + RoleForm
    ├── Settings/            # Index (horario laboral, YouTube, dashboard, respaldo, empresa + canales inline)
    ├── TaskHistory/         # Index (lista tareas con estado) + Show (timeline de cambios)
    ├── TimeOff/             # Index (listado con búsqueda, formulario modal, aprobar/rechazar)
    ├── Users/               # Index + Create + Edit
    ├── Vacations/           # Index (listado con búsqueda, formulario modal, aprobar/rechazar)
    ├── VideoTasks/          # Create, Edit, Show (3 columnas + video embed), VideoTaskForm
    └── Youtube/             # Index (tabs canal, gráficos Chart.js, cards/lista videos)
```

---

## Arquitectura

- **Service Layer** — lógica de negocio desacoplada de controladores (`UserService`, `DashboardService`, `PlanningCalendarService`, `IdeaService`)
- **Enums nativos** — `VideoTaskStatus` como backed enum PHP 8 con labels y helpers
- **Policies + middleware `can`** — autorización en backend (no solo en UI)
- **Inertia** — una sola app Vue sin API REST duplicada para el panel
- **Componentes Vue reutilizables** — DRY en formularios y UI
- **Activity Log** — `spatie/laravel-activitylog` registra automáticamente cambios en `User` y `VideoTask` (quién, qué, cuándo)
- **PDF generation** — `barryvdh/laravel-dompdf` con plantilla Blade agrupada por días, logo empresa (base64), color corporativo, links en cursiva y footer con nombre del sistema
- **AI Generator** — Módulo de generación de contenido con **Google Gemini 2.5 Flash** (rotación de API keys, rate-limit handling) y **ElevenLabs** (TTS a MP3). Servicios: `GeminiService`, `ElevenLabsService`, `AIContentService`, `ScriptCleaner`, `CopyParser`, `PhraseCleaner`, `Prompts`. Persistencia en tabla `generated_videos` con flag `used_in_planner`. Envío directo al planificador desde el generador y el historial.
- **Permisos granulares** — cada acción CRUD tiene su propio permiso (34 permisos en 12 grupos). Las rutas se protegen con middleware `can:*` en backend y la UI oculta botones según los permisos del usuario.
- **Backup de datos** — exportación completa del tenant en JSON con streaming chunked (500 registros por lote), restauración con transacciones, scoping por organización, programación semanal dinámica
- **CSRF handling** — token refrescado cliente-side en cada navegación Inertia, recarga automática en error 419
- **Blade 403** — página de error personalizada con Vite CSS en vez de CDN Tailwind

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
| `GEMINI_API_KEY` | API Key de Google Gemini (o `GEMINI_KEY_1`, `GEMINI_KEY_2`... para rotación) |
| `ELEVENLABS_API_KEY` | API Key de ElevenLabs para generación de audio MP3 |
| `ELEVENLABS_VOICE_ID` | Voice ID de ElevenLabs para narración |
| `ELEVENLABS_MODEL_ID` | Modelo ElevenLabs (default: `eleven_multilingual_v2`) |
| `APP_TIMEZONE` | Zona horaria de la aplicación (`America/Lima`) |

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

- [x] PDF Report: observaciones por día (subtítulo "Observaciones" con color empresa, borde izquierdo)
- [x] PDF Report: tareas extra con diseño de borde izquierdo gris oscuro
- [x] DaySidebar: secciones colapsables (Tareas de video, Tareas extra, Observaciones)
- [x] Planning: botón "+" oculto cuando bloque/día completo
- [x] VideoTaskForm: auto-selección del primer bloque libre (salta ocupados al cargar)
- [x] Show: layout 3 columnas (guion, copy, video) + YouTube/TikTok embed
- [x] PDF mejorado: line-height 1.6, escalado proporcional, footer, color en links
- [x] AI Generator con Gemini 2.5 Flash (guion, copy, frases, audio ElevenLabs)
- [x] Historial de generaciones AI (descarga TXT, envío a planificador, cargar en editor, filtro usado en planner)
- [x] Dark theme en Welcome, Login, Register y Forgot Password
- [x] Permisos granulares (32 permisos en 10 grupos, reemplazando `manage users`, `manage tasks`, `view ai`)
- [x] Modal de error 403 con redirect a página anterior (Inertia)
- [x] Protección Super Admin: solo otro Super Admin puede editar/eliminar su cuenta
- [x] StatCard con prop `color` (borde + valor coloreado)
- [x] ColorPicker circular inline en formularios
- [x] Dashboard responsivo: stats 2 col mobile, KPIs coloreados, actividad limitada a 5, ocultar secciones a no-admin, excluir Super Admin de usuarios recientes
- [x] Ideas: layout 3 columnas con scroll solo en lista central
- [x] SearchInput con debounce (400ms) en todos los módulos
- [x] Roles: paginación en tabla
- [x] Localización: Logout → Cerrar sesión, Search → Buscar, GrowthOS → VITE_APP_NAME
- [x] YouTube: stats coloreadas, 2 cards por fila en mobile
- [x] Topbar: memory leak corregido (removeEventListener en unmount)
- [x] Planning: navegación SPA con router.visit/replace (sin window.location)
- [x] VideoTaskForm: auto-liberar bloque ocupado si el seleccionado vía URL está ocupado
- [x] CalendarMonth: cards independientes, días fuera de mes en baja opacidad
- [x] Settings: grid 2 columnas, color con círculos
- [x] Botón copiar con animación check (pop-in) en invitaciones
- [x] Overflow-x-hidden global para mobile
- [x] Topbar sticky → fixed con padding compensado
- [x] Vacaciones y Permisos (CRUD, aprobar/rechazar, formulario modal, tabla con búsqueda)
- [x] Respaldo de datos (BackupService con export streaming chunked, restore, schedule)
- [x] Backup schedule en Settings con permiso granular `configure backup`
- [x] Permisos granulares en Configuración (`configure work hours`, `configure youtube`, `configure dashboard`, `configure backup`)
- [x] Eliminado permiso master `edit configuracion`
- [x] Chart.js en /youtube (reemplaza SVG custom)
- [x] 403 personalizado (SPA + Blade con Vite CSS)
- [x] Planning: tareas extra como barras ámbar en vista mensual
- [x] Dashboard adaptativo (grid dinámico según permisos)
- [x] Sidebar reordenado por flujo de trabajo
- [x] Manual del sistema
- [x] Backup scope selector (Super Admin elige empresa en /backup)
- [x] Dashboard multi-empresa (Super Admin ve stats de todas o por empresa)
- [x] Ideas: paginación, filtro Todas/Pendientes/Usadas, selección múltiple con checkboxes, edición en masa
- [x] Company picker forzoso para Super Admin sin empresa activa
- [x] CSRF token refrescado cliente-side, recarga en 419
- [x] Sesiones de trabajo multi-día (WorkSessions): tareas continuadas en días posteriores, visibles en calendario y PDF
- [x] Multi-idioma en VideoTasks (EN/PT): pestañas de idioma en crear, editar y ver; columna `translations` JSON
- [x] Leyenda de colores unificada en planificación con todos los estados de tarea y sesión
- [x] Gestión de sesiones desde sidebar: crear sesión en fecha actual, completar sesión
- [x] Edición y eliminación de sesiones con modal de confirmación desde el formulario de tarea

### Pendiente
- [ ] Tests de autorización y CRUD
- [ ] Multi-tenancy y suscripciones

---

## Licencia

MIT
