<script setup>
import { computed, inject, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { X, ChevronLeft, ChevronRight } from 'lucide-vue-next'

import navigation from '@/config/navigation'
import SidebarItem from './SidebarItem.vue'

const page = usePage()
const appName = import.meta.env.VITE_APP_NAME || 'GrowthOS'
const collapsed = inject('sidebarCollapsed')
const toggle = inject('toggleSidebar')
const mobileOpen = inject('mobileSidebarOpen')
const closeMobile = inject('closeMobileSidebar')

const EXPANDED_KEY = 'sidebar_expanded'

const loadExpanded = () => {
    try {
        return new Set(JSON.parse(localStorage.getItem(EXPANDED_KEY) || '[]'))
    } catch {
        return new Set()
    }
}

const expandedRoutes = ref(loadExpanded())

const persistExpanded = () => {
    localStorage.setItem(EXPANDED_KEY, JSON.stringify([...expandedRoutes.value]))
}

const toggleExpanded = (route) => {
    if (expandedRoutes.value.has(route)) {
        expandedRoutes.value.delete(route)
    } else {
        // Acordeón: al abrir un grupo se cierran los demás
        expandedRoutes.value = new Set([route])
    }
    persistExpanded()
}

const items = computed(() => {
    const permissions = page.props.auth?.user?.permissions ?? []
    const permissionList = Array.isArray(permissions)
        ? permissions
        : Object.values(permissions)

    const roles = page.props.auth?.user?.roles ?? []
    const roleList = Array.isArray(roles) ? roles : Object.values(roles)

    const passes = entry => {
        const allowedByPermission = !entry.permission || permissionList.includes(entry.permission)
        const allowedByRole = !entry.roles || entry.roles.some(role => roleList.includes(role))
        return allowedByPermission && allowedByRole
    }

    // Un grupo con hijos solo es visible si el padre pasa su regla
    // y al menos uno de sus hijos es visible (p. ej. "Ausencias").
    return navigation
        .filter(item => {
            if (item.children?.length) {
                return passes(item) && item.children.some(passes)
            }
            return passes(item)
        })
        .map(item => ({
            ...item,
            children: item.children ? item.children.filter(passes) : undefined,
        }))
})

// Ruta activa: la entrada que coincide con el prefijo MÁS LARGO
// (incluye subentradas: en /youtube/comments se resalta el hijo "Comentarios").
const path = computed(() => {
    const clean = page.url.split('?')[0].replace(/\/+$/, '')
    return clean || '/'
})

const activeRoute = computed(() => {
    let best = null
    let bestLength = -1

    for (const item of items.value) {
        for (const route of [item.route, ...(item.children?.map(child => child.route) ?? [])]) {
            if (path.value === route || path.value.startsWith(route + '/')) {
                if (route.length > bestLength) {
                    best = route
                    bestLength = route.length
                }
            }
        }
    }

    return best
})

// Auto-expandir el grupo cuando su hijo es la ruta activa (acordeón: cierra los demás)
watch(activeRoute, (route) => {
    if (!route) return
    const parent = items.value.find(item => item.children?.some(child => child.route === route))
    if (parent && !expandedRoutes.value.has(parent.route)) {
        expandedRoutes.value = new Set([parent.route])
        persistExpanded()
    }
}, { immediate: true })

const isExpanded = (item) => expandedRoutes.value.has(item.route)
</script>

<template>
    <aside
        class="fixed left-0 top-0 h-screen bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800 z-50 flex flex-col transition-all duration-300"
        :class="[
            collapsed ? 'w-16' : 'w-64',
            'max-lg:fixed max-lg:z-50 max-lg:transition-transform max-lg:duration-300',
            mobileOpen ? 'max-lg:translate-x-0' : 'max-lg:-translate-x-full',
        ]">

        <div class="flex items-center h-[72px] sm:h-[88px] px-4 border-b border-gray-200 dark:border-gray-800 shrink-0">
            <h1 v-if="!collapsed" class="text-xl font-bold text-indigo-600 truncate flex-1">
                {{ appName }}
            </h1>
            <h1 v-else class="text-xl font-bold text-indigo-600 mx-auto">
                G
            </h1>
            <button @click="closeMobile"
                class="lg:hidden p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition ml-auto"
                :class="{ 'mx-auto': collapsed }">
                <X class="w-5 h-5 text-gray-500" />
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto p-3 space-y-1">
            <SidebarItem
                v-for="item in items"
                :key="item.title"
                :item="item"
                :active="item.route === activeRoute && !(item.children?.some(child => child.route === activeRoute) ?? false)"
                :active-route="activeRoute"
                :expanded="isExpanded(item)"
                @toggle="toggleExpanded(item.route)"
                @close="closeMobile"
            />
        </nav>

        <div class="border-t border-gray-200 dark:border-gray-800 p-3 shrink-0 max-lg:hidden">
            <button @click="toggle"
                class="w-full flex items-center justify-center p-2 rounded-xl text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition">
                <ChevronLeft v-if="!collapsed" class="w-5 h-5" />
                <ChevronRight v-else class="w-5 h-5" />
            </button>
        </div>

    </aside>
</template>