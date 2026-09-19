<script setup>
import { ref, computed, watch, inject, onMounted } from 'vue'
import { usePage, Link } from '@inertiajs/vue3'
import axios from 'axios'
import TextInput from '@/Components/Forms/TextInput.vue'
import PrimaryButton from '@/Components/UI/PrimaryButton.vue'
import { Plus, X, Globe, Users } from 'lucide-vue-next'

const page = usePage()
const userSettings = computed(() => page.props.auth?.user?.settings ?? {})

const form = inject('taskForm')

const props = defineProps({
    statuses: Array,
    channels: Array,
    submitLabel: { type: String, default: 'Guardar' },
    exceptTaskId: { type: Number, default: null },
    taskId: { type: Number, default: null },
    taskDate: { type: String, default: null },
    sessions: { type: Array, default: () => [] },
    isCreator: { type: Boolean, default: true },
})

const emit = defineEmits(['submit'])

const orgUsers = ref([])
const selectedUsers = ref([])
const shareRoles = ref({})
const shareSearch = ref('')

const filteredShareUsers = computed(() => {
    if (!shareSearch.value.trim()) return orgUsers.value
    const q = shareSearch.value.toLowerCase()
    return orgUsers.value.filter(u => u.name.toLowerCase().includes(q))
})

onMounted(async () => {
    try {
        const res = await axios.get('/task-shares/users')
        orgUsers.value = res.data
    } catch { /* ignore */ }

    if (form.shared_user_ids && form.shared_user_ids.length) {
        selectedUsers.value = form.shared_user_ids
    }
    if (form.shared_roles) {
        form.shared_user_ids.forEach((uid, idx) => {
            shareRoles.value[uid] = form.shared_roles[idx] || 'editor'
        })
    }
})

watch(selectedUsers, (val) => {
    form.shared_user_ids = val
    form.shared_roles = val.map(uid => shareRoles.value[uid] || 'editor')
}, { immediate: true })

const allLangLabels = { es: 'ES', en: 'EN', pt: 'PT', fr: 'FR', de: 'DE', it: 'IT', ja: 'JA', ko: 'KO', zh: 'ZH' }
const langNames = { es: 'Español', en: 'English', pt: 'Português', fr: 'Français', de: 'Deutsch', it: 'Italiano', ja: '日本語', ko: '한국어', zh: '中文' }

const userLangs = computed(() => userSettings.value?.languages || ['es'])
const availableToAdd = computed(() => userLangs.value.filter(l => !languages.value.includes(l)))

const languages = ref(['es'])
const currentLang = ref('es')

function initLanguages() {
    const existing = form.translations ? Object.keys(form.translations) : []
    const fromSettings = userLangs.value.filter(l => l !== 'es' && !existing.includes(l))
    languages.value = ['es', ...existing.filter(l => l !== 'es'), ...fromSettings]
}

initLanguages()

function getVal(field) {
    if (currentLang.value === 'es') return form[field] || ''
    return form.translations?.[currentLang.value]?.[field] ?? ''
}

function setVal(field, val) {
    if (currentLang.value === 'es') {
        form[field] = val
    } else {
        if (!form.translations) form.translations = {}
        if (!form.translations[currentLang.value]) form.translations[currentLang.value] = { title: '', script: '', copy: '' }
        form.translations[currentLang.value][field] = val
    }
}

function switchLang(lang) {
    currentLang.value = lang
}

function addLang(lang) {
    if (languages.value.includes(lang)) return
    languages.value.push(lang)
    if (!form.translations) {
        form.translations = {}
    }
    form.translations[lang] = { title: '', script: '', copy: '' }
    currentLang.value = lang
}

const confirmRemoveLang = ref(null)

function confirmRemove(lang) {
    if (lang === 'es') return
    confirmRemoveLang.value = lang
}

function removeLang(lang) {
    if (lang === 'es') return
    languages.value = languages.value.filter(l => l !== lang)
    if (form.translations) {
        delete form.translations[lang]
        if (Object.keys(form.translations).length === 0) {
            form.translations = null
        }
    }
    if (currentLang.value === lang) {
        currentLang.value = 'es'
    }
    confirmRemoveLang.value = null
}

const useBlocks = computed(() => userSettings.value.use_blocks ?? true)

const MORNING_END = 13
const AFTERNOON_START = 14

