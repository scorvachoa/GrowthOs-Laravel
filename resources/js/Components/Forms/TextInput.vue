<script setup>
import { computed, useAttrs } from 'vue'

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    label: { type: String, default: null },
    type: {
        type: String,
        default: 'text',
    },
    placeholder: { type: String, default: null },
    error: String,
    id: String,
})

defineEmits(['update:modelValue'])

// placeholder/required/min/@blur/@change... viajan al <input>, no al wrapper.
defineOptions({ inheritAttrs: false })

const attrs = useAttrs()

const inputId = computed(() => props.id || `input-${Math.random().toString(36).slice(2, 9)}`)
</script>

<template>
    <div>
        <label
            v-if="label"
            :for="inputId"
            class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300"
        >
            {{ label }}
        </label>

        <input
            :id="inputId"
            :type="type"
            :value="modelValue"
            :placeholder="placeholder"
            v-bind="attrs"
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
