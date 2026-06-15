<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'

const props = defineProps({
    links: Array,
})

const links = computed(() => props.links || [])
const len = computed(() => links.value.length)
</script>

<template>
    <div class="flex items-center justify-center mt-8 gap-1.5 flex-wrap">

        <template
            v-for="(link, i) in links"
            :key="link.label"
        >

            <Link
                v-if="i === 0 && link.url"
                :href="link.url"
                class="px-3 py-2 rounded-xl border text-sm transition bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700"
            >
                <ChevronLeft class="w-4 h-4" />
            </Link>

            <span
                v-else-if="i === 0"
                class="px-3 py-2 rounded-xl border border-gray-200 text-gray-300 dark:text-gray-600 text-sm cursor-not-allowed"
            >
                <ChevronLeft class="w-4 h-4" />
            </span>

            <Link
                v-else-if="i === len - 1 && link.url"
                :href="link.url"
                class="px-3 py-2 rounded-xl border text-sm transition bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700"
            >
                <ChevronRight class="w-4 h-4" />
            </Link>

            <span
                v-else-if="i === len - 1"
                class="px-3 py-2 rounded-xl border border-gray-200 text-gray-300 dark:text-gray-600 text-sm cursor-not-allowed"
            >
                <ChevronRight class="w-4 h-4" />
            </span>

            <Link
                v-else-if="link.url"
                :href="link.url"
                class="px-3.5 py-2 rounded-xl border text-sm transition font-medium min-w-[36px] text-center"
                :class="[
                    link.active
                        ? 'bg-indigo-600 text-white border-indigo-600'
                        : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700'
                ]"
                v-text="link.label"
            />

            <span
                v-else
                class="px-3.5 py-2 rounded-xl border border-gray-200 text-gray-400 text-sm min-w-[36px] text-center"
                v-text="link.label"
            />

        </template>

    </div>
</template>