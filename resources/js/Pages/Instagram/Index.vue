<script setup>
import { ref, computed, watch } from 'vue'
import { router, usePage, Link } from '@inertiajs/vue3'
import { usePermissions } from '@/Composables/usePermissions'
import { useTimeAgo } from '@/Composables/useTimeAgo'
import { useFormat } from '@/Composables/useFormat'
import { useCopyFeedback } from '@/Composables/useCopyFeedback'
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import StatCard from '@/Components/UI/StatCard.vue'
import Modal from '@/Components/Modals/Modal.vue'
import ConfirmDelete from '@/Components/Modals/ConfirmDelete.vue'
import PostPreviewModal from '@/Components/Modals/PostPreviewModal.vue'
import { Instagram, Facebook, AlertCircle, ExternalLink, ThumbsUp, Newspaper, MessageCircle, MessagesSquare, BarChart3, Clock, Flame, Trophy, TrendingUp, TrendingDown, Megaphone, Loader2, Trash2, Copy, CheckCircle2, RefreshCw, Link2, Film, Image as ImageIcon, Clapperboard } from 'lucide-vue-next'
import { Line } from 'vue-chartjs'
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Filler,
    Tooltip,
    Legend,
} from 'chart.js'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Filler, Tooltip, Legend)

const { can } = usePermissions()
const page = usePage()
const { timeAgo } = useTimeAgo()
const { copiedKey, copy } = useCopyFeedback()

const props = defineProps({
    connection: Object,
    account: Object,
    feed: Object,
    insights: Object,
    organization_id: Number,
    days: Number,
})

const OAUTH_URL = 'https://www.facebook.com/v26.0/dialog/oauth?client_id=922961427281115&redirect_uri=https%3A%2F%2Fwww.facebook.com%2Fconnect%2Flogin_success.html&response_type=token&scope=pages_read_engagement%2Cpages_read_user_content%2Cpages_manage_engagement%2Cpages_manage_posts%2Cpages_messaging%2Cread_insights%2Cpages_show_list%2Cpublish_video%2Cinstagram_basic%2Cinstagram_manage_insights%2Cinstagram_manage_comments%2Cinstagram_content_publish&display=popup'

const rangeDays = ref([7, 28, 90].includes(props.days) ? props.days : 28)
const comparePrev = ref(true)
const previewPost = ref(null)

watch(rangeDays, (value) => {
    router.reload({
        only: ['insights'],
        data: { days: value },
        preserveState: true,
        preserveScroll: true,
    })
})

const linked = computed(() => props.account?.linked === true)
const accountError = computed(() => props.account?.error || null)
const media = computed(() => props.feed?.media ?? [])
const feedError = computed(() => props.feed?.error ?? false)
const insightsReady = computed(() => props.insights?.available && (props.insights?.days?.length ?? 0) > 0)

const formatNumber = (n) => {
    if (n === null || n === undefined) return '—'
    if (n >= 1000000) return (n / 1000000).toFixed(1) + 'M'
    if (n >= 1000) return (n / 1000).toFixed(1) + 'K'
    return n.toLocaleString()
}

const { formatDateTime: formatDate, formatChartDate } = useFormat()

const deltaInfo = (current, previous) => {
    if (previous === null || previous === undefined || previous === 0) return null
    const pct = Math.round(((current - previous) / previous) * 100)
    return { pct, up: pct >= 0 }
}

const deltas = computed(() => {
    const t = props.insights?.totals
    const p = props.insights?.previous_totals
    if (!t || !p) return {}
    return {
        reach: deltaInfo(t.reach, p.reach),
        impressions: deltaInfo(t.impressions, p.impressions),
        followers: deltaInfo(t.followers, p.followers),
    }
})

const engagementOf = (m) => (m.likes || 0) + (m.comments || 0)

const topMedia = computed(() =>
    [...media.value].sort((a, b) => engagementOf(b) - engagementOf(a)).slice(0, 3)
)

const rankOf = (id) => topMedia.value.findIndex(m => m.id === id)

const lastMedia = computed(() => media.value[0] || null)

const daysSinceLastPost = computed(() => {
    const iso = lastMedia.value?.timestamp
    if (!iso) return null
    const diff = Date.now() - new Date(iso).getTime()
    if (isNaN(diff)) return null
    return Math.max(0, Math.floor(diff / 86400000))
})

const postStreak = computed(() => {
    if (!lastMedia.value?.timestamp) return 0
    const dates = new Set()
    media.value.forEach(m => {
        if (!m.timestamp) return
        const d = new Date(m.timestamp)
        if (!isNaN(d.getTime())) dates.add(`${d.getFullYear()}-${d.getMonth() + 1}-${d.getDate()}`)
    })
    const cur = new Date(lastMedia.value.timestamp)
    cur.setHours(12, 0, 0, 0)
    let streak = 0
    while (dates.has(`${cur.getFullYear()}-${cur.getMonth() + 1}-${cur.getDate()}`)) {
        streak++
        cur.setDate(cur.getDate() - 1)
    }
    return streak
})

