<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

import navigation from '@/config/navigation'
import SidebarItem from './SidebarItem.vue'

const page = usePage()

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
    <aside class="w-72 bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800 min-h-screen p-6">

        <div class="mb-10">

            <h1 class="text-2xl font-bold text-indigo-600">
                GrowthOS
            </h1>

        </div>

        <nav class="space-y-2">

            <SidebarItem
                v-for="item in items"
                :key="item.title"
                :item="item"
            />

        </nav>

    </aside>
</template>