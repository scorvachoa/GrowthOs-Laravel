<script setup>
import { reactive, ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { usePermissions } from '@/Composables/usePermissions'
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import PrimaryButton from '@/Components/UI/PrimaryButton.vue'
import { ToggleLeft, Clock, Eye, BarChart3, Monitor } from 'lucide-vue-next'

const { can } = usePermissions()
const canEdit = (section) => can(section)

const props = defineProps({
    settings: Object,
})

const days = [
    { value: 0, label: 'Dom' },
    { value: 1, label: 'Lun' },
    { value: 2, label: 'Mar' },
    { value: 3, label: 'Mié' },
    { value: 4, label: 'Jue' },
    { value: 5, label: 'Vie' },
    { value: 6, label: 'Sáb' },
]

const form = reactive({
    use_blocks: props.settings.use_blocks,
    block_hours: props.settings.block_hours,
    show_youtube_chart: props.settings.show_youtube_chart,
    default_work_start: props.settings.default_work_start,
    default_work_end: props.settings.default_work_end,
    lunch_start: props.settings.lunch_start,
    lunch_end: props.settings.lunch_end,
    working_days: [...(props.settings.working_days || [])],
    max_tasks_per_block: props.settings.max_tasks_per_block,
    default_report_scope: props.settings.default_report_scope,
    dashboard_default_view: props.settings.dashboard_default_view,
    youtube_max_recent_videos: props.settings.youtube_max_recent_videos,
})

const processing = ref(false)
const workTimeError = ref('')
const lunchTimeError = ref('')

const page = usePage()
const serverErrors = computed(() => {
    const errs = page.props.errors || {}
    return Object.keys(errs).length ? errs : null
})
const serverErrorList = computed(() => serverErrors.value ? Object.values(serverErrors.value).flat() : [])

function validateWorkTime() {
    if (form.default_work_start && form.default_work_end && form.default_work_end <= form.default_work_start) {
        workTimeError.value = 'La hora de fin debe ser mayor a la hora de inicio'
    } else {
        workTimeError.value = ''
    }
}

function validateLunchTime() {
    if (form.lunch_start && form.lunch_end && form.lunch_end <= form.lunch_start) {
        lunchTimeError.value = 'La hora de fin de almuerzo debe ser mayor a la hora de inicio'
    } else {
        lunchTimeError.value = ''
    }
}

const previewBlocks = computed(() => {
    if (!form.use_blocks) return []
    const startHour = parseInt(form.default_work_start.split(':')[0])
    const endHour = parseInt(form.default_work_end.split(':')[0])
    const h = form.block_hours
    const lunchStart = parseInt(form.lunch_start.split(':')[0])
    const lunchEnd = parseInt(form.lunch_end.split(':')[0])
    const blocks = []
    for (let m = startHour; m + h <= lunchStart && m + h <= endHour; m += h) {
        blocks.push(`${String(m).padStart(2, '0')}:00-${String(m + h).padStart(2, '0')}:00`)
    }
    for (let m = Math.max(startHour, lunchEnd); m + h <= endHour; m += h) {
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
    validateWorkTime()
    validateLunchTime()
    if (workTimeError.value || lunchTimeError.value) return
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
        <div class="space-y-6">
            <PageHeader title="Configuración" subtitle="Preferencias personales" />

            <form @submit.prevent="submit" class="space-y-6">

                <div v-if="serverErrorList.length"
                    class="rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 px-4 py-3">
                    <p class="text-sm font-medium text-red-700 dark:text-red-300 mb-1">No se pudo guardar:</p>
                    <ul class="list-disc list-inside text-sm text-red-600 dark:text-red-400 space-y-0.5">
                        <li v-for="(msg, i) in serverErrorList" :key="i">{{ msg }}</li>
                    </ul>
                </div>

                <!-- Fila 1: Horario + Bloques -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                    <!-- Horario laboral -->
                    <div v-if="canEdit('configure work hours')" class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-2 rounded-lg bg-indigo-100 dark:bg-indigo-900">
                                <Clock class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Horario laboral</h2>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Define el horario base para la planificación</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mb-5">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Inicio jornada</label>
                                <input type="time" v-model="form.default_work_start" @blur="validateWorkTime"
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:[color-scheme:dark] focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Fin jornada</label>
                                <input type="time" v-model="form.default_work_end" @blur="validateWorkTime"
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:[color-scheme:dark] focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                            </div>
                        </div>
                        <p v-if="workTimeError" class="text-xs text-red-500 -mt-3 mb-3">{{ workTimeError }}</p>

                        <div class="grid grid-cols-2 gap-4 mb-5">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Inicio almuerzo</label>
                                <input type="time" v-model="form.lunch_start" @blur="validateLunchTime"
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:[color-scheme:dark] focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Fin almuerzo</label>
                                <input type="time" v-model="form.lunch_end" @blur="validateLunchTime"
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:[color-scheme:dark] focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                            </div>
                        </div>
                        <p v-if="lunchTimeError" class="text-xs text-red-500 -mt-3 mb-3">{{ lunchTimeError }}</p>

                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">Días laborables</label>
                            <div class="flex gap-1.5">
                                <button v-for="d in days" :key="d.value" type="button"
                                    @click="toggleWorkingDay(d.value)"
                                    :class="[
                                        'flex-1 py-2 rounded-lg text-xs font-medium transition-colors',
                                        form.working_days.includes(d.value)
                                            ? 'bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300'
                                            : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600'
                                    ]">
                                    {{ d.label }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Bloques -->
                    <div v-if="canEdit('configure work hours')" class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 flex flex-col">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-2 rounded-lg bg-violet-100 dark:bg-violet-900/50">
                                <ToggleLeft class="w-5 h-5 text-violet-600 dark:text-violet-400" />
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Bloques de tiempo</h2>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Organiza la jornada en bloques fijos</p>
                            </div>
                        </div>

                        <div class="flex-1 space-y-4">
                            <div class="flex items-center justify-between p-4 rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700">
                                <span class="text-sm font-medium text-gray-900 dark:text-white">Activar bloques</span>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" v-model="form.use_blocks" class="sr-only peer" />
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 dark:peer-focus:ring-indigo-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-indigo-600"></div>
                                </label>
                            </div>

                            <div v-if="form.use_blocks">
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Duración del bloque</label>
                                <select v-model="form.block_hours"
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                    <option :value="1">1 hora</option>
                                    <option :value="2">2 horas</option>
                                    <option :value="3">3 horas</option>
                                    <option :value="4">4 horas</option>
                                </select>
                            </div>

                            <div v-if="form.use_blocks && previewBlocks.length > 0" class="p-4 rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700">
                                <div class="flex items-center gap-2 mb-3">
                                    <Eye class="w-4 h-4 text-gray-400" />
                                    <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">Vista previa</span>
                                </div>
                                <div class="flex flex-wrap gap-1.5">
                                    <span v-for="block in previewBlocks" :key="block"
                                        class="px-2.5 py-1 rounded-lg bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 text-xs font-mono">
                                        {{ block }}
                                    </span>
                                </div>
                            </div>

                            <div v-if="!form.use_blocks" class="p-4 rounded-xl bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-800 text-sm text-amber-700 dark:text-amber-400">
                                Con el horario libre, podrás escribir cualquier rango horario al crear o editar tareas.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Fila 2: YouTube + Dashboard -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                    <!-- YouTube -->
                    <div v-if="canEdit('configure youtube')" class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-2 rounded-lg bg-red-100 dark:bg-red-900/50">
                                <BarChart3 class="w-5 h-5 text-red-600 dark:text-red-400" />
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">YouTube</h2>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Configuración de la página de YouTube</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-4 rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700">
                                <span class="text-sm font-medium text-gray-900 dark:text-white">Gráfico de rendimiento</span>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" v-model="form.show_youtube_chart" class="sr-only peer" />
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 dark:peer-focus:ring-indigo-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-indigo-600"></div>
                                </label>
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Máximos videos recientes</label>
                                <select v-model="form.youtube_max_recent_videos"
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white text-sm focus:ring-indigo-500 focus:border-indigo-500">
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
                    <div v-if="canEdit('configure dashboard')" class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-2 rounded-lg bg-emerald-100 dark:bg-emerald-900/50">
                                <Monitor class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Dashboard y Reportes</h2>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Vistas predeterminadas</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Vista del Dashboard</label>
                                <select v-model="form.dashboard_default_view"
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="week">Semanal</option>
                                    <option value="month">Mensual</option>
                                    <option value="year">Anual</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Alcance de reportes</label>
                                <select v-model="form.default_report_scope"
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="dia">Diario</option>
                                    <option value="semanal">Semanal</option>
                                    <option value="mensual">Mensual</option>
                                    <option value="anual">Anual</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Guardar -->
                <div class="flex justify-end" v-if="can('configure work hours') || can('configure youtube') || can('configure dashboard')">
                    <PrimaryButton type="submit" :disabled="processing" class="px-8">
                        {{ processing ? 'Guardando...' : 'Guardar configuración' }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
