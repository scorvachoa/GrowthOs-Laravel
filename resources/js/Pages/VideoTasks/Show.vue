<script setup>
import { ref, computed, watch } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import ConfirmDeleteModal from '@/Components/Modals/ConfirmDelete.vue'
import { Copy, Check, ExternalLink, Globe, Users, Share2, Pencil, Trash2, Plus, X, ArrowLeft } from 'lucide-vue-next'
import ShareModal from '@/Components/Modals/ShareModal.vue'
import axios from 'axios'

const page = usePage()
const permissions = page.props.auth?.user?.permissions ?? []
const currentUserId = page.props.auth?.user?.id
const isAdmin = computed(() => {
    const roles = page.props.auth?.user?.roles ?? []
    return roles.includes('Super Admin') || roles.includes('Admin')
})
const can = (perm) => permissions.includes(perm)
const canDeleteTask = computed(() => props.task.created_by === currentUserId || isAdmin.value)

const props = defineProps({
    task: Object,
    statuses: Array,
    channels: Array,
    activities: { type: Array, default: () => [] },
})

const statusLabel = (value) => {
    const found = props.statuses.find(s => s.value === value)
    return found ? found.label : value
}

const statusColor = (value) => {
    const colors = {
        pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        script_ready: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        editing: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
        review: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
        scheduled: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
        published: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    }
    return colors[value] || 'bg-gray-100 text-gray-800'
}

const showDeleteModal = ref(false)
const showShareModal = ref(false)

const userSettings = computed(() => page.props.auth?.user?.settings ?? {})
const useBlocks = computed(() => userSettings.value.use_blocks ?? true)
const MORNING_END = 14
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

const showCreateSessionModal = ref(false)
const newSessionDate = ref('')
const newSessionTimeRange = ref(null)
const newSessionCompleted = ref(false)
const newSessionOccupied = ref([])
const savingSession = ref(false)

const showEditSessionModal = ref(false)
const editingSession = ref(null)
const editSessionDate = ref('')
const editSessionTimeRange = ref(null)
const editSessionCompleted = ref(false)
const editSessionOccupied = ref([])
const savingEditSession = ref(false)

function sessionBlockDisabled(block, occupied) {
    return occupied.includes(block)
}

watch(newSessionDate, async (date) => {
    if (!date || !useBlocks.value) { newSessionOccupied.value = []; return }
    try {
        const res = await axios.get('/planning/occupied-blocks', { params: { date, except_task_id: props.task.id } })
        newSessionOccupied.value = res.data.occupied || []
    } catch { newSessionOccupied.value = [] }
})

watch(editSessionDate, async (date) => {
    if (!date || !useBlocks.value) { editSessionOccupied.value = []; return }
    try {
        const res = await axios.get('/planning/occupied-blocks', { params: { date, except_task_id: props.task.id } })
        editSessionOccupied.value = res.data.occupied || []
    } catch { editSessionOccupied.value = [] }
})

const confirmDelete = () => {
    router.delete(route('tasks.destroy', props.task.id), {
        onSuccess: () => showDeleteModal.value = false,
    })
}

const copiedKey = ref(null)
function copyText(text, key) {
    navigator.clipboard.writeText(text)
    copiedKey.value = key
    setTimeout(() => { copiedKey.value = null }, 1500)
}

const expandedActivity = ref(null)

function truncate(str, len) {
    if (!str) return ''
    return str.length > len ? str.slice(0, len) + '...' : str
}

function toggleActivity(id) {
    expandedActivity.value = expandedActivity.value === id ? null : id
}

const attrOrder = [
    'title', 'script', 'copy', 'youtube_url', 'channel_id',
    'status', 'task_date', 'time_range', 'shared_user_ids', 'shared_roles',
]

const attrLabels = {
    title: 'Título',
    script: 'Guion',
    copy: 'Copy',
    youtube_url: 'URL',
    channel_id: 'Canal',
    status: 'Estado',
    task_date: 'Fecha',
    time_range: 'Bloque horario',
    shared_user_ids: 'Compartido con',
    shared_roles: 'Roles',
    created_at: 'Creado',
    updated_at: 'Actualizado',
    deleted_at: 'Eliminado',
}

const statusLabels = {
    pending: 'Pendiente',
    script_ready: 'Guion listo',
    editing: 'Edición',
    review: 'Revisión',
    scheduled: 'Programado',
    published: 'Publicado',
    cancelled: 'Cancelado',
    in_progress: 'En progreso',
    completed: 'Completado',
}