const workBlocks = computed(() => {
    if (!useBlocks.value) return []
    const h = userSettings.value.block_hours ?? 2
    const startHour = parseInt(userSettings.value.default_work_start?.split(':')[0] || '9')
    const endHour = parseInt(userSettings.value.default_work_end?.split(':')[0] || '18')
    const blocks = []
    for (let m = startHour; m + h <= Math.min(endHour, MORNING_END); m += h) {
        blocks.push(`${String(m).padStart(2, '0')}:00-${String(m + h).padStart(2, '0')}:00`)
    }
    for (let m = Math.max(startHour, AFTERNOON_START); m + h <= endHour; m += h) {
        blocks.push(`${String(m).padStart(2, '0')}:00-${String(m + h).padStart(2, '0')}:00`)
    }
    return blocks
})

const occupiedBlocks = ref([])
const loadingBlocks = ref(false)

watch(() => form.task_date, async (date) => {
    if (!date || !useBlocks.value) {
        occupiedBlocks.value = []
        return
    }
    loadingBlocks.value = true
    try {
        const params = { date }
        if (props.exceptTaskId) {
            params.except_task_id = props.exceptTaskId
        }
        const res = await axios.get('/planning/occupied-blocks', { params })
        occupiedBlocks.value = res.data.occupied || []
        const currentOccupied = form.time_range && occupiedBlocks.value.includes(form.time_range)
        if (!form.time_range || currentOccupied) {
            const free = workBlocks.value?.find(b => !occupiedBlocks.value.includes(b))
            if (free) form.time_range = free
        }
    } catch {
        occupiedBlocks.value = []
    } finally {
        loadingBlocks.value = false
    }
}, { immediate: true })

