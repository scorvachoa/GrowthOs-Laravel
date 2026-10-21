<script setup>
import { ShieldAlert } from 'lucide-vue-next'
import { useErrorModal } from '@/Composables/useErrorModal'

const { state, close } = useErrorModal()
</script>

<template>
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="state.show"
                class="fixed inset-0 z-[70] flex items-center justify-center bg-black/50 p-4"
                @click.self="close">
                <div role="dialog" aria-modal="true" aria-labelledby="error-modal-title"
                    class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-md p-6 mx-4">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="p-3 rounded-xl bg-red-100 dark:bg-red-900">
                            <ShieldAlert class="w-5 h-5 text-red-600 dark:text-red-400" />
                        </div>
                        <h3 id="error-modal-title" class="text-lg font-bold text-gray-900 dark:text-white">
                            {{ state.title }}
                        </h3>
                    </div>
                    <p class="text-sm text-gray-700 dark:text-gray-300 mb-6 whitespace-pre-line">{{ state.message }}</p>
                    <div class="flex justify-end">
                        <button @click="close"
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
