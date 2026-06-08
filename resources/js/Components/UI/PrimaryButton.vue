<script setup>
defineProps({
    disabled: Boolean,
})
</script>

<template>
    <button
        :disabled="disabled"
        class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-medium transition disabled:opacity-50"
    >
        <slot />
    </button>
</template>