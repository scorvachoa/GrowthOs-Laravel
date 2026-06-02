<script setup>
defineProps({
    message: String,
    type: {
        type: String,
        default: 'success',
    },
})
</script>

<template>
    <div
        class="min-w-[300px] rounded-2xl shadow-xl p-4 text-white"

        :class="{
            'bg-green-600': type === 'success',
            'bg-red-600': type === 'error',
            'bg-blue-600': type === 'info',
        }"
    >
        <p class="font-medium">
            {{ message }}
        </p>
    </div>
</template>