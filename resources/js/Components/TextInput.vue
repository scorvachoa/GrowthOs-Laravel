<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        class="rounded-xl border border-white/10 bg-white/5 text-white placeholder-gray-500 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 focus:bg-white/10 w-full px-4 py-2.5"
        v-model="model"
        ref="input"
    />
</template>
