<script setup>
import { ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { ShieldAlert, X } from 'lucide-vue-next'

const page = usePage()
const show = ref(false)
const message = ref('')

watch(() => page.props.flash?.error, (msg) => {
    if (msg) {
        message.value = msg
        show.value = true
    }
})

const close = () => {
    show.value = false
    message.value = ''
}
</script>

<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" @click.self="close">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-sm p-8 text-center relative">
                    <button @click="close" class="absolute top-3 right-3 p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                        <X class="w-4 h-4 text-gray-500 dark:text-gray-400" />
                    </button>

                    <div class="w-16 h-16 rounded-2xl bg-red-100 dark:bg-red-900/30 flex items-center justify-center mx-auto mb-4">
                        <ShieldAlert class="w-8 h-8 text-red-500 dark:text-red-400" />
                    </div>

                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Acceso denegado</h3>

                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-6 leading-relaxed">
                        {{ message }}
                    </p>

                    <button @click="close"
                        class="w-full px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-medium transition text-sm">
                        Entendido
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.modal-enter-active, .modal-leave-active {
    transition: all 0.25s ease;
}
.modal-enter-from, .modal-leave-to {
    opacity: 0;
}
.modal-enter-from .bg-white, .modal-leave-to .bg-white {
    transform: scale(0.95);
}
</style>