const weekdayStats = computed(() => {
    const days = props.insights?.days
    if (!days || days.length < 14) return null
    const names = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado']
    const short = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb']
    const sums = Array(7).fill(0)
    const counts = Array(7).fill(0)
    days.forEach(d => {
        const wd = new Date(d.date + 'T12:00:00').getDay()
        sums[wd] += (d.reach || 0)
        counts[wd]++
    })
    const avgs = sums.map((s, i) => counts[i] ? s / counts[i] : 0)
    const max = Math.max(...avgs)
    if (max <= 0) return null
    return { short, avgs, max, best: avgs.indexOf(max), bestName: names[avgs.indexOf(max)] }
})

const health = computed(() => {
    const d = daysSinceLastPost.value
    if (d === null) return { label: 'Sin publicaciones', tone: 'gray' }
    if (d <= 1) return { label: 'Al día', tone: 'green' }
    if (d <= 3) return { label: `${d} días sin publicar`, tone: 'amber' }
    return { label: `${d} días sin publicar`, tone: 'red' }
})

const mediaThisMonth = computed(() => {
    const now = new Date()
    return media.value.filter(m => {
        const d = m.timestamp ? new Date(m.timestamp) : null
        return d && !isNaN(d.getTime()) && d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear()
    }).length
})

const profileUrl = computed(() =>
    linked.value && props.account?.username ? `https://www.instagram.com/${props.account.username}/` : null
)

const feedTotals = computed(() => {
    const t = { likes: 0, comments: 0 }
    media.value.forEach(m => {
        t.likes += m.likes || 0
        t.comments += m.comments || 0
    })
    return t
})

const avgEngagement = computed(() => {
    if (!media.value.length) return 0
    return Math.round((feedTotals.value.likes + feedTotals.value.comments) / media.value.length)
})

const chartData = computed(() => {
    const days = props.insights?.days
    if (!days || days.length < 2) return null
    const isDark = document.documentElement.classList.contains('dark')

    const datasets = [
        {
            label: 'Alcance',
            data: days.map(d => d.reach),
            fill: true,
            borderColor: '#e1306c',
            backgroundColor: 'rgba(225,48,108,0.12)',
            tension: 0.35,
            pointRadius: 3,
            pointHoverRadius: 6,
            pointBackgroundColor: '#e1306c',
            pointBorderColor: isDark ? '#1f2937' : '#ffffff',
            pointBorderWidth: 2,
            borderWidth: 2.5,
        },
        {
            label: 'Impresiones',
            data: days.map(d => d.impressions),
            fill: true,
            borderColor: '#833ab4',
            backgroundColor: 'rgba(131,58,180,0.10)',
            tension: 0.35,
            pointRadius: 3,
            pointHoverRadius: 6,
            pointBackgroundColor: '#833ab4',
            pointBorderColor: isDark ? '#1f2937' : '#ffffff',
            pointBorderWidth: 2,
            borderWidth: 2.5,
        },
        {
            label: 'Seguidores',
            data: days.map(d => d.followers),
            fill: false,
            borderColor: '#f77737',
            backgroundColor: 'transparent',
            tension: 0.35,
            pointRadius: 3,
            pointHoverRadius: 6,
            pointBackgroundColor: '#f77737',
            pointBorderColor: isDark ? '#1f2937' : '#ffffff',
            pointBorderWidth: 2,
            borderWidth: 2.5,
        },
    ]

    const prev = props.insights?.previous_days

    if (comparePrev.value && prev && prev.length) {
        const offset = days.length - prev.length
        const align = (key) => days.map((_, i) => {
            const j = i - offset
            return j >= 0 && j < prev.length ? prev[j][key] : null
        })

        datasets.push(
            {
                label: 'Alcance (periodo anterior)',
                data: align('reach'),
                fill: false,
                borderColor: 'rgba(225,48,108,0.4)',
                backgroundColor: 'transparent',
                borderDash: [5, 4],
                tension: 0.35,
                pointRadius: 0,
                pointHoverRadius: 4,
                borderWidth: 1.5,
            },
            {
                label: 'Impresiones (periodo anterior)',
                data: align('impressions'),
                fill: false,
                borderColor: 'rgba(131,58,180,0.4)',
                backgroundColor: 'transparent',
                borderDash: [5, 4],
                tension: 0.35,
                pointRadius: 0,
                pointHoverRadius: 4,
                borderWidth: 1.5,
            }
        )
    }

    return {
        labels: days.map(d => formatChartDate(d.date)),
        datasets,
    }
})

const chartOptions = computed(() => {
    const isDark = document.documentElement.classList.contains('dark')
    const gridColor = isDark ? 'rgba(75,85,99,0.3)' : 'rgba(226,232,240,0.8)'
    const tickColor = isDark ? '#6b7280' : '#9ca3af'

    return {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 600 },
        interaction: { intersect: false, mode: 'index' },
        plugins: {
            tooltip: {
                enabled: true,
                backgroundColor: isDark ? '#111827' : '#1f2937',
                titleFont: { size: 12, weight: '600' },
                bodyFont: { size: 11 },
                padding: 12,
                cornerRadius: 8,
                borderColor: isDark ? 'rgba(75,85,99,0.5)' : 'rgba(255,255,255,0.1)',
                borderWidth: 1,
                displayColors: false,
            },
            legend: {
                display: true,
                position: 'top',
                align: 'end',
                labels: {
                    color: tickColor,
                    usePointStyle: true,
                    pointStyle: 'circle',
                    boxWidth: 8,
                    font: { size: 11 },
                },
            },
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: { color: tickColor, font: { size: 10 }, maxTicksLimit: 10 },
            },
            y: {
                grid: { color: gridColor },
                border: { display: false },
                ticks: {
                    color: tickColor,
                    font: { size: 10 },
                    padding: 4,
                    callback: (v) => {
                        if (v >= 1000000) return (v / 1000000).toFixed(1) + 'M'
                        if (v >= 1000) return (v / 1000).toFixed(1) + 'K'
                        return v
                    },
                },
            },
        },
    }
})

