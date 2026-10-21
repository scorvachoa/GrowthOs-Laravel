<script setup>
import { ref } from 'vue'
import { CircleAlert, Info } from 'lucide-vue-next'
import { useModalA11y } from '@/Composables/useModalA11y'

const props = defineProps({
    show: Boolean,
    title: { type: String, default: 'Error' },
    message: { type: String, default: '' },
    type: { type: String, default: 'error' },
})

const emit = defineEmits(['close'])

const dialogRef = ref(null)

useModalA11y(dialogRef, () => props.show, () => emit('close'))
</script>

<template>
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="show" class="fixed inset-0 z-[70] flex items-center justify-center bg-black/50" @click.self="emit('close')">
                <div
                    ref="dialogRef"
                    role="alertdialog"
                    aria-modal="true"
                    :aria-label="title"
                    tabindex="-1"
                    class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-md p-6 mx-4 outline-none"
                >
                    <div class="flex items-center gap-3 mb-4">
                        <div class="p-3 rounded-xl" :class="type === 'error' ? 'bg-red-100 dark:bg-red-900' : 'bg-indigo-100 dark:bg-indigo-900'">
                            <CircleAlert v-if="type === 'error'" class="w-5 h-5 text-red-600 dark:text-red-400" />
                            <Info v-else class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ title }}</h3>
                        </div>
                    </div>
                    <p class="text-sm text-gray-700 dark:text-gray-300 mb-6 px-2">{{ message }}</p>
                    <div class="flex justify-end">
                        <button @click="emit('close')"
                            class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium transition">
                            Entendido
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
