<script setup>
import { ref, computed, watch } from 'vue'
import { router, usePage, Link } from '@inertiajs/vue3'
import { usePermissions } from '@/Composables/usePermissions'
import { useTimeAgo } from '@/Composables/useTimeAgo'
import { useFormat } from '@/Composables/useFormat'
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import StatCard from '@/Components/UI/StatCard.vue'
import Modal from '@/Components/Modals/Modal.vue'
import ConfirmDelete from '@/Components/Modals/ConfirmDelete.vue'
import PostPreviewModal from '@/Components/Modals/PostPreviewModal.vue'
import { Facebook, AlertCircle, ExternalLink, ThumbsUp, Newspaper, MessageCircle, MessagesSquare, FileText, BarChart3, Clock, Flame, Trophy, TrendingUp, TrendingDown, Megaphone, Loader2, Pencil, Trash2 } from 'lucide-vue-next'
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

const props = defineProps({
    connection: Object,
    feed: Object,
    insights: Object,
    organization_id: Number,
    days: Number,
})

const facebookToken = ref('')
const facebookError = computed(() => page.props.errors?.facebook_token || null)

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

const posts = computed(() => props.feed?.posts ?? [])
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
        views: deltaInfo(t.views, p.views),
        engagements: deltaInfo(t.engagements, p.engagements),
        video_views: deltaInfo(t.video_views, p.video_views),
    }
})

const engagementOf = (p) => (p.likes || 0) + (p.comments || 0)

const topPosts = computed(() =>
    [...posts.value].sort((a, b) => engagementOf(b) - engagementOf(a)).slice(0, 3)
)

const rankOf = (id) => topPosts.value.findIndex(p => p.id === id)

const lastPost = computed(() => posts.value[0] || null)

const daysSinceLastPost = computed(() => {
    const iso = lastPost.value?.created_time
    if (!iso) return null
    const diff = Date.now() - new Date(iso).getTime()
    if (isNaN(diff)) return null
    return Math.max(0, Math.floor(diff / 86400000))
})

