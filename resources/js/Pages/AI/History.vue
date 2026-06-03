<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import UseTaskModal from '@/Components/AI/UseTaskModal.vue'
import { ChevronLeft, ChevronRight, Sparkles, FileText, CopyCheck, Quote, Eye, Trash2, Download, CalendarPlus } from 'lucide-vue-next'
import axios from 'axios'

const props = defineProps({
    videos: Object,
    channels: Array,
    work_blocks: Array,
    statuses: Array,
})

const modalVideoId = ref(null)
const modalIdea = ref('')
const showModal = ref(false)

function openUseModal(id, idea) {
    modalVideoId.value = id
    modalIdea.value = idea
    showModal.value = true
}

function downloadTxt(id) {
    window.open(`/ai/history/${id}/download`, '_blank')
}

async function deleteVideo(id) {
    if (!confirm('¿Eliminar este registro del historial?')) return
    try {
        await axios.delete(`/ai/history/${id}`)
        router.reload({ preserveState: true })
    } catch {
        alert('Error al eliminar')
    }
}

const formatDate = (dateStr) => {
    if (!dateStr) return '—'
    const d = new Date(dateStr)
    return d.toLocaleDateString('es-PE', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    })
}
</script>

<template>
    <AppLayout>
        <div class="max-w-7xl mx-auto space-y-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Historial de generaciones</h1>
                <button @click="router.get('/ai')"
                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition flex items-center gap-2">
                    <Sparkles class="w-4 h-4" /> Nuevo
                </button>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                                <th class="text-left px-4 py-3 font-semibold text-gray-500 dark:text-gray-400">ID</th>
                                <th class="text-left px-4 py-3 font-semibold text-gray-500 dark:text-gray-400">Idea</th>
                                <th class="text-left px-4 py-3 font-semibold text-gray-500 dark:text-gray-400">Guion</th>
                                <th class="text-left px-4 py-3 font-semibold text-gray-500 dark:text-gray-400">Contenido</th>
                                <th class="text-left px-4 py-3 font-semibold text-gray-500 dark:text-gray-400">Creado</th>
                                <th class="text-right px-4 py-3 font-semibold text-gray-500 dark:text-gray-400">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="v in videos.data" :key="v.id"
                                class="border-b border-gray-50 dark:border-gray-700/50 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition">
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400 font-mono text-xs">{{ v.id }}</td>
                                <td class="px-4 py-3 text-gray-900 dark:text-white font-medium max-w-xs truncate">{{ v.idea }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400 max-w-sm truncate text-xs">
                                    {{ v.script_preview || '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <span v-if="v.has_script" title="Tiene guion"
                                            class="w-6 h-6 flex items-center justify-center rounded-full bg-green-100 dark:bg-green-900">
                                            <FileText class="w-3.5 h-3.5 text-green-600 dark:text-green-400" />
                                        </span>
                                        <span v-if="v.has_copy" title="Tiene copy"
                                            class="w-6 h-6 flex items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900">
                                            <CopyCheck class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" />
                                        </span>
                                        <span v-if="v.has_phrases" title="Tiene frases"
                                            class="w-6 h-6 flex items-center justify-center rounded-full bg-purple-100 dark:bg-purple-900">
                                            <Quote class="w-3.5 h-3.5 text-purple-600 dark:text-purple-400" />
                                        </span>
                                        <span v-if="!v.has_script && !v.has_copy && !v.has_phrases"
                                            class="text-xs text-gray-400">—</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                    {{ formatDate(v.created_at) }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <button @click="downloadTxt(v.id)"
                                            class="p-1.5 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900 text-gray-500 hover:text-blue-600 transition"
                                            title="Descargar TXT">
                                            <Download class="w-4 h-4" />
                                        </button>
                                        <button @click="openUseModal(v.id, v.idea)"
                                            class="p-1.5 rounded-lg hover:bg-emerald-100 dark:hover:bg-emerald-900 text-gray-500 hover:text-emerald-600 transition"
                                            title="Usar en planificador">
                                            <CalendarPlus class="w-4 h-4" />
                                        </button>
                                        <button @click="router.get(`/ai?load=${v.id}`)"
                                            class="p-1.5 rounded-lg hover:bg-indigo-100 dark:hover:bg-indigo-800 text-gray-500 hover:text-indigo-600 transition"
                                            title="Cargar en generador">
                                            <Eye class="w-4 h-4" />
                                        </button>
                                        <button @click="deleteVideo(v.id)"
                                            class="p-1.5 rounded-lg hover:bg-red-100 dark:hover:bg-red-900 text-gray-500 hover:text-red-600 transition"
                                            title="Eliminar">
                                            <Trash2 class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="videos.data.length === 0">
                                <td colspan="6" class="px-4 py-16 text-center text-gray-400 dark:text-gray-500 italic">
                                    No hay generaciones en el historial.
                                    <br>
                                    <button @click="router.get('/ai')"
                                        class="mt-2 text-indigo-600 hover:text-indigo-700 font-medium underline">
                                        Ir al generador
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="videos.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100 dark:border-gray-700">
                    <span class="text-xs text-gray-500">
                        Página {{ videos.current_page }} de {{ videos.last_page }}
                        ({{ videos.total }} registros)
                    </span>
                    <div class="flex gap-1">
                        <button :disabled="!videos.prev_page_url"
                            @click="router.get(videos.prev_page_url, {}, { preserveState: true, replace: true })"
                            class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-30 disabled:cursor-not-allowed transition">
                            <ChevronLeft class="w-4 h-4 text-gray-600 dark:text-gray-400" />
                        </button>
                        <button :disabled="!videos.next_page_url"
                            @click="router.get(videos.next_page_url, {}, { preserveState: true, replace: true })"
                            class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-30 disabled:cursor-not-allowed transition">
                            <ChevronRight class="w-4 h-4 text-gray-600 dark:text-gray-400" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <UseTaskModal
            :show="showModal"
            :video-id="modalVideoId"
            :idea="modalIdea"
            :channels="channels"
            :work-blocks="work_blocks"
            :statuses="statuses"
            @close="showModal = false"
        />
    </AppLayout>
</template>
