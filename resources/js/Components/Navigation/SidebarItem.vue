<script setup>
import { inject } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const props = defineProps({
    item: Object,
})

const page = usePage()
const collapsed = inject('sidebarCollapsed')

const isActive = (route) => {
    return page.url.startsWith(route)
}
</script>

<template>
    <Link
        :href="item.route"
        class="flex items-center rounded-xl transition"
        :class="[
            collapsed
                ? 'justify-center p-3'
                : 'gap-3 px-4 py-3',
            isActive(item.route)
                ? 'bg-indigo-600 text-white'
                : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800'
        ]"
        :title="collapsed ? item.title : undefined"
    >

        <component
            :is="item.icon"
            class="w-5 h-5 shrink-0"
        />

        <span v-if="!collapsed" class="font-medium truncate">
            {{ item.title }}
        </span>

    </Link>
</template>