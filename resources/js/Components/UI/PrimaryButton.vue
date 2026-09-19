<script setup>
defineProps({
    disabled: Boolean,
})
</script>

<template>
    <button
        :disabled="disabled"
        class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-medium transition focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-gray-900 disabled:opacity-50"
    >
        <slot />
    </button>
</template>