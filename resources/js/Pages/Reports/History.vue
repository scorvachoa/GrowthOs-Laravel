<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { FileDown, Clock } from 'lucide-vue-next'

defineProps({
    histories: Array,
})

const scopeLabel = (scope) => {
    const labels = { anual: 'Anual', mensual: 'Mensual', semanal: 'Semanal', dia: 'Diario' }
    return labels[scope] || scope
}
</script>

<template>
    <AppLayout>
        <div class="space-y-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Historial de reportes</h1>
                <p class="text-gray-500 dark:text-gray-400 mt-1">Reportes PDF generados anteriormente</p>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-6">
                <div v-if="histories.length === 0" class="text-center py-12 text-gray-500 dark:text-gray-400">
                    <Clock class="w-12 h-12 mx-auto mb-3 opacity-50" />
                    <p>Aun no se han generado reportes</p>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="text-left py-4 text-sm font-semibold text-gray-500 dark:text-gray-400">Fecha</th>
                                <th class="text-left py-4 text-sm font-semibold text-gray-500 dark:text-gray-400">Usuario</th>
                                <th class="text-left py-4 text-sm font-semibold text-gray-500 dark:text-gray-400">Tipo</th>
                                <th class="text-left py-4 text-sm font-semibold text-gray-500 dark:text-gray-400">Archivo</th>
                                <th class="text-right py-4 text-sm font-semibold text-gray-500 dark:text-gray-400">Accion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in histories" :key="item.id"
                                class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                <td class="py-4 text-sm text-gray-900 dark:text-white">{{ item.created_at }}</td>
                                <td class="py-4 text-sm text-gray-700 dark:text-gray-300">{{ item.user_name }}</td>
                                <td class="py-4">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full"
                                        :class="{
                                            'bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-200': item.scope === 'anual',
                                            'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200': item.scope === 'mensual',
                                            'bg-teal-100 text-teal-700 dark:bg-teal-900 dark:text-teal-200': item.scope === 'semanal',
                                            'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-200': item.scope === 'dia',
                                        }">
                                        {{ scopeLabel(item.scope) }}
                                    </span>
                                </td>
                                <td class="py-4 text-sm text-gray-700 dark:text-gray-300">{{ item.filename }}</td>
                                <td class="py-4 text-right">
                                    <a :href="`/report-history/${item.id}/download`"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium transition">
                                        <FileDown class="w-3 h-3" />
                                        Descargar
                                    </a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