function refreshAccount() {
    router.reload({
        only: ['account', 'feed', 'insights'],
        preserveState: true,
        preserveScroll: true,
    })
}

const publishOpen = ref(false)
const publishForm = ref({ caption: '', image_url: '', video_url: '', reel: false })
const publishing = ref(false)

function openPublish() {
    publishForm.value = { caption: '', image_url: '', video_url: '', reel: false }
    publishOpen.value = true
}

function submitPublish() {
    const hasImage = !!publishForm.value.image_url.trim()
    const hasVideo = !!publishForm.value.video_url.trim()
    if ((!hasImage && !hasVideo) || publishing.value) return

    router.post('/instagram/publish', {
        caption: publishForm.value.caption.trim() || undefined,
        image_url: hasImage ? publishForm.value.image_url.trim() : undefined,
        video_url: hasVideo ? publishForm.value.video_url.trim() : undefined,
        reel: hasVideo && publishForm.value.reel ? 1 : undefined,
        organization_id: props.organization_id ?? undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        onStart: () => { publishing.value = true },
        onSuccess: () => {
            const hasErrors = Object.keys(page.props.errors ?? {}).length > 0
            if (!hasErrors) {
                publishForm.value = { caption: '', image_url: '', video_url: '', reel: false }
                publishOpen.value = false
            }
            router.reload({ only: ['feed', 'account'], preserveState: true, preserveScroll: true })
        },
        onFinish: () => { publishing.value = false },
    })
}

const deleteTarget = ref(null)
const deleting = ref(false)

function confirmDelete() {
    if (!deleteTarget.value || deleting.value) return

    router.post('/instagram/media/delete', {
        media_id: deleteTarget.value.id,
        organization_id: props.organization_id ?? undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        onStart: () => { deleting.value = true },
        onSuccess: () => {
            deleteTarget.value = null
            router.reload({ only: ['feed', 'account'], preserveState: true, preserveScroll: true })
        },
        onFinish: () => { deleting.value = false },
    })
}

