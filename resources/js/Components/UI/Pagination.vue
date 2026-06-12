<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    links: Array,
})
</script>

<template>
    <div class="flex items-center justify-center mt-8 gap-2 flex-wrap">

        <template
            v-for="link in links || []"
            :key="link.label"
        >

            <Link
                v-if="link.url"
                :href="link.url"
                class="px-4 py-2 rounded-xl border text-sm transition"
                :class="[
                    link.active
                        ? 'bg-indigo-600 text-white border-indigo-600'
                        : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700'
                ]"
                v-text="link.label"
            />

            <span
                v-else
                class="px-4 py-2 rounded-xl border border-gray-200 text-gray-400 text-sm"
                v-text="link.label"
            />

        </template>

    </div>
</template>