<script setup>
import { computed, inject } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { PanelLeftClose, PanelLeft, ChevronLeft, ChevronRight } from 'lucide-vue-next'

import navigation from '@/config/navigation'
import SidebarItem from './SidebarItem.vue'

const page = usePage()
const collapsed = inject('sidebarCollapsed')
const toggle = inject('toggleSidebar')

const items = computed(() => {
    const permissions = page.props.auth?.user?.permissions ?? []
    const permissionList = Array.isArray(permissions)
        ? permissions
        : Object.values(permissions)

    return navigation.filter(item =>
        !item.permission || permissionList.includes(item.permission)
    )
})
</script>

<template>
    <aside
        class="fixed left-0 top-0 h-screen bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800 z-30 flex flex-col transition-all duration-300"
        :class="collapsed ? 'w-16' : 'w-64'">

        <div class="flex items-center h-16 px-4 border-b border-gray-200 dark:border-gray-800 shrink-0">
            <h1 v-if="!collapsed" class="text-xl font-bold text-indigo-600 truncate">
                GrowthOS
            </h1>
            <h1 v-else class="text-xl font-bold text-indigo-600 mx-auto">
                G
            </h1>
        </div>

        <nav class="flex-1 overflow-y-auto p-3 space-y-1">
            <SidebarItem
                v-for="item in items"
                :key="item.title"
                :item="item"
            />
        </nav>

        <div class="border-t border-gray-200 dark:border-gray-800 p-3 shrink-0">
            <button @click="toggle"
                class="w-full flex items-center justify-center p-2 rounded-xl text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition">
                <ChevronLeft v-if="!collapsed" class="w-5 h-5" />
                <ChevronRight v-else class="w-5 h-5" />
            </button>
        </div>

    </aside>
</template>