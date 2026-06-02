<script setup>
import { Link, usePage } from '@inertiajs/vue3'

const props = defineProps({
    item: Object,
})

const page = usePage()

const isActive = (route) => {
    return page.url.startsWith(route)
}
</script>

<template>
    <Link
        :href="item.route"
        class="flex items-center gap-3 px-4 py-3 rounded-xl transition"

        :class="[
            isActive(item.route)
                ? 'bg-indigo-600 text-white'
                : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800'
        ]"
    >

        <component
            :is="item.icon"
            class="w-5 h-5"
        />

        <span class="font-medium">
            {{ item.title }}
        </span>

    </Link>
</template>