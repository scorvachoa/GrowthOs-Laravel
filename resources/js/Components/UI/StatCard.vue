<script setup>
defineProps({
    title: String,
    value: [String, Number],
})
</script>

<template>
    <div class="bg-white dark:bg-gray-900 rounded-2xl p-6 shadow-sm border border-gray-100 dark:border-gray-800">

        <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">
            {{ title }}
        </p>

        <h2 class="text-3xl font-bold text-gray-900 dark:text-white">
            {{ value }}
        </h2>

    </div>
</template>