<script setup>
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
})
</script>

<template>
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">
                <slot name="title" :title="title">{{ title }}</slot>
            </h1>
            <p v-if="subtitle || $slots.subtitle" class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                <slot name="subtitle">{{ subtitle }}</slot>
            </p>
        </div>
        <div v-if="$slots.default" class="flex items-center gap-3">
            <slot />
        </div>
    </div>
</template>
