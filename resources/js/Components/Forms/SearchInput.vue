<script setup>
import { useAttrs } from 'vue'

defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Buscar...' },
})

defineEmits(['update:modelValue'])

defineOptions({ inheritAttrs: false })
const attrs = useAttrs()
</script>

<template>
    <input
        type="text"
        :value="modelValue"
        :placeholder="placeholder"
        v-bind="attrs"
        @input="$emit('update:modelValue', $event.target.value)"
        class="w-full md:w-80 rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
    >
</template>
