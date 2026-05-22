<script setup>
defineProps({
    modelValue: String,
    label: String,
    type: {
        type: String,
        default: 'text',
    },
    error: String,
})

defineEmits(['update:modelValue'])
</script>

<template>
    <div>
        <label
            class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300"
        >
            {{ label }}
        </label>

        <input
            :type="type"
            :value="modelValue"
            @input="$emit('update:modelValue', $event.target.value)"
            class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500"
        >

        <div
            v-if="error"
            class="mt-1 text-sm text-red-500"
        >
            {{ error }}
        </div>
    </div>
</template>