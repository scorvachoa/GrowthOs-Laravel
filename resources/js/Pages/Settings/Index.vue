<script setup>
import { reactive, ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PrimaryButton from '@/Components/UI/PrimaryButton.vue'
import { ToggleLeft, Clock, Eye, BarChart3, Monitor } from 'lucide-vue-next'

const page = usePage()
const permissions = page.props.auth?.user?.permissions ?? []
const can = (perm) => permissions.includes(perm)

const props = defineProps({
    settings: Object,
    workBlocks: Array,
})

const days = [
    { value: 0, label: 'Dom' },
    { value: 1, label: 'Lun' },
    { value: 2, label: 'Mar' },
    { value: 3, label: 'Mie' },
    { value: 4, label: 'Jue' },
    { value: 5, label: 'Vie' },
    { value: 6, label: 'Sab' },
]

const form = reactive({
    use_blocks: props.settings.use_blocks,
    block_hours: props.settings.block_hours,
    show_youtube_chart: props.settings.show_youtube_chart,
    default_work_start: props.settings.default_work_start,
    default_work_end: props.settings.default_work_end,
    working_days: [...(props.settings.working_days || [])],
    max_tasks_per_block: props.settings.max_tasks_per_block,
    default_report_scope: props.settings.default_report_scope,
    dashboard_default_view: props.settings.dashboard_default_view,
    youtube_max_recent_videos: props.settings.youtube_max_recent_videos,
})

const processing = ref(false)

const previewBlocks = computed(() => {
    if (!form.use_blocks) return []
    const s = form.default_work_start
    const e = form.default_work_end
    const h = form.block_hours
    const startHour = parseInt(s.split(':')[0])
    const endHour = parseInt(e.split(':')[0])
    const blocks = []
    for (let m = startHour; m + h <= endHour; m += h) {
        blocks.push(`${String(m).padStart(2, '0')}:00-${String(m + h).padStart(2, '0')}:00`)
    }
    return blocks
})

function toggleWorkingDay(day) {
    const idx = form.working_days.indexOf(day)
    if (idx === -1) {
        form.working_days = [...form.working_days, day]
    } else {
        form.working_days = form.working_days.filter(d => d !== day)
    }
}

function submit() {
    processing.value = true
    router.put('/settings', { ...form }, {
        preserveScroll: true,
        preserveState: false,
        onFinish: () => { processing.value = false },
    })
}
</script>

<template>
    <AppLayout>
        <div class="max-w-4xl mx-auto space-y-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Configuracion</h1>
                <p class="text-gray-500 dark:text-gray-400 mt-1">Preferencias personales</p>
            </div>



            <form @submit.prevent="submit" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Horario laboral -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 lg:col-span-2">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="p-2 rounded-lg bg-indigo-100 dark:bg-indigo-900">
                            <Clock class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Horario laboral</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Define el horario base para la planificacion</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Inicio jornada</label>
                            <input type="time" v-model="form.default_work_start"
                                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:[color-scheme:dark] focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fin jornada</label>
                            <input type="time" v-model="form.default_work_end"
                                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:[color-scheme:dark] focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Dias laborables</label>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="d in days" :key="d.value" type="button"
                                @click="toggleWorkingDay(d.value)"
                                :class="[
                                    'px-3 py-1.5 rounded-lg text-sm font-medium transition-colors',
                                    form.working_days.includes(d.value)
                                        ? 'bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300'
                                        : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400'
                                ]">
                                {{ d.label }}
                            </button>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 dark:border-gray-700 pt-6 space-y-4">
                        <div class="flex items-center justify-between p-4 rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700">
                            <div class="flex items-center gap-3">
                                <div class="p-2 rounded-lg bg-indigo-100 dark:bg-indigo-900">
                                    <ToggleLeft class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-white">Horario por bloques</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Al desactivar, el bloque horario sera un campo de texto libre</p>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="form.use_blocks" class="sr-only peer" />
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 dark:peer-focus:ring-indigo-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-indigo-600"></div>
                            </label>
                        </div>

                        <div v-if="form.use_blocks">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Duracion del bloque (horas)</label>
                            <select v-model="form.block_hours"
                                class="w-full max-w-xs rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                                <option :value="1">1 hora</option>
                                <option :value="2">2 horas</option>
                                <option :value="3">3 horas</option>
                                <option :value="4">4 horas</option>
                            </select>
                        </div>

                        <div v-if="form.use_blocks && previewBlocks.length > 0" class="p-4 rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700">
                            <div class="flex items-center gap-2 mb-3">
                                <Eye class="w-4 h-4 text-gray-400" />
                                <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">Vista previa de bloques</span>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <span v-for="block in previewBlocks" :key="block"
                                    class="px-3 py-1.5 rounded-lg bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 text-sm font-mono">
                                    {{ block }}
                                </span>
                            </div>
                        </div>

                        <div v-if="!form.use_blocks" class="p-4 rounded-xl bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-800 text-sm text-amber-700 dark:text-amber-400">
                            Con el horario libre, podras escribir cualquier rango horario al crear o editar tareas.
                        </div>
                    </div>
                </div>

                <!-- YouTube -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="p-2 rounded-lg bg-red-100 dark:bg-red-900/50">
                            <BarChart3 class="w-5 h-5 text-red-600 dark:text-red-400" />
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">YouTube</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Configuracion de la pagina de YouTube</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-4 rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700">
                            <div class="flex items-center gap-3">
                                <div class="p-2 rounded-lg bg-red-100 dark:bg-red-900/50">
                                    <BarChart3 class="w-5 h-5 text-red-600 dark:text-red-400" />
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-white">Grafico de rendimiento</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Muestra el grafico de lineas de vistas</p>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="form.show_youtube_chart" class="sr-only peer" />
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 dark:peer-focus:ring-indigo-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-indigo-600"></div>
                            </label>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Maximos videos recientes</label>
                            <select v-model="form.youtube_max_recent_videos"
                                class="w-full max-w-xs rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                                <option :value="5">5 videos</option>
                                <option :value="10">10 videos</option>
                                <option :value="15">15 videos</option>
                                <option :value="20">20 videos</option>
                                <option :value="30">30 videos</option>
                                <option :value="50">50 videos</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Dashboard y Reportes -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="p-2 rounded-lg bg-emerald-100 dark:bg-emerald-900/50">
                            <Monitor class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Dashboard y Reportes</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Vistas predeterminadas</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Vista del Dashboard</label>
                            <select v-model="form.dashboard_default_view"
                                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="week">Semanal</option>
                                <option value="month">Mensual</option>
                                <option value="year">Anual</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Alcance de reportes</label>
                            <select v-model="form.default_report_scope"
                                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="daily">Diario</option>
                                <option value="weekly">Semanal</option>
                                <option value="monthly">Mensual</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end lg:col-span-2">
                    <PrimaryButton v-if="can('edit configuracion')" type="submit" :disabled="processing">
                        Guardar configuracion
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
