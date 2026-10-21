<script setup>
import { ref, computed, watch } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import ConfirmDeleteModal from '@/Components/Modals/ConfirmDelete.vue'
import { Copy, Check, ExternalLink, Users, Share2, Plus, X, ArrowLeft, ChevronLeft, ChevronRight, Download, Languages, Loader2 } from 'lucide-vue-next'
import ShareModal from '@/Components/Modals/ShareModal.vue'
import CompleteVideoUrlModal from '@/Pages/Planning/Components/CompleteVideoUrlModal.vue'
import axios from 'axios'
import { statusLabels } from '@/config/statusConstants'
import { usePermissions } from '@/Composables/usePermissions'
import { useCopyFeedback } from '@/Composables/useCopyFeedback'
import { useFormat } from '@/Composables/useFormat'
import { localeLabel } from '@/config/localeLabels'

const page = usePage()
const { formatDateTime } = useFormat()
const currentUserId = page.props.auth?.user?.id
const isAdmin = computed(() => {
    const roles = page.props.auth?.user?.roles ?? []
    return roles.includes('Super Admin') || roles.includes('Admin')
})
const { can } = usePermissions()
const canDeleteTask = computed(() => props.task.created_by === currentUserId || isAdmin.value)

const props = defineProps({
    task: Object,
    statuses: Array,
    channels: Array,
    activities: { type: Array, default: () => [] },
    translation_history: { type: Array, default: () => [] },
    prev_task: { type: Object, default: null },
    next_task: { type: Object, default: null },
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

const showVideoUrlModal = ref(false)
const publishing = ref(false)

function openPublishModal() {
    showVideoUrlModal.value = true
}

async function confirmPublish(url) {
    if (publishing.value) return
    publishing.value = true
    try {
        await axios.patch(`/tasks/${props.task.id}/status`, { status: 'published', youtube_url: url })
        showVideoUrlModal.value = false
        router.reload({ only: ['task'] })
    } catch (e) {
        console.error('Failed to publish task', e)
    } finally {
        publishing.value = false
    }
}

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

const { copiedKey, copy: copyText } = useCopyFeedback()

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

function formatAttrValue(key, val) {
    if (val === null || val === '' || val === undefined) return null
    if (key === 'status') return statusLabels[val] || val
    if (key === 'channel_id') {
        const ch = props.channels.find(c => c.id === Number(val))
        return ch ? ch.name : `Canal #${val}`
    }
    if (key === 'task_date') {
        return formatDateTime(val)
    }
    if (Array.isArray(val)) return val.join(', ')
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

const showDeleteSessionModal = ref(false)
const deleteSessionTarget = ref(null)

function deleteSession(session) {
    deleteSessionTarget.value = session
    showDeleteSessionModal.value = true
}

function confirmDeleteSession() {
    if (!deleteSessionTarget.value) return
    const sessionId = deleteSessionTarget.value.id
    showDeleteSessionModal.value = false
    deleteSessionTarget.value = null
    axios.delete(`/tasks/${props.task.id}/sessions/${sessionId}`).then(() => {
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

const showTranslateModal = ref(false)
const translating = ref(false)
const translateError = ref('')
const translateLocale = ref('es')
const translateChannelId = ref(null)
const translateDate = ref(props.task.task_date)
const translateTimeRange = ref('')

const translateLocales = computed(() => {
    const locales = [...new Set((props.channels || []).flatMap(c => c.locales || []))]
    return locales.length ? locales : ['es']
})

const translateChannels = computed(() => {
    const list = (props.channels || []).filter(c => (c.locales || []).includes(translateLocale.value))
    if (list.length && !list.some(c => c.id === translateChannelId.value)) {
        translateChannelId.value = list[0].id
    }
    return list
})

const canTranslateTask = computed(() => can('create planning') && can('generate ai'))

function openTranslateModal() {
    const locales = translateLocales.value
    const taskLocale = props.task.locale || 'es'
    translateLocale.value = locales.includes(taskLocale) ? taskLocale : (locales[0] || 'es')
    translateChannelId.value = null
    translateDate.value = props.task.task_date
    translateTimeRange.value = ''
    translateError.value = ''
    showTranslateModal.value = true
}

async function submitTranslate() {
    if (translating.value) return
    translateError.value = ''
    translating.value = true
    try {
        const res = await axios.post(`/tasks/${props.task.id}/translate`, {
            locale: translateLocale.value,
            channel_id: translateChannelId.value,
            task_date: translateDate.value,
            time_range: translateTimeRange.value,
        })
        showTranslateModal.value = false
        if (res.data?.redirect) {
            router.visit(res.data.redirect)
        }
    } catch (error) {
        const data = error.response?.data
        if (data?.errors) {
            translateError.value = Object.values(data.errors).flat().join(' ')
        } else {
            translateError.value = data?.error || 'No se pudo traducir la tarea. Inténtalo de nuevo.'
        }
    } finally {
        translating.value = false
    }
}

const embedUrl = computed(() => {
    const url = props.task.youtube_url
    if (!url) return null
    const ytMatch = url.match(/(?:youtube\.com\/(?:watch\?v=|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/)
    if (ytMatch) return { src: `https://www.youtube.com/embed/${ytMatch[1]}`, type: 'youtube', shorts: url.includes('/shorts/') }
    const ttMatch = url.match(/tiktok\.com\/@[\w.-]+\/video\/(\d+)/)
    if (ttMatch) return { src: `https://www.tiktok.com/player/v1/${ttMatch[1]}`, type: 'tiktok' }
    return null
})

function exportTxt() {
    const lines = []
    lines.push(`TAREA: ${props.task.title}`)
    lines.push(`ESTADO: ${statusLabel(props.task.status)}`)
    lines.push(`FECHA: ${props.task.task_date}`)
    lines.push(`BLOQUE: ${props.task.time_range || 'Sin asignar'}`)
    if (props.task.channel) lines.push(`CANAL: ${props.task.channel.name}`)
    lines.push('')
    lines.push('--- GUION ---')
    lines.push(props.task.script || 'Sin guion')
    lines.push('')
    lines.push('--- COPY / DESCRIPCIÓN ---')
    lines.push(props.task.copy || 'Sin copy')
    lines.push('')
    lines.push('--- NOTAS ---')
    lines.push(props.task.notes || 'Sin notas')
    if (props.task.youtube_url) {
        lines.push('')
        lines.push(`--- VIDEO ---`)
        lines.push(props.task.youtube_url)
    }
    const blob = new Blob([lines.join('\n')], { type: 'text/plain;charset=utf-8' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `tarea_${props.task.id}_${props.task.title?.replace(/[^a-zA-Z0-9]/g, '_') || 'sin_titulo'}.txt`
    a.click()
    URL.revokeObjectURL(url)
}
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
                    <div class="flex items-center gap-2">
                        <button v-if="prev_task" @click="router.visit(`/tasks/${prev_task.id}`)"
                            class="p-2 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700 transition text-gray-500 dark:text-gray-400"
                            :title="prev_task.title">
                            <ChevronLeft class="w-5 h-5" />
                        </button>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ task.title }}</h1>
                        <button v-if="next_task" @click="router.visit(`/tasks/${next_task.id}`)"
                            class="p-2 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700 transition text-gray-500 dark:text-gray-400"
                            :title="next_task.title">
                            <ChevronRight class="w-5 h-5" />
                        </button>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-semibold" :class="statusColor(task.status)">
                        {{ statusLabel(task.status) }}
                    </span>
                </div>
                <div class="flex items-center gap-3">
                    <button @click="exportTxt"
                        class="px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition flex items-center gap-2">
                        <Download class="w-4 h-4" />
                        TXT
                    </button>
                    <button v-if="canTranslateTask" @click="openTranslateModal"
                        class="px-4 py-2 rounded-xl border border-teal-200 dark:border-teal-800 text-teal-700 dark:text-teal-300 text-sm font-medium hover:bg-teal-50 dark:hover:bg-teal-900/30 transition flex items-center gap-2">
                        <Languages class="w-4 h-4" />
                        Traducir
                    </button>
                    <button v-if="!task.shared_by_user_name" @click="showShareModal = true"
                        class="px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition flex items-center gap-2">
                        <Share2 class="w-4 h-4" />
                        Compartir
                    </button>
                    <button v-if="can('edit planning')" @click="openPublishModal"
                        class="px-4 py-2 rounded-xl border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-300 text-sm font-medium hover:bg-green-100 dark:hover:bg-green-900/40 transition flex items-center gap-2">
                        <Check class="w-4 h-4" />
                        Publicado
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

            <!-- Title card -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <h2 class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Título del video</h2>
                        <span v-if="task.locale" class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-300">
                            {{ localeLabel(task.locale) }}
                        </span>
                    </div>
                    <button @click="copyText(task.title, 'title')"
                        class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                        <Check v-if="copiedKey === 'title'" class="w-4 h-4 text-green-500" />
                        <Copy v-else class="w-4 h-4" />
                    </button>
                </div>
                <p class="text-gray-900 dark:text-white font-medium">{{ task.title }}</p>
            </div>

            <!-- Content grid -->
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
                <!-- Script -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
                    <div class="flex items-center justify-between mb-2">
                        <h2 class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Guion</h2>
                        <button v-if="task.script" @click="copyText(task.script, 'script')"
                            class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                            <Check v-if="copiedKey === 'script'" class="w-4 h-4 text-green-500" />
                            <Copy v-else class="w-4 h-4" />
                        </button>
                    </div>
                    <p v-if="task.script" class="text-gray-900 dark:text-white whitespace-pre-wrap text-sm leading-relaxed">{{ task.script }}</p>
                    <p v-else class="text-gray-400 dark:text-gray-500 text-sm italic">Sin guion</p>
                </div>

                <!-- Copy -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h2 class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Copy / Descripción</h2>
                        </div>
                        <button v-if="task.copy" @click="copyText(task.copy, 'copy')"
                            class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                            <Check v-if="copiedKey === 'copy'" class="w-4 h-4 text-green-500" />
                            <Copy v-else class="w-4 h-4" />
                        </button>
                    </div>
                    <p v-if="task.copy" class="text-gray-900 dark:text-white whitespace-pre-wrap text-sm leading-relaxed">{{ task.copy }}</p>
                    <p v-else class="text-gray-400 dark:text-gray-500 text-sm italic">Sin copy</p>
                </div>

                <!-- Video -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5 flex flex-col">
                    <div class="flex items-center justify-between mb-2">
                        <h2 class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Video</h2>
                        <button v-if="task.youtube_url" @click="copyText(task.youtube_url, 'url')"
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
                    <a v-if="task.youtube_url" :href="task.youtube_url" target="_blank"
                        class="mt-2 text-xs text-gray-400 hover:text-red-600 dark:hover:text-red-400 truncate inline-flex items-center gap-1">
                        <ExternalLink class="w-3 h-3 flex-shrink-0" />
                        {{ task.youtube_url }}
                    </a>
                    <p v-if="!task.youtube_url" class="text-gray-400 dark:text-gray-500 text-sm italic text-center py-10 mt-auto">Sin enlace de video</p>
                </div>
            </div>

            <!-- Notas -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Notas</h2>
                    </div>
                    <button v-if="task.notes" @click="copyText(task.notes, 'notes')"
                        class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                        <Check v-if="copiedKey === 'notes'" class="w-4 h-4 text-green-500" />
                        <Copy v-else class="w-4 h-4" />
                    </button>
                </div>
                <p v-if="task.notes" class="text-gray-900 dark:text-white whitespace-pre-wrap text-sm leading-relaxed">{{ task.notes }}</p>
                <p v-else class="text-gray-400 dark:text-gray-500 text-sm italic">Sin notas</p>
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
                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-300 font-medium">
                                {{ localeLabel(task.locale) }}
                            </span>
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
                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-300 font-medium">
                                {{ localeLabel(session.locale) }}
                            </span>
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

            <!-- Translation history -->
            <div v-if="translation_history.length > 1" class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
                <div class="flex items-center gap-2 mb-4">
                    <Languages class="w-4 h-4 text-teal-600 dark:text-teal-400" />
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Tareas vinculadas por traducción</h3>
                </div>
                <div class="space-y-2">
                    <div v-for="item in translation_history" :key="item.id"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm border transition"
                        :class="item.is_self
                            ? 'border-teal-300 dark:border-teal-700 bg-teal-50 dark:bg-teal-900/20'
                            : 'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 hover:border-indigo-300 dark:hover:border-indigo-700 cursor-pointer'"
                        @click="!item.is_self && router.visit(`/tasks/${item.id}`)">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-300">
                            {{ localeLabel(item.locale) }}
                        </span>
                        <span v-if="item.is_original"
                            class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">
                            Original
                        </span>
                        <span v-if="item.is_self"
                            class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                            Actual
                        </span>
                        <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: item.channel_color || '#94a3b8' }"></span>
                        <span class="flex-1 min-w-0 truncate text-gray-800 dark:text-gray-200 font-medium">{{ item.title }}</span>
                        <span class="text-xs text-gray-400 shrink-0">{{ item.channel_name || 'Sin canal' }}</span>
                        <span class="text-xs text-gray-400 shrink-0">{{ item.task_date }} · {{ item.time_range || '—' }}</span>
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium shrink-0" :class="statusColor(item.status)">
                            {{ item.status_label }}
                        </span>
                        <a v-if="item.youtube_url" :href="item.youtube_url" target="_blank" @click.stop
                            class="text-gray-400 hover:text-red-600 dark:hover:text-red-400 shrink-0">
                            <ExternalLink class="w-4 h-4" />
                        </a>
                    </div>
                </div>
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

            <ConfirmDeleteModal
                :show="showDeleteSessionModal"
                title="Eliminar sesión"
                message="¿Eliminar esta sesión de trabajo?"
                @close="showDeleteSessionModal = false"
                @confirm="confirmDeleteSession"
            />

            <ShareModal
                :show="showShareModal"
                :shareable-type="'video_tasks'"
                :shareable-id="task.id"
                :current-shares="task.shared_with_users || []"
                @close="showShareModal = false"
                @saved="router.reload({ only: ['task'] })"
            />

            <CompleteVideoUrlModal
                :show="showVideoUrlModal"
                :task="task"
                :confirm-label="publishing ? 'Guardando...' : 'Publicar'"
                @close="showVideoUrlModal = false"
                @confirm="confirmPublish"
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
            <Teleport to="body">
                <div v-if="showTranslateModal" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50" @click.self="showTranslateModal = false">
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-md p-6 mx-4">
                        <div class="flex items-center gap-2 mb-4">
                            <Languages class="w-5 h-5 text-teal-600 dark:text-teal-400" />
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Traducir tarea</h3>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                            Se creará una nueva tarea en el idioma y canal elegidos, vinculada a esta. Título, guion y copy se traducirán con DeepL.
                        </p>
                        <div class="space-y-4">
                            <div>
                                <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Idioma destino</label>
                                <select v-model="translateLocale"
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                                    <option v-for="lang in translateLocales" :key="lang" :value="lang">
                                        {{ localeLabel(lang) }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Canal destino</label>
                                <select v-model="translateChannelId"
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                                    <option v-for="ch in translateChannels" :key="ch.id" :value="ch.id">{{ ch.name }}</option>
                                </select>
                                <p v-if="!translateChannels.length" class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                                    No hay canales con el idioma {{ localeLabel(translateLocale) }}.
                                </p>
                            </div>
                            <div>
                                <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Fecha</label>
                                <input v-model="translateDate" type="date"
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500" />
                            </div>
                            <div v-if="useBlocks">
                                <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Bloque horario</label>
                                <select v-model="translateTimeRange"
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                                    <option :value="''">Seleccionar bloque...</option>
                                    <option v-for="block in workBlocks" :key="block" :value="block">{{ block }}</option>
                                </select>
                            </div>
                            <div v-else>
                                <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Horario (ej. 10:00-12:00)</label>
                                <input v-model="translateTimeRange" type="text" placeholder="10:00-12:00"
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500" />
                            </div>
                        </div>
                        <p v-if="translateError" class="mt-3 text-sm text-red-500">{{ translateError }}</p>
                        <div class="flex justify-end gap-3 mt-6">
                            <button type="button" @click="showTranslateModal = false"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition">
                                Cancelar
                            </button>
                            <button type="button" @click="submitTranslate"
                                :disabled="translating || !translateChannelId || !translateTimeRange"
                                class="px-4 py-2 text-sm font-medium bg-teal-600 hover:bg-teal-700 text-white rounded-xl transition inline-flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                                <Loader2 v-if="translating" class="w-4 h-4 animate-spin" />
                                <Languages v-else class="w-4 h-4" />
                                {{ translating ? 'Traduciendo...' : 'Traducir' }}
                            </button>
                        </div>
                    </div>
                </div>
            </Teleport>
        </div>
    </AppLayout>
</template>
