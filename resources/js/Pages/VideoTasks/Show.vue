<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
    task: Object,
    statuses: Array,
})

const statusLabel = (value) => {
    const found = props.statuses.find(s => s.value === value)
    return found ? found.label : value
}

const statusColor = (value) => {
    const colors = {
        pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        script_ready: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        editing: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
        review: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
        scheduled: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
        published: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    }
    return colors[value] || 'bg-gray-100 text-gray-800'
}

const showDeleteModal = ref(false)
const confirmDelete = () => {
    router.delete(route('video-tasks.destroy', props.task.id), {
        onSuccess: () => showDeleteModal.value = false,
    })
}
</script>

<template>
    <AppLayout>
        <div class="max-w-3xl mx-auto">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Detalle de Tarea</h1>
                <Link href="/planning" class="text-indigo-600 hover:text-indigo-700">Volver al calendario</Link>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-6 space-y-6">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ task.title }}</h2>
                        <p class="text-sm text-gray-500 mt-1">{{ task.task_date }} &middot; {{ task.time_range }}</p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-sm font-medium" :class="statusColor(task.status)">
                        {{ statusLabel(task.status) }}
                    </span>
                </div>

                <div v-if="task.script" class="border-t border-gray-200 dark:border-gray-700 pt-4">
                    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Guion</h3>
                    <p class="text-gray-900 dark:text-white whitespace-pre-wrap">{{ task.script }}</p>
                </div>

                <div v-if="task.copy" class="border-t border-gray-200 dark:border-gray-700 pt-4">
                    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Copy / Descripcion</h3>
                    <p class="text-gray-900 dark:text-white whitespace-pre-wrap">{{ task.copy }}</p>
                </div>

                <div v-if="task.key_phrases" class="border-t border-gray-200 dark:border-gray-700 pt-4">
                    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Frases clave</h3>
                    <p class="text-gray-900 dark:text-white whitespace-pre-wrap">{{ task.key_phrases }}</p>
                </div>

                <div v-if="task.youtube_url" class="border-t border-gray-200 dark:border-gray-700 pt-4">
                    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">URL del video</h3>
                    <a :href="task.youtube_url" target="_blank" class="text-indigo-600 hover:text-indigo-700 break-all">
                        {{ task.youtube_url }}
                    </a>
                </div>

                <div class="border-t border-gray-200 dark:border-gray-700 pt-4 flex gap-3">
                    <Link :href="`/video-tasks/${task.id}/edit`"
                        class="px-5 py-3 rounded-xl bg-amber-500 hover:bg-amber-600 text-white font-medium transition">
                        Editar
                    </Link>
                    <button @click="showDeleteModal = true"
                        class="px-5 py-3 rounded-xl bg-red-600 hover:bg-red-700 text-white font-medium transition">
                        Eliminar
                    </button>
                </div>
            </div>

            <transition name="fade">
                <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-md p-6">
                        <h2 class="text-xl font-bold mb-4 text-gray-900 dark:text-white">Confirmar eliminacion</h2>
                        <p class="text-gray-600 dark:text-gray-300 mb-6">Esta accion no se puede deshacer.</p>
                        <div class="flex justify-end gap-3">
                            <button @click="showDeleteModal = false"
                                class="px-4 py-2 rounded-xl border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300">
                                Cancelar
                            </button>
                            <button @click="confirmDelete"
                                class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white">
                                Eliminar
                            </button>
                        </div>
                    </div>
                </div>
            </transition>
        </div>
    </AppLayout>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity .2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
