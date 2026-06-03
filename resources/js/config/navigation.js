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
        permission: null,
    },

    {
        title: 'Users',
        icon: Users,
        route: '/users',
        permission: 'manage users',
    },

    {
        title: 'Roles',
        icon: Shield,
        route: '/roles',
        permission: 'manage roles',
    },

    {
        title: 'Planificacion',
        icon: CalendarDays,
        route: '/planning',
        permission: 'manage tasks',
    },

    {
        title: 'Tareas',
        icon: ClipboardList,
        route: '/task-history',
        permission: 'manage tasks',
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
        title: 'Ideas',
        icon: Lightbulb,
        route: '/ideas',
        permission: 'manage tasks',
    },

    {
        title: 'Empresa',
        icon: Settings,
        route: '/settings',
        permission: 'manage tasks',
    },
]
