<script setup>
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatCard from '@/Components/UI/StatCard.vue'
import { Youtube, Film, BarChart3, ExternalLink, Users, Eye, Video, Globe, AlertCircle, ChevronRight, MessageCircle, LayoutGrid, List } from 'lucide-vue-next'

const page = usePage()
const userSettings = computed(() => page.props.auth?.user?.settings ?? {})

const props = defineProps({
    channels: Array,
    stats: Object,
    recent_published: Array,
})

const activeTab = ref(0)
const viewMode = ref('cards')

const activeChannel = computed(() => props.channels[activeTab.value] || null)

const statusLabel = (status) => {
    const labels = {
        pending: 'Pendiente',
        script_ready: 'Guion listo',
        editing: 'Edicion',
        review: 'Revision',
        scheduled: 'Programada',
        published: 'Publicada',
        cancelled: 'Cancelada',
    }
    return labels[status] || status
}

const statusColor = (status) => {
    const colors = {
        pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        script_ready: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        editing: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
        review: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
        scheduled: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
        published: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    }
    return colors[status] || 'bg-gray-100 text-gray-800'
}

const statusKeys = computed(() => Object.keys(props.stats.statuses || {}))

const chartVideos = computed(() => {
    const videos = activeChannel.value?.videos
    if (!videos?.length) return []
    return [...videos]
        .filter(v => v.view_count !== undefined)
        .sort((a, b) => new Date(a.published_at) - new Date(b.published_at))
})

const CHART_W = 600, CHART_H = 220
const PAD = { left: 50, top: 16, right: 20, bottom: 16 }

const chartData = computed(() => {
    const sorted = chartVideos.value
    if (sorted.length < 2) return []
    const maxViews = Math.max(...sorted.map(v => v.view_count || 0), 1)
    const cw = CHART_W - PAD.left - PAD.right
    const ch = CHART_H - PAD.top - PAD.bottom
    return sorted.map((v, i) => ({
        x: PAD.left + (i / (sorted.length - 1)) * cw,
        y: PAD.top + ch - ((v.view_count || 0) / maxViews) * ch,
        video: v,
    }))
})

function smoothPath(points) {
    if (points.length < 2) return ''
    let d = `M ${points[0].x},${points[0].y}`
    for (let i = 1; i < points.length; i++) {
        const p0 = points[i - 1], p1 = points[i]
        const prev = points[i - 2] || p0
        const next = points[i + 1] || p1
        const cp1x = p0.x + (p1.x - prev.x) / 6
        const cp1y = p0.y + (p1.y - prev.y) / 6
        const cp2x = p1.x - (next.x - p0.x) / 6
        const cp2y = p1.y - (next.y - p0.y) / 6
        d += ` C ${cp1x},${cp1y} ${cp2x},${cp2y} ${p1.x},${p1.y}`
    }
    return d
}

const linePath = computed(() => smoothPath(chartData.value))

const areaPath = computed(() => {
    const d = chartData.value
    if (d.length < 2) return ''
    const bottom = CHART_H - PAD.bottom
    let path = smoothPath(d)
    path += ` L ${d[d.length - 1].x},${bottom} L ${d[0].x},${bottom} Z`
    return path
})

const yTicks = computed(() => {
    const sorted = chartVideos.value
    if (!sorted.length) return [0]
    const maxViews = Math.max(...sorted.map(v => v.view_count || 0), 1)
    const nice = Math.ceil(maxViews / 500) * 500 || 500
    return Array.from({ length: 5 }, (_, i) => Math.round((nice / 4) * i))
})

const tooltip = ref({ show: false, x: 0, y: 0, data: null })

const hoveredIndex = ref(-1)

function onChartMouseMove(e) {
    const rect = e.currentTarget.getBoundingClientRect()
    const mx = ((e.clientX - rect.left) / rect.width) * CHART_W
    const points = chartData.value
    if (!points.length) return
    let nearest = 0, minDist = Infinity
    for (let i = 0; i < points.length; i++) {
        const dist = Math.abs(points[i].x - mx)
        if (dist < minDist) { minDist = dist; nearest = i }
    }
    hoveredIndex.value = nearest
    const p = points[nearest]
    tooltip.value = {
        show: true,
        x: e.clientX - rect.left,
        y: e.clientY - rect.top,
        data: p.video,
    }
}

