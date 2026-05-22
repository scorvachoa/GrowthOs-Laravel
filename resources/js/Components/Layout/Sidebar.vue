<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { navigation } from '@/Services/navigation'

const page = usePage()

const hasPermission = (permission) => {
    if (!permission) return true

    return page.props.auth.user.permissions.includes(permission)
}
</script>

<template>
    <aside
        class="w-64 min-h-screen bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700"
    >
        <div class="p-6">
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">
                GrowthOS
            </h1>
        </div>

        <nav class="px-4 space-y-2">
            <template
                v-for="item in navigation"
                :key="item.href"
            >
                <Link
                    v-if="hasPermission(item.permission)"
                    :href="item.href"
                    class="block px-4 py-3 rounded-lg text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                >
                    {{ item.title }}
                </Link>
            </template>
        </nav>
    </aside>
</template>