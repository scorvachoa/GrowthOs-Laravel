import {
    LayoutDashboard,
    Users,
    Shield,
    ClipboardList,
    CalendarDays,
    FileClock,
    Settings,
} from 'lucide-vue-next'

export default [
    {
        title: 'Dashboard',
        icon: LayoutDashboard,
        route: '/dashboard',
        permission: 'view dashboard',
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
        title: 'Reportes',
        icon: ClipboardList,
        route: '/task-reports',
        permission: 'view reports',
    },

    {
        title: 'Historial',
        icon: FileClock,
        route: '/report-history',
        permission: 'view reports',
    },

    {
        title: 'Empresa',
        icon: Settings,
        route: '/settings',
        permission: 'manage tasks',
    },
]
