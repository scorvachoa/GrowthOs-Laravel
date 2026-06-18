<script setup>
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatCard from '@/Components/UI/StatCard.vue'
import { Youtube, Film, BarChart3, ExternalLink, Users, Eye, Video, Globe, AlertCircle, ChevronRight, MessageCircle, LayoutGrid, List } from 'lucide-vue-next'
import { Line } from 'vue-chartjs'
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Filler,
    Tooltip,
} from 'chart.js'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Filler, Tooltip)

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

const chartData = computed(() => {
    const sorted = chartVideos.value
    if (sorted.length < 2) return null
    const isDark = document.documentElement.classList.contains('dark')

    return {
        labels: sorted.map(v => {
            const d = new Date(v.published_at)
            return d.toLocaleDateString('es', { day: 'numeric', month: 'short' })
        }),
        datasets: [{
            label: 'Vistas',
            data: sorted.map(v => v.view_count || 0),
            fill: true,
            borderColor: '#ef4444',
            backgroundColor: (ctx) => {
                if (!ctx.chart?.chartArea) return 'rgba(239,68,68,0.1)'
                const g = ctx.chart.ctx.createLinearGradient(0, ctx.chart.chartArea.top, 0, ctx.chart.chartArea.bottom)
                g.addColorStop(0, 'rgba(239,68,68,0.3)')
                g.addColorStop(1, 'rgba(239,68,68,0.02)')
                return g
            },
            tension: 0.35,
            pointRadius: 3.5,
            pointHoverRadius: 6,
            pointBackgroundColor: '#ef4444',
            pointBorderColor: isDark ? '#1f2937' : '#ffffff',
            pointBorderWidth: 2,
            pointHoverBorderWidth: 2.5,
            borderWidth: 2.5,
        }],
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
        interaction: {
            intersect: false,
            mode: 'index',
        },
        plugins: {
            tooltip: {
                enabled: true,
                backgroundColor: isDark ? '#1f2937' : '#111827',
                titleFont: { size: 12, weight: '600' },
                bodyFont: { size: 11 },
                padding: 12,
                cornerRadius: 8,
                borderColor: isDark ? 'rgba(75,85,99,0.5)' : 'rgba(255,255,255,0.1)',
                borderWidth: 1,
                displayColors: false,
                callbacks: {
                    label: (ctx) => {
                        const v = ctx.parsed.y
                        if (v >= 1000000) return (v / 1000000).toFixed(1) + 'M vistas'
                        if (v >= 1000) return (v / 1000).toFixed(1) + 'K vistas'
                        return v.toLocaleString() + ' vistas'
                    },
                },
            },
            legend: { display: false },
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: {
                    color: tickColor,
                    font: { size: 10 },
                    maxTicksLimit: 10,
                },
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
                                class="px-4 py-3 transition border-b-2 flex-shrink-0 inline-flex items-center gap-2"
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
                                <span v-if="activeTab === i" class="text-sm font-semibold text-gray-700 dark:text-gray-300 truncate max-w-[140px]">{{ channel.name }}</span>
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

                        <div v-if="userSettings.show_youtube_chart && chartData" class="mb-8 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="p-2 rounded-lg bg-gradient-to-br from-red-500 to-rose-600 shadow-md shadow-red-200 dark:shadow-red-900/30">
                                    <BarChart3 class="w-4 h-4 text-white" />
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-900 dark:text-white text-sm">Rendimiento de videos</h3>
                                    <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">Vistas por video en orden cronologico</p>
                                </div>
                            </div>
                            <div class="relative" style="height: 220px">
                                <Line :data="chartData" :options="chartOptions" />
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