function onChartMouseLeave() {
    tooltip.value.show = false
    hoveredIndex.value = -1
}

const formatDate = (iso) => {
    if (!iso) return ''
    const d = new Date(iso)
    return d.toLocaleDateString('es', { day: 'numeric', month: 'short' })
}

const formatDateFull = (iso) => {
    if (!iso) return ''
    const d = new Date(iso)
    return d.toLocaleDateString('es', { day: 'numeric', month: 'long', year: 'numeric' })
}

const formatNumber = (n) => {
    if (!n && n !== 0) return '—'
    if (n >= 1000000) return (n / 1000000).toFixed(1) + 'M'
    if (n >= 1000) return (n / 1000).toFixed(1) + 'K'
    return n.toLocaleString()
}
</script>

<template>
    <AppLayout>
        <div class="space-y-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">YouTube</h1>
                    <p class="text-gray-500 dark:text-gray-400 mt-1">Canales y rendimiento de contenido</p>
                </div>
                <div v-if="!stats.api_connected"
                    class="flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-sm text-amber-700 dark:text-amber-400">
                    <AlertCircle class="w-4 h-4" />
                    <span>API key no configurada</span>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-2 xl:grid-cols-5 gap-6">
                <StatCard title="Canales" :value="stats.total_channels" color="#ef4444" />
                <StatCard title="Videos totales" :value="stats.total_videos" color="#6366f1" />
                <StatCard title="Publicados" :value="stats.published_total" color="#22c55e" />
                <StatCard title="Publicados este mes" :value="stats.published_this_month" color="#f59e0b" />
                <StatCard title="Pendientes" :value="stats.pending_count" color="#a855f7" />
            </div>

            <div v-if="channels.length === 0"
                class="text-center py-16 text-gray-400 dark:text-gray-500 bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800">
                <Youtube class="w-16 h-16 mx-auto mb-4 opacity-40" />
                <p class="text-base font-medium">No hay canales configurados</p>
                <p class="text-sm mt-1">Agrega canales en Empresa > Canales administrados</p>
            </div>

            <template v-else>
                <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800">
                    <div class="overflow-x-auto">
                        <div class="flex border-b border-gray-200 dark:border-gray-700">
                            <button v-for="(channel, i) in channels" :key="channel.id"
                                @click="activeTab = i"
                                class="px-3 py-3 transition border-b-2 flex-shrink-0"
                                :class="activeTab === i
                                    ? 'border-red-500 bg-red-50 dark:bg-red-900/10'
                                    : 'border-transparent hover:bg-gray-50 dark:hover:bg-gray-800'">
                                <img v-if="channel.thumbnail" :src="channel.thumbnail"
                                    class="w-9 h-9 rounded-full flex-shrink-0 ring-2 transition"
                                    :class="activeTab === i ? 'ring-red-500' : 'ring-transparent'" />
                                <div v-else
                                    class="w-9 h-9 rounded-full flex-shrink-0 ring-2 flex items-center justify-center text-xs font-bold text-white transition"
                                    :class="activeTab === i ? 'ring-red-500' : 'ring-transparent'"
                                    :style="{ backgroundColor: channel.color }">
                                    {{ channel.name.charAt(0).toUpperCase() }}
                                </div>
                            </button>
                        </div>
                    </div>

                    <div v-if="activeChannel" class="p-6">
                        <div v-if="activeChannel.subscriber_count !== undefined" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                            <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-800 flex items-center gap-4">
                                <div class="p-3 rounded-lg bg-red-100 dark:bg-red-900">
                                    <Users class="w-5 h-5 text-red-600 dark:text-red-400" />
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ formatNumber(activeChannel.subscriber_count) }}</p>
                                    <p class="text-xs text-gray-500">Suscriptores</p>
                                </div>
                            </div>
                            <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-800 flex items-center gap-4">
                                <div class="p-3 rounded-lg bg-indigo-100 dark:bg-indigo-900">
                                    <Eye class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ formatNumber(activeChannel.view_count) }}</p>
                                    <p class="text-xs text-gray-500">Vistas totales</p>
                                </div>
                            </div>
                            <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-800 flex items-center gap-4">
                                <div class="p-3 rounded-lg bg-green-100 dark:bg-green-900">
                                    <Video class="w-5 h-5 text-green-600 dark:text-green-400" />
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ activeChannel.live_video_count || 0 }}</p>
                                    <p class="text-xs text-gray-500">Videos publicados</p>
                                </div>
                            </div>
                            <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-800 flex items-center gap-4">
                                <div class="p-3 rounded-lg bg-amber-100 dark:bg-amber-900">
                                    <Globe class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ activeChannel.country || '—' }}</p>
                                    <p class="text-xs text-gray-500">Pais</p>
                                </div>
                            </div>
                        </div>

                        <div v-else-if="activeChannel.youtube_channel_id" class="mb-8 p-4 rounded-xl bg-gray-50 dark:bg-gray-800 text-center text-sm text-gray-500">
                            ID del canal: {{ activeChannel.youtube_channel_id }}
                        </div>

                        <div v-if="userSettings.show_youtube_chart && chartData.length > 1" class="mb-8 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                            <div class="flex items-center gap-3 mb-5">
                                <div class="p-2 rounded-lg bg-gradient-to-br from-red-500 to-rose-600 shadow-md shadow-red-200 dark:shadow-red-900/30">
                                    <BarChart3 class="w-4 h-4 text-white" />
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-900 dark:text-white text-sm">Rendimiento de videos</h3>
                                    <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">Vistas por video en orden cronologico</p>
                                </div>
                            </div>

                            <div class="relative">
                                <svg viewBox="0 0 600 220" class="w-full h-auto select-none" preserveAspectRatio="xMidYMid meet"
                                    @mousemove="onChartMouseMove" @mouseleave="onChartMouseLeave">
                                    <defs>
                                        <linearGradient id="areaGrad" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="0%" stop-color="#ef4444" stop-opacity="0.3" />
                                            <stop offset="100%" stop-color="#ef4444" stop-opacity="0.02" />
                                        </linearGradient>
                                        <filter id="lineGlow">
                                            <feGaussianBlur stdDeviation="2" result="blur"/>
                                            <feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge>
                                        </filter>
                                    </defs>

                                    <line v-for="(tick, i) in yTicks" :key="'g'+i"
                                        :x1="PAD.left" :x2="CHART_W - PAD.right"
                                        :y1="PAD.top + ((CHART_H - PAD.top - PAD.bottom) / 4) * (4 - i)"
                                        :y2="PAD.top + ((CHART_H - PAD.top - PAD.bottom) / 4) * (4 - i)"
                                        stroke="#f1f5f9" stroke-width="1"
                                        class="dark:stroke-gray-800" />

                                    <text v-for="(tick, i) in yTicks" :key="'t'+i"
                                        :x="PAD.left - 4" :y="PAD.top + ((CHART_H - PAD.top - PAD.bottom) / 4) * (4 - i) + 4"
                                        text-anchor="end" class="fill-gray-400 dark:fill-gray-600 text-[10px] font-medium">
                                        {{ formatNumber(tick) }}
                                    </text>

                                    <path :d="areaPath" fill="url(#areaGrad)" class="transition-all duration-300" />

                                    <path :d="linePath" fill="none" stroke="#ef4444" stroke-width="2.5"
                                        stroke-linecap="round" stroke-linejoin="round" filter="url(#lineGlow)" class="transition-all duration-300" />

                                    <circle v-for="(p, i) in chartData" :key="i"
                                        :cx="p.x" :cy="p.y"
                                        :r="hoveredIndex === i ? 6 : 3.5"
                                        class="fill-red-500 stroke-white dark:stroke-gray-900 transition-all duration-200"
                                        stroke-width="2.5"
                                        :class="hoveredIndex === i ? 'drop-shadow-md' : ''" />

                                    <rect x="0" y="0" :width="CHART_W" :height="CHART_H" fill="transparent"
                                        @mousemove="onChartMouseMove" @mouseleave="onChartMouseLeave" />
                                </svg>

                                <transition name="fade">
                                    <div v-if="tooltip.show && tooltip.data"
                                        class="absolute pointer-events-none z-10 -translate-x-1/2 -translate-y-full -mt-2"
                                        :style="{ left: tooltip.x + 'px', top: tooltip.y + 'px' }">
                                        <div class="bg-gray-900 dark:bg-gray-800 text-white rounded-lg shadow-xl border border-gray-700/50 backdrop-blur-sm px-3 py-2.5 min-w-[180px]">
                                            <p class="text-xs font-medium leading-tight line-clamp-2">{{ tooltip.data.title }}</p>
                                            <div class="flex items-center gap-3 mt-1.5 text-[11px] text-gray-300">
                                                <span class="flex items-center gap-1"><Eye class="w-3 h-3" /> {{ formatNumber(tooltip.data.view_count) }}</span>
                                                <span class="flex items-center gap-1"><MessageCircle class="w-3 h-3" /> {{ formatNumber(tooltip.data.comment_count) }}</span>
                                            </div>
                                            <p class="text-[10px] text-gray-500 mt-1">{{ formatDateFull(tooltip.data.published_at) }}</p>
                                        </div>
                                    </div>
                                </transition>
                            </div>

                            <div class="flex items-center gap-4 mt-3 text-[11px] text-gray-400 dark:text-gray-500">
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="w-3 h-0.5 bg-red-500 rounded-full"></span> Vistas
                                </span>
                                <span>Pasa el mouse sobre los puntos para ver detalles</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-4 mb-6">
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Ultimos videos</h2>
                            <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-800 rounded-lg p-0.5">
                                <button @click="viewMode = 'cards'"
                                    class="p-1.5 rounded-md transition"
                                    :class="viewMode === 'cards' ? 'bg-white dark:bg-gray-700 shadow-sm text-gray-900 dark:text-white' : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300'">
                                    <LayoutGrid class="w-4 h-4" />
                                </button>
                                <button @click="viewMode = 'list'"
                                    class="p-1.5 rounded-md transition"
                                    :class="viewMode === 'list' ? 'bg-white dark:bg-gray-700 shadow-sm text-gray-900 dark:text-white' : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300'">
                                    <List class="w-4 h-4" />
                                </button>
                            </div>
                            <a v-if="activeChannel.channel_url" :href="activeChannel.channel_url" target="_blank"
                                class="inline-flex items-center gap-1.5 text-sm font-medium text-red-600 dark:text-red-400 hover:underline ml-auto">
                                <ExternalLink class="w-4 h-4" /> Ver canal en YouTube
                            </a>
                        </div>

                        <div v-if="activeChannel.videos.length === 0"
                            class="text-center py-10 text-gray-400 dark:text-gray-500 text-sm bg-gray-50 dark:bg-gray-800 rounded-xl">
                            <Film class="w-10 h-10 mx-auto mb-2 opacity-40" />
                            No se pudieron obtener videos recientes
                        </div>

                        <div v-else-if="viewMode === 'cards'" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                            <a v-for="video in activeChannel.videos" :key="video.id" :href="video.url" target="_blank"
                                class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden hover:shadow-lg transition group bg-white dark:bg-gray-800/50">
                                <div class="relative">
                                    <img :src="video.medium_thumbnail" :alt="video.title"
                                        class="w-full aspect-video object-cover" />
                                    <div class="absolute top-2 left-2 flex gap-2">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-black/60 text-white backdrop-blur-sm">
                                            <Eye class="w-3 h-3" /> {{ formatNumber(video.view_count) }}
                                        </span>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-black/60 text-white backdrop-blur-sm">
                                            <MessageCircle class="w-3 h-3" /> {{ formatNumber(video.comment_count) }}
                                        </span>
                                    </div>
                                    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition flex items-center justify-center">
                                        <Youtube class="w-10 h-10 text-white opacity-0 group-hover:opacity-80 transition drop-shadow-lg" />
                                    </div>
                                </div>
                                <div class="p-3">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white line-clamp-2 group-hover:text-red-600 dark:group-hover:text-red-400 transition">{{ video.title }}</p>
                                    <p class="text-xs text-gray-400 mt-1">{{ video.published_at ? new Date(video.published_at).toLocaleDateString() : '' }}</p>
                                </div>
                            </a>
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        <th class="text-left py-3 px-3 font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider text-[10px]">Video</th>
                                        <th class="text-left py-3 px-3 font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider text-[10px]">Titulo</th>
                                        <th class="text-right py-3 px-3 font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider text-[10px]">Vistas</th>
                                        <th class="text-right py-3 px-3 font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider text-[10px]">Comentarios</th>
                                        <th class="text-right py-3 px-3 font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider text-[10px]">Publicado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="video in activeChannel.videos" :key="video.id"
                                        class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                                        <td class="py-3 px-3 w-16">
                                            <img :src="video.medium_thumbnail" :alt="video.title"
                                                class="w-14 h-10 rounded-lg object-cover" />
                                        </td>
                                        <td class="py-3 px-3">
                                            <a :href="video.url" target="_blank"
                                                class="font-medium text-gray-900 dark:text-white hover:text-red-600 dark:hover:text-red-400 transition line-clamp-1">
                                                {{ video.title }}
                                            </a>
                                        </td>
                                        <td class="py-3 px-3 text-right text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ formatNumber(video.view_count) }}</td>
                                        <td class="py-3 px-3 text-right text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ formatNumber(video.comment_count) }}</td>
                                        <td class="py-3 px-3 text-right text-gray-400 text-xs whitespace-nowrap">{{ video.published_at ? new Date(video.published_at).toLocaleDateString() : '' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                    <div class="xl:col-span-2 bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-3 rounded-xl bg-indigo-100 dark:bg-indigo-900">
                                <Film class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                            </div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Publicaciones del plan</h2>
                        </div>

                        <div v-if="recent_published.length === 0"
                            class="text-center py-6 text-gray-400 dark:text-gray-500 text-sm">
                            Aun no hay videos publicados
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        <th class="text-left py-3 px-2 font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider text-[10px]">Titulo</th>
                                        <th class="text-left py-3 px-2 font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider text-[10px]">Fecha</th>
                                        <th class="text-left py-3 px-2 font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider text-[10px]">Publicado</th>
                                        <th class="text-right py-3 px-2 font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider text-[10px]">Link</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="video in recent_published" :key="video.id"
                                        class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                                        <td class="py-3 px-2 font-medium text-gray-900 dark:text-white truncate max-w-[250px]">{{ video.title }}</td>
                                        <td class="py-3 px-2 text-gray-500 whitespace-nowrap">{{ video.task_date }}</td>
                                        <td class="py-3 px-2 text-gray-400 text-xs whitespace-nowrap">{{ video.updated_at }}</td>
                                        <td class="py-3 px-2 text-right">
                                            <a v-if="video.youtube_url" :href="video.youtube_url" target="_blank"
                                                class="inline-flex items-center gap-1 text-xs font-medium text-red-600 dark:text-red-400 hover:underline">
                                                <ExternalLink class="w-3 h-3" /> YouTube
                                            </a>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-3 rounded-xl bg-green-100 dark:bg-green-900">
                                <BarChart3 class="w-5 h-5 text-green-600 dark:text-green-400" />
                            </div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Estado de videos</h2>
                        </div>
                        <div class="space-y-3">
                            <div v-for="status in statusKeys" :key="status"
                                class="flex items-center justify-between p-3 rounded-xl bg-gray-50 dark:bg-gray-800">
                                <span class="text-xs font-medium px-2 py-0.5 rounded-full" :class="statusColor(status)">
                                    {{ statusLabel(status) }}
                                </span>
                                <span class="font-bold text-gray-900 dark:text-white">{{ stats.statuses[status] }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </AppLayout>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}
.fade-enter-from, .fade-leave-to {
    opacity: 0;
    transform: translateY(4px);
}
</style>
