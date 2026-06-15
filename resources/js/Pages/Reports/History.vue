<script setup>
import { ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import ConfirmDeleteModal from '@/Components/Modals/ConfirmDelete.vue'
import { FileDown, Trash2, Search } from 'lucide-vue-next'

const page = usePage()
const permissions = page.props.auth?.user?.permissions ?? []
const can = (perm) => permissions.includes(perm)

const props = defineProps({
    histories: Object,
    filters: Object,
})

const search = ref(props.filters?.search || '')
const scopeFilter = ref(props.filters?.scope || '')
const perPage = ref(props.filters?.per_page || 10)

function load() {
    router.get('/report-history', {
        search: search.value || '',
        scope: scopeFilter.value || '',
        per_page: perPage.value,
    }, { preserveState: true, replace: true })
}

let debounceTimer
watch(search, () => {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(load, 400)
})

watch(scopeFilter, load)
watch(perPage, load)

const deletingItem = ref(null)
const deleteMessage = ref('')

function confirmDelete(item) {
    deletingItem.value = item
    deleteMessage.value = `¿Eliminar el reporte "${item.filename}"?`
}

function executeDelete() {
    router.delete(`/report-history/${deletingItem.value.id}`, {
        preserveState: true, preserveScroll: true,
        onSuccess: () => { deletingItem.value = null },
    })
}

const scopeLabel = (scope) => {
    const labels = { anual: 'Anual', mensual: 'Mensual', semanal: 'Semanal', dia: 'Diario' }
    return labels[scope] || scope
}
</script>

<template>
    <AppLayout>
        <div class="max-w-7xl mx-auto space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Historial de reportes</h1>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-4 border-b border-gray-100 dark:border-gray-700 flex flex-col sm:flex-row sm:items-center gap-3">
                    <div class="relative flex-1 max-w-md">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                        <input v-model="search" type="text" placeholder="Buscar por archivo o usuario..."
                            class="w-full pl-10 pr-4 py-2 rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white text-sm">
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button @click="scopeFilter = scopeFilter === 'anual' ? '' : 'anual'"
                            class="px-3 py-1.5 rounded-lg border text-xs font-medium transition"
                            :class="scopeFilter === 'anual'
                                ? 'bg-indigo-50 border-indigo-300 text-indigo-700 dark:bg-indigo-900/30 dark:border-indigo-700 dark:text-indigo-300'
                                : 'bg-gray-50 border-gray-200 text-gray-500 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400'">
                            Anual
                        </button>
                        <button @click="scopeFilter = scopeFilter === 'mensual' ? '' : 'mensual'"
                            class="px-3 py-1.5 rounded-lg border text-xs font-medium transition"
                            :class="scopeFilter === 'mensual'
                                ? 'bg-blue-50 border-blue-300 text-blue-700 dark:bg-blue-900/30 dark:border-blue-700 dark:text-blue-300'
                                : 'bg-gray-50 border-gray-200 text-gray-500 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400'">
                            Mensual
                        </button>
                        <button @click="scopeFilter = scopeFilter === 'semanal' ? '' : 'semanal'"
                            class="px-3 py-1.5 rounded-lg border text-xs font-medium transition"
                            :class="scopeFilter === 'semanal'
                                ? 'bg-teal-50 border-teal-300 text-teal-700 dark:bg-teal-900/30 dark:border-teal-700 dark:text-teal-300'
                                : 'bg-gray-50 border-gray-200 text-gray-500 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400'">
                            Semanal
                        </button>
                        <button @click="scopeFilter = scopeFilter === 'dia' ? '' : 'dia'"
                            class="px-3 py-1.5 rounded-lg border text-xs font-medium transition"
                            :class="scopeFilter === 'dia'
                                ? 'bg-amber-50 border-amber-300 text-amber-700 dark:bg-amber-900/30 dark:border-amber-700 dark:text-amber-300'
                                : 'bg-gray-50 border-gray-200 text-gray-500 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400'">
                            Diario
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
                    <table class="w-full table-fixed">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="text-left px-4 py-4 text-xs sm:text-sm font-semibold text-gray-500 dark:text-gray-400 w-[90px] sm:w-[160px]">Fecha</th>
                                <th class="text-left px-4 py-4 text-xs sm:text-sm font-semibold text-gray-500 dark:text-gray-400 hidden sm:table-cell w-[140px]">Usuario</th>
                                <th class="text-left px-4 py-4 text-xs sm:text-sm font-semibold text-gray-500 dark:text-gray-400 hidden md:table-cell w-[110px]">Tipo</th>
                                <th class="text-left px-4 py-4 text-xs sm:text-sm font-semibold text-gray-500 dark:text-gray-400">Archivo</th>
                                <th class="text-right px-4 py-4 text-xs sm:text-sm font-semibold text-gray-500 dark:text-gray-400 w-[80px] sm:w-[140px]">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in histories?.data || []" :key="item.id"
                                class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                <td class="px-4 py-4 text-xs sm:text-sm text-gray-900 dark:text-white whitespace-nowrap">{{ item.created_at?.substring(0, 10) }}</td>
                                <td class="px-4 py-4 text-xs sm:text-sm text-gray-700 dark:text-gray-300 truncate hidden sm:table-cell">{{ item.user_name }}</td>
                                <td class="px-4 py-4 hidden md:table-cell">
                                    <span class="px-2 py-1 text-[10px] sm:text-xs font-medium rounded-full whitespace-nowrap"
                                        :class="{
                                            'bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-200': item.scope === 'anual',
                                            'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200': item.scope === 'mensual',
                                            'bg-teal-100 text-teal-700 dark:bg-teal-900 dark:text-teal-200': item.scope === 'semanal',
                                            'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-200': item.scope === 'dia',
                                        }">
                                        {{ scopeLabel(item.scope) }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-xs sm:text-sm text-gray-700 dark:text-gray-300 truncate max-w-[25ch]">{{ item.filename }}</td>
                                <td class="px-4 py-4 text-right">
                                    <div class="flex items-center justify-end gap-1 sm:gap-2">
                                        <a v-if="can('download reports')" :href="`/report-history/${item.id}/download`"
                                            class="inline-flex items-center gap-1 px-2 sm:px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-[10px] sm:text-xs font-medium transition flex-shrink-0">
                                            <FileDown class="w-3 h-3" />
                                            <span class="hidden sm:inline">Descargar</span>
                                        </a>
                                        <button v-if="can('delete reports')" @click="confirmDelete(item)"
                                            class="p-1.5 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-400 hover:text-red-600 transition flex-shrink-0"
                                            title="Eliminar">
                                            <Trash2 class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!histories?.data?.length">
                                <td colspan="5" class="px-4 py-16 text-center text-gray-400 dark:text-gray-500 italic">
                                    Aún no se han generado reportes
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex items-center justify-between gap-4">
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    Mostrando {{ histories?.from || 0 }} - {{ histories?.to || 0 }} de {{ histories?.total || 0 }} reportes
                </div>
                <Pagination v-if="histories?.links" :links="histories.links" />
            </div>
        </div>

        <ConfirmDeleteModal
            :show="deletingItem !== null"
            title="Eliminar reporte"
            :message="deleteMessage"
            @close="deletingItem = null"
            @confirm="executeDelete"
        />
    </AppLayout>
</template>