const postStreak = computed(() => {
    if (!lastPost.value?.created_time) return 0
    const dates = new Set()
    posts.value.forEach(p => {
        if (!p.created_time) return
        const d = new Date(p.created_time)
        if (!isNaN(d.getTime())) dates.add(`${d.getFullYear()}-${d.getMonth() + 1}-${d.getDate()}`)
    })
    const cur = new Date(lastPost.value.created_time)
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
        sums[wd] += (d.engagements || 0)
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

const postsThisMonth = computed(() => {
    const now = new Date()
    return posts.value.filter(p => {
        const d = p.created_time ? new Date(p.created_time) : null
        return d && !isNaN(d.getTime()) && d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear()
    }).length
})

const pageUrl = computed(() => props.connection ? `https://facebook.com/${props.connection.page_id}` : null)

const feedTotals = computed(() => {
    const t = { likes: 0, comments: 0 }
    posts.value.forEach(p => {
        t.likes += p.likes || 0
        t.comments += p.comments || 0
    })
    return t
})

const avgEngagement = computed(() => {
    if (!posts.value.length) return 0
    return Math.round((feedTotals.value.likes + feedTotals.value.comments) / posts.value.length)
})

const chartData = computed(() => {
    const days = props.insights?.days
    if (!days || days.length < 2) return null
    const isDark = document.documentElement.classList.contains('dark')

    const datasets = [
        {
            label: 'Vistas',
            data: days.map(d => d.views),
            fill: true,
            borderColor: '#1877f2',
            backgroundColor: 'rgba(24,119,242,0.12)',
            tension: 0.35,
            pointRadius: 3,
            pointHoverRadius: 6,
            pointBackgroundColor: '#1877f2',
            pointBorderColor: isDark ? '#1f2937' : '#ffffff',
            pointBorderWidth: 2,
            borderWidth: 2.5,
        },
        {
            label: 'Interacciones',
            data: days.map(d => d.engagements),
            fill: true,
            borderColor: '#f59e0b',
            backgroundColor: 'rgba(245,158,11,0.10)',
            tension: 0.35,
            pointRadius: 3,
            pointHoverRadius: 6,
            pointBackgroundColor: '#f59e0b',
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
                label: 'Vistas (periodo anterior)',
                data: align('views'),
                fill: false,
                borderColor: 'rgba(24,119,242,0.4)',
                backgroundColor: 'transparent',
                borderDash: [5, 4],
                tension: 0.35,
                pointRadius: 0,
                pointHoverRadius: 4,
                borderWidth: 1.5,
            },
            {
                label: 'Interacciones (periodo anterior)',
                data: align('engagements'),
                fill: false,
                borderColor: 'rgba(245,158,11,0.4)',
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

function connectFacebook() {
    const token = facebookToken.value.trim()
    if (!token || !props.organization_id) return
    router.post('/company/facebook/connect', {
        access_token: token,
        organization_id: props.organization_id,
    }, {
        preserveScroll: true,
        onFinish: () => { facebookToken.value = '' },
    })
}

function disconnectFacebook() {
    if (!window.confirm('¿Desconectar la página de Facebook?')) return
    router.post('/company/facebook/disconnect', {
        organization_id: props.organization_id,
    }, { preserveScroll: true })
}

const publishOpen = ref(false)
const publishForm = ref({ message: '', link: '', photo: '' })
const publishing = ref(false)

function openPublish() {
    publishForm.value = { message: '', link: '', photo: '' }
    publishOpen.value = true
}

function submitPublish() {
    const message = publishForm.value.message.trim()
    if (!message || publishing.value) return

    router.post('/facebook/publish', {
        message,
        link: publishForm.value.link.trim() || undefined,
        photo: publishForm.value.photo.trim() || undefined,
        organization_id: props.organization_id ?? undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        onStart: () => { publishing.value = true },
        onSuccess: () => {
            const hasErrors = Object.keys(page.props.errors ?? {}).length > 0
            if (!hasErrors) {
                publishForm.value = { message: '', link: '', photo: '' }
                publishOpen.value = false
            }
            router.reload({ only: ['feed'], preserveState: true, preserveScroll: true })
        },
        onFinish: () => { publishing.value = false },
    })
}

const editOpen = ref(false)
const editForm = ref({ post_id: '', message: '', link: '' })
const editing = ref(false)
const deleteTarget = ref(null)
const deleting = ref(false)

function openEdit(post) {
    editForm.value = { post_id: post.id, message: post.message || '', link: '' }
    editOpen.value = true
}

function submitEdit() {
    const message = editForm.value.message.trim()
    if (!message || editing.value) return

    router.post('/facebook/posts/update', {
        post_id: editForm.value.post_id,
        message,
        link: editForm.value.link.trim() || undefined,
        organization_id: props.organization_id ?? undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        onStart: () => { editing.value = true },
        onSuccess: () => {
            if (!Object.keys(page.props.errors ?? {}).length) editOpen.value = false
            router.reload({ only: ['feed'], preserveState: true, preserveScroll: true })
        },
        onFinish: () => { editing.value = false },
    })
}

function confirmDelete() {
    if (!deleteTarget.value || deleting.value) return

    router.post('/facebook/posts/delete', {
        post_id: deleteTarget.value.id,
        organization_id: props.organization_id ?? undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        onStart: () => { deleting.value = true },
        onSuccess: () => {
            deleteTarget.value = null
            router.reload({ only: ['feed'], preserveState: true, preserveScroll: true })
        },
        onFinish: () => { deleting.value = false },
    })
}
</script>

<template>
    <AppLayout>
        <div class="space-y-8">
            <!-- Header -->
            <PageHeader title="Facebook" subtitle="Página conectada y publicaciones recientes">
                <button v-if="connection && can('edit empresa')" @click="openPublish"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium shadow-sm transition">
                    <Megaphone class="w-4 h-4" /> Publicar
                </button>
                <Link v-if="connection" href="/facebook/comments"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 text-sm font-medium hover:bg-blue-100 dark:hover:bg-blue-900/50 transition">
                    <MessageCircle class="w-4 h-4" /> Comentarios
                </Link>
                <Link v-if="connection" href="/facebook/messages"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 text-sm font-medium hover:bg-blue-100 dark:hover:bg-blue-900/50 transition">
                    <MessagesSquare class="w-4 h-4" /> Mensajes
                </Link>
                <a v-if="connection && pageUrl" :href="pageUrl" target="_blank"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium shadow-sm transition">
                    <ExternalLink class="w-4 h-4" /> Ver página
                </a>
                <div v-if="connection && connection.stale"
                    class="flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-sm text-amber-700 dark:text-amber-400">
                    <AlertCircle class="w-4 h-4" />
                    <span>Info desactualizada</span>
                </div>
                <div v-else-if="!connection && !organization_id"
                    class="flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-sm text-amber-700 dark:text-amber-400">
                    <AlertCircle class="w-4 h-4" />
                    <span>Selecciona una empresa activa</span>
                </div>
                <div v-else-if="!connection"
                    class="flex items-center gap-2 px-4 py-2 rounded-xl bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-sm text-gray-600 dark:text-gray-400">
                    <Facebook class="w-4 h-4" />
                    <span>Sin conexión</span>
                </div>
            </PageHeader>

            <!-- Not connected -->
            <div v-if="!connection"
                class="text-center py-16 text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800">
                <Facebook class="w-16 h-16 mx-auto mb-4 text-blue-500 opacity-60" />
                <p class="text-base font-medium text-gray-900 dark:text-white">No hay una Página de Facebook conectada</p>
                <p class="text-sm mt-1">Conecta tu Página con un User Access Token (dura 60 días) o un Page Access Token (~1h) de Meta Graph Explorer.</p>

                <div v-if="can('edit empresa')" class="max-w-xl mx-auto mt-6 px-4 flex flex-col sm:flex-row gap-3 text-left">
                    <div class="flex-1">
                        <input v-model="facebookToken" type="password" placeholder="User o Page Access Token" autocomplete="off"
                            class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-blue-500 focus:border-blue-500 text-sm"
                            :class="{ 'border-red-400 focus:border-red-500 focus:ring-red-500': facebookError }"
                            @keyup.enter="connectFacebook" />
                        <p v-if="facebookError" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ facebookError }}</p>
                    </div>
                    <button @click="connectFacebook" :disabled="!facebookToken.trim() || !organization_id"
                        class="shrink-0 px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium transition flex items-center justify-center gap-1.5">
                        <Facebook class="w-4 h-4" /> Conectar
                    </button>
                </div>
                <p v-else class="text-xs text-gray-400 mt-4">Pide a un administrador que conecte la Página.</p>
            </div>

            <!-- Connected -->
            <template v-else>
                <div class="grid grid-cols-2 md:grid-cols-2 xl:grid-cols-4 gap-6">
                    <StatCard title="Seguidores" :value="formatNumber(connection.followers)" color="#1877f2" />
                    <StatCard title="Me gusta" :value="formatNumber(connection.fan_count)" color="#f59e0b" />
                    <StatCard title="Publicaciones" :value="posts.length" color="#22c55e" />
                    <StatCard title="Este mes" :value="postsThisMonth" color="#a855f7" />
                </div>

                <!-- Performance chart -->
                <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-5">
                        <div class="flex items-center gap-3">
                            <div class="p-2 rounded-lg bg-gradient-to-br from-blue-500 to-blue-700 shadow-md shadow-blue-200 dark:shadow-blue-900/30">
                                <BarChart3 class="w-4 h-4 text-white" />
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-900 dark:text-white text-sm">Rendimiento de la Página</h3>
                                <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">Vistas e interacciones diarias · últimos {{ rangeDays }} días</p>
                            </div>
                        </div>
                        <div class="sm:ml-auto flex flex-col items-start sm:items-end gap-2">
                            <div class="flex items-center gap-3">
                                <div class="inline-flex rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden text-xs font-medium">
                                    <button v-for="r in [7, 28, 90]" :key="r" @click="rangeDays = r"
                                        :class="rangeDays === r
                                            ? 'bg-blue-600 text-white'
                                            : 'text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800'"
                                        class="px-3 py-1.5 transition">
                                        {{ r }}d
                                    </button>
                                </div>
                                <label v-if="insightsReady" class="inline-flex items-center gap-1.5 text-[11px] text-gray-500 dark:text-gray-400 cursor-pointer select-none">
                                    <input type="checkbox" v-model="comparePrev"
                                        class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500" />
                                    vs. periodo anterior
                                </label>
                            </div>
                            <div v-if="insightsReady" class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                <span class="inline-flex items-center gap-1.5">
                                    <strong class="text-gray-900 dark:text-white">{{ formatNumber(insights.totals.views) }}</strong> vistas
                                    <span v-if="deltas.views" class="inline-flex items-center gap-0.5 font-semibold"
                                        :class="deltas.views.up ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400'">
                                        <component :is="deltas.views.up ? TrendingUp : TrendingDown" class="w-3 h-3" />
                                        {{ deltas.views.up ? '+' : '' }}{{ deltas.views.pct }}%
                                    </span>
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <strong class="text-gray-900 dark:text-white">{{ formatNumber(insights.totals.engagements) }}</strong> interacciones
                                    <span v-if="deltas.engagements" class="inline-flex items-center gap-0.5 font-semibold"
                                        :class="deltas.engagements.up ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400'">
                                        <component :is="deltas.engagements.up ? TrendingUp : TrendingDown" class="w-3 h-3" />
                                        {{ deltas.engagements.up ? '+' : '' }}{{ deltas.engagements.pct }}%
                                    </span>
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <strong class="text-gray-900 dark:text-white">{{ formatNumber(insights.totals.video_views) }}</strong> videos vistos
                                    <span v-if="deltas.video_views" class="inline-flex items-center gap-0.5 font-semibold"
                                        :class="deltas.video_views.up ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400'">
                                        <component :is="deltas.video_views.up ? TrendingUp : TrendingDown" class="w-3 h-3" />
                                        {{ deltas.video_views.up ? '+' : '' }}{{ deltas.video_views.pct }}%
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
                        <span class="text-[11px]">Si el error menciona permisos, verifica que el token tenga <code class="font-mono font-semibold">read_insights</code> en Meta Graph Explorer y vuelve a conectar la Página.</span>
                    </div>

                    <div v-else
                        class="text-center py-8 text-gray-400 dark:text-gray-500 text-sm bg-gray-50 dark:bg-gray-800 rounded-xl">
                        <BarChart3 class="w-10 h-10 mx-auto mb-2 opacity-40" />
                        Sin datos de rendimiento en el periodo
                    </div>
                </div>

                <!-- Page info -->
                <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6 flex flex-col sm:flex-row sm:items-center gap-5">
                    <img v-if="connection.page_picture" :src="connection.page_picture" :alt="connection.page_name"
                        class="w-20 h-20 rounded-full object-cover bg-white border border-gray-200 dark:border-gray-700 shadow-sm flex-shrink-0"
                        @error="$event.target.style.display='none'" />
                    <div v-else class="w-20 h-20 rounded-full bg-blue-600 text-white flex items-center justify-center flex-shrink-0">
                        <Facebook class="w-10 h-10" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white truncate">{{ connection.page_name }}</h2>
                            <span class="inline-flex items-center text-[11px] font-semibold px-2 py-0.5 rounded-full bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400">Conectada</span>
                            <span v-if="connection.stale" class="inline-flex items-center text-[11px] font-semibold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400">Info desactualizada</span>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            ID de la Página: <code class="font-mono">{{ connection.page_id }}</code>
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Conectada el {{ formatDate(connection.connected_at) }}</p>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-2 sm:flex-shrink-0">
                        <a v-if="pageUrl" :href="pageUrl" target="_blank"
                            class="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                            <ExternalLink class="w-4 h-4" /> Ver en Facebook
                        </a>
                        <button v-if="can('edit empresa')" @click="disconnectFacebook"
                            class="px-4 py-2 rounded-xl border border-red-300 text-red-600 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/30 text-sm font-medium transition">
                            Desconectar
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                    <!-- Recent posts -->
                    <div class="xl:col-span-2 bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-3 rounded-xl bg-blue-100 dark:bg-blue-900">
                                <Newspaper class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                            </div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Publicaciones recientes</h2>
                        </div>

                        <div v-if="!feedError && posts.length > 0" class="flex flex-wrap gap-2 mb-5">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-50 dark:bg-blue-900/30 text-xs font-medium text-blue-700 dark:text-blue-300">
                                <ThumbsUp class="w-3.5 h-3.5" /> {{ formatNumber(feedTotals.likes) }} likes
                            </span>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-50 dark:bg-amber-900/30 text-xs font-medium text-amber-700 dark:text-amber-300">
                                <MessageCircle class="w-3.5 h-3.5" /> {{ formatNumber(feedTotals.comments) }} comentarios
                            </span>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-green-50 dark:bg-green-900/30 text-xs font-medium text-green-700 dark:text-green-300">
                                <BarChart3 class="w-3.5 h-3.5" /> {{ avgEngagement }} promedio por publicación
                            </span>
                        </div>

                        <div v-if="feedError"
                            class="flex items-center gap-2 p-4 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-sm text-amber-700 dark:text-amber-400">
                            <AlertCircle class="w-4 h-4 flex-shrink-0" />
                            No se pudieron cargar las publicaciones de la Página. Se reintentará en unos minutos.
                        </div>

                        <div v-else-if="posts.length === 0"
                            class="text-center py-10 text-gray-400 dark:text-gray-500 text-sm bg-gray-50 dark:bg-gray-800 rounded-xl">
                            <FileText class="w-10 h-10 mx-auto mb-2 opacity-40" />
                            Aún no hay publicaciones en la Página
                        </div>

                        <div v-else class="space-y-4">
                            <article v-for="post in posts" :key="post.id"
                                class="relative flex gap-4 p-4 rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700 cursor-pointer hover:border-blue-200 dark:hover:border-blue-900 hover:shadow-sm transition"
                                @click="previewPost = post">
                                <div v-if="can('edit empresa')" @click.stop class="absolute top-2 right-2 flex items-center gap-1">
                                    <button type="button" title="Editar" @click="openEdit(post)"
                                        class="p-1.5 rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 transition">
                                        <Pencil class="w-3.5 h-3.5" />
                                    </button>
                                    <button type="button" title="Eliminar" @click="deleteTarget = post"
                                        class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 transition">
                                        <Trash2 class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                                <img v-if="post.full_picture" :src="post.full_picture"
                                    class="w-20 h-20 sm:w-24 sm:h-24 rounded-lg object-cover bg-white border border-gray-200 dark:border-gray-700 flex-shrink-0"
                                    title="Ver vista previa"
                                    @error="$event.target.style.display='none'" />
                                <div v-else
                                    class="w-20 h-20 sm:w-24 sm:h-24 rounded-lg bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center flex-shrink-0">
                                    <Facebook class="w-8 h-8 text-blue-500" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <span v-if="rankOf(post.id) >= 0"
                                        class="inline-flex items-center gap-1 mb-1.5 text-[10px] font-bold px-2 py-0.5 rounded-full"
                                        :class="rankOf(post.id) === 0
                                            ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-400'
                                            : rankOf(post.id) === 1
                                                ? 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300'
                                                : 'bg-orange-100 text-orange-600 dark:bg-orange-900/40 dark:text-orange-400'">
                                        <Trophy class="w-3 h-3" /> TOP {{ rankOf(post.id) + 1 }}
                                    </span>
                                    <p class="text-sm text-gray-800 dark:text-gray-200 line-clamp-3">{{ post.message || '(sin texto)' }}</p>
                                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-2 text-xs text-gray-500 dark:text-gray-400">
                                        <span class="inline-flex items-center gap-1">
                                            <ThumbsUp class="w-3.5 h-3.5" /> {{ formatNumber(post.likes) }}
                                        </span>
                                        <span class="inline-flex items-center gap-1">
                                            <MessageCircle class="w-3.5 h-3.5" /> {{ formatNumber(post.comments) }}
                                        </span>
                                        <span>{{ timeAgo(post.created_time) }}</span>
                                        <a v-if="post.permalink_url" :href="post.permalink_url" target="_blank" @click.stop
                                            class="inline-flex items-center gap-1 font-medium text-blue-600 dark:text-blue-400 hover:underline sm:ml-auto">
                                            <ExternalLink class="w-3 h-3" /> Ver publicación
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
                                <div v-if="lastPost" class="flex items-center justify-between p-3 rounded-xl bg-gray-50 dark:bg-gray-800">
                                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Última publicación</span>
                                    <span class="text-xs font-bold text-gray-900 dark:text-white">{{ timeAgo(lastPost.created_time) }}</span>
                                </div>
                                <div v-if="lastPost" class="flex items-center justify-between p-3 rounded-xl bg-gray-50 dark:bg-gray-800">
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
                                        <span class="text-xs font-bold text-blue-600 dark:text-blue-400 capitalize">{{ weekdayStats.bestName }}</span>
                                    </div>
                                    <div class="flex items-end justify-between gap-1.5">
                                        <div v-for="(avg, i) in weekdayStats.avgs" :key="i" class="flex flex-col items-center gap-1 flex-1">
                                            <div class="w-full rounded-t-sm"
                                                :class="i === weekdayStats.best ? 'bg-blue-500' : 'bg-gray-200 dark:bg-gray-700'"
                                                :style="{ height: (avg > 0 ? Math.max(4, Math.round((avg / weekdayStats.max) * 32)) : 3) + 'px' }"></div>
                                            <span class="text-[9px] leading-none"
                                                :class="i === weekdayStats.best ? 'text-blue-600 dark:text-blue-400 font-semibold' : 'text-gray-400 dark:text-gray-500'">
                                                {{ weekdayStats.short[i] }}
                                            </span>
                                        </div>
                                    </div>
                                    <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-2">Promedio de interacciones por día</p>
                                </div>
                            </div>
                        </div>

                        <!-- Top posts -->
                        <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6">
                            <div class="flex items-center gap-3 mb-5">
                                <div class="p-3 rounded-xl bg-amber-100 dark:bg-amber-900">
                                    <Trophy class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                                </div>
                                <h2 class="text-lg font-bold text-gray-900 dark:text-white">Top publicaciones</h2>
                            </div>

                            <ol v-if="topPosts.length" class="space-y-3">
                                <li v-for="(p, i) in topPosts" :key="p.id"
                                    class="flex items-start gap-3 -mx-2 px-2 py-1 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800 transition"
                                    @click="previewPost = p">
                                    <span class="w-6 h-6 rounded-full flex items-center justify-center text-[11px] font-bold flex-shrink-0"
                                        :class="i === 0 ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-400'
                                            : i === 1 ? 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300'
                                            : 'bg-orange-100 text-orange-600 dark:bg-orange-900/40 dark:text-orange-400'">
                                        {{ i + 1 }}
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs text-gray-700 dark:text-gray-300 line-clamp-2">{{ p.message || '(sin texto)' }}</p>
                                        <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">
                                            {{ formatNumber(p.likes) }} likes · {{ formatNumber(p.comments) }} comentarios
                                        </p>
                                    </div>
                                </li>
                            </ol>
                            <p v-else class="text-xs text-gray-400 dark:text-gray-500">Aún no hay publicaciones</p>
                        </div>

                        <!-- Connection details -->
                        <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-3 rounded-xl bg-blue-100 dark:bg-blue-900">
                                <Facebook class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                            </div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Conexión</h2>
                        </div>

                        <div class="space-y-3">
                            <div class="flex items-center justify-between p-3 rounded-xl bg-gray-50 dark:bg-gray-800">
                                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Estado</span>
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                    :class="connection.stale
                                        ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400'
                                        : 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400'">
                                    {{ connection.stale ? 'Desactualizada' : 'Activa' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between p-3 rounded-xl bg-gray-50 dark:bg-gray-800">
                                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Seguidores</span>
                                <span class="font-bold text-gray-900 dark:text-white">{{ formatNumber(connection.followers) }}</span>
                            </div>
                            <div class="flex items-center justify-between p-3 rounded-xl bg-gray-50 dark:bg-gray-800">
                                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Me gusta</span>
                                <span class="font-bold text-gray-900 dark:text-white">{{ formatNumber(connection.fan_count) }}</span>
                            </div>
                            <div class="flex items-center justify-between p-3 rounded-xl bg-gray-50 dark:bg-gray-800">
                                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Conectada desde</span>
                                <span class="text-xs font-bold text-gray-900 dark:text-white">{{ formatDate(connection.connected_at) }}</span>
                            </div>
                        </div>

                        <p class="mt-4 text-[11px] text-gray-400 dark:text-gray-500">
                            El token se guarda cifrado. Si caduca, vuelve a pegarlo en Empresa &gt; Conexiones sociales.
                        </p>

                        <button v-if="can('edit empresa')" @click="disconnectFacebook"
                            class="mt-4 w-full px-4 py-2 rounded-xl border border-red-300 text-red-600 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/30 text-sm font-medium transition">
                            Desconectar
                        </button>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Publish modal -->
            <Modal :show="publishOpen" @close="publishOpen = false">
                <div class="flex items-center gap-2">
                    <Megaphone class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Publicar en Facebook</h3>
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 mb-4">
                    Se publicará al instante en {{ connection?.page_name }}.
                </p>

                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Texto *</label>
                <textarea v-model="publishForm.message" rows="5" maxlength="10000" placeholder="Escribe tu publicación..."
                    class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"></textarea>
                <p v-if="page.props.errors?.message" class="text-xs text-red-500 mt-1">{{ page.props.errors.message }}</p>

                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mt-4 mb-1">Enlace (opcional)</label>
                <input v-model="publishForm.link" type="url" placeholder="https://..."
                    class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" />
                <p v-if="page.props.errors?.link" class="text-xs text-red-500 mt-1">{{ page.props.errors.link }}</p>

                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mt-4 mb-1">Imagen por URL (opcional)</label>
                <input v-model="publishForm.photo" type="url" placeholder="https://.../foto.jpg"
                    class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" />
                <p v-if="page.props.errors?.photo" class="text-xs text-red-500 mt-1">{{ page.props.errors.photo }}</p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">Si indicas imagen, se publica como foto con el texto.</p>

                <div class="flex justify-end gap-3 mt-6">
                    <button @click="publishOpen = false"
                        class="px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        Cancelar
                    </button>
                    <button @click="submitPublish" :disabled="!publishForm.message.trim() || publishing"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium transition disabled:opacity-50 disabled:cursor-not-allowed">
                        <Loader2 v-if="publishing" class="w-4 h-4 animate-spin" />
                        <Megaphone v-else class="w-4 h-4" />
                        Publicar
                    </button>
                </div>
            </Modal>

            <!-- Edit post modal -->
            <Modal :show="editOpen" @close="editOpen = false">
                <div class="flex items-center gap-2">
                    <Pencil class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Editar publicación</h3>
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 mb-4">
                    Los cambios se aplican al instante en Facebook.
                </p>

                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Texto *</label>
                <textarea v-model="editForm.message" rows="5" maxlength="10000"
                    class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"></textarea>
                <p v-if="page.props.errors?.message" class="text-xs text-red-500 mt-1">{{ page.props.errors.message }}</p>

                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mt-4 mb-1">Cambiar enlace (opcional)</label>
                <input v-model="editForm.link" type="url" placeholder="https://..."
                    class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" />
                <p v-if="page.props.errors?.link" class="text-xs text-red-500 mt-1">{{ page.props.errors.link }}</p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">Si lo dejas vacío, el enlace actual se mantiene.</p>

                <div class="flex justify-end gap-3 mt-6">
                    <button @click="editOpen = false"
                        class="px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        Cancelar
                    </button>
                    <button @click="submitEdit" :disabled="!editForm.message.trim() || editing"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium transition disabled:opacity-50 disabled:cursor-not-allowed">
                        <Loader2 v-if="editing" class="w-4 h-4 animate-spin" />
                        <Pencil v-else class="w-4 h-4" />
                        Guardar
                    </button>
                </div>
            </Modal>

            <!-- Delete post confirm -->
            <ConfirmDelete :show="!!deleteTarget" title="Eliminar publicación"
                :message="deleteTarget ? 'Se eliminará de Facebook permanentemente: ' + (deleteTarget.message || '(sin texto)').slice(0, 120) : null"
                @confirm="confirmDelete" @close="deleteTarget = null" />

            <!-- Post preview -->
            <PostPreviewModal :show="!!previewPost" network="facebook" :post="previewPost"
                :profile="{ picture: connection?.page_picture, name: connection?.page_name }"
                @close="previewPost = null" />
        </div>
    </AppLayout>
</template>
