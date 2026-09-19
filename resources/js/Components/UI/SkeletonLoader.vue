<script setup>
defineProps({
    rows: {
        type: Number,
        default: 3,
    },
    type: {
        type: String,
        default: 'text', // text, card, table, avatar
    },
})
</script>

<template>
    <div class="animate-pulse space-y-3">
        <!-- Text skeleton -->
        <template v-if="type === 'text'">
            <div v-for="i in rows" :key="i"
                class="h-4 bg-gray-200 dark:bg-gray-700 rounded"
                :class="{ 'w-3/4': i === rows }"
            />
        </template>

        <!-- Card skeleton -->
        <template v-else-if="type === 'card'">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6 space-y-4">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 bg-gray-200 dark:bg-gray-700 rounded-full" />
                    <div class="flex-1 space-y-2">
                        <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/3" />
                        <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-1/4" />
                    </div>
                </div>
                <div class="space-y-2">
                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-full" />
                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-5/6" />
                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-2/3" />
                </div>
            </div>
        </template>

        <!-- Table skeleton -->
        <template v-else-if="type === 'table'">
            <div class="space-y-2">
                <div class="h-10 bg-gray-200 dark:bg-gray-700 rounded" />
                <div v-for="i in rows" :key="i" class="h-12 bg-gray-100 dark:bg-gray-800 rounded" />
            </div>
        </template>

        <!-- Stats skeleton -->
        <template v-else-if="type === 'stats'">
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                <div v-for="i in 5" :key="i"
                    class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-5"
                >
                    <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-1/2 mb-3" />
                    <div class="h-7 bg-gray-200 dark:bg-gray-700 rounded w-1/3" />
                </div>
            </div>
        </template>

        <!-- Calendar skeleton -->
        <template v-else-if="type === 'calendar'">
            <div class="grid grid-cols-7 gap-2">
                <div v-for="i in 35" :key="i"
                    class="h-20 bg-gray-100 dark:bg-gray-800 rounded-lg"
                />
            </div>
        </template>

        <!-- Avatar + lines -->
        <template v-else-if="type === 'avatar'">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-gray-200 dark:bg-gray-700 rounded-full" />
                <div class="flex-1 space-y-2">
                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/2" />
                    <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-1/3" />
                </div>
            </div>
        </template>

        <!-- Generic block -->
        <template v-else>
            <div v-for="i in rows" :key="i"
                class="h-20 bg-gray-100 dark:bg-gray-800 rounded-lg"
            />
        </template>
    </div>
</template>