function formatAttrValue(key, val) {
    if (val === null || val === '' || val === undefined) return null
    if (key === 'status') return statusLabels[val] || val
    if (key === 'channel_id') {
        const ch = props.channels.find(c => c.id === Number(val))
        return ch ? ch.name : `Canal #${val}`
    }
    if (key === 'task_date') {
        const d = new Date(val + (val.includes('T') ? '' : 'T00:00:00'))
        return d.toLocaleDateString('es-PE', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' })
    }
    if (Array.isArray(val)) {
        const userNames = val.map(id => {
            const u = props.activities?.length ? null : null
            return `#${id}`
        })
        return val.join(', ')
    }
    return String(val)
}

function formatAttrLabel(key) {
    return attrLabels[key] || key
}

function sortedAttrs(attributes) {
    if (!attributes) return []
    return attrOrder
        .filter(k => {
            const v = attributes[k]
            return v !== null && v !== '' && v !== undefined
        })
        .map(k => ({ key: k, val: attributes[k] }))
}

function openEditSession(session) {
    editingSession.value = session
    editSessionDate.value = session.date
    editSessionTimeRange.value = session.time_range
    editSessionCompleted.value = session.status === 'completed'
    showEditSessionModal.value = true
}

async function saveEditSession() {
    if (!editingSession.value?.id) return
    savingEditSession.value = true
    try {
        await axios.patch(`/tasks/${props.task.id}/sessions/${editingSession.value.id}`, {
            date: editSessionDate.value,
            time_range: editSessionTimeRange.value,
            status: editSessionCompleted.value ? 'completed' : 'in_progress',
        })
        showEditSessionModal.value = false
        editingSession.value = null
        router.reload({ only: ['task'] })
    } catch (e) {
        console.error('Failed to edit session', e)
    } finally {
        savingEditSession.value = false
    }
}

function deleteSession(session) {
    if (!confirm('¿Eliminar esta sesión?')) return
    axios.delete(`/tasks/${props.task.id}/sessions/${session.id}`).then(() => {
        router.reload({ only: ['task'] })
    }).catch(e => console.error('Failed to delete session', e))
}

function openCreateSession() {
    const today = new Date().toISOString().split('T')[0]
    newSessionDate.value = today
    newSessionTimeRange.value = null
    newSessionCompleted.value = false
    showCreateSessionModal.value = true
}

async function saveCreateSession() {
    if (!newSessionDate.value) return
    savingSession.value = true
    try {
        await axios.post(`/tasks/${props.task.id}/sessions`, {
            date: newSessionDate.value,
            time_range: newSessionTimeRange.value,
            status: newSessionCompleted.value ? 'completed' : 'in_progress',
        })
        showCreateSessionModal.value = false
        router.reload({ only: ['task'] })
    } catch (e) {
        console.error('Failed to create session', e)
    } finally {
        savingSession.value = false
    }
}

const translations = computed(() => props.task.translations || {})
const langs = computed(() => {
    const keys = Object.keys(translations.value)
    const withContent = keys.filter(k =>
        translations.value[k]?.title ||
        translations.value[k]?.script ||
        translations.value[k]?.copy ||
        translations.value[k]?.youtube_url
    )
    return ['es', ...withContent]
})
const langLabels = { es: 'ES', en: 'EN', pt: 'PT', fr: 'FR', de: 'DE', it: 'IT', ja: 'JA', ko: 'KO', zh: 'ZH' }
const currentLang = ref('es')

const currentTitle = computed(() => {
    if (currentLang.value === 'es') return props.task.title
    return translations.value[currentLang.value]?.title || ''
})
const currentScript = computed(() => {
    if (currentLang.value === 'es') return props.task.script
    return translations.value[currentLang.value]?.script || ''
})
const currentCopy = computed(() => {
    if (currentLang.value === 'es') return props.task.copy
    return translations.value[currentLang.value]?.copy || ''
})

const currentYoutubeUrl = computed(() => {
    if (currentLang.value === 'es') return props.task.youtube_url
    return translations.value[currentLang.value]?.youtube_url || ''
})

const embedUrl = computed(() => {
    const url = currentYoutubeUrl.value
    if (!url) return null
    const ytMatch = url.match(/(?:youtube\.com\/(?:watch\?v=|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/)
    if (ytMatch) return { src: `https://www.youtube.com/embed/${ytMatch[1]}`, type: 'youtube', shorts: url.includes('/shorts/') }
    const ttMatch = url.match(/tiktok\.com\/@[\w.-]+\/video\/(\d+)/)
    if (ttMatch) return { src: `https://www.tiktok.com/player/v1/${ttMatch[1]}`, type: 'tiktok' }
    return null
})
</script>

<template>
    <AppLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link href="/planning"
                        class="p-2 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700 transition text-gray-500 dark:text-gray-400">
                        <ArrowLeft class="w-5 h-5" />
                    </Link>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ task.title }}</h1>
                    <span class="px-3 py-1 rounded-full text-xs font-semibold" :class="statusColor(task.status)">
                        {{ statusLabel(task.status) }}
                    </span>
                </div>
                <div class="flex items-center gap-3">
                    <button v-if="!task.shared_by_user_name" @click="showShareModal = true"
                        class="px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition flex items-center gap-2">
                        <Share2 class="w-4 h-4" />
                        Compartir
                    </button>
                    <Link v-if="can('edit planning')" :href="`/tasks/${task.id}/edit`"
                        class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium transition">
                        Editar
                    </Link>
                    <button v-if="canDeleteTask" @click="showDeleteModal = true"
                        class="px-4 py-2 rounded-xl bg-red-50 hover:bg-red-100 dark:bg-red-900/20 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 text-sm font-medium transition">
                        Eliminar
                    </button>
                </div>
            </div>

            <!-- Meta info -->
            <div class="flex items-center gap-4 text-sm text-gray-500 dark:text-gray-400">
                <span class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-indigo-400"></span>
                    {{ task.task_date }}
                </span>
                <span>{{ task.time_range }}</span>
                <span v-if="task.channel" class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full" :style="{ backgroundColor: task.channel.color }"></span>
                    {{ task.channel.name }}
                </span>
            </div>

            <!-- Shared by -->
            <div v-if="task.shared_by_user_name" class="flex items-center gap-2 px-4 py-3 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800">
                <Users class="w-4 h-4 text-indigo-500 dark:text-indigo-400" />
                <span class="text-sm text-indigo-700 dark:text-indigo-300">Compartido por <strong>{{ task.shared_by_user_name }}</strong></span>
            </div>

            <!-- Shared with -->
            <div v-else-if="task.shared_with_users?.length" class="flex items-center justify-between px-4 py-3 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800">
                <div class="flex items-center gap-2">
                    <Users class="w-4 h-4 text-indigo-500 dark:text-indigo-400" />
                    <span class="text-sm font-medium text-indigo-700 dark:text-indigo-300">Compartido con</span>
                </div>
                <div class="flex flex-wrap gap-2">
                    <span v-for="u in task.shared_with_users" :key="u.id"
                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium"
                        :class="u.accepted
                            ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300'
                            : 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300'">
                        {{ u.name }}
                        <span class="text-[10px] opacity-70">({{ u.accepted ? (u.role === 'editor' ? 'editor' : 'lector') : 'pendiente' }})</span>
                    </span>
                </div>
            </div>

            <!-- Language tabs -->
            <div v-if="langs.length > 1" class="flex items-center gap-1">
                <button v-for="lang in langs" :key="lang" @click="currentLang = lang"
                    class="px-3 py-1.5 text-xs font-medium rounded-lg transition flex items-center gap-1.5"
                    :class="currentLang === lang
                        ? 'bg-indigo-600 text-white shadow-sm'
                        : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800'">
                    <Globe class="w-3 h-3" />
                    {{ langLabels[lang] || lang.toUpperCase() }}
                </button>
            </div>

            <!-- Title card -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
                <div class="flex items-center justify-between mb-2">
                    <h2 class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Título del video</h2>
                    <button @click="copyText(currentTitle, 'title')"
                        class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                        <Check v-if="copiedKey === 'title'" class="w-4 h-4 text-green-500" />
                        <Copy v-else class="w-4 h-4" />
                    </button>
                </div>
                <p class="text-gray-900 dark:text-white font-medium">{{ currentTitle }}</p>
            </div>

            <!-- Content grid -->
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
                <!-- Script -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
                    <div class="flex items-center justify-between mb-2">
                        <h2 class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Guion</h2>
                        <button v-if="currentScript" @click="copyText(currentScript, 'script')"
                            class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                            <Check v-if="copiedKey === 'script'" class="w-4 h-4 text-green-500" />
                            <Copy v-else class="w-4 h-4" />
                        </button>
                    </div>
                    <p v-if="currentScript" class="text-gray-900 dark:text-white whitespace-pre-wrap text-sm leading-relaxed">{{ currentScript }}</p>
                    <p v-else class="text-gray-400 dark:text-gray-500 text-sm italic">Sin guion</p>
                </div>

                <!-- Copy -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
                    <div class="flex items-center justify-between mb-2">
                        <h2 class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Copy / Descripción</h2>
                        <button v-if="currentCopy" @click="copyText(currentCopy, 'copy')"
                            class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                            <Check v-if="copiedKey === 'copy'" class="w-4 h-4 text-green-500" />
                            <Copy v-else class="w-4 h-4" />
                        </button>
                    </div>
                    <p v-if="currentCopy" class="text-gray-900 dark:text-white whitespace-pre-wrap text-sm leading-relaxed">{{ currentCopy }}</p>
                    <p v-else class="text-gray-400 dark:text-gray-500 text-sm italic">Sin copy</p>
                </div>

                <!-- Video -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5 flex flex-col">
                    <div class="flex items-center justify-between mb-2">
                        <h2 class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Video</h2>
                        <button v-if="currentYoutubeUrl" @click="copyText(currentYoutubeUrl, 'url')"
                            class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                            <Check v-if="copiedKey === 'url'" class="w-4 h-4 text-green-500" />
                            <Copy v-else class="w-4 h-4" />
                        </button>
                    </div>
                    <iframe v-if="embedUrl && embedUrl.type === 'youtube' && !embedUrl.shorts" :src="embedUrl.src"
                        class="w-full aspect-video rounded-xl"
                        frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                        referrerpolicy="strict-origin-when-cross-origin"
                        allowfullscreen>
                    </iframe>
                    <iframe v-else-if="embedUrl" :src="embedUrl.src"
                        class="rounded-xl mx-auto w-full max-w-[325px] aspect-[9/16]"
                        frameborder="0"
                        allowfullscreen>
                    </iframe>
                    <a v-if="currentYoutubeUrl" :href="currentYoutubeUrl" target="_blank"
                        class="mt-2 text-xs text-gray-400 hover:text-red-600 dark:hover:text-red-400 truncate inline-flex items-center gap-1">
                        <ExternalLink class="w-3 h-3 flex-shrink-0" />
                        {{ currentYoutubeUrl }}
                    </a>
                    <p v-if="!currentYoutubeUrl" class="text-gray-400 dark:text-gray-500 text-sm italic text-center py-10 mt-auto">Sin enlace de video</p>
                </div>
            </div>

            <!-- Sessions -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Sesiones de trabajo</h3>
                <p class="text-xs text-gray-400 dark:text-gray-500 mb-4">
                    La sesión inicial se crea al generar la tarea en la fecha: <strong>{{ task.task_date }}</strong>.
                    Agrega sesiones adicionales si el trabajo continúa en otros días.
                </p>
                <div class="space-y-2 mb-4">
                    <div class="flex items-center gap-3 bg-gray-50 dark:bg-gray-900 rounded-xl px-4 py-3 text-sm">
                        <div class="flex-1 flex items-center gap-4">
                            <span class="text-gray-700 dark:text-gray-300 font-medium">{{ task.task_date }}</span>
                            <span class="text-gray-500 dark:text-gray-400">{{ task.time_range || '—' }}</span>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300 font-medium">
                                Inicial
                            </span>
                        </div>
                    </div>
                    <div v-for="session in task.sessions" :key="session.id"
                        class="flex items-center gap-3 bg-gray-50 dark:bg-gray-900 rounded-xl px-4 py-3 text-sm">
                        <div class="flex-1 flex items-center gap-4">
                            <span class="text-gray-700 dark:text-gray-300 font-medium">{{ session.date }}</span>
                            <span class="text-gray-500 dark:text-gray-400">{{ session.time_range || '—' }}</span>
                            <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                                :class="session.status === 'completed'
                                    ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                                    : 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300'">
                                {{ session.status === 'completed' ? 'Completado' : 'En progreso' }}
                            </span>
                        </div>
                        <button type="button" @click="openEditSession(session)"
                            class="p-1.5 rounded-lg hover:bg-indigo-100 dark:hover:bg-indigo-900/30 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </button>
                        <button type="button" @click="deleteSession(session)"
                            class="p-1.5 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/30 text-gray-400 hover:text-red-500 transition">
                            <X class="w-4 h-4" />
                        </button>
                    </div>
                </div>
                <button type="button" @click="openCreateSession"
                    class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium transition flex items-center gap-1.5">
                    <Plus class="w-3.5 h-3.5" /> Agregar sesión
                </button>
            </div>

            <!-- Activity log -->
            <div v-if="activities.length" class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Historial de cambios</h3>
                <div class="relative">
                    <div class="absolute left-4 top-3 bottom-3 w-px bg-gray-200 dark:bg-gray-700"></div>
                    <div class="space-y-4">
                        <div v-for="activity in activities" :key="activity.id"
                            class="relative flex items-start gap-4 pl-1 cursor-pointer"
                            @click="toggleActivity(activity.id)">
                            <div class="w-7 h-7 rounded-full bg-gradient-to-br from-indigo-500 to-purple-500 flex items-center justify-center flex-shrink-0 z-10 ring-2 ring-white dark:ring-gray-800">
                                <span class="text-[10px] font-bold text-white">
                                    {{ activity.causer_name.charAt(0).toUpperCase() }}
                                </span>
                            </div>
                            <div class="flex-1 min-w-0 pb-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ activity.causer_name }}</span>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ activity.description }}</span>
                                    <svg v-if="activity.properties?.attributes" class="w-3.5 h-3.5 text-gray-400 transition-transform" :class="{ 'rotate-180': expandedActivity === activity.id }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                                <div v-if="activity.properties?.attributes" class="mt-1.5">
                                    <div v-if="expandedActivity !== activity.id" class="flex flex-wrap gap-1.5">
                                        <span v-for="attr in sortedAttrs(activity.properties.attributes)" :key="attr.key"
                                            class="inline-flex items-center px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-700 text-xs font-medium text-gray-600 dark:text-gray-400">
                                            {{ formatAttrLabel(attr.key) }}
                                        </span>
                                    </div>
                                    <div v-else class="bg-gray-50 dark:bg-gray-900 rounded-xl p-3 mt-1 space-y-1.5">
                                        <div v-for="attr in sortedAttrs(activity.properties.attributes)" :key="attr.key"
                                            class="flex items-start gap-2 text-xs">
                                            <span class="font-semibold text-gray-500 dark:text-gray-400 min-w-[120px] shrink-0">{{ formatAttrLabel(attr.key) }}</span>
                                            <span class="text-gray-700 dark:text-gray-300 break-all">{{ formatAttrValue(attr.key, attr.val) }}</span>
                                        </div>
                                    </div>
                                </div>
                                <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-1.5 flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    {{ activity.created_at }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modals -->
            <ConfirmDeleteModal
                :show="showDeleteModal"
                title="Eliminar tarea"
                message="¿Eliminar esta tarea de planificación?"
                @close="showDeleteModal = false"
                @confirm="confirmDelete"
            />

            <ShareModal
                :show="showShareModal"
                :shareable-type="'video_tasks'"
                :shareable-id="task.id"
                :current-shares="task.shared_with_users || []"
                @close="showShareModal = false"
                @saved="router.reload({ only: ['task'] })"
            />

            <Teleport to="body">
                <div v-if="showEditSessionModal" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50" @click.self="showEditSessionModal = false">
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-md p-6 mx-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Editar sesión</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Fecha</label>
                                <input v-model="editSessionDate" type="date" :min="task.task_date"
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
                            <button type="button" @click="showEditSessionModal = false"
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
            </Teleport>

            <Teleport to="body">
                <div v-if="showCreateSessionModal" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40" @click.self="showCreateSessionModal = false">
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-6 w-full max-w-md mx-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Nueva sesión de trabajo</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Fecha</label>
                                <input v-model="newSessionDate" type="date" :min="task.task_date"
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
                            <button type="button" @click="showCreateSessionModal = false"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition">
                                Cancelar
                            </button>
                            <button type="button" @click="saveCreateSession"
                                class="px-4 py-2 text-sm font-medium bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl transition"
                                :disabled="savingSession">
                                {{ savingSession ? 'Guardando...' : 'Guardar sesión' }}
                            </button>
                        </div>
                    </div>
                </div>
            </Teleport>
        </div>
    </AppLayout>
</template>
