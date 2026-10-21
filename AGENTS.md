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
External platform integrations live in per-provider subfolders (`AI/`, `YouTube/`, `Facebook/`); internal domain services and cross-platform orchestration stay at the root. Platform folders never import each other — cross-platform flows go through a root-level orchestrator. **Exception:** the Instagram services (`InstagramService`, `InstagramCommentsService`) live inside `Facebook/` because they ride on the exact same Graph API, page token and `SocialConnection` (the account is resolved via the page's `instagram_business_account` field).

### Policies
`app/Policies/` — authorization on CRUD operations. Used via `Gate` in controllers, middleware `can:*` on routes, and blade directives. Key patterns:
- **UserPolicy:** Super Admin can only be edited/deleted by another Super Admin; org-scoped access
- **ChannelPolicy, VideoTaskPolicy, IdeaPolicy, ExtraTaskPolicy:** Org-scoped via `BelongsToOrganization` trait + policy checks
- **VacationPolicy, TimeOffPolicy:** Org-scoped; approve/reject require different user than creator
- **WorkSessionPolicy:** Org-scoped; nested under video tasks
- **ReportHistoryPolicy, GeneratedVideoPolicy:** Org-scoped; delete requires ownership (GeneratedVideo)

### Global Scopes (Traits)
- `BelongsToOrganization` — auto-filters all queries by `organization_id` from `Auth::user()->activeOrganizationId()`. Fail-closed: no Auth → no filter (console/seed), authenticated Super Admin without org → sees all orgs, authenticated non–Super Admin without org → `whereRaw('1 = 0')` (matches nothing). Applied to: `VideoTask`, `Channel`, `Idea`, `ExtraTask`, `GeneratedVideo`, `ReportHistory`, `DayObservation`, `UsageEvent`. También define `tapActivity()` — si el modelo usa LogsActivity estampa `organization_id` en el `activity_log` (idem `User::tapActivity`)
- `OwnedByUser` — auto-filters by `user_id`. Applied to: `GeneratedVideo`

### Enums (PHP 8)
`app/Enums/VideoTaskStatus.php` — backed string enum with `label()` (Spanish), `values()`, `labels()`, `options()`, `isValid()`.

### Suscripción y cuotas
- **Middleware `subscription`** (`EnsureSubscriptionActive`, alias en `bootstrap/app.php`) — en el grupo `auth` de `routes/web.php`; redirige a `/subscription/blocked` si la org está `suspended`/`expired` (trial vencido se evalúa en-request vía `Organization::effectiveStatus()`, sin cron). Exentos: Super Admin, rutas `logout` y `profile.*`.
- **Middleware `quota:{event}`** (`CheckQuota`) — en las 12 rutas de generación IA; chequea el límite, y si la respuesta es < 400 registra el uso vía `UsageTrackingService`. El **techo de seguridad** (`quotas.safety_ceiling`, default 10000/mes por org+event) se aplica siempre → redirect back con flash `error` (o 429 JSON); los límites de plan (`quotas.limits.*`, todos `null` por ahora) solo bloquean con `QUOTA_MODE=enforce` — por defecto `observe` (log + medición durante ~2 semanas antes de fijar planes).
- **Licencia:** `LICENSE.txt` = MIT (relicenciada en Fase 5, coincide con `composer.json`).

---

## Roles & Permissions

### Default Roles (from `RolesAndPermissionsSeeder`)
| Role | Scope | Permissions |
|------|-------|-------------|
| **Super Admin** | Global (no org) | All 47 permissions + can switch companies |
| **Admin** | Per-org | All permissions except role management |
| **Employee** | Per-org | None by default (assignable per-org) |

### All 47 Permissions
```
Dashboard:   view dashboard
Usuarios:    view users, create users, edit users, delete users
Roles:       view roles, create roles, edit roles, delete roles
Planning:    view planning, create planning, edit planning, delete planning, export planning
Ideas:       view ideas, create ideas, edit ideas, delete ideas, import ideas, export ideas
Reportes:    view reports, download reports, delete reports
YouTube:     view youtube
AI:          generate ai, view ai history, download ai
Empresa:     view empresa, create empresa, edit empresa, delete empresa
Config:      view configuracion, configure work hours, configure youtube, configure dashboard
Vacaciones:  view vacations, create vacations, edit vacations, delete vacations, approve vacations, reject vacations
Time Off:    view time off, create time off, edit time off, delete time off, approve time off, reject time off
```

### Super Admin Special Behavior
- `activeOrganizationId()` returns `session('active_company_id', $this->organization_id)` — can switch companies via `/company/switch`
- Can see all orgs' data in dashboard
- `UserPolicy::update/delete` — `false` if target is Super Admin and actor is not
- Excluded from `recent_users` in DashboardService

### Navigation Filtering
`resources/js/config/navigation.js` — 15 top-level items (no incluye Notificaciones: vive en el Topbar); **YouTube, Facebook, Instagram e Ausencias llevan `children`** (submenú desplegable: YouTube→Comentarios; Facebook/Instagram→Mensajes+Comentarios; Ausencias→Vacaciones+Permisos, padre sin permiso propio: visible si algún hijo lo es) y cada entrada/child tiene `permission` (string, optional) y opcionalmente `roles` (array, optional). The `Sidebar` component filters `auth.user.permissions` against `item.permission` and `auth.user.roles` against `item.roles`; items without them are always visible (los hijos se filtran con la misma regla; un grupo con hijos requiere al menos un hijo visible). El estado expandido de los grupos persiste en `localStorage.sidebar_expanded` con comportamiento **acordeón** (solo un grupo abierto a la vez; navegar al hijo de otro grupo cierra el anterior) y el grupo se auto-expande cuando su hijo es la ruta activa. `SidebarItem` alterna el submenú por bubbling en un `div` (`data-parent-row`) porque Inertia `<Link>` anula el `@click` propio (los hijos se cierran el drawer móvil vía evento raíz). "Consola" (`/admin/organizations`) solo se muestra con rol `Super Admin`.

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
├── Models/             # Eloquent models (14 total)
├── Policies/           # 6 policies
├── Services/           # Business logic (platform subfolders: AI/, YouTube/, Facebook/)
├── Support/            # WorkBlocks (block time logic)
└── Traits/             # BelongsToOrganization, OwnedByUser

resources/js/
├── Components/
│   ├── Forms/          # TextInput, SearchInput
│   ├── Modals/         # Modal, ConfirmDelete
│   ├── Navigation/     # Sidebar, SidebarItem, Topbar
│   ├── UI/             # StatCard, Pagination, FlashMessage, ErrorModal, PrimaryButton
│   └── AI/             # UseTaskModal
├── Composables/        # useTheme, usePermissions, useDebounce, useCopyFeedback, useAbsenceActions, useFormat, useModalA11y
├── config/
│   ├── navigation.js   # Sidebar menu definition
│   ├── statusConstants.js # statusLabels/statusColors/calendarStatusColors (canonical frontend status maps)
│   └── localeLabels.js # localeLabels + localeLabel() for ES/EN/PT... chips
├── Layouts/
│   └── AppLayout.vue   # Main authenticated layout
└── Pages/              # One directory per route module (Planning/ has composables/usePlanning.js)
```

---

## Models

| Model | Table | Traits | Key Relations | Fillable Highlights |
|-------|-------|--------|---------------|---------------------|
| User | users | HasRoles, LogsActivity, HasFactory, Notifiable | organization() | name, email, password, organization_id, settings |
| VideoTask | video_tasks | SoftDeletes, LogsActivity, BelongsToOrganization | channel(), sessions() | task_date, time_range, title, script, copy, youtube_url, status, channel_id, locale, key_phrases, translation_group_id, translated_from_task_id |
| ExtraTask | extra_tasks | SoftDeletes, BelongsToOrganization | — | task_date, time_range, title, status, location |
| Organization | organizations | — | users(), channels() | name, logo_path, primary_color, invite codes, **status** (active/trial/suspended/expired, default active), **plan**, **trial_ends_at**; helpers `effectiveStatus()` (trial vencido → expired en-request, sin cron), `isAccessBlocked()`, `statusLabels()` |
| Channel | channels | BelongsToOrganization | — | name, color, youtube_channel_id, channel_url, locales (JSON array) |
| Idea | ideas | BelongsToOrganization | — | channel_id, content, is_used, tags, priority |
| GeneratedVideo | generated_videos | BelongsToOrganization, OwnedByUser | — | idea, script, copy fields, used_in_planner |
| ReportHistory | report_history | BelongsToOrganization | user() | scope, filename, filters_json |
| DayObservation | day_observations | BelongsToOrganization | — | task_date, notes, created_by |
| Vacation | vacations | — | — | user_id, start_date, end_date, status |
| TimeOff | time_offs | — | — | user_id, date, type, status |
| SocialConnection | social_connections | BelongsToOrganization | — | provider, page_id, page_name, page_picture, access_token (encrypted), user_token (encrypted, long-lived 60d user token), expires_at (user-token expiry), meta (JSON) |
| UsageEvent | usage_events | BelongsToOrganization, HasFactory | user() | organization_id, user_id, event (p.ej. `ai.generate`), quantity, metadata; índice (organization_id, event, created_at) para agregados mensuales |

---

## Key Services

| Service | Key Method | Returns |
|---------|-----------|---------|
| `DashboardService` | `stats(scope, authUser)` | Array with counts, period stats, recent activity, status labels |
| `PlanningCalendarService` | `snapshot(year, month, weekStart, workBlocks)` | Full calendar data with tasks, absences, holidays per day |
| `UserService` | `create(data)`, `update(user, data)` | User with role assignment, org-scoped; roles resolved via `RoleProvisioningService` (`ValidationException` on Super Admin/inexistent role, self-heals missing Admin/Employee) |
| `RoleProvisioningService` | `ensureDefaultsFor(orgId)`, `ensureRoleFor(orgId, name)`, `resolveFor(orgId, name)` | Single source of the 47-permission catalog and default roles (Super Admin global / Admin 43 perms / Employee 0). Used by seeder, registration, `CompanyController::store`, `UserService` and the `2026_10_07_000001` backfill migration (unique index `organization_id+name+guard_name` on `roles`) |
| `IdeaService` | `list(channelId, search, sort)` | Collection of ideas ordered by used status + sort |
| `ReportService` | `generateAndSave(scope, start, title, days, company, systemName)` | Saves PDF to the **private** `local` disk (`reports/`, never web-servible), returns filename |
| `YouTube\YouTubeService` | — | Channel stats from YouTube Data API v3 |
| `YouTube\AutoPublishService` | `publishLiveScheduled()` | Auto-publishes "scheduled" tasks whose video is already live |
| `YouTube\CommentsService` | `channelThreads(channel, limit)`, `channelAvatars(ids)` | Latest comment threads + channel avatar thumbnails (API key) |
| `Facebook\AuthService` | `connect(token, orgId)`, `connection(orgId)`, `pageInfo(orgId)` | SocialConnection (manual token paste) or page info array; `stale: true` when Graph API fails. **Long-lived tokens:** with `FACEBOOK_APP_ID`/`FACEBOOK_APP_SECRET` in `.env`, `connect()` auto-exchanges any pasted user token via `fb_exchange_token` (60 days), stores the derived page token + the encrypted `user_token`, and sets `expires_at`; without credentials or when pasting a Page token it behaves as before (short-lived, `expires_at = null`). `connection()` silently calls `refreshIfDue()` — re-exchanges the user token and re-derives the page token when `expires_at` is within 7 days (no-op without a stored user token) |
| `Facebook\GraphClient` | `get(path, token, params)`, `post(path, token, params)`, `delete(path, token, params)` | Decoded Graph API payload; throws `FacebookGraphException` on errors (DELETE sends params as query string) |
| `Facebook\PageService` | `feed(orgId, limit)`, `insights(orgId, days=28)`, `publish(orgId, message, link?, photo?)`, `updatePost(orgId, postId, message, link?)`, `deletePost(orgId, postId)` | Recent page posts (5 min cache, 60s negative cache); daily views/engagements/video_views (10 min cache, 60s negative); null/error states when disconnected or Graph fails. Mutations need `pages_manage_posts` and clear the feed cache: publish (photo URL → `/{page}/photos`, else message+link → `/{page}/feed`), edit (`POST /{post-id}`), delete (`DELETE /{post-id}`). Hiding own posts is NOT possible via API (Meta rejects `is_published=false` and `is_hidden` on page-owned posts) |
| `Facebook\CommentsService` | `threads(orgId, limit)`, `reply(orgId, commentId, message)`, `hide(orgId, commentId, hidden)`, `delete(orgId, commentId)` | Comment threads on latest posts via one feed call with nested `comments{comments{}}` expansion (2 min cache, 60s negative; excludes the page's own top-level comments, marks `responded` when the page replied); reply posts as the page (needs `pages_manage_engagement`); hide toggles `is_hidden` (reversible), delete removes permanently (`DELETE /{comment-id}`) — both clear the threads cache |
| `Facebook\MessagesService` | `conversations(orgId, platform=null)`, `messages(orgId, convId)`, `send(orgId, convId, text)` | Inbox for both platforms: conversation list (60s cache, 60s negative) filtered by `platform=messenger\|instagram` (cache key suffixed with the platform), thread messages (30s cache), text reply as the page (needs `pages_messaging`; resolves the participant PSID from `GET /{conv-id}?fields=participants`, posts to `/{page-id}/messages` — same endpoint for Messenger PSIDs and Instagram IGSIDs — clears all three conversation caches + the thread cache) |
| `Facebook\InstagramService` | `account(orgId)`, `feed(orgId, limit)`, `insights(orgId, days=28)`, `publish(orgId, caption, image?, video?, reel?)`, `deleteMedia(orgId, mediaId)` | Resolves the linked IG Business account through `GET /{page-id}?fields=instagram_business_account{...}` (10 min cache on success, 60s when unlinked/on failure; `linked:false` + raw `error` on Graph rejection e.g. missing `instagram_*` scopes). Media feed (5 min/60s negative); account insights with metric fallback chain (`reach,impressions,profile_views,follower_count` → down to `reach`; follower_count mapped to last value not sum; 10 min/60s negative). Publish = create container (`image_url` or `video_url`+caption, `media_type=REELS` for reels) → video containers polled for `status_code` up to ~45s → `media_publish` (needs `instagram_content_publish`); delete = `DELETE /{media-id}`; both clear account/feed/comments caches |
| `Facebook\InstagramCommentsService` | `threads(orgId, mediaLimit=8)`, `reply(orgId, commentId, text)`, `hide(orgId, commentId, hidden)`, `delete(orgId, commentId)`, `setLike(orgId, commentId, liked)` | Comments on latest media: media list + one `/{media-id}/comments` call each with nested `replies{...}` (2 min cache, 60s negative; skips the account's own top-level comments, `responded` when a reply username matches the account); reply via `POST /{comment-id}/replies`, hide toggles `hide=true\|false` (reversible), delete removes permanently (`DELETE /{comment-id}`) — all need `instagram_manage_comments`; like/unlike via `POST\|DELETE /{ig-user-id}/likes` with `comment_id` (needs `instagram_manage_engagement`); every mutation clears the threads cache |
| `QuotaService` | `used(org, event)`, `limitFor(event)`, `safetyCeiling()`, `monthlyUsage(org, months=1)`, `isEnforcing()` | Conteo mensual (mes calendario) por org+event desde `usage_events` (consulta con `withoutGlobalScope`); lee `config/quotas.php` (mode observe/enforce, techo duro, limits por evento null = sin límite) |
| `UsageTrackingService` | `record(event, qty=1, metadata=[])` | Inserta `UsageEvent` para la org activa; try/catch — un fallo de tracking nunca rompe el flujo IA que lo origina |

---

## Routes

All authenticated routes are under `Route::middleware('auth')` in `routes/web.php`. Protected with `->middleware('can:*')`. Key patterns:
- Resourceful routes for users, roles, ideas, company
- `/planning/*` group under `can:view planning` — includes snapshot, tasks-by-date, occupied-blocks
- `/tasks/{id}/*` individually permissioned (create, edit, delete); legacy `/video-tasks*` and `/extra-tasks*` only kept as GET redirects
- `/ai/*` throttled at 10 requests/minute for generation endpoints; all generation endpoints (`ai/*`, `tasks/generate-*`, `tasks/translate*`, `ideas/generate`) también llevan `quota:{event}` (ver "Suscripción y cuotas")
- `/subscription/blocked` — página Inertia de cuenta bloqueada (`Subscription/Blocked`); los usuarios con org suspendida/expirada son redirigidos aquí por el middleware `subscription` (exento: Super Admin, logout, `profile.*`); trial vencido se detecta en-request
- `/admin/organizations` (+ `PATCH .../suspend|activate|plan`) — consola Super Admin bajo `prefix('admin')` + `role:Super Admin`; lista orgs con `users_count`, estado efectivo, plan y `usage_this_month`; suspend/activate/setPlan auditados con activity log estampado con `organization_id`
- Translation: `GET /tasks/translate-candidates` (registered **before** `GET /tasks/{video_task}`, `can:view planning`), `POST /tasks/translate` (fill fields, `can:generate ai` + throttle), `POST /tasks/{id}/translate` and `POST /tasks/{id}/translate-content` (`can:create planning` + `can:generate ai` + throttle)
- `/company/*` CRUD + channel/invite sub-routes
- `/facebook` — Facebook hub page (page info, insights chart with `?days=7|28|90` range (invalid → 28) + previous-period comparison (delta % + dashed lines), publication health card, top posts, recent feed with engagement totals, connect/disconnect), `can:view empresa`; clicking a post (feed card or top-posts row) opens `Components/Modals/PostPreviewModal.vue` — a native Facebook-style mockup (avatar/page name/time + globe, linkified text with blue hashtags, image **or playable video** — feed requests `attachments{media{source},media_type}` and the modal embeds `video_url` with controls, poster = `full_picture`, play overlay when the source is missing —, like/comment counts, Me gusta/Comentar/Compartir row); the old image lightbox was removed. Sidebar item uses the same permission (no `view facebook` perm exists). Insights use only metrics alive after Meta's June 2026 Page Insights deprecation (`page_views_total`, `page_post_engagements`, `page_video_views` — legacy ones like `page_impressions`/`page_engaged_users`/`page_fan_adds` now return "(#100) The value must be a valid insights metric"); on failure the UI shows the raw Graph error
- `/company/facebook/connect|disconnect` — manual token paste (no OAuth redirect flow), `can:edit empresa`; accepts a **User Access Token** (exchanged server-side to a 60-day token when App ID/Secret are configured — flash reports the expiry date) or a legacy Page Access Token (~1h, flash warns); validated via `me/accounts`/`me?fields=id,name,picture{url},followers_count,fan_count`
- `/facebook/comments` — comment inbox of latest posts (todos/sin responder filters, reply form, hide toggle + delete behind `ConfirmDelete`), `can:view empresa`; `POST /facebook/comments/reply|/facebook/comments/hide|/facebook/comments/delete` (`can:edit empresa`, throttle 10/min each, `comment_id` regex-validated `[A-Za-z0-9._-]{1,64}`, flash success/error with raw Graph message)
- `/facebook/messages` — Messenger inbox (`platform=messenger` filter; conversation list + chat thread selected via `?conversation={conv-id}`, regex-validated `[A-Za-z0-9._-]{1,128}`), `can:view empresa`; `POST /facebook/messages/send` (`can:edit empresa`, throttle 10/min, text ≤1000 chars, flash success/error with raw Graph message; requires the `pages_messaging` token permission)
- `/facebook/publish` — publish a post now from the hub's "Publicar" modal (message required ≤10000, optional `link`/`photo` URLs), `can:edit empresa`, throttle 10/min, flash success/error with raw Graph message; requires `pages_manage_posts` on the token
- `/facebook/posts/update|delete` — per-post actions from the hub feed cards (edit modal with message + optional link replace; delete behind `ConfirmDelete`), `can:edit empresa`, throttle 10/min each, `post_id` regex-validated `[A-Za-z0-9._-]{1,64}`, flash success/error with raw Graph message
- `/instagram` — Instagram hub (account card, insights chart with `?days=7|28|90` + previous-period comparison, media grid with type badges/top-3/health card, publish modal, delete behind `ConfirmDelete`), `can:view empresa`; clicking a media card (grid or top-3 row) opens `PostPreviewModal` — a native Instagram-style mockup (gradient-ring avatar, username, uppercase date, media on black — VIDEO/REELS embedded with native controls, heart/comment/send + bookmark row, likes, linkified caption, "Ver todos los comentarios"); the old image lightbox was removed. Sidebar item same permission. Rides on the Facebook connection — states: no FB page (CTA to `/facebook`), page without linked IG (linking steps + refresh), Graph error (raw error + copyable OAuth URL with `instagram_*` scopes). Insights try progressive metric sets and map `follower_count` to the latest value (level metric). Videos poll the container `status_code` (FINISHED/ERROR) before `media_publish`; caption edit is NOT supported by the IG API (no edit button)
- `/instagram/comments` — comment inbox of latest media (todos/sin responder filters, reply form "respondes como @username", heart like button per comment (session-local state — the API has no liked-by-you field), hide toggle + delete behind `ConfirmDelete`), `can:view empresa`; `POST /instagram/comments/reply|/hide|/delete|/like` (`can:edit empresa`, throttle 10/min, `comment_id` regex-validated `[A-Za-z0-9._-]{1,64}`, flash success/error with raw Graph message)
- `/instagram/messages` — Instagram DM inbox (`platform=instagram` filter, same conversation/thread/send flow as Messenger via `MessagesService`; no sidebar item — entered from the Instagram hub header with a "Próximamente" pill), `can:view empresa`; `POST /instagram/messages/send` (`can:edit empresa`, throttle 10/min, text ≤1000 chars, flash success/error with raw Graph message; requires `instagram_manage_messages` + the account's "connected tools" toggle). **Status: "En implementación"** — the page shows a badge and, on read failure, an "en implementación" notice explaining that Meta requires App Review/Advanced Access of `instagram_manage_messages` (dev-mode subcode 2534084: conversations from users without an app role make the listing time out); the raw Graph `error_message` is collapsed under "Detalle técnico"
- `/instagram/publish` — publish now (caption ≤2200 optional; `image_url`/`video_url` required_without each other; `reel` flag → `media_type=REELS`), `can:edit empresa`, throttle 10/min; requires `instagram_content_publish`
- `/instagram/media/delete` — delete own media, `can:edit empresa`, throttle 10/min, `media_id` regex-validated, flash success/error
- `/youtube/comments` inbox of latest YouTube comment threads, last 7 days only (`can:view youtube`, API key only, 120s per-user cache)
- `/vacations/*` and `/time-off/*` under `can:view planning`

---

## Frontend Component Conventions

### Layout
- `AppLayout.vue` — provides `sidebarCollapsed`, `toggleSidebar`, `mobileSidebarOpen`, `closeMobileSidebar` via `provide()`; child components (Sidebar/SidebarItem) use `inject()`. Dark mode lives in the `useTheme` composable (used directly by Topbar).
- Sidebar width: `w-64` expanded, `w-16` collapsed. Main content margin adjusts via `lg:ml-64` / `lg:ml-16`.
- Topbar is fixed. Main has `pt-[88px] sm:pt-[112px]`.

### StatCard
Props: `title` (String), `value` (String|Number), `color` (String, optional). When color is set: 3px top border + colored value text.

### Pagination
Used by users, roles, task history. Laravel pagination links rendered as Vue components.

### SearchInput
Props: `modelValue` (String). Emits `update:modelValue`. Placeholder "Buscar...". Width `w-full md:w-80`. Debounce is handled by the parent page via `useDebounce(search, cb)` (400ms).

### Shared Composables (`resources/js/Composables/`)
- `usePermissions()` — `{ can }` for permission checks (canonical; replaces per-page `const can` copies). Exception: `AI/Index.vue` keeps its local copy (part of the untouchable `/ai` block).
- `useDebounce(source, cb, delay=400)` — watch with debounce (search filters).
- `useCopyFeedback()` — `{ copiedKey, copy(text, key) }` clipboard + 1500ms check feedback.
- `useAbsenceActions(baseUrl)` — approve/reject/delete confirm flow for `/vacations` and `/time-off` pages.
- `useTimeAgo()` — `{ timeAgo(iso) }` relative time in ES ("hace 3 h", "ayer") with formatted fallback; used by the Facebook hub, comments inbox, messages inbox and Instagram hub/comments.
- `useFormat()` — `{ formatDate, formatDateTime, formatShortDate, formatChartDate }` unified es-PE date helpers; replaces the per-page `formatDate` copies (AI history, FB/IG hubs + chart labels, YT comments/index, VideoTasks/Show, Roles/Edit, PostPreviewModal). Do NOT use it in `usePlanning.js` (its `formatDate` is a key for API calls, not display).
- `useModalA11y()` — Escape + focus trap + focus restore for modals; used by Modal, AlertModal, ConfirmDelete and PostPreviewModal (role=dialog/alertdialog + aria-labelledby).
- `useTheme()` — dark mode.

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
Every query on scoped models auto-filters by `organization_id`. For Super Admin switching companies, this uses `session('active_company_id')`. The scope is **fail-closed**: authenticated non–Super Admin users without an org match nothing; unauthenticated context (console/seed/queue) is unfiltered. To query across orgs, use `->withoutGlobalScope('organization')`.

### Activity Log
Only logs changes (dirty), not full snapshots. Uses `logOnlyDirty()`. The `activity_log` table has no `organization_id` column — don't scope activity queries by org.

### Topbar Memory Leak
Topbar listens for `scroll` event on `window`. Must call `removeEventListener('scroll', handler)` in `onUnmounted`.

### VideoTaskForm Block Selection
When URL params set `time_range` and that block is occupied, the form auto-selects the first free block instead. The watch fetches occupied blocks after mount and re-evaluates.

### Task Language (multi-language channels)
Channels have `locales` (JSON array, one or many) and each task stores its own `video_tasks.locale`, which must belong to `channel.locales`. `VideoTaskController::resolveTaskLocale()` validates/default this on store/update; `translateTask` rejects a target channel that doesn't support the chosen locale with 422. Sessions inherit the task's locale.

### CSP & Media (SecurityHeaders middleware)
`app/Http/Middleware/SecurityHeaders.php` sets the CSP on every response. It now includes `media-src 'self' data: https:` — without it, `<video>`/`<audio>` from external CDNs (fbcdn/instagram) are blocked by the browser with the misleading error `MEDIA_ELEMENT_ERROR: Media load rejected by URL safety check` (Blink's `IsSafeToLoadURL` → CSP path; the URL itself may return HTTP 200). `img-src` already allowed `https:`; media-src mirrors it. Vite dev sources are appended only in `local` + `public/hot`.

### Graph API attachments (2026)
`attachments{type}` and `attachments{media{type}}` no longer exist ("Tried accessing nonexisting field (type)"). Use `attachments{media{source},media_type}` — `media_type === 'video'` identifies video posts/reels and `media.source` is a directly playable mp4 URL (used by `PageService::mapPosts` → `is_video`/`video_url`, embedded in `PostPreviewModal`).

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
php artisan tasks:publish-scheduled  # Auto-publish "scheduled" tasks already live on YouTube (also scheduled every 15 min)
./vendor/bin/pint                     # PHP formatting
php artisan test                      # PHPUnit
composer dev                          # Serve + queue + logs + Vite
```