const typeBadge = (type) => {
    switch (type) {
        case 'REELS': return { label: 'Reel', cls: 'bg-pink-100 text-pink-700 dark:bg-pink-900/40 dark:text-pink-400' }
        case 'VIDEO': return { label: 'Video', cls: 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-400' }
        case 'CAROUSEL': return { label: 'Carrusel', cls: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400' }
        default: return { label: 'Foto', cls: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400' }
    }
}

const thumbnailOf = (m) => m.thumbnail_url || m.media_url
</script>

<template>
    <AppLayout>
        <div class="space-y-8">
            <!-- Header -->
            <PageHeader title="Instagram" subtitle="Cuenta profesional y publicaciones recientes">
                <button v-if="linked && can('edit empresa')" @click="openPublish"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-[#833ab4] via-[#e1306c] to-[#f77737] hover:opacity-95 text-white text-sm font-medium shadow-sm transition">
                    <Megaphone class="w-4 h-4" /> Publicar
                </button>
                <Link v-if="linked" href="/instagram/comments"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-pink-200 dark:border-pink-800 bg-pink-50 dark:bg-pink-900/30 text-pink-700 dark:text-pink-300 text-sm font-medium hover:bg-pink-100 dark:hover:bg-pink-900/50 transition">
                    <MessageCircle class="w-4 h-4" /> Comentarios
                </Link>
                <Link v-if="linked" href="/instagram/messages"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-pink-200 dark:border-pink-800 bg-pink-50 dark:bg-pink-900/30 text-pink-700 dark:text-pink-300 text-sm font-medium hover:bg-pink-100 dark:hover:bg-pink-900/50 transition">
                    <MessagesSquare class="w-4 h-4" /> Mensajes
                    <span class="text-[10px] font-semibold uppercase tracking-wide bg-white/70 dark:bg-black/40 border border-white/60 dark:border-black/40 px-1.5 py-0.5 rounded-md">Próximamente</span>
                </Link>
                <a v-if="linked && profileUrl" :href="profileUrl" target="_blank"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-[#833ab4] via-[#e1306c] to-[#f77737] hover:opacity-95 text-white text-sm font-medium shadow-sm transition">
                    <ExternalLink class="w-4 h-4" /> Ver perfil
                </a>
                <div v-if="accountError"
                    class="flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-sm text-amber-700 dark:text-amber-400">
                    <AlertCircle class="w-4 h-4" />
                    <span>Error de Instagram</span>
                </div>
                <div v-else-if="connection?.stale"
                    class="flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-sm text-amber-700 dark:text-amber-400">
                    <AlertCircle class="w-4 h-4" />
                    <span>Info desactualizada</span>
                </div>
                <div v-if="!connection && !organization_id"
                    class="flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-sm text-amber-700 dark:text-amber-400">
                    <AlertCircle class="w-4 h-4" />
                    <span>Selecciona una empresa activa</span>
                </div>
            </PageHeader>

            <!-- Not connected: needs the Facebook page first -->
            <div v-if="!connection"
                class="text-center py-16 text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800">
                <Facebook class="w-16 h-16 mx-auto mb-4 text-blue-500 opacity-60" />
                <p class="text-base font-medium text-gray-900 dark:text-white">No hay una Página de Facebook conectada</p>
                <p class="text-sm mt-1">Instagram se conecta a través de la Página: primero conecta Facebook y vincula
                    tu cuenta profesional de Instagram a esa Página.</p>
                <Link href="/facebook"
                    class="inline-flex items-center gap-2 mt-6 px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium transition">
                    <Facebook class="w-4 h-4" /> Ir a Facebook
                </Link>
            </div>

            <!-- Connected but no linked instagram account -->
            <div v-else-if="!linked && !accountError"
                class="text-center py-16 text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800">
                <Instagram class="w-16 h-16 mx-auto mb-4 text-pink-500 opacity-60" />
                <p class="text-base font-medium text-gray-900 dark:text-white">No hay una cuenta de Instagram vinculada</p>
                <div class="max-w-md mx-auto mt-3 text-sm space-y-1 text-left">
                    <p>1. Convierte tu cuenta de Instagram en <strong>cuenta profesional o de creador</strong>.</p>
                    <p>2. En Facebook, ve a la Página &gt; Editar perfil &gt; <strong>Vincular cuenta de Instagram</strong>.</p>
                    <p>3. Vuelve aquí y actualiza.</p>
                </div>
                <button @click="refreshAccount"
                    class="inline-flex items-center gap-2 mt-6 px-5 py-2 rounded-xl border border-pink-300 text-pink-600 hover:bg-pink-50 dark:border-pink-800 dark:text-pink-400 dark:hover:bg-pink-900/30 text-sm font-medium transition">
                    <RefreshCw class="w-4 h-4" /> Actualizar
                </button>
            </div>

            <!-- Linked account: Graph rejected the request (usually missing instagram_* scopes) -->
            <div v-else-if="!linked && accountError"
                class="p-6 bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-amber-200 dark:border-amber-800">
                <div class="flex items-start gap-3">
                    <AlertCircle class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" />
                    <div class="min-w-0">
                        <h2 class="font-semibold text-gray-900 dark:text-white">No se pudo leer la cuenta de Instagram</h2>
                        <code class="block font-mono text-xs mt-2 leading-relaxed break-words text-amber-600 dark:text-amber-500">{{ accountError }}</code>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-3">
                            Si el error menciona permisos, vuelve a conectar la Página con un token que incluya
                            <code class="font-mono font-semibold">instagram_basic</code> e
                            <code class="font-mono font-semibold">instagram_content_publish</code> usando esta URL:
                        </p>
                        <div class="flex flex-col sm:flex-row gap-2 mt-3">
                            <code class="flex-1 min-w-0 block font-mono text-[11px] leading-relaxed break-all bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-xl px-3 py-2 text-gray-600 dark:text-gray-300">{{ OAUTH_URL }}</code>
                            <button type="button" @click="copy(OAUTH_URL, 'oauth')"
                                class="shrink-0 inline-flex items-center gap-1.5 px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700 text-xs font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                                <CheckCircle2 v-if="copiedKey === 'oauth'" class="w-4 h-4 text-green-500" />
                                <Copy v-else class="w-4 h-4" />
                                {{ copiedKey === 'oauth' ? 'Copiada' : 'Copiar URL' }}
                            </button>
                        </div>
                        <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-2">
                            Pega el token nuevo en Facebook &gt; Conectar Página y vuelve aquí.
                        </p>
                        <button @click="refreshAccount"
                            class="inline-flex items-center gap-2 mt-4 px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700 text-xs font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                            <RefreshCw class="w-4 h-4" /> Reintentar
                        </button>
                    </div>
                </div>
            </div>

            <!-- Linked -->
            <template v-else>
                <div class="grid grid-cols-2 md:grid-cols-2 xl:grid-cols-4 gap-6">
                    <StatCard title="Seguidores" :value="formatNumber(account.followers)" color="#e1306c" />
                    <StatCard title="Publicaciones" :value="formatNumber(account.media_count)" color="#833ab4" />
                    <StatCard title="Alcance" :value="insightsReady ? formatNumber(insights.totals.reach) : '—'" color="#f77737" />
                    <StatCard title="Impresiones" :value="insightsReady ? formatNumber(insights.totals.impressions) : '—'" color="#fd5949" />
                </div>

                <!-- Performance chart -->
                <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-5">
                        <div class="flex items-center gap-3">
                            <div class="p-2 rounded-lg bg-gradient-to-br from-[#833ab4] to-[#e1306c] shadow-md shadow-pink-200 dark:shadow-pink-900/30">
                                <BarChart3 class="w-4 h-4 text-white" />
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-900 dark:text-white text-sm">Rendimiento de la cuenta</h3>
                                <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">Alcance, impresiones y seguidores · últimos {{ rangeDays }} días</p>
                            </div>
                        </div>
                        <div class="sm:ml-auto flex flex-col items-start sm:items-end gap-2">
                            <div class="flex items-center gap-3">
                                <div class="inline-flex rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden text-xs font-medium">
                                    <button v-for="r in [7, 28, 90]" :key="r" @click="rangeDays = r"
                                        :class="rangeDays === r
                                            ? 'bg-gradient-to-r from-[#833ab4] to-[#e1306c] text-white'
                                            : 'text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800'"
                                        class="px-3 py-1.5 transition">
                                        {{ r }}d
                                    </button>
                                </div>
                                <label v-if="insightsReady" class="inline-flex items-center gap-1.5 text-[11px] text-gray-500 dark:text-gray-400 cursor-pointer select-none">
                                    <input type="checkbox" v-model="comparePrev"
                                        class="rounded border-gray-300 dark:border-gray-600 text-pink-600 focus:ring-pink-500" />
                                    vs. periodo anterior
                                </label>
                            </div>
                            <div v-if="insightsReady" class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                <span class="inline-flex items-center gap-1.5">
                                    <strong class="text-gray-900 dark:text-white">{{ formatNumber(insights.totals.reach) }}</strong> alcance
                                    <span v-if="deltas.reach" class="inline-flex items-center gap-0.5 font-semibold"
                                        :class="deltas.reach.up ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400'">
                                        <component :is="deltas.reach.up ? TrendingUp : TrendingDown" class="w-3 h-3" />
                                        {{ deltas.reach.up ? '+' : '' }}{{ deltas.reach.pct }}%
                                    </span>
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <strong class="text-gray-900 dark:text-white">{{ formatNumber(insights.totals.impressions) }}</strong> impresiones
                                    <span v-if="deltas.impressions" class="inline-flex items-center gap-0.5 font-semibold"
                                        :class="deltas.impressions.up ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400'">
                                        <component :is="deltas.impressions.up ? TrendingUp : TrendingDown" class="w-3 h-3" />
                                        {{ deltas.impressions.up ? '+' : '' }}{{ deltas.impressions.pct }}%
                                    </span>
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <strong class="text-gray-900 dark:text-white">{{ formatNumber(insights.totals.followers) }}</strong> seguidores
                                    <span v-if="deltas.followers" class="inline-flex items-center gap-0.5 font-semibold"
                                        :class="deltas.followers.up ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400'">
                                        <component :is="deltas.followers.up ? TrendingUp : TrendingDown" class="w-3 h-3" />
                                        {{ deltas.followers.up ? '+' : '' }}{{ deltas.followers.pct }}%
                                    </span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div v-if="insightsReady" class="relative" style="height: 220px">
                        <Line :data="chartData" :options="chartOptions" />
                    </div>

                    <div v-else-if="insights && !insights.available"
                        class="flex flex-col gap-2 p-4 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-sm text-amber-700 dark:text-amber-400">
                        <div class="flex items-start gap-2">
                            <AlertCircle class="w-4 h-4 flex-shrink-0 mt-0.5" />
                            <span>No se pudieron cargar las estadísticas.</span>
                        </div>
                        <code v-if="insights.error" class="block font-mono text-[11px] leading-relaxed break-words text-amber-600 dark:text-amber-500">{{ insights.error }}</code>
                        <span class="text-[11px]">Si el error menciona permisos, verifica que el token tenga <code class="font-mono font-semibold">instagram_manage_insights</code> y vuelve a conectar la Página.</span>
                    </div>

                    <div v-else
                        class="text-center py-8 text-gray-400 dark:text-gray-500 text-sm bg-gray-50 dark:bg-gray-800 rounded-xl">
                        <BarChart3 class="w-10 h-10 mx-auto mb-2 opacity-40" />
                        Sin datos de rendimiento en el periodo
                    </div>
                </div>

                <!-- Account info -->
                <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6 flex flex-col sm:flex-row sm:items-center gap-5">
                    <img v-if="account.picture" :src="account.picture" :alt="account.username"
                        class="w-20 h-20 rounded-full object-cover bg-white border border-gray-200 dark:border-gray-700 shadow-sm flex-shrink-0"
                        @error="$event.target.style.display='none'" />
                    <div v-else class="w-20 h-20 rounded-full bg-gradient-to-br from-[#833ab4] to-[#e1306c] text-white flex items-center justify-center flex-shrink-0">
                        <Instagram class="w-10 h-10" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white truncate">@{{ account.username }}</h2>
                            <span class="inline-flex items-center text-[11px] font-semibold px-2 py-0.5 rounded-full bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400">Conectada</span>
                            <span v-if="account.stale" class="inline-flex items-center text-[11px] font-semibold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400">Info desactualizada</span>
                        </div>
                        <p v-if="account.name" class="text-sm text-gray-500 dark:text-gray-400 mt-1 truncate">{{ account.name }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ formatNumber(account.followers) }} seguidores · {{ formatNumber(account.follows) }} siguiendo ·
                            {{ formatNumber(account.media_count) }} publicaciones
                        </p>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-2 sm:flex-shrink-0">
                        <a v-if="profileUrl" :href="profileUrl" target="_blank"
                            class="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                            <ExternalLink class="w-4 h-4" /> Ver en Instagram
                        </a>
                        <Link href="/facebook"
                            class="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                            <Facebook class="w-4 h-4" /> Gestionar conexión
                        </Link>
                    </div>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                    <!-- Recent media -->
                    <div class="xl:col-span-2 bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-3 rounded-xl bg-pink-100 dark:bg-pink-900">
                                <Newspaper class="w-5 h-5 text-pink-600 dark:text-pink-400" />
                            </div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Publicaciones recientes</h2>
                        </div>

                        <div v-if="!feedError && media.length > 0" class="flex flex-wrap gap-2 mb-5">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-pink-50 dark:bg-pink-900/30 text-xs font-medium text-pink-700 dark:text-pink-300">
                                <ThumbsUp class="w-3.5 h-3.5" /> {{ formatNumber(feedTotals.likes) }} likes
                            </span>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-purple-50 dark:bg-purple-900/30 text-xs font-medium text-purple-700 dark:text-purple-300">
                                <MessageCircle class="w-3.5 h-3.5" /> {{ formatNumber(feedTotals.comments) }} comentarios
                            </span>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-orange-50 dark:bg-orange-900/30 text-xs font-medium text-orange-700 dark:text-orange-300">
                                <BarChart3 class="w-3.5 h-3.5" /> {{ avgEngagement }} promedio por publicación
                            </span>
                        </div>

                        <div v-if="feedError"
                            class="flex items-center gap-2 p-4 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-sm text-amber-700 dark:text-amber-400">
                            <AlertCircle class="w-4 h-4 flex-shrink-0" />
                            No se pudieron cargar las publicaciones. Se reintentará en unos minutos.
                        </div>

                        <div v-else-if="media.length === 0"
                            class="text-center py-10 text-gray-400 dark:text-gray-500 text-sm bg-gray-50 dark:bg-gray-800 rounded-xl">
                            <Newspaper class="w-10 h-10 mx-auto mb-2 opacity-40" />
                            Aún no hay publicaciones en Instagram
                        </div>

                        <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <article v-for="item in media" :key="item.id"
                                class="relative rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700 overflow-hidden cursor-pointer hover:border-pink-200 dark:hover:border-pink-900 hover:shadow-sm transition"
                                @click="previewPost = item">
                                <button v-if="can('edit empresa')" type="button" title="Eliminar"
                                    @click.stop="deleteTarget = item"
                                    class="absolute top-2 right-2 z-10 p-1.5 rounded-lg bg-black/40 text-white hover:bg-red-600 transition">
                                    <Trash2 class="w-3.5 h-3.5" />
                                </button>
                                <div class="relative">
                                    <img v-if="thumbnailOf(item)" :src="thumbnailOf(item)"
                                        class="w-full h-40 object-cover bg-white"
                                        title="Ver vista previa"
                                        @error="$event.target.style.display='none'" />
                                    <div v-else class="w-full h-40 bg-gradient-to-br from-pink-100 to-purple-100 dark:from-pink-900/40 dark:to-purple-900/40 flex items-center justify-center">
                                        <Instagram class="w-10 h-10 text-pink-400" />
                                    </div>
                                    <span class="absolute top-2 left-2 inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full"
                                        :class="typeBadge(item.type).cls">
                                        <component :is="item.type === 'VIDEO' ? Film : item.type === 'REELS' ? Clapperboard : ImageIcon" class="w-3 h-3" />
                                        {{ typeBadge(item.type).label }}
                                    </span>
                                    <span v-if="rankOf(item.id) >= 0"
                                        class="absolute bottom-2 left-2 inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full"
                                        :class="rankOf(item.id) === 0
                                            ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-400'
                                            : rankOf(item.id) === 1
                                                ? 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300'
                                                : 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-400'">
                                        <Trophy class="w-3 h-3" /> TOP {{ rankOf(item.id) + 1 }}
                                    </span>
                                </div>
                                <div class="p-3">
                                    <p class="text-sm text-gray-800 dark:text-gray-200 line-clamp-2 min-h-[2.5rem]">{{ item.caption || '(sin pie de foto)' }}</p>
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-2 text-xs text-gray-500 dark:text-gray-400">
                                        <span class="inline-flex items-center gap-1">
                                            <ThumbsUp class="w-3.5 h-3.5" /> {{ formatNumber(item.likes) }}
                                        </span>
                                        <span class="inline-flex items-center gap-1">
                                            <MessageCircle class="w-3.5 h-3.5" /> {{ formatNumber(item.comments) }}
                                        </span>
                                        <span>{{ timeAgo(item.timestamp) }}</span>
                                        <a v-if="item.permalink" :href="item.permalink" target="_blank" @click.stop
                                            class="inline-flex items-center gap-1 font-medium text-pink-600 dark:text-pink-400 hover:underline sm:ml-auto">
                                            <ExternalLink class="w-3 h-3" /> Ver
                                        </a>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </div>

                    <div class="space-y-6">
                        <!-- Publication health -->
                        <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6">
                            <div class="flex items-center gap-3 mb-5">
                                <div class="p-3 rounded-xl bg-purple-100 dark:bg-purple-900">
                                    <Clock class="w-5 h-5 text-purple-600 dark:text-purple-400" />
                                </div>
                                <h2 class="text-lg font-bold text-gray-900 dark:text-white">Salud de publicación</h2>
                            </div>

                            <div class="space-y-3">
                                <div class="flex items-center justify-between p-3 rounded-xl"
                                    :class="health.tone === 'green' ? 'bg-green-50 dark:bg-green-900/20'
                                        : health.tone === 'amber' ? 'bg-amber-50 dark:bg-amber-900/20'
                                        : health.tone === 'red' ? 'bg-red-50 dark:bg-red-900/20'
                                        : 'bg-gray-50 dark:bg-gray-800'">
                                    <span class="text-xs font-medium"
                                        :class="health.tone === 'green' ? 'text-green-700 dark:text-green-400'
                                            : health.tone === 'amber' ? 'text-amber-700 dark:text-amber-400'
                                            : health.tone === 'red' ? 'text-red-600 dark:text-red-400'
                                            : 'text-gray-500 dark:text-gray-400'">
                                        {{ health.label }}
                                    </span>
                                    <span class="w-2.5 h-2.5 rounded-full"
                                        :class="health.tone === 'green' ? 'bg-green-500'
                                            : health.tone === 'amber' ? 'bg-amber-500'
                                            : health.tone === 'red' ? 'bg-red-500'
                                            : 'bg-gray-400'"></span>
                                </div>
                                <div v-if="lastMedia" class="flex items-center justify-between p-3 rounded-xl bg-gray-50 dark:bg-gray-800">
                                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Última publicación</span>
                                    <span class="text-xs font-bold text-gray-900 dark:text-white">{{ timeAgo(lastMedia.timestamp) }}</span>
                                </div>
                                <div v-if="lastMedia" class="flex items-center justify-between p-3 rounded-xl bg-gray-50 dark:bg-gray-800">
                                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400 inline-flex items-center gap-1">
                                        <Flame class="w-3.5 h-3.5" /> Racha
                                    </span>
                                    <span class="text-xs font-bold"
                                        :class="postStreak >= 3 ? 'text-orange-600 dark:text-orange-400' : 'text-gray-900 dark:text-white'">
                                        {{ postStreak }} {{ postStreak === 1 ? 'día' : 'días' }}
                                    </span>
                                </div>
                                <div v-if="weekdayStats" class="p-3 rounded-xl bg-gray-50 dark:bg-gray-800">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Mejor día</span>
                                        <span class="text-xs font-bold text-pink-600 dark:text-pink-400 capitalize">{{ weekdayStats.bestName }}</span>
                                    </div>
                                    <div class="flex items-end justify-between gap-1.5">
                                        <div v-for="(avg, i) in weekdayStats.avgs" :key="i" class="flex flex-col items-center gap-1 flex-1">
                                            <div class="w-full rounded-t-sm"
                                                :class="i === weekdayStats.best ? 'bg-pink-500' : 'bg-gray-200 dark:bg-gray-700'"
                                                :style="{ height: (avg > 0 ? Math.max(4, Math.round((avg / weekdayStats.max) * 32)) : 3) + 'px' }"></div>
                                            <span class="text-[9px] leading-none"
                                                :class="i === weekdayStats.best ? 'text-pink-600 dark:text-pink-400 font-semibold' : 'text-gray-400 dark:text-gray-500'">
                                                {{ weekdayStats.short[i] }}
                                            </span>
                                        </div>
                                    </div>
                                    <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-2">Promedio de alcance por día</p>
                                </div>
                            </div>
                        </div>

                        <!-- Top media -->
                        <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6">
                            <div class="flex items-center gap-3 mb-5">
                                <div class="p-3 rounded-xl bg-amber-100 dark:bg-amber-900">
                                    <Trophy class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                                </div>
                                <h2 class="text-lg font-bold text-gray-900 dark:text-white">Top publicaciones</h2>
                            </div>

                            <ol v-if="topMedia.length" class="space-y-3">
                                <li v-for="(m, i) in topMedia" :key="m.id"
                                    class="flex items-start gap-3 -mx-2 px-2 py-1 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800 transition"
                                    @click="previewPost = m">
                                    <span class="w-6 h-6 rounded-full flex items-center justify-center text-[11px] font-bold flex-shrink-0"
                                        :class="i === 0 ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-400'
                                            : i === 1 ? 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300'
                                            : 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-400'">
                                        {{ i + 1 }}
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs text-gray-700 dark:text-gray-300 line-clamp-2">{{ m.caption || '(sin pie de foto)' }}</p>
                                        <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">
                                            {{ formatNumber(m.likes) }} likes · {{ formatNumber(m.comments) }} comentarios
                                        </p>
                                    </div>
                                </li>
                            </ol>
                            <p v-else class="text-xs text-gray-400 dark:text-gray-500">Aún no hay publicaciones</p>
                        </div>

                        <!-- Connection details -->
                        <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="p-3 rounded-xl bg-pink-100 dark:bg-pink-900">
                                    <Instagram class="w-5 h-5 text-pink-600 dark:text-pink-400" />
                                </div>
                                <h2 class="text-lg font-bold text-gray-900 dark:text-white">Conexión</h2>
                            </div>

                            <div class="space-y-3">
                                <div class="flex items-center justify-between p-3 rounded-xl bg-gray-50 dark:bg-gray-800">
                                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Estado</span>
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                        :class="account.stale
                                            ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400'
                                            : 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400'">
                                        {{ account.stale ? 'Desactualizada' : 'Activa' }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between p-3 rounded-xl bg-gray-50 dark:bg-gray-800">
                                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Cuenta</span>
                                    <span class="text-xs font-bold text-gray-900 dark:text-white">@{{ account.username }}</span>
                                </div>
                                <div class="flex items-center justify-between p-3 rounded-xl bg-gray-50 dark:bg-gray-800">
                                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Seguidores</span>
                                    <span class="font-bold text-gray-900 dark:text-white">{{ formatNumber(account.followers) }}</span>
                                </div>
                                <div class="flex items-center justify-between p-3 rounded-xl bg-gray-50 dark:bg-gray-800">
                                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Vinculada a</span>
                                    <span class="text-xs font-bold text-gray-900 dark:text-white truncate max-w-[55%]">{{ connection.page_name }}</span>
                                </div>
                            </div>

                            <p class="mt-4 text-[11px] text-gray-400 dark:text-gray-500">
                                La conexión usa el token de la Página de Facebook. Para cambiarla o desconectarla, ve a
                                Facebook. La cuenta de Instagram debe estar vinculada a la Página.
                            </p>

                            <Link href="/facebook"
                                class="mt-4 w-full inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                                <Link2 class="w-4 h-4" /> Gestionar en Facebook
                            </Link>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Publish modal -->
            <Modal :show="publishOpen" @close="publishOpen = false">
                <div class="flex items-center gap-2">
                    <Megaphone class="w-5 h-5 text-pink-600 dark:text-pink-400" />
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Publicar en Instagram</h3>
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 mb-4">
                    Se publicará al instante en @{{ account?.username }}.
                </p>

                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Pie de foto (opcional)</label>
                <textarea v-model="publishForm.caption" rows="4" maxlength="2200" placeholder="Escribe el pie de foto..."
                    class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:border-pink-500 focus:outline-none focus:ring-1 focus:ring-pink-500"></textarea>
                <p v-if="page.props.errors?.caption" class="text-xs text-red-500 mt-1">{{ page.props.errors.caption }}</p>

                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mt-4 mb-1">Imagen por URL</label>
                <input v-model="publishForm.image_url" type="url" placeholder="https://.../foto.jpg"
                    class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:border-pink-500 focus:outline-none focus:ring-1 focus:ring-pink-500" />
                <p v-if="page.props.errors?.image_url" class="text-xs text-red-500 mt-1">{{ page.props.errors.image_url }}</p>

                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mt-4 mb-1">Video por URL</label>
                <input v-model="publishForm.video_url" type="url" placeholder="https://.../video.mp4"
                    class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:border-pink-500 focus:outline-none focus:ring-1 focus:ring-pink-500" />
                <p v-if="page.props.errors?.video_url" class="text-xs text-red-500 mt-1">{{ page.props.errors.video_url }}</p>

                <label v-if="publishForm.video_url.trim()" class="flex items-center gap-2 mt-3 text-sm text-gray-700 dark:text-gray-300 cursor-pointer select-none">
                    <input type="checkbox" v-model="publishForm.reel"
                        class="rounded border-gray-300 dark:border-gray-600 text-pink-600 focus:ring-pink-500" />
                    Publicar como Reel
                </label>

                <p class="text-xs text-gray-400 dark:text-gray-500 mt-3">
                    Indica una imagen o un video. El archivo debe estar en una URL pública; Instagram puede tardar
                    unos segundos en procesar el video antes de publicarlo.
                </p>

                <div class="flex justify-end gap-3 mt-6">
                    <button @click="publishOpen = false"
                        class="px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        Cancelar
                    </button>
                    <button @click="submitPublish"
                        :disabled="(!publishForm.image_url.trim() && !publishForm.video_url.trim()) || publishing"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-[#833ab4] via-[#e1306c] to-[#f77737] text-white text-sm font-medium transition disabled:opacity-50 disabled:cursor-not-allowed">
                        <Loader2 v-if="publishing" class="w-4 h-4 animate-spin" />
                        <Megaphone v-else class="w-4 h-4" />
                        Publicar
                    </button>
                </div>
            </Modal>

            <!-- Delete media confirm -->
            <ConfirmDelete :show="!!deleteTarget" title="Eliminar publicación"
                :message="deleteTarget ? 'Se eliminará de Instagram permanentemente: ' + (deleteTarget.caption || '(sin pie de foto)').slice(0, 120) : null"
                @confirm="confirmDelete" @close="deleteTarget = null" />

            <!-- Post preview -->
            <PostPreviewModal :show="!!previewPost" network="instagram" :post="previewPost"
                :profile="{ picture: account?.picture, username: account?.username }"
                @close="previewPost = null" />
        </div>
    </AppLayout>
</template>
