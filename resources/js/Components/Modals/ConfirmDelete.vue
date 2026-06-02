<script setup>
import Modal from './Modal.vue'

defineProps({
    show: Boolean,
})

const emit = defineEmits([
    'confirm',
    'close',
])
</script>

<template>
    <Modal :show="show">

        <h2 class="text-xl font-bold mb-4 text-gray-900 dark:text-white">
            Confirm Delete
        </h2>

        <p class="text-gray-600 dark:text-gray-300 mb-6">
            This action cannot be undone.
        </p>

        <div class="flex justify-end gap-3">

            <button
                @click="emit('close')"
                class="px-4 py-2 rounded-xl border"
            >
                Cancel
            </button>

            <button
                @click="emit('confirm')"
                class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white"
            >
                Delete
            </button>

        </div>

    </Modal>
</template>