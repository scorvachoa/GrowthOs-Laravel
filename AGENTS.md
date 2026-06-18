# GrowthOS — System Reference for Agents

## Stack
- **Backend:** PHP 8.2+, Laravel 12, MySQL
- **Frontend:** Vue 3 (Composition API, `<script setup>`), Inertia.js 2, Tailwind CSS 3, Vite 6
- **Auth:** Laravel Breeze + Inertia (login, register, password reset, email verification)
- **Packages:** Spatie Permission (RBAC), Spatie Activity Log (audit), barryvdh/laravel-dompdf (PDF), lucide-vue-next (icons)

---

## Architecture

### Inertia SPA
All authenticated routes render Vue pages via Inertia. No REST API for the panel. Shared data (auth user, flash) passed via `HandleInertiaRequests` middleware.

### Service Layer
Business logic lives in `app/Services/`. Controllers call services and return Inertia responses.

### Policies
`app/Policies/` — authorization on CRUD operations. Used via `Gate` in controllers, middleware `can:*` on routes, and blade directives. Key patterns:
- **UserPolicy:** Super Admin can only be edited/deleted by another Super Admin; org-scoped access
- **ChannelPolicy, VideoTaskPolicy, IdeaPolicy, ExtraTaskPolicy:** Org-scoped via `BelongsToOrganization` trait + policy checks

### Global Scopes (Traits)
- `BelongsToOrganization` — auto-filters all queries by `organization_id` from `Auth::user()->activeOrganizationId()`. Applied to: `VideoTask`, `Channel`, `Idea`, `ExtraTask`, `GeneratedVideo`, `ReportHistory`, `DayObservation`
- `OwnedByUser` — auto-filters by `user_id`. Applied to: `GeneratedVideo`

### Enums (PHP 8)
`app/Enums/VideoTaskStatus.php` — backed string enum with `label()` (Spanish), `values()`, `labels()`, `options()`, `isValid()`.

---

## Roles & Permissions

### Default Roles (from `RolesAndPermissionsSeeder`)
| Role | Scope | Permissions |
|------|-------|-------------|
| **Super Admin** | Global (no org) | All 34 permissions + can switch companies |
| **Admin** | Per-org | All permissions except role management |
| **Employee** | Per-org | None by default (assignable per-org) |

### All 34 Permissions
```
Dashboard:   view dashboard
Usuarios:    view users, create users, edit users, delete users
Roles:       view roles, create roles, edit roles, delete roles
Planning:    view planning, create planning, edit planning, delete planning, export planning
Tareas:      view tasks
Ideas:       view ideas, create ideas, edit ideas, delete ideas, import ideas, export ideas
Reportes:    view reports, download reports, delete reports
YouTube:     view youtube
AI:          generate ai, view ai history, download ai
Empresa:     view empresa, create empresa, edit empresa, delete empresa
Config:      view configuracion, configure work hours, configure youtube, configure dashboard, configure backup
```

### Super Admin Special Behavior
- `activeOrganizationId()` returns `session('active_company_id', $this->organization_id)` — can switch companies via `/company/switch`
- Can see all orgs' data in dashboard
- `UserPolicy::update/delete` — `false` if target is Super Admin and actor is not
- Excluded from `recent_users` in DashboardService

### Navigation Filtering
`resources/js/config/navigation.js` — 14 items. Each has `permission` (string, optional). The `Sidebar` component filters `auth.user.permissions` against `item.permission`; items without permission are always visible.

---

## Project Structure

### Key Directories
```
app/
├── Enums/              # VideoTaskStatus (backed string enum)
├── Http/
│   ├── Controllers/    # One per module, thin (delegate to services)
│   ├── Middleware/      # HandleInertiaRequests
│   └── Requests/       # Form request validation
├── Models/             # Eloquent models (11 total)
├── Policies/           # 6 policies
├── Services/           # Business logic (9 service classes)
├── Support/            # WorkBlocks (block time logic)
└── Traits/             # BelongsToOrganization, OwnedByUser

resources/js/
├── Components/
│   ├── Forms/          # TextInput, SearchInput
│   ├── Modals/         # Modal, ConfirmDelete
│   ├── Navigation/     # Sidebar, SidebarItem, Topbar
│   ├── UI/             # StatCard, Pagination, FlashMessage, ErrorModal, PrimaryButton
│   └── AI/             # UseTaskModal
├── Composables/        # useTheme
├── config/
│   └── navigation.js   # Sidebar menu definition
├── Layouts/
│   └── AppLayout.vue   # Main authenticated layout
└── Pages/              # One directory per route module (Planning/ has composables/usePlanning.js)
```

---

## Models

| Model | Table | Traits | Key Relations | Fillable Highlights |
|-------|-------|--------|---------------|---------------------|
| User | users | HasRoles, LogsActivity, HasFactory, Notifiable | organization() | name, email, password, organization_id, settings |
| VideoTask | video_tasks | SoftDeletes, LogsActivity, BelongsToOrganization | creator(), channel() | task_date, time_range, title, script, copy, youtube_url, status, channel_id, key_phrases |
| ExtraTask | extra_tasks | SoftDeletes, BelongsToOrganization | creator() | task_date, time_range, title, status, location |
| Organization | organizations | — | users(), channels() | name, logo_path, primary_color, invite codes |
| Channel | channels | BelongsToOrganization | — | name, color, youtube_channel_id, channel_url |
| Idea | ideas | BelongsToOrganization | channel() | channel_id, content, is_used, tags, priority |
| GeneratedVideo | generated_videos | BelongsToOrganization, OwnedByUser | — | idea, script, copy fields, used_in_planner |
| ReportHistory | report_history | BelongsToOrganization | user() | scope, filename, filters_json |
| DayObservation | day_observations | BelongsToOrganization | — | date, observation |
| Vacation | vacations | — | — | user_id, start_date, end_date, status |
| TimeOff | time_offs | — | — | user_id, date, type, status |

---

## Key Services

| Service | Key Method | Returns |
|---------|-----------|---------|
| `DashboardService` | `stats(scope, authUser)` | Array with counts, period stats, recent activity, status labels |
| `PlanningCalendarService` | `snapshot(year, month, weekStart, workBlocks)` | Full calendar data with tasks, absences, holidays per day |
| `UserService` | `create(data)`, `update(user, data)` | User with role assignment, org-scoped |
| `IdeaService` | `list(channelId, search, sort)` | Collection of ideas ordered by used status + sort |
| `ReportService` | `generateAndSave(scope, start, title, days, company, systemName)` | Saves PDF to disk, returns filename |
| `YouTubeService` | — | Channel stats from YouTube Data API v3 |

---

## Routes

All authenticated routes are under `Route::middleware('auth')` in `routes/web.php`. Protected with `->middleware('can:*')`. Key patterns:
- Resourceful routes for users, roles, ideas, company
- `/planning/*` group under `can:view planning` — includes snapshot, tasks-by-date, occupied-blocks
- `/video-tasks/{id}/*` individually permissioned (create, edit, delete)
- `/ai/*` throttled at 10 requests/minute for generation endpoints
- `/company/*` CRUD + channel/invite sub-routes
- `/vacations/*` and `/time-off/*` under `can:view planning`

---

## Frontend Component Conventions

### Layout
- `AppLayout.vue` — provides `isDark`, `toggleDark`, `sidebarCollapsed`, `toggleSidebar`, `mobileSidebarOpen`, `toggleMobileSidebar`, `closeMobileSidebar` via `provide()`; child pages use `inject()`.
- Sidebar width: `w-64` expanded, `w-16` collapsed. Main content margin adjusts via `lg:ml-64` / `lg:ml-16`.
- Topbar is fixed. Main has `pt-[88px] sm:pt-[112px]`.

### StatCard
Props: `title` (String), `value` (String|Number), `color` (String, optional). When color is set: 3px top border + colored value text.

### Pagination
Used by users, roles, task history. Laravel pagination links rendered as Vue components.

### SearchInput
Props: `modelValue` (String). Emits `update:modelValue`. Placeholder "Buscar...". Width `w-full md:w-80`. Debounce is handled by the parent page (400ms).

### ErrorModal
Global 403 modal (backdrop, icon, "Entendido" button, navigates back). Triggered by Inertia error responses.

### Modals
- `Modal.vue` — reusable overlay with `max-h-[90vh] overflow-y-auto mx-4`
- `ConfirmDelete.vue` — confirmation dialog for deletions

---

## Key UI Patterns
- **Color picker:** White circle (`w-8 h-8 rounded-full border-2 border-gray-300`) with smaller inner circle (`w-5 h-5 rounded-full`) for the selected color
- **Dashboard stat grid:** 2 columns mobile (`grid-cols-2`), 5 columns desktop
- **Ideas page:** 3-column grid `lg:grid-cols-[220px_1fr_280px]` — only center column scrolls
- **YouTube videos:** 2 cards per row on mobile (`grid-cols-2`)
- **Copy button:** Check icon with pop animation (CSS `pop` keyframes)
- **Toggle sidebar:** Persisted in localStorage
- **Dark mode:** Tailwind `dark:` classes, toggled via `useTheme` composable
- **Responsive modals:** `mx-4 max-h-[90vh] overflow-y-auto`

---

## Common Pitfalls

### BelongsToOrganization Global Scope
Every query on scoped models auto-filters by `organization_id`. For Super Admin switching companies, this uses `session('active_company_id')`. To query across orgs, use `->withoutGlobalScope('organization')`.

### Activity Log
Only logs changes (dirty), not full snapshots. Uses `logOnlyDirty()`. The `activity_log` table has no `organization_id` column — don't scope activity queries by org.

### Topbar Memory Leak
Topbar listens for `scroll` event on `window`. Must call `removeEventListener('scroll', handler)` in `onUnmounted`.

### VideoTaskForm Block Selection
When URL params set `time_range` and that block is occupied, the form auto-selects the first free block instead. The watch fetches occupied blocks after mount and re-evaluates.

### Organization IDs
- Admin/Employee roles have `organization_id` set in roles table
- Super Admin role has `organization_id = null` (global)
- User creation uses `Auth::user()->activeOrganizationId()`

### PDF Generation
Uses `barryvdh/laravel-dompdf`. Templates in `resources/views/pdf/`. Logo passed as base64. Color applied inline. Letter page size.

---

## Commands

```bash
npm run dev        # Vite HMR
npm run build      # Prod build
php artisan migrate
php artisan db:seed
php artisan permission:cache-reset   # After permission changes
./vendor/bin/pint                     # PHP formatting
php artisan test                      # PHPUnit
composer dev                          # Serve + queue + logs + Vite
```
