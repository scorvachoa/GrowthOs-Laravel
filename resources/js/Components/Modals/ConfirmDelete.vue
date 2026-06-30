<script setup>
import { Trash2 } from 'lucide-vue-next'

defineProps({
    show: Boolean,
    title: { type: String, default: 'Eliminar' },
    message: { type: String, default: null },
})

const emit = defineEmits(['confirm', 'close'])
</script>

<template>
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="emit('close')">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-md p-6 mx-4">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="p-3 rounded-xl bg-red-100 dark:bg-red-900">
                            <Trash2 class="w-5 h-5 text-red-600 dark:text-red-400" />
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ title }}</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Esta acción no se puede deshacer</p>
                        </div>
                    </div>
                    <p v-if="message" class="text-sm text-gray-700 dark:text-gray-300 mb-6 px-2">{{ message }}</p>
                    <div class="flex justify-end gap-3">
                        <button @click="emit('close')"
                            class="px-4 py-2 rounded-xl border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            Cancelar
                        </button>
                        <button @click="emit('confirm')"
                            class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white text-sm font-medium flex items-center gap-2 transition">
                            <Trash2 class="w-4 h-4" /> Eliminar
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity .2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
