import {
    LayoutDashboard,
    Users,
    Shield,
    CalendarDays,
    FileClock,
    Settings,
    Youtube,
    Facebook,
    Instagram,
    Lightbulb,
    Sparkles,
    Building2,
    BookOpen,
    Umbrella,
    Gauge,
} from 'lucide-vue-next'

export default [
    {
        title: 'Panel',
        icon: LayoutDashboard,
        route: '/dashboard',
        permission: 'view dashboard',
    },

    {
        title: 'Planificación',
        icon: CalendarDays,
        route: '/planning',
        permission: 'view planning',
    },

    {
        title: 'YouTube',
        icon: Youtube,
        route: '/youtube',
        permission: 'view youtube',
        children: [
            { title: 'Comentarios', route: '/youtube/comments', permission: 'view youtube' },
        ],
    },

    {
        title: 'Facebook',
        icon: Facebook,
        route: '/facebook',
        permission: 'view empresa',
        children: [
            { title: 'Mensajes', route: '/facebook/messages', permission: 'view empresa' },
            { title: 'Comentarios', route: '/facebook/comments', permission: 'view empresa' },
        ],
    },

    {
        title: 'Instagram',
        icon: Instagram,
        route: '/instagram',
        permission: 'view empresa',
        children: [
            { title: 'Mensajes', route: '/instagram/messages', permission: 'view empresa' },
            { title: 'Comentarios', route: '/instagram/comments', permission: 'view empresa' },
        ],
    },

    {
        title: 'Ideas',
        icon: Lightbulb,
        route: '/ideas',
        permission: 'view ideas',
    },

    {
        title: 'Generador IA',
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
        title: 'Usuarios',
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
        title: 'Ausencias',
        icon: Umbrella,
        route: '/vacations',
        children: [
            { title: 'Vacaciones', route: '/vacations', permission: 'view vacations' },
            { title: 'Permisos', route: '/time-off', permission: 'view time off' },
        ],
    },

    {
        title: 'Empresa',
        icon: Building2,
        route: '/company',
        permission: 'view empresa',
    },

    {
        title: 'Consola',
        icon: Gauge,
        route: '/admin/organizations',
        roles: ['Super Admin'],
    },

    {
        title: 'Configuración',
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
