<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatCard from '@/Components/UI/StatCard.vue'
import { FileDown, X } from 'lucide-vue-next'

const props = defineProps({
    stats: Object,
    year: Number,
    month: Number,
    today: String,
    week_start: String,
})

const showModal = ref(false)
const scope = ref('mensual')
const reportYear = ref(props.year)
const reportMonth = ref(props.month)
const reportWeekStart = ref(props.week_start)
const reportDay = ref(props.today)

const scopeOptions = [
    { value: 'anual', label: 'Anual' },
    { value: 'mensual', label: 'Mensual' },
    { value: 'semanal', label: 'Semanal' },
    { value: 'dia', label: 'Dia' },
]

function exportPdf() {
    const params = new URLSearchParams({ scope: scope.value })
    if (scope.value === 'anual' || scope.value === 'mensual') {
        params.append('year', reportYear.value)
    }
    if (scope.value === 'mensual') {
        params.append('month', reportMonth.value)
    }
    if (scope.value === 'semanal') {
        params.append('week_start', reportWeekStart.value)
    }
    if (scope.value === 'dia') {
        params.append('day', reportDay.value)
    }
    window.open(`/task-reports/pdf?${params.toString()}`, '_blank')
    showModal.value = false
}

function openModal() {
    const now = new Date()
    reportYear.value = props.year || now.getFullYear()
    reportMonth.value = props.month || now.getMonth() + 1
    reportWeekStart.value = props.week_start
    reportDay.value = props.today
    showModal.value = true
}
</script>

<template>
    <AppLayout>
        <div class="space-y-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Reportes de tareas</h1>
                    <p class="text-gray-500 dark:text-gray-400 mt-1">Estadisticas y exportacion de tareas</p>
                </div>
                <button @click="openModal"
                    class="px-5 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-medium transition flex items-center gap-2">
                    <FileDown class="w-4 h-4" />
                    Exportar PDF
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
                <StatCard title="Total tareas" :value="stats.total" />
                <StatCard title="Completadas" :value="stats.completed" />
                <StatCard title="Pendientes" :value="stats.pending" />
                <StatCard title="Vencidas" :value="stats.overdue" />
            </div>
        </div>

        <transition name="fade">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-md p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Exportar reporte PDF</h2>
                        <button @click="showModal = false" class="text-gray-500 hover:text-gray-700">
                            <X class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Tipo de reporte</label>
                            <select v-model="scope"
                                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                                <option v-for="opt in scopeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                        </div>

                        <div v-if="scope === 'anual' || scope === 'mensual'">
                            <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Ano</label>
                            <input v-model.number="reportYear" type="number" min="2000" max="2100"
                                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                        </div>

                        <div v-if="scope === 'mensual'">
                            <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Mes</label>
                            <input v-model.number="reportMonth" type="number" min="1" max="12"
                                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                        </div>

                        <div v-if="scope === 'semanal'">
                            <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Inicio de semana (Lunes)</label>
                            <input v-model="reportWeekStart" type="date"
                                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                        </div>

                        <div v-if="scope === 'dia'">
                            <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Fecha</label>
                            <input v-model="reportDay" type="date"
                                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                        </div>

                        <div class="flex justify-end gap-3 pt-2">
                            <button @click="showModal = false"
                                class="px-4 py-2 rounded-xl border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300">
                                Cancelar
                            </button>
                            <button @click="exportPdf"
                                class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white flex items-center gap-2">
                                <FileDown class="w-4 h-4" />
                                Descargar PDF
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </AppLayout>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity .2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
