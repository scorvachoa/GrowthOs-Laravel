<script setup>
import { ref, computed, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import ConfirmDeleteModal from '@/Components/Modals/ConfirmDelete.vue'

const page = usePage()
const permissions = page.props.auth?.user?.permissions ?? []
const can = (perm) => permissions.includes(perm)
import UseTaskModal from '@/Components/AI/UseTaskModal.vue'
import { ChevronLeft, ChevronRight, Sparkles, FileText, CopyCheck, Quote, Eye, Trash2, Download, CalendarPlus, Search, CalendarCheck } from 'lucide-vue-next'
import axios from 'axios'

const props = defineProps({
    videos: Object,
    channels: Array,
    work_blocks: Array,
    statuses: Array,
    filters: Object,
})

const search = ref(props.filters?.search || '')
const hasScript = ref(props.filters?.has_script === '1')
const hasCopy = ref(props.filters?.has_copy === '1')
const hasPhrases = ref(props.filters?.has_phrases === '1')
const usedFilter = ref(props.filters?.used_in_planner || '')

function applyFilters() {
    router.get('/ai/history', {
        search: search.value || '',
        has_script: hasScript.value ? '1' : '',
        has_copy: hasCopy.value ? '1' : '',
        has_phrases: hasPhrases.value ? '1' : '',
        used_in_planner: usedFilter.value,
    }, { preserveState: true, replace: true })
}

watch(search, () => applyFilters())

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

const deleteTargetId = ref(null)
const showDeleteModal = computed(() => deleteTargetId.value !== null)

function deleteVideo(id) {
    deleteTargetId.value = id
}

async function executeDelete() {
    if (!deleteTargetId.value) return
    try {
        await axios.delete(`/ai/history/${deleteTargetId.value}`)
        router.reload({ preserveState: true })
        deleteTargetId.value = null
    } catch {
        alert('Error al eliminar')
        deleteTargetId.value = null
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
                <div class="p-4 border-b border-gray-100 dark:border-gray-700 flex flex-col sm:flex-row sm:items-center gap-3">
                    <div class="relative flex-1 max-w-md">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                        <input v-model="search" type="text" placeholder="Buscar por idea..."
                            class="w-full pl-10 pr-4 py-2 rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white text-sm">
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <label class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-xs font-medium cursor-pointer select-none transition"
                            :class="hasScript ? 'bg-green-50 border-green-300 text-green-700 dark:bg-green-900/30 dark:border-green-700 dark:text-green-300' : 'bg-gray-50 border-gray-200 text-gray-500 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400'">
                            <input type="checkbox" v-model="hasScript" @change="applyFilters" class="sr-only">
                            <FileText class="w-3.5 h-3.5" />
                            Guion
                        </label>
                        <label class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-xs font-medium cursor-pointer select-none transition"
                            :class="hasCopy ? 'bg-blue-50 border-blue-300 text-blue-700 dark:bg-blue-900/30 dark:border-blue-700 dark:text-blue-300' : 'bg-gray-50 border-gray-200 text-gray-500 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400'">
                            <input type="checkbox" v-model="hasCopy" @change="applyFilters" class="sr-only">
                            <CopyCheck class="w-3.5 h-3.5" />
                            Copy
                        </label>
                        <label class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-xs font-medium cursor-pointer select-none transition"
                            :class="hasPhrases ? 'bg-purple-50 border-purple-300 text-purple-700 dark:bg-purple-900/30 dark:border-purple-700 dark:text-purple-300' : 'bg-gray-50 border-gray-200 text-gray-500 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400'">
                            <input type="checkbox" v-model="hasPhrases" @change="applyFilters" class="sr-only">
                            <Quote class="w-3.5 h-3.5" />
                            Frases
                        </label>
                        <label class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-xs font-medium cursor-pointer select-none transition"
                            :class="usedFilter === '1' ? 'bg-emerald-50 border-emerald-300 text-emerald-700 dark:bg-emerald-900/30 dark:border-emerald-700 dark:text-emerald-300' : 'bg-gray-50 border-gray-200 text-gray-500 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400'">
                            <input type="checkbox" :checked="usedFilter === '1'" @change="usedFilter = usedFilter === '1' ? '' : '1'; applyFilters()" class="sr-only">
                            <CalendarCheck class="w-3.5 h-3.5" />
                            Usado
                        </label>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm table-fixed">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="text-left px-4 py-4 font-semibold text-gray-500 dark:text-gray-400 w-[60px]">ID</th>
                                <th class="text-left px-4 py-4 font-semibold text-gray-500 dark:text-gray-400">Idea</th>
                                <th class="text-left px-4 py-4 font-semibold text-gray-500 dark:text-gray-400 w-[250px]">Guion</th>
                                <th class="text-left px-4 py-4 font-semibold text-gray-500 dark:text-gray-400 w-[120px]">Contenido</th>
                                <th class="text-left px-4 py-4 font-semibold text-gray-500 dark:text-gray-400 w-[140px]">Creado</th>
                                <th class="text-right px-4 py-4 font-semibold text-gray-500 dark:text-gray-400 w-[180px]">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="v in videos.data" :key="v.id"
                                class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition"
                                :class="v.used_in_planner ? 'bg-emerald-50/40 dark:bg-emerald-900/10' : ''">
                                <td class="px-4 py-4 text-gray-500 dark:text-gray-400 font-mono text-xs">{{ v.id }}</td>
                                <td class="px-4 py-4 text-gray-900 dark:text-white font-medium max-w-xs truncate">
                                    <div class="flex items-center gap-2">
                                        <span class="truncate">{{ v.idea }}</span>
                                        <span v-if="v.used_in_planner"
                                            class="inline-flex items-center gap-0.5 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400 bg-emerald-100 dark:bg-emerald-900/40 px-1.5 py-0.5 rounded-full flex-shrink-0">
                                            <CalendarCheck class="w-2.5 h-2.5" /> usado
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-gray-600 dark:text-gray-400 max-w-sm truncate text-xs">
                                    {{ v.script_preview || '—' }}
                                </td>
                                <td class="px-4 py-4">
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
                                <td class="px-4 py-4 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                    {{ formatDate(v.created_at) }}
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <button v-if="can('download ai')" @click="downloadTxt(v.id)"
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

        <ConfirmDeleteModal
            :show="showDeleteModal"
            title="Eliminar historial"
            message="¿Eliminar este registro del historial?"
            @close="deleteTargetId = null"
            @confirm="executeDelete"
        />

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
