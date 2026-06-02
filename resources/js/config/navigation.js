import {
    LayoutDashboard,
    Users,
    Shield,
    ClipboardList,
    Video,
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
        title: 'Task Reports',
        icon: ClipboardList,
        route: '/task-reports',
        permission: 'view reports',
    },

    {
        title: 'Video Tasks',
        icon: Video,
        route: '/video-tasks',
        permission: 'view video tasks',
    },
]