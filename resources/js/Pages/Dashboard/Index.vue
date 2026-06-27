<script setup>
import { ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatCard from '@/Components/UI/StatCard.vue'
import ExportPdfModal from '@/Components/ExportPdfModal.vue'
import {
    ListChecks, TrendingUp, Eye, FileDown,
    Users, Activity, BarChart3, CalendarCheck,
    CheckCircle, XCircle, Umbrella, Clock
} from 'lucide-vue-next'

const page = usePage()
const user = computed(() => page.props.auth?.user)
const canViewUsers = computed(() =>
    user.value?.permissions?.includes('view users')
)

const props = defineProps({
    stats: Object,
})

const defaultReportScope = computed(() => page.props.auth?.user?.settings?.default_report_scope ?? 'mensual')

const statusColor = (status) => {
    const colors = {
        pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        script_ready: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        editing: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
        review: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
        scheduled: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
        published: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        in_progress: 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
        completed: 'bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-200',
    }
    return colors[status] || 'bg-gray-100 text-gray-800'
}

const statusBarColor = (status) => {
    const colors = {
        pending: 'bg-yellow-500',
        script_ready: 'bg-blue-500',
        editing: 'bg-purple-500',
        review: 'bg-orange-500',
        scheduled: 'bg-indigo-500',
        published: 'bg-green-500',
        cancelled: 'bg-red-500',
    }
    return colors[status] || 'bg-gray-500'
}

const statusLabel = (status) => props.stats.status_labels?.[status] || status

const goToPlanning = () => router.get('/planning')
const viewTask = (id) => router.get(`/video-tasks/${id}`)
const approveAbsence = (type, id) => router[type === 'vacation' ? 'patch' : 'patch'](`/${type === 'vacation' ? 'vacations' : 'time-off'}/${id}/approve`)
const rejectAbsence = (type, id) => router[type === 'vacation' ? 'patch' : 'patch'](`/${type === 'vacation' ? 'vacations' : 'time-off'}/${id}/reject`)
const showPdfModal = ref(false)

const pendingTotal = computed(() => props.stats.pending_approvals?.total || 0)

const statusSummary = computed(() => {
    const keys = ['published', 'scheduled', 'review', 'editing', 'script_ready', 'pending', 'cancelled']
    return keys.map(k => ({
        key: k,
        label: statusLabel(k),
        count: props.stats.status_counts?.[k] || 0,
        color: statusBarColor(k),
    }))
})

const totalWithStatus = computed(() => statusSummary.value.reduce((s, i) => s + i.count, 0) || 1)

const circumference = 2 * Math.PI * 15.5
</script>

<template>
    <AppLayout>
        <div class="space-y-6 sm:space-y-8">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Dashboard</h1>
                    <p class="text-gray-500 dark:text-gray-400 mt-1">Resumen de produccion de video</p>
                </div>
                <button @click="showPdfModal = true"
                    class="px-4 sm:px-5 py-2.5 sm:py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-medium transition flex items-center gap-2 text-sm">
                    <FileDown class="w-4 h-4" />
                    <span class="hidden sm:inline">Exportar PDF</span>
                    <span class="sm:hidden">PDF</span>
                </button>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-2 xl:grid-cols-5 gap-3 sm:gap-6">
                <StatCard title="Tareas de video" :value="stats.total_video_tasks" color="#6366f1" />
                <StatCard title="Tareas extra" :value="stats.total_extra_tasks" color="#14b8a6" />
                <StatCard title="En progreso" :value="stats.in_progress" color="#f59e0b" />
                <StatCard title="Completadas" :value="stats.completed" color="#22c55e" />
                <StatCard title="Vencidas" :value="stats.overdue" color="#ef4444" />
            </div>

            <div class="grid grid-cols-1 gap-6" :class="canViewUsers ? 'xl:grid-cols-3' : 'xl:grid-cols-2'">

                <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="p-3 rounded-xl bg-indigo-100 dark:bg-indigo-900">
                            <ListChecks class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Tareas de hoy</h2>
                            <p class="text-sm text-gray-500">{{ stats.today_tasks_count }} tareas, {{ stats.today_extra_count }} extras</p>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div v-if="stats.today_tasks.length === 0 && stats.today_extra.length === 0"
                            class="text-center py-6 text-gray-400 dark:text-gray-500 text-sm">
                            Sin tareas para hoy
                        </div>
                        <div v-for="task in stats.today_tasks" :key="'t' + task.id + (task.is_session ? '-s' : '')"
                            @click="viewTask(task.id)"
                            class="flex items-center justify-between p-3 rounded-xl border border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer transition">
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-900 dark:text-white text-sm truncate">
                                    {{ task.title }}
                                    <span v-if="task.is_session"
                                        class="text-[10px] font-medium px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-200 ml-1">Sesión</span>
                                </p>
                                <p class="text-xs text-gray-500">{{ task.time_range }}</p>
                            </div>
                            <div class="flex items-center gap-2 ml-2">
                                <span class="text-[10px] font-medium px-2 py-0.5 rounded-full whitespace-nowrap"
                                    :class="statusColor(task.status)">
                                    {{ task.status_label }}
                                </span>
                                <Eye class="w-3.5 h-3.5 text-indigo-500" />
                            </div>
                        </div>
                        <div v-for="task in stats.today_extra" :key="'e' + task.id"
                            @click="goToPlanning"
                            class="flex items-center justify-between p-3 rounded-xl border border-dashed border-teal-300 dark:border-teal-700 bg-teal-50 dark:bg-teal-900/10 hover:bg-teal-100 dark:hover:bg-teal-900/20 cursor-pointer transition">
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-900 dark:text-white text-sm truncate">[Extra] {{ task.title }}</p>
                                <p class="text-xs text-gray-500">{{ task.time_range }}</p>
                            </div>
                            <span class="text-[10px] px-2 py-0.5 rounded-full whitespace-nowrap ml-2"
                                :class="task.location === 'fuera' ? 'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-200' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'">
                                {{ task.location === 'fuera' ? 'Fuera' : 'Oficina' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="p-3 rounded-xl bg-green-100 dark:bg-green-900">
                            <TrendingUp class="w-5 h-5 text-green-600 dark:text-green-400" />
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Rendimiento {{ stats.period_label?.toLowerCase() }}</h2>
                            <p class="text-sm text-gray-500">Completadas vs totales</p>
                        </div>
                    </div>
                    <div class="flex items-center justify-center py-6">
                        <div class="relative w-32 h-32">
                            <svg class="w-32 h-32 -rotate-90" viewBox="0 0 36 36">
                                <circle cx="18" cy="18" r="15.5" fill="none" stroke="#e5e7eb" stroke-width="3"
                                    class="dark:stroke-gray-700" />
                                <circle cx="18" cy="18" r="15.5" fill="none" stroke="#4f46e5" stroke-width="3"
                                    :stroke-dashoffset="circumference - (stats.period_completion / 100 * circumference)"
                                    :stroke-dasharray="circumference" stroke-linecap="round" />
                            </svg>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <span class="text-3xl font-bold text-gray-900 dark:text-white">{{ stats.period_completion }}%</span>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4 text-center">
                        <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-800">
                            <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ stats.published_yesterday }}</p>
                            <p class="text-xs text-gray-500">Publicadas ayer</p>
                        </div>
                        <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-800">
                            <p class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ stats.overdue }}</p>
                            <p class="text-xs text-gray-500">Vencidas</p>
                        </div>
                    </div>
                </div>

                <div v-if="canViewUsers" class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="p-3 rounded-xl bg-amber-100 dark:bg-amber-900">
                            <Users class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Usuarios</h2>
                            <p class="text-sm text-gray-500">{{ stats.new_users }} nuevos hoy</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-800 text-center">
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total_users }}</p>
                            <p class="text-xs text-gray-500">Total usuarios</p>
                        </div>
                        <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-800 text-center">
                            <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ stats.new_users }}</p>
                            <p class="text-xs text-gray-500">Nuevos hoy</p>
                        </div>
                    </div>
                    <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Usuarios recientes</h3>
                    <div class="space-y-3">
                        <div v-for="user in stats.recent_users" :key="user.id"
                            class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3 last:border-0">
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white text-sm">{{ user.name }}</p>
                                <p class="text-xs text-gray-500">{{ user.email }}</p>
                            </div>
                            <span class="text-xs text-gray-400">{{ (user.created_at || '').slice(0, 19).replace('T', ' ') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6" :class="canViewUsers ? 'xl:grid-cols-2' : 'xl:grid-cols-1'">
                <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="p-3 rounded-xl bg-purple-100 dark:bg-purple-900">
                            <BarChart3 class="w-5 h-5 text-purple-600 dark:text-purple-400" />
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Tareas por estado</h2>
                            <p class="text-sm text-gray-500">Distribucion de todas las tareas</p>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <div v-for="s in statusSummary" :key="s.key" class="space-y-1.5">
                            <div class="flex items-center justify-between text-sm">
                                <div class="flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-sm" :class="s.color"></span>
                                    <span class="text-gray-700 dark:text-gray-300">{{ s.label }}</span>
                                </div>
                                <span class="font-semibold text-gray-900 dark:text-white">{{ s.count }}</span>
                            </div>
                            <div class="w-full h-2 rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-500" :class="s.color"
                                    :style="{ width: (s.count / totalWithStatus * 100) + '%' }"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="canViewUsers" class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="p-3 rounded-xl bg-blue-100 dark:bg-blue-900">
                            <Activity class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Actividad reciente</h2>
                            <p class="text-sm text-gray-500">Ultimas acciones en el sistema</p>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div v-if="!stats.recent_activity?.length"
                            class="text-center py-6 text-gray-400 dark:text-gray-500 text-sm">
                            Sin actividad reciente
                        </div>
                        <div v-for="log in stats.recent_activity?.slice(0, 5)" :key="log.id"
                            class="flex items-start gap-3 border-b border-gray-100 dark:border-gray-800 pb-3 last:border-0">
                            <div class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center text-xs font-bold text-indigo-600 dark:text-indigo-400 shrink-0 mt-0.5">
                                {{ log.causer?.name?.charAt(0) || '?' }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-gray-900 dark:text-white">
                                    <span class="font-medium">{{ log.causer?.name || 'Sistema' }}</span> <span class="text-gray-500">{{ log.description }}</span>
                                </p>
                                <p class="text-xs text-gray-400 mt-0.5"> {{ log.created_at }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                <div v-if="canViewUsers" class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="p-3 rounded-xl bg-purple-100 dark:bg-purple-900">
                            <CalendarCheck class="w-5 h-5 text-purple-600 dark:text-purple-400" />
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Ausencias de hoy</h2>
                            <p class="text-sm text-gray-500">Vacaciones y permisos del dia</p>
                        </div>
                    </div>
                    <div v-if="!stats.today_absences?.vacations?.length && !stats.today_absences?.time_offs?.length"
                        class="text-center py-6 text-gray-400 dark:text-gray-500 text-sm">
                        Sin ausencias hoy
                    </div>
                    <div v-if="stats.today_absences?.vacations?.length" class="mb-4">
                        <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                            <Umbrella class="w-3.5 h-3.5 text-purple-500" /> Vacaciones
                        </h3>
                        <div class="flex flex-wrap gap-2">
                            <span v-for="name in stats.today_absences.vacations" :key="name"
                                class="inline-flex items-center gap-1 text-sm font-medium px-3 py-1.5 rounded-full bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300">
                                {{ name }}
                            </span>
                        </div>
                    </div>
                    <div v-if="stats.today_absences?.time_offs?.length">
                        <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                            <Clock class="w-3.5 h-3.5 text-orange-500" /> Permisos
                        </h3>
                        <div class="flex flex-wrap gap-2">
                            <span v-for="name in stats.today_absences.time_offs" :key="name"
                                class="inline-flex items-center gap-1 text-sm font-medium px-3 py-1.5 rounded-full bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300">
                                {{ name }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="p-3 rounded-xl bg-amber-100 dark:bg-amber-900">
                            <Clock class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Pendientes de aprobar</h2>
                            <p class="text-sm text-gray-500">{{ pendingTotal }} solicitud{{ pendingTotal !== 1 ? 'es' : '' }} pendiente{{ pendingTotal !== 1 ? 's' : '' }}</p>
                        </div>
                    </div>
                    <div v-if="!pendingTotal"
                        class="text-center py-6 text-gray-400 dark:text-gray-500 text-sm">
                        Sin solicitudes pendientes
                    </div>
                    <div v-for="item in stats.pending_approvals?.vacations" :key="'vac' + item.id"
                        class="flex items-center justify-between py-3 border-b border-gray-100 dark:border-gray-800 last:border-0">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1.5">
                                <Umbrella class="w-3.5 h-3.5 text-purple-500 shrink-0" />
                                <p class="font-medium text-gray-900 dark:text-white text-sm truncate">{{ item.user_name }}</p>
                            </div>
                            <p class="text-xs text-gray-500 mt-0.5">
                                Vacaciones {{ item.days_used }} dias ({{ item.start_date }} a {{ item.end_date }})
                            </p>
                            <p v-if="item.reason" class="text-xs text-gray-400 truncate">{{ item.reason }}</p>
                        </div>
                        <div v-if="stats.can_approve_absences" class="flex items-center gap-1 ml-2 shrink-0">
                            <button @click="approveAbsence('vacation', item.id)"
                                class="p-1.5 rounded-lg hover:bg-green-100 dark:hover:bg-green-900/30 text-green-600 dark:text-green-400 transition"
                                title="Aprobar">
                                <CheckCircle class="w-4 h-4" />
                            </button>
                            <button @click="rejectAbsence('vacation', item.id)"
                                class="p-1.5 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/30 text-red-600 dark:text-red-400 transition"
                                title="Rechazar">
                                <XCircle class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                    <div v-for="item in stats.pending_approvals?.time_offs" :key="'to' + item.id"
                        class="flex items-center justify-between py-3 border-b border-gray-100 dark:border-gray-800 last:border-0">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1.5">
                                <Clock class="w-3.5 h-3.5 text-orange-500 shrink-0" />
                                <p class="font-medium text-gray-900 dark:text-white text-sm truncate">{{ item.user_name }}</p>
                            </div>
                            <p class="text-xs text-gray-500 mt-0.5">
                                Permiso {{ item.date }} {{ item.start_time }}–{{ item.end_time }}
                            </p>
                            <p v-if="item.reason" class="text-xs text-gray-400 truncate">{{ item.reason }}</p>
                        </div>
                        <div v-if="stats.can_approve_absences" class="flex items-center gap-1 ml-2 shrink-0">
                            <button @click="approveAbsence('time_off', item.id)"
                                class="p-1.5 rounded-lg hover:bg-green-100 dark:hover:bg-green-900/30 text-green-600 dark:text-green-400 transition"
                                title="Aprobar">
                                <CheckCircle class="w-4 h-4" />
                            </button>
                            <button @click="rejectAbsence('time_off', item.id)"
                                class="p-1.5 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/30 text-red-600 dark:text-red-400 transition"
                                title="Rechazar">
                                <XCircle class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <ExportPdfModal :show="showPdfModal" :default-scope="defaultReportScope" @close="showPdfModal = false" />
    </AppLayout>
</template>
