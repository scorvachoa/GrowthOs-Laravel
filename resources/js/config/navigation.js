import {
    LayoutDashboard,
    Users,
    Shield,
    CalendarDays,
    FileClock,
    Settings,
    Youtube,
    Lightbulb,
    ClipboardList,
    Sparkles,
    Building2,
    BookOpen,
} from 'lucide-vue-next'

export default [
    {
        title: 'Dashboard',
        icon: LayoutDashboard,
        route: '/dashboard',
        permission: 'view dashboard',
    },

    {
        title: 'Planificacion',
        icon: CalendarDays,
        route: '/planning',
        permission: 'view planning',
    },

    {
        title: 'Tareas',
        icon: ClipboardList,
        route: '/task-history',
        permission: 'view tasks',
    },

    {
        title: 'AI Generator',
        icon: Sparkles,
        route: '/ai',
        permission: 'generate ai',
    },

    {
        title: 'YouTube',
        icon: Youtube,
        route: '/youtube',
        permission: 'view youtube',
    },

    {
        title: 'Ideas',
        icon: Lightbulb,
        route: '/ideas',
        permission: 'view ideas',
    },

    {
        title: 'Historial',
        icon: FileClock,
        route: '/report-history',
        permission: 'view reports',
    },

    {
        title: 'Users',
        icon: Users,
        route: '/users',
        permission: 'view users',
    },

    {
        title: 'Roles',
        icon: Shield,
        route: '/roles',
        permission: 'view roles',
    },

    {
        title: 'Configuracion',
        icon: Settings,
        route: '/settings',
        permission: 'view configuracion',
    },

    {
        title: 'Empresa',
        icon: Building2,
        route: '/company',
        permission: 'view empresa',
    },

    {
        title: 'Manual',
        icon: BookOpen,
        route: '/manual',
    },
]
