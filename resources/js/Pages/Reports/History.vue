<script setup>
import { ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import ConfirmDeleteModal from '@/Components/Modals/ConfirmDelete.vue'
import { FileDown, Trash2, Search } from 'lucide-vue-next'

const page = usePage()
const permissions = page.props.auth?.user?.permissions ?? []
const can = (perm) => permissions.includes(perm)

const props = defineProps({
    histories: Array,
})

const search = ref('')
const scopeFilter = ref('')

const filteredHistories = computed(() => {
    let result = props.histories

    if (search.value) {
        const q = search.value.toLowerCase()
        result = result.filter(h =>
            (h.filename && h.filename.toLowerCase().includes(q)) ||
            (h.user_name && h.user_name.toLowerCase().includes(q))
        )
    }

    if (scopeFilter.value) {
        result = result.filter(h => h.scope === scopeFilter.value)
    }

    return result
})

const deletingItem = ref(null)
const deleteMessage = computed(() => deletingItem.value
    ? `¿Eliminar el reporte "${deletingItem.value.filename}"?`
    : '')

function confirmDelete(item) { deletingItem.value = item }

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
                    <div class="flex flex-wrap gap-2">
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
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full table-fixed">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="text-left px-4 py-4 text-sm font-semibold text-gray-500 dark:text-gray-400 w-[160px]">Fecha</th>
                                <th class="text-left px-4 py-4 text-sm font-semibold text-gray-500 dark:text-gray-400 w-[140px]">Usuario</th>
                                <th class="text-left px-4 py-4 text-sm font-semibold text-gray-500 dark:text-gray-400 w-[110px]">Tipo</th>
                                <th class="text-left px-4 py-4 text-sm font-semibold text-gray-500 dark:text-gray-400">Archivo</th>
                                <th class="text-right px-4 py-4 text-sm font-semibold text-gray-500 dark:text-gray-400 w-[140px]">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in filteredHistories" :key="item.id"
                                class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                <td class="px-4 py-4 text-sm text-gray-900 dark:text-white whitespace-nowrap">{{ item.created_at }}</td>
                                <td class="px-4 py-4 text-sm text-gray-700 dark:text-gray-300 truncate">{{ item.user_name }}</td>
                                <td class="px-4 py-4">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full whitespace-nowrap"
                                        :class="{
                                            'bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-200': item.scope === 'anual',
                                            'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200': item.scope === 'mensual',
                                            'bg-teal-100 text-teal-700 dark:bg-teal-900 dark:text-teal-200': item.scope === 'semanal',
                                            'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-200': item.scope === 'dia',
                                        }">
                                        {{ scopeLabel(item.scope) }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-sm text-gray-700 dark:text-gray-300 truncate">{{ item.filename }}</td>
                                <td class="px-4 py-4 text-right flex items-center justify-end gap-2">
                                    <a v-if="can('download reports')" :href="`/report-history/${item.id}/download`"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium transition flex-shrink-0">
                                        <FileDown class="w-3 h-3" />
                                        Descargar
                                    </a>
                                    <button v-if="can('delete reports')" @click="confirmDelete(item)"
                                        class="p-1.5 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-400 hover:text-red-600 transition flex-shrink-0"
                                        title="Eliminar">
                                        <Trash2 class="w-4 h-4" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="filteredHistories.length === 0">
                                <td colspan="5" class="px-4 py-16 text-center text-gray-400 dark:text-gray-500 italic">
                                    <template v-if="histories.length === 0">Aún no se han generado reportes</template>
                                    <template v-else>No hay reportes que coincidan con tu búsqueda</template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
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
