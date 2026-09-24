<script setup>
import { ref, watch } from 'vue'
import { Link2 } from 'lucide-vue-next'
import Modal from '@/Components/Modals/Modal.vue'

const props = defineProps({
    show: Boolean,
    task: { type: Object, default: null },
})

const emit = defineEmits(['close', 'confirm'])

const url = ref('')
const error = ref('')

watch(() => props.show, (visible) => {
    if (visible) {
        url.value = props.task?.youtube_url || ''
        error.value = ''
    }
})

function submit() {
    const value = url.value.trim()
    if (!value) {
        error.value = 'Ingresa el enlace del video.'
        return
    }
    if (!/^https?:\/\/.+/i.test(value)) {
        error.value = 'Ingresa una URL válida (https://...).'
        return
    }
    emit('confirm', value)
}
</script>

<template>
    <Modal :show="show" @close="emit('close')">
        <div class="flex items-center gap-3 mb-4">
            <div class="p-3 rounded-xl bg-green-100 dark:bg-green-900">
                <Link2 class="w-5 h-5 text-green-600 dark:text-green-400" />
            </div>
            <div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">Enlace del video</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">Añade el enlace al publicar o completar</p>
            </div>
        </div>
        <p v-if="task?.title" class="text-sm text-gray-700 dark:text-gray-300 mb-3 truncate">
            {{ task.title }}
        </p>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">URL del video</label>
        <input
            v-model="url"
            type="url"
            placeholder="https://youtube.com/..."
            autofocus
            class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white text-sm"
            :class="error ? 'border-red-400' : ''"
            @input="error = ''"
            @keydown.enter.prevent="submit"
        />
        <p v-if="error" class="text-xs text-red-500 mt-1">{{ error }}</p>
        <div class="flex justify-end gap-3 mt-5">
            <button
                type="button"
                class="px-4 py-2 rounded-xl border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition"
                @click="emit('close')"
            >
                Cancelar
            </button>
            <button
                type="button"
                class="px-4 py-2 rounded-xl bg-green-600 hover:bg-green-700 text-white text-sm font-medium transition"
                @click="submit"
            >
                Completar
            </button>
        </div>
    </Modal>
</template>
