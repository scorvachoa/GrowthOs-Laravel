<script setup>
import { ref, watch, computed } from 'vue'
import { FileDown, X } from 'lucide-vue-next'

const props = defineProps({
    show: Boolean,
    year: { type: Number, default: () => new Date().getFullYear() },
    month: { type: Number, default: () => new Date().getMonth() + 1 },
    weekStart: { type: String, default: '' },
    day: { type: String, default: '' },
    defaultScope: { type: String, default: 'mensual' },
})

const emit = defineEmits(['close'])

const scope = ref(props.defaultScope)
const reportYear = ref(props.year)
const reportMonth = ref(props.month)
const reportWeekStart = ref(props.weekStart || getCurrentMonday())
const reportDay = ref(props.day || formatDateLocal(new Date()))

function formatDateLocal(d) {
    const y = d.getFullYear()
    const m = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    return `${y}-${m}-${day}`
}

function getCurrentMonday() {
    const now = new Date()
    const day = now.getDay()
    const diff = day === 0 ? 6 : day - 1
    const monday = new Date(now)
    monday.setDate(now.getDate() - diff)
    return formatDateLocal(monday)
}

const downloading = ref(false)

const scopeOptions = [
    { value: 'anual', label: 'Anual' },
    { value: 'mensual', label: 'Mensual' },
    { value: 'semanal', label: 'Semanal' },
    { value: 'dia', label: 'Dia' },
]

watch(() => props.show, (v) => {
    if (v) {
        scope.value = props.defaultScope
        reportYear.value = props.year
        reportMonth.value = props.month
        reportWeekStart.value = props.weekStart || getCurrentMonday()
        reportDay.value = props.day || formatDateLocal(new Date())
    }
})

function exportPdf() {
    if (downloading.value) return
    downloading.value = true

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
    window.location.href = `/task-reports/pdf?${params.toString()}`
    setTimeout(() => { downloading.value = false; emit('close') }, 500)
}
</script>

<template>
    <transition name="fade">
        <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="emit('close')">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-md p-6 mx-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Exportar reporte PDF</h2>
                    <button @click="emit('close')" class="text-gray-500 hover:text-gray-700">
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
                        <button @click="emit('close')"
                            class="px-4 py-2 rounded-xl border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300">
                            Cancelar
                        </button>
                        <button @click="exportPdf" :disabled="downloading"
                            class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                            <FileDown class="w-4 h-4" />
                            {{ downloading ? 'Descargando...' : 'Descargar PDF' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </transition>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity .2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
