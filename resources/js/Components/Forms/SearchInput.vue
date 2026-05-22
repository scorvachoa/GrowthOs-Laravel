<script setup>
defineProps({
    modelValue: String,
})

defineEmits(['update:modelValue'])
</script>

<template>
    <input
        type="text"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
        placeholder="Search..."
        class="w-full md:w-80 rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
    >
</template>