<script setup>
defineProps({
    title: String,
    value: [String, Number],
    color: { type: String, default: null },
})
</script>

<template>
    <div class="bg-white dark:bg-gray-900 rounded-2xl p-6 shadow-sm border border-gray-100 dark:border-gray-800"
        :class="{ 'border-t-[3px]': color }"
        :style="color ? { borderTopColor: color } : {}">

        <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">
            {{ title }}
        </p>

        <h2 class="text-3xl font-bold"
            :class="color ? '' : 'text-gray-900 dark:text-white'"
            :style="color ? { color } : {}">
            {{ value }}
        </h2>

    </div>
</template>