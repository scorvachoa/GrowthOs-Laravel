<script setup>
defineProps({
    title: String,
    value: [String, Number],
})
</script>

<template>
    <div
        class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-6"
    >
        <h3 class="text-gray-500 dark:text-gray-400 text-sm">
            {{ title }}
        </h3>

        <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
            {{ value }}
        </p>
    </div>
</template>