<script setup>
import { reactive, ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PrimaryButton from '@/Components/UI/PrimaryButton.vue'
import { ToggleLeft, Clock, Eye, BarChart3, Monitor, HardDrive, Globe, X, Plus } from 'lucide-vue-next'

const page = usePage()
const permissions = page.props.auth?.user?.permissions ?? []
const can = (perm) => permissions.includes(perm)
const canEdit = (section) => can(section)

const props = defineProps({
    settings: Object,
    workBlocks: Array,
    backup_schedule: Object,
})

const scheduleForm = reactive({
    time: props.backup_schedule?.time || '03:00',
    day: props.backup_schedule?.day || 'sunday',
})

const scheduleProcessing = ref(false)

function saveSchedule() {
    scheduleProcessing.value = true
    router.post(route('settings.backup-schedule'), { ...scheduleForm }, {
        preserveState: false,
        preserveScroll: true,
        onFinish: () => { scheduleProcessing.value = false },
    })
}

const dayLabels = {
    monday: 'Lunes', tuesday: 'Martes', wednesday: 'Miércoles',
    thursday: 'Jueves', friday: 'Viernes', saturday: 'Sábado', sunday: 'Domingo',
}

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
    languages: [...(props.settings.languages || ['es'])],
})

const processing = ref(false)
const workTimeError = ref('')
const lunchTimeError = ref('')

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

const allAvailableLangs = [
    { code: 'en', label: 'English' },
    { code: 'pt', label: 'Português' },
    { code: 'fr', label: 'Français' },
    { code: 'de', label: 'Deutsch' },
    { code: 'it', label: 'Italiano' },
    { code: 'ja', label: '日本語' },
    { code: 'ko', label: '한국어' },
    { code: 'zh', label: '中文' },
]

const availableToAdd = computed(() =>
    allAvailableLangs.filter(l => !(form.languages || []).includes(l.code))
)

const newLang = ref('')

function addLanguage() {
    if (!newLang.value || (form.languages || []).includes(newLang.value)) return
    form.languages = [...(form.languages || []), newLang.value]
    newLang.value = ''
}

function removeLanguage(lang) {
    if (lang === 'es') return
    form.languages = (form.languages || []).filter(l => l !== lang)
}

const langNames = { es: 'Español', en: 'English', pt: 'Português', fr: 'Français', de: 'Deutsch', it: 'Italiano', ja: '日本語', ko: '한국어', zh: '中文' }

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
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Configuración</h1>
                <p class="text-gray-500 dark:text-gray-400 mt-1">Preferencias personales</p>
            </div>

            <form @submit.prevent="submit" class="space-y-6">

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

                <!-- Fila 3: Idiomas + Backup -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                    <!-- Idiomas -->
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-2 rounded-lg bg-sky-100 dark:bg-sky-900/50">
                                <Globe class="w-5 h-5 text-sky-600 dark:text-sky-400" />
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Idiomas</h2>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Idiomas disponibles para traducciones en las tareas</p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2 mb-4">
                            <span v-for="lang in form.languages" :key="lang"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium"
                                :class="lang === 'es'
                                    ? 'bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300'
                                    : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300'">
                                <Globe class="w-3.5 h-3.5" />
                                {{ langNames[lang] || lang.toUpperCase() }}
                                <button v-if="lang !== 'es'" type="button" @click="removeLanguage(lang)"
                                    class="p-0.5 rounded hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                                    <X class="w-3 h-3" />
                                </button>
                            </span>
                        </div>

                        <div class="flex items-center gap-2">
                            <select v-model="newLang"
                                class="flex-1 rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="" disabled>Seleccionar idioma...</option>
                                <option v-for="l in availableToAdd" :key="l.code" :value="l.code">{{ l.label }} ({{ l.code.toUpperCase() }})</option>
                            </select>
                            <button type="button" @click="addLanguage" :disabled="!newLang"
                                class="p-2.5 rounded-xl bg-sky-100 dark:bg-sky-900/50 hover:bg-sky-200 dark:hover:bg-sky-900 text-sky-600 dark:text-sky-400 transition disabled:opacity-50 disabled:cursor-not-allowed"
                                title="Agregar idioma">
                                <Plus class="w-4 h-4" />
                            </button>
                        </div>
                    </div>

                    <!-- Backup automático -->
                    <div v-if="canEdit('configure backup')" class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-2 rounded-lg bg-amber-100 dark:bg-amber-900/50">
                                <HardDrive class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Backup automático</h2>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Programa la generación automática de backups</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Hora</label>
                                    <input type="time" v-model="scheduleForm.time"
                                        class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:[color-scheme:dark] text-sm focus:ring-indigo-500 focus:border-indigo-500" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Día</label>
                                    <select v-model="scheduleForm.day"
                                        class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                        <option v-for="(label, key) in dayLabels" :key="key" :value="key">{{ label }}</option>
                                    </select>
                                </div>
                            </div>
                            <PrimaryButton @click="saveSchedule" :disabled="scheduleProcessing" class="w-full justify-center">
                                {{ scheduleProcessing ? 'Guardando...' : 'Guardar horario de backup' }}
                            </PrimaryButton>
                            <p class="text-xs text-gray-400 text-center">Los backups se guardan en el servidor y están disponibles en la página de Backup.</p>
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
