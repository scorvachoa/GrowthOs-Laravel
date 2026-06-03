<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Search, X, ChevronLeft, ChevronRight } from 'lucide-vue-next'

const props = defineProps({
    tasks: Object,
    statuses: Array,
    filters: Object,
})

const search = ref(props.filters?.q || '')
const statusFilter = ref(props.filters?.status || '')

const statusColors = {
    pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    script_ready: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    editing: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
    review: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
    scheduled: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
    published: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
}

const statusLabel = (value) => {
    const found = props.statuses.find(s => s.value === value)
    return found ? found.label : value
}

const formatDate = (dateStr) => {
    const d = new Date(dateStr + 'T12:00:00')
    return d.toLocaleDateString('es-PE', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })
}

function applyFilters() {
    router.get('/task-history', {
        q: search.value || '',
        status: statusFilter.value || '',
    }, { preserveState: true, replace: true })
}

function clearFilters() {
    search.value = ''
    statusFilter.value = ''
    router.get('/task-history', {}, { preserveState: true, replace: true })
}
</script>

<template>
    <AppLayout>
        <div class="max-w-7xl mx-auto space-y-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Historial de Tareas</h1>

            <div class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                    <input v-model="search" @input="applyFilters" placeholder="Buscar por titulo..."
                        class="w-full pl-9 pr-3 py-2 rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                </div>
                <select v-model="statusFilter" @change="applyFilters"
                    class="rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                    <option value="">Todos los estados</option>
                    <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
                <button v-if="search || statusFilter" @click="clearFilters"
                    class="px-3 py-2 rounded-xl text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 transition flex items-center gap-1.5">
                    <X class="w-4 h-4" /> Limpiar
                </button>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                                <th class="text-left px-4 py-3 font-semibold text-gray-500 dark:text-gray-400">Fecha</th>
                                <th class="text-left px-4 py-3 font-semibold text-gray-500 dark:text-gray-400">Bloque</th>
                                <th class="text-left px-4 py-3 font-semibold text-gray-500 dark:text-gray-400">Titulo</th>
                                <th class="text-left px-4 py-3 font-semibold text-gray-500 dark:text-gray-400">Estado</th>
                                <th class="text-left px-4 py-3 font-semibold text-gray-500 dark:text-gray-400">Canal</th>
                                <th class="text-left px-4 py-3 font-semibold text-gray-500 dark:text-gray-400">Creado por</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="task in tasks.data" :key="task.id" @click="router.get(`/task-history/${task.id}`)"
                                class="border-b border-gray-50 dark:border-gray-700/50 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 cursor-pointer transition">
                                <td class="px-4 py-3 text-gray-900 dark:text-white whitespace-nowrap">{{ formatDate(task.task_date) }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ task.time_range }}</td>
                                <td class="px-4 py-3 text-gray-900 dark:text-white font-medium max-w-xs truncate">{{ task.title }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-medium"
                                        :class="statusColors[task.status] || 'bg-gray-100 text-gray-800'">
                                        {{ statusLabel(task.status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span v-if="task.channel" class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-600 dark:text-gray-400">
                                        <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: task.channel.color }"></span>
                                        {{ task.channel.name }}
                                    </span>
                                    <span v-else class="text-xs text-gray-400">—</span>
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400 text-xs">{{ task.created_by || '—' }}</td>
                            </tr>
                            <tr v-if="tasks.data.length === 0">
                                <td colspan="6" class="px-4 py-12 text-center text-gray-400 dark:text-gray-500 italic">No se encontraron tareas</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="tasks.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100 dark:border-gray-700">
                    <span class="text-xs text-gray-500">
                        Pagina {{ tasks.current_page }} de {{ tasks.last_page }}
                        ({{ tasks.total }} tareas)
                    </span>
                    <div class="flex gap-1">
                        <button :disabled="!tasks.prev_page_url" @click="router.get(tasks.prev_page_url, {}, { preserveState: true, replace: true })"
                            class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-30 disabled:cursor-not-allowed transition">
                            <ChevronLeft class="w-4 h-4 text-gray-600 dark:text-gray-400" />
                        </button>
                        <button :disabled="!tasks.next_page_url" @click="router.get(tasks.next_page_url, {}, { preserveState: true, replace: true })"
                            class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-30 disabled:cursor-not-allowed transition">
                            <ChevronRight class="w-4 h-4 text-gray-600 dark:text-gray-400" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
