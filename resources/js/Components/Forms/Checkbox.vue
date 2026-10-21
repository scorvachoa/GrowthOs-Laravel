<script setup>
import { computed } from 'vue'

const props = defineProps({
    modelValue: { type: [Boolean, Array], default: false },
    value: { type: [String, Number], default: null },
    label: { type: String, default: null },
    id: { type: String, default: null },
    disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const inputId = computed(() => props.id || `checkbox-${Math.random().toString(36).slice(2, 9)}`)

// Soporta v-model boolean (toggle) y v-model array (selección múltiple con :value).
const checked = computed({
    get() {
        if (Array.isArray(props.modelValue)) {
            return props.modelValue.includes(props.value)
        }
        return Boolean(props.modelValue)
    },
    set(checkedValue) {
        if (Array.isArray(props.modelValue)) {
            const arr = [...props.modelValue]
            const index = arr.indexOf(props.value)
            if (checkedValue && index === -1) {
                arr.push(props.value)
            } else if (!checkedValue && index !== -1) {
                arr.splice(index, 1)
            }
            emit('update:modelValue', arr)
            return
        }
        emit('update:modelValue', checkedValue)
    },
})
</script>

<template>
    <label :for="inputId" class="inline-flex items-center gap-2 cursor-pointer select-none">
        <input
            :id="inputId"
            v-model="checked"
            type="checkbox"
            :disabled="disabled"
            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:focus:ring-indigo-600 dark:ring-offset-gray-800"
        >
        <span v-if="label" class="text-sm text-gray-700 dark:text-gray-300">{{ label }}</span>
        <slot />
    </label>
</template>