const embedUrl = computed(() => {
    const url = getVal('youtube_url')
    if (!url) return null
    const ytMatch = url.match(/(?:youtube\.com\/(?:watch\?v=|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/)
    if (ytMatch) return { src: `https://www.youtube.com/embed/${ytMatch[1]}`, type: 'youtube', shorts: url.includes('/shorts/') }
    const ttMatch = url.match(/tiktok\.com\/@[\w.-]+\/video\/(\d+)/)
    if (ttMatch) return { src: `https://www.tiktok.com/player/v1/${ttMatch[1]}`, type: 'tiktok' }
    return null
})

function parseTimeRange(range) {
    if (!range) return { start: '08:00', end: '09:00' }
    const parts = range.split('-')
    if (parts.length !== 2) return { start: '08:00', end: '09:00' }
    return { start: parts[0], end: parts[1] }
}

const parsed = parseTimeRange(form.time_range)
const timeStart = ref(parsed.start)
const timeEnd = ref(parsed.end)
const timeError = ref('')

function toMinutes(t) {
    const [h, m] = t.split(':').map(Number)
    return h * 60 + m
}

function validateTimes() {
    if (!useBlocks.value && timeStart.value && timeEnd.value && toMinutes(timeEnd.value) <= toMinutes(timeStart.value)) {
        timeError.value = 'La hora fin debe ser mayor a la hora de inicio'
        return false
    }
    timeError.value = ''
    return true
}

watch([timeStart, timeEnd], ([s, e]) => {
    if (s && e) {
        validateTimes()
        form.time_range = `${s}-${e}`
    }
}, { immediate: true })

function blockDisabled(block) {
    return occupiedBlocks.value.includes(block) && form.time_range !== block
}

const localSessions = ref([])
const showAddSession = ref(false)
const newSessionDate = ref('')
const newSessionTimeRange = ref(null)
const newSessionCompleted = ref(false)
const savingSession = ref(false)
const newSessionOccupied = ref([])

const editingSession = ref(null)
const editSessionDate = ref('')
const editSessionTimeRange = ref(null)
const editSessionCompleted = ref(false)
const savingEditSession = ref(false)
const editSessionOccupied = ref([])

const confirmDeleteSession = ref(null)
const deletingSession = ref(false)

watch(newSessionDate, async (date) => {
    if (!date || !useBlocks.value) { newSessionOccupied.value = []; return }
    try {
        const res = await axios.get('/planning/occupied-blocks', { params: { date, except_task_id: taskId } })
        newSessionOccupied.value = res.data.occupied || []
    } catch { newSessionOccupied.value = [] }
})

watch(editSessionDate, async (date) => {
    if (!date || !useBlocks.value) { editSessionOccupied.value = []; return }
    try {
        const res = await axios.get('/planning/occupied-blocks', { params: { date, except_task_id: taskId } })
        editSessionOccupied.value = res.data.occupied || []
    } catch { editSessionOccupied.value = [] }
})

function sessionBlockDisabled(block, occupied) {
    return occupied.includes(block)
}

watch(() => props.sessions, (sessions) => {
    localSessions.value = buildSessionList(sessions)
}, { immediate: true })

function buildSessionList(sessions) {
    const list = []
    if (props.taskDate) {
        list.push({
            id: null,
            date: props.taskDate,
            time_range: form.time_range,
            status: 'in_progress',
            _is_initial: true,
        })
    }
    const existing = (sessions || []).map(s => ({
        id: s.id,
        date: s.date,
        time_range: s.time_range,
        status: s.status,
        _is_initial: false,
    }))
    return [...list, ...existing]
}

async function saveSession() {
    if (!newSessionDate.value || !props.taskId) return
    savingSession.value = true
    try {
        const res = await axios.post(`/tasks/${props.taskId}/sessions`, {
            date: newSessionDate.value,
            time_range: newSessionTimeRange.value,
            status: newSessionCompleted.value ? 'completed' : 'in_progress',
        })
        localSessions.value = buildSessionList([...(props.sessions || []), res.data.session])
        showAddSession.value = false
        newSessionDate.value = ''
        newSessionTimeRange.value = null
        newSessionCompleted.value = false
    } catch {
        alert('Error al guardar la sesión')
    } finally {
        savingSession.value = false
    }
}

async function deleteSession(session) {
    if (!session?.id) return
    deletingSession.value = true
    try {
        await axios.delete(`/tasks/${props.taskId}/sessions/${session.id}`)
        const remaining = (props.sessions || []).filter(s => s.id !== session.id)
        localSessions.value = buildSessionList(remaining)
        confirmDeleteSession.value = null
    } catch {
        alert('Error al eliminar la sesión')
    } finally {
        deletingSession.value = false
    }
}

function startEditSession(session) {
    editingSession.value = session
    editSessionDate.value = session.date
    editSessionTimeRange.value = session.time_range
    editSessionCompleted.value = session.status === 'completed'
}

async function saveEditSession() {
    if (!editingSession.value?.id || !props.taskId) return
    savingEditSession.value = true
    try {
        const res = await axios.patch(`/tasks/${props.taskId}/sessions/${editingSession.value.id}`, {
            date: editSessionDate.value,
            time_range: editSessionTimeRange.value,
            status: editSessionCompleted.value ? 'completed' : 'in_progress',
        })
        const updated = (props.sessions || []).map(s =>
            s.id === editingSession.value.id ? res.data.session : s
        )
        localSessions.value = buildSessionList(updated)
        editingSession.value = null
    } catch {
        alert('Error al actualizar la sesión')
    } finally {
        savingEditSession.value = false
    }
}

function askDeleteSession(session) {
    confirmDeleteSession.value = session
}

function onSubmit() {
    if (!useBlocks.value && !validateTimes()) return
    shareSearch.value = ''
    emit('submit')
}
</script>

<template>
    <form @submit.prevent="onSubmit" class="space-y-6">
        <div class="flex items-center gap-1 flex-wrap">
            <button v-for="lang in languages" :key="lang" type="button" @click="switchLang(lang)"
                class="px-3 py-1.5 text-xs font-medium rounded-lg transition flex items-center gap-1.5"
                :class="currentLang === lang
                    ? 'bg-indigo-600 text-white shadow-sm ring-1 ring-indigo-500'
                    : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800'">
                <Globe class="w-3 h-3" />
                {{ allLangLabels[lang] || lang.toUpperCase() }}
                <span v-if="lang !== 'es'" @click.stop="confirmRemove(lang)" class="ml-0.5 p-0.5 rounded hover:bg-red-100 dark:hover:bg-red-900/30"
                    :class="currentLang === lang ? 'text-white/70 hover:text-white hover:bg-white/20' : 'text-gray-400 hover:text-red-500'">
                    <X class="w-3 h-3" />
                </span>
            </button>
            <template v-for="lang in availableToAdd" :key="'add-' + lang">
                <button type="button" @click="addLang(lang)"
                    class="px-3 py-1.5 text-xs font-medium rounded-lg transition flex items-center gap-1.5 text-gray-400 hover:text-indigo-600 hover:bg-gray-100 dark:hover:bg-gray-800">
                    <Plus class="w-3 h-3" /> {{ allLangLabels[lang] || lang.toUpperCase() }}
                </button>
            </template>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <div class="space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <TextInput v-model="form.task_date" label="Fecha" type="date" :error="form.errors.task_date" />
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ useBlocks ? 'Bloque horario' : 'Horario inicio / fin' }}
                            <span v-if="loadingBlocks" class="text-xs text-gray-400 ml-1">Verificando...</span>
                        </label>
                        <select v-if="useBlocks" v-model="form.time_range"
                            class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                            <option v-for="block in workBlocks" :key="block" :value="block"
                                :disabled="blockDisabled(block)"
                                :class="{ 'text-gray-400 dark:text-gray-600 line-through': blockDisabled(block) }">
                                {{ block }}
                                <span v-if="blockDisabled(block)">(ocupado)</span>
                            </option>
                        </select>
                        <div v-else class="flex items-center gap-2">
                            <input v-model="timeStart" type="time" @blur="validateTimes"
                                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 dark:[color-scheme:dark]" />
                            <span class="text-gray-400 font-medium">a</span>
                            <input v-model="timeEnd" type="time" @blur="validateTimes"
                                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 dark:[color-scheme:dark]" />
                        </div>
                        <p v-if="timeError && !useBlocks" class="mt-1 text-sm text-red-500">{{ timeError }}</p>
                        <div v-if="form.errors.time_range" class="mt-1 text-sm text-red-500">{{ form.errors.time_range }}</div>
                    </div>
                </div>

                <TextInput :model-value="getVal('title')" @update:model-value="v => setVal('title', v)" label="Título del video" :error="form.errors.title" />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Estado</label>
                        <select v-model="form.status"
                            class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                            <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                        <div v-if="form.errors.status" class="mt-1 text-sm text-red-500">{{ form.errors.status }}</div>
                    </div>
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Canal</label>
                        <select v-model="form.channel_id"
                            class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                            <option :value="null">Sin canal</option>
                            <option v-for="ch in channels" :key="ch.id" :value="ch.id">{{ ch.name }}</option>
                        </select>
                        <div v-if="form.errors.channel_id" class="mt-1 text-sm text-red-500">{{ form.errors.channel_id }}</div>
                    </div>
                </div>

                <div>
                    <TextInput :model-value="getVal('youtube_url')" @update:model-value="v => setVal('youtube_url', v)" label="URL de YouTube / TikTok" type="url" :error="form.errors.youtube_url" />
                    <div v-if="embedUrl" class="mt-2">
                        <iframe v-if="embedUrl.type === 'youtube' && !embedUrl.shorts" :src="embedUrl.src"
                            class="w-full aspect-video rounded-xl"
                            frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen>
                        </iframe>
                        <iframe v-else :src="embedUrl.src"
                            class="rounded-xl mx-auto w-full max-w-[325px] aspect-[9/16]"
                            frameborder="0"
                            allowfullscreen>
                        </iframe>
                    </div>
                </div>

                <div v-if="isCreator && orgUsers.length" class="p-4 rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center gap-2 mb-3">
                        <Users class="w-4 h-4 text-gray-400" />
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Compartir con</span>
                    </div>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mb-3">Solo los usuarios seleccionados podran ver esta tarea</p>
                    <div class="relative mb-3">
                        <input v-model="shareSearch" type="text" placeholder="Buscar usuario..."
                            class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                    </div>
                    <div v-if="selectedUsers.length" class="space-y-2 mb-3">
                        <div v-for="uid in selectedUsers" :key="uid"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg bg-indigo-50 dark:bg-indigo-900/30">
                            <span class="flex-1 text-sm font-medium text-indigo-700 dark:text-indigo-300">
                                {{ orgUsers.find(u => u.id === uid)?.name || uid }}
                            </span>
                            <select :value="shareRoles[uid] || 'editor'"
                                @change="shareRoles[uid] = $event.target.value; form.shared_roles = selectedUsers.map(id => shareRoles[id] || 'editor')"
                                class="text-xs rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white py-1 px-2">
                                <option value="editor">Editor</option>
                                <option value="reader">Lector</option>
                            </select>
                            <button type="button" @click="selectedUsers = selectedUsers.filter(id => id !== uid)"
                                class="p-1 rounded hover:bg-indigo-200 dark:hover:bg-indigo-800 transition text-indigo-500">
                                <X class="w-3 h-3" />
                            </button>
                        </div>
                    </div>
                    <div class="max-h-40 overflow-y-auto space-y-1">
                        <label v-for="user in filteredShareUsers" :key="user.id"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm cursor-pointer transition"
                            :class="selectedUsers.includes(user.id)
                                ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300'
                                : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800'">
                            <input type="checkbox" :value="user.id" v-model="selectedUsers"
                                class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500" />
                            {{ user.name }}
                        </label>
                        <p v-if="shareSearch && filteredShareUsers.length === 0" class="text-xs text-gray-400 dark:text-gray-500 text-center py-2">
                            Sin resultados
                        </p>
                    </div>
                </div>
            </div>

            <div class="space-y-5">
                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Guion</label>
                    <textarea :value="getVal('script')" @input="e => setVal('script', e.target.value)" rows="10"
                        class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 font-mono text-sm"></textarea>
                    <div v-if="form.errors.script" class="mt-1 text-sm text-red-500">{{ form.errors.script }}</div>
                </div>

                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Copy / Descripción</label>
                    <textarea :value="getVal('copy')" @input="e => setVal('copy', e.target.value)" rows="6"
                        class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 font-mono text-sm"></textarea>
                    <div v-if="form.errors.copy" class="mt-1 text-sm text-red-500">{{ form.errors.copy }}</div>
                </div>

            </div>
        </div>

        <div v-if="taskId" class="border-t border-gray-200 dark:border-gray-700 pt-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Sesiones de trabajo</h3>
            <p class="text-xs text-gray-400 dark:text-gray-500 mb-4">
                La sesión inicial se crea al generar la tarea en la fecha: <strong>{{ taskDate }}</strong>.
                Agrega sesiones adicionales si el trabajo continúa en otros días.
            </p>
            <div v-if="localSessions.length > 0" class="space-y-2 mb-4">
                <div v-for="(session, idx) in localSessions" :key="session.id || idx"
                    class="flex items-center gap-3 bg-gray-50 dark:bg-gray-900 rounded-xl px-4 py-3 text-sm">
                    <div class="flex-1 flex items-center gap-4">
                        <span class="text-gray-700 dark:text-gray-300 font-medium">{{ session.date }}</span>
                        <span class="text-gray-500 dark:text-gray-400">{{ session.time_range || '—' }}</span>
                        <span v-if="session._is_initial"
                            class="text-xs px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300 font-medium">
                            Inicial
                        </span>
                        <span v-else
                            class="text-xs px-2 py-0.5 rounded-full font-medium"
                            :class="session.status === 'completed'
                                ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                                : 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300'">
                            {{ session.status === 'completed' ? 'Completado' : 'En progreso' }}
                        </span>
                    </div>
                    <button v-if="!session._is_initial && taskId" type="button" @click="startEditSession(session)"
                        class="p-1.5 rounded-lg hover:bg-indigo-100 dark:hover:bg-indigo-900/30 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </button>
                    <button v-if="!session._is_initial && taskId" type="button" @click="askDeleteSession(session)"
                        class="p-1.5 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/30 text-gray-400 hover:text-red-500 transition">
                        <X class="w-4 h-4" />
                    </button>
                </div>
            </div>
            <div v-else class="text-sm text-gray-400 dark:text-gray-500 mb-4 italic">
                Sin sesiones adicionales.
            </div>
            <button type="button" @click="showAddSession = true"
                class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium transition flex items-center gap-1.5">
                <Plus class="w-3.5 h-3.5" /> Agregar sesión
            </button>
        </div>

        <Teleport to="body">
        <div v-if="showAddSession" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40" @click.self="showAddSession = false">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-6 w-full max-w-md mx-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Nueva sesión de trabajo</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Fecha</label>
                        <input v-model="newSessionDate" type="date" :min="taskDate"
                            class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500" />
                    </div>
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Bloque horario</label>
                        <select v-model="newSessionTimeRange"
                            class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                            <option :value="null">Sin bloque</option>
                            <option v-for="block in workBlocks" :key="block" :value="block" :disabled="sessionBlockDisabled(block, newSessionOccupied)">
                                {{ block }}<template v-if="sessionBlockDisabled(block, newSessionOccupied)"> (ocupado)</template>
                            </option>
                        </select>
                    </div>
                    <div class="flex items-center gap-2">
                        <input v-model="newSessionCompleted" type="checkbox" id="session-completed"
                            class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500" />
                        <label for="session-completed" class="text-sm text-gray-700 dark:text-gray-300">Marcar como completado</label>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" @click="showAddSession = false"
                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition">
                        Cancelar
                    </button>
                    <button type="button" @click="saveSession"
                        class="px-4 py-2 text-sm font-medium bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl transition"
                        :disabled="savingSession">
                        {{ savingSession ? 'Guardando...' : 'Guardar sesión' }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="editingSession" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50" @click.self="editingSession = null">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-md p-6 mx-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Editar sesión</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Fecha</label>
                        <input v-model="editSessionDate" type="date" :min="taskDate"
                            class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500" />
                    </div>
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Bloque horario</label>
                        <select v-model="editSessionTimeRange"
                            class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                            <option :value="null">Sin bloque</option>
                            <option v-for="block in workBlocks" :key="block" :value="block" :disabled="sessionBlockDisabled(block, editSessionOccupied)">
                                {{ block }}<template v-if="sessionBlockDisabled(block, editSessionOccupied)"> (ocupado)</template>
                            </option>
                        </select>
                    </div>
                    <div class="flex items-center gap-2">
                        <input v-model="editSessionCompleted" type="checkbox" id="edit-session-completed"
                            class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500" />
                        <label for="edit-session-completed" class="text-sm text-gray-700 dark:text-gray-300">Marcar como completado</label>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" @click="editingSession = null"
                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition">
                        Cancelar
                    </button>
                    <button type="button" @click="saveEditSession"
                        class="px-4 py-2 text-sm font-medium bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl transition"
                        :disabled="savingEditSession">
                        {{ savingEditSession ? 'Guardando...' : 'Guardar' }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="confirmDeleteSession" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50" @click.self="confirmDeleteSession = null">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-md p-6 mx-4">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-3 rounded-xl bg-red-100 dark:bg-red-900">
                        <X class="w-5 h-5 text-red-600 dark:text-red-400" />
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Eliminar sesión</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Esta acción no se puede deshacer</p>
                    </div>
                </div>
                <p class="text-sm text-gray-700 dark:text-gray-300 mb-6 px-2">
                    ¿Eliminar la sesión del <strong>{{ confirmDeleteSession.date }}</strong>?
                </p>
                <div class="flex justify-end gap-3">
                    <button @click="confirmDeleteSession = null"
                        class="px-4 py-2 rounded-xl border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        Cancelar
                    </button>
                    <button @click="deleteSession(confirmDeleteSession)"
                        class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white text-sm font-medium flex items-center gap-2 transition"
                        :disabled="deletingSession">
                        <X class="w-4 h-4" /> {{ deletingSession ? 'Eliminando...' : 'Eliminar' }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="confirmRemoveLang" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50" @click.self="confirmRemoveLang = null">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-md p-6 mx-4">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-3 rounded-xl bg-red-100 dark:bg-red-900">
                        <X class="w-5 h-5 text-red-600 dark:text-red-400" />
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Eliminar idioma</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Esta acción no se puede deshacer</p>
                    </div>
                </div>
                <p class="text-sm text-gray-700 dark:text-gray-300 mb-6 px-2">
                    ¿Eliminar el idioma <strong>{{ langNames[confirmRemoveLang] || confirmRemoveLang.toUpperCase() }}</strong>? Los datos se perderán.
                </p>
                <div class="flex justify-end gap-3">
                    <button @click="confirmRemoveLang = null"
                        class="px-4 py-2 rounded-xl border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        Cancelar
                    </button>
                    <button @click="removeLang(confirmRemoveLang)"
                        class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white text-sm font-medium flex items-center gap-2 transition">
                        <X class="w-4 h-4" /> Eliminar
                    </button>
                </div>
            </div>
        </div>
        </Teleport>

        <div class="flex justify-end gap-3">
            <Link :href="`/tasks/${props.taskId}`"
                class="px-5 py-3 rounded-xl bg-red-50 hover:bg-red-100 dark:bg-red-900/20 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 font-medium transition text-sm">
                Cancelar
            </Link>
            <PrimaryButton :disabled="form.processing">{{ submitLabel }}</PrimaryButton>
        </div>
    </form>
</template>
