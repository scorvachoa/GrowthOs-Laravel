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
    Umbrella,
    CalendarClock,
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
        title: 'AI Generator',
        icon: Sparkles,
        route: '/ai',
        permission: 'generate ai',
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
        title: 'Vacaciones',
        icon: Umbrella,
        route: '/vacations',
        permission: 'view vacations',
    },

    {
        title: 'Permisos',
        icon: CalendarClock,
        route: '/time-off',
        permission: 'view time off',
    },

    {
        title: 'Empresa',
        icon: Building2,
        route: '/company',
        permission: 'view empresa',
    },

    {
        title: 'Configuracion',
        icon: Settings,
        route: '/settings',
        permission: 'view configuracion',
    },

    {
        title: 'Manual',
        icon: BookOpen,
        route: '/manual',
    },
]
