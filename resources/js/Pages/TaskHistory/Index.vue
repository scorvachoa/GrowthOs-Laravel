<script setup>
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import { Search } from 'lucide-vue-next'

const props = defineProps({
    tasks: Object,
    statuses: Array,
    filters: Object,
})

const search = ref(props.filters?.q || '')
const statusFilter = ref(props.filters?.status || '')
const perPage = ref(props.filters?.per_page || 10)

function load() {
    router.get('/task-history', {
        q: search.value || '',
        status: statusFilter.value || '',
        per_page: perPage.value,
    }, { preserveState: true, replace: true })
}

let debounceTimer
watch(search, () => {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(load, 400)
})

watch(statusFilter, load)
watch(perPage, load)

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
</script>

<template>
    <AppLayout>
        <div class="max-w-7xl mx-auto space-y-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Historial de Tareas</h1>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-4 border-b border-gray-100 dark:border-gray-700 flex flex-col sm:flex-row sm:items-center gap-3">
                    <div class="relative flex-1 max-w-md">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                        <input v-model="search" placeholder="Buscar por título..."
                            class="w-full pl-10 pr-4 py-2 rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white text-sm" />
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button v-for="s in statuses" :key="s.value"
                            @click="statusFilter = statusFilter === s.value ? '' : s.value"
                            class="px-3 py-1.5 rounded-lg border text-xs font-medium transition"
                            :class="statusFilter === s.value
                                ? 'bg-indigo-50 border-indigo-300 text-indigo-700 dark:bg-indigo-900/30 dark:border-indigo-700 dark:text-indigo-300'
                                : 'bg-gray-50 border-gray-200 text-gray-500 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400'">
                            {{ s.label }}
                        </button>

                        <span class="w-px h-6 bg-gray-200 dark:bg-gray-700 mx-1 hidden sm:block"></span>

                        <select v-model="perPage"
                            class="px-4 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-xs font-medium text-gray-500 dark:text-gray-400 focus:outline-none focus:ring-1 focus:ring-indigo-400 min-w-[80px]">
                            <option value="5">5</option>
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm table-fixed">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="text-left px-4 py-4 font-semibold text-gray-500 dark:text-gray-400 w-[100px] sm:w-[140px]">Fecha</th>
                                <th class="text-left px-4 py-4 font-semibold text-gray-500 dark:text-gray-400 hidden sm:table-cell w-[90px]">Bloque</th>
                                <th class="text-left px-4 py-4 font-semibold text-gray-500 dark:text-gray-400">Título</th>
                                <th class="text-left px-4 py-4 font-semibold text-gray-500 dark:text-gray-400 w-[100px] sm:w-[130px]">Estado</th>
                                <th class="text-left px-4 py-4 font-semibold text-gray-500 dark:text-gray-400 hidden lg:table-cell w-[140px]">Canal</th>
                                <th class="text-left px-4 py-4 font-semibold text-gray-500 dark:text-gray-400 hidden lg:table-cell w-[130px]">Creado por</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="task in tasks.data" :key="task.id" @click="router.get(`/task-history/${task.id}`)"
                                class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer transition">
                                <td class="px-4 py-4 text-gray-900 dark:text-white whitespace-nowrap text-xs sm:text-sm">{{ formatDate(task.task_date) }}</td>
                                <td class="px-4 py-4 text-gray-600 dark:text-gray-400 whitespace-nowrap text-xs hidden sm:table-cell">{{ task.time_range }}</td>
                                <td class="px-4 py-4 text-gray-900 dark:text-white font-medium truncate text-sm">{{ task.title }}</td>
                                <td class="px-4 py-4">
                                    <span class="inline-block px-2 py-0.5 sm:px-2.5 rounded-full text-[10px] sm:text-xs font-medium whitespace-nowrap"
                                        :class="statusColors[task.status] || 'bg-gray-100 text-gray-800'">
                                        {{ statusLabel(task.status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 hidden lg:table-cell">
                                    <span v-if="task.channel" class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-600 dark:text-gray-400 truncate">
                                        <span class="w-2 h-2 rounded-full flex-shrink-0" :style="{ backgroundColor: task.channel.color }"></span>
                                        {{ task.channel.name }}
                                    </span>
                                    <span v-else class="text-xs text-gray-400">—</span>
                                </td>
                                <td class="px-4 py-4 text-gray-600 dark:text-gray-400 text-xs truncate hidden lg:table-cell">{{ task.created_by || '—' }}</td>
                            </tr>
                            <tr v-if="tasks.data.length === 0">
                                <td colspan="6" class="px-4 py-16 text-center text-gray-400 dark:text-gray-500 italic">No se encontraron tareas</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-between gap-4 px-4 py-3 border-t border-gray-100 dark:border-gray-700">
                    <div class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">
                        Mostrando {{ tasks?.from || 0 }} - {{ tasks?.to || 0 }} de {{ tasks?.total || 0 }} tareas
                    </div>
                    <Pagination v-if="tasks?.links" :links="tasks.links" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
