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
} from 'lucide-vue-next'

export default [
    {
        title: 'Dashboard',
        icon: LayoutDashboard,
        route: '/dashboard',
        permission: 'view dashboard',
    },

    {
        title: 'AI Generator',
        icon: Sparkles,
        route: '/ai',
        permission: 'generate ai',
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
        title: 'YouTube',
        icon: Youtube,
        route: '/youtube',
        permission: 'view youtube',
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
]
