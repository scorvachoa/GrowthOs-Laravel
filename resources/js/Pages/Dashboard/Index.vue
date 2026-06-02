<script setup>
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatCard from '@/Components/UI/StatCard.vue'
import { ListChecks, ClockAlert, CheckCircle2, TrendingUp, ExternalLink } from 'lucide-vue-next'

const props = defineProps({
    stats: Object,
})

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

const goToPlanning = () => router.get('/planning')
</script>

<template>
    <AppLayout>
        <div class="space-y-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Dashboard</h1>
                <p class="text-gray-500 dark:text-gray-400 mt-1">Resumen de produccion de video</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-6">
                <StatCard title="Tareas de video" :value="stats.total_video_tasks" />
                <StatCard title="Tareas extra" :value="stats.total_extra_tasks" />
                <StatCard title="En progreso" :value="stats.in_progress" />
                <StatCard title="Completadas" :value="stats.completed" />
                <StatCard title="Vencidas" :value="stats.overdue" />
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="p-3 rounded-xl bg-indigo-100 dark:bg-indigo-900">
                            <ListChecks class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Tareas de hoy</h2>
                            <p class="text-sm text-gray-500">{{ stats.today_tasks_count }} tareas de video, {{ stats.today_extra_count }} extras</p>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div v-if="stats.today_tasks.length === 0 && stats.today_extra.length === 0"
                            class="text-center py-6 text-gray-400 dark:text-gray-500 text-sm">
                            Sin tareas para hoy
                        </div>
                        <div v-for="task in stats.today_tasks" :key="'v' + task.id"
                            @click="goToPlanning"
                            class="flex items-center justify-between p-3 rounded-xl border border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer transition">
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-900 dark:text-white text-sm truncate">{{ task.title }}</p>
                                <p class="text-xs text-gray-500">{{ task.time_range }}</p>
                            </div>
                            <span class="text-[10px] font-medium px-2 py-0.5 rounded-full whitespace-nowrap ml-2"
                                :class="statusColor(task.status)">
                                {{ task.status_label }}
                            </span>
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
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Rendimiento semanal</h2>
                            <p class="text-sm text-gray-500">Completadas vs totales</p>
                        </div>
                    </div>
                    <div class="flex items-center justify-center py-6">
                        <div class="relative w-32 h-32">
                            <svg class="w-32 h-32 -rotate-90" viewBox="0 0 36 36">
                                <circle cx="18" cy="18" r="15.5" fill="none" stroke="#e5e7eb" stroke-width="3"
                                    class="dark:stroke-gray-700" />
                                <circle cx="18" cy="18" r="15.5" fill="none" stroke="#4f46e5" stroke-width="3"
                                    stroke-dasharray="100" stroke-dashoffset="calc(100 - {{ stats.weekly_completion }})"
                                    :stroke-dashoffset="100 - stats.weekly_completion"
                                    stroke-linecap="round" />
                            </svg>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <span class="text-3xl font-bold text-gray-900 dark:text-white">{{ stats.weekly_completion }}%</span>
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

                <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="p-3 rounded-xl bg-amber-100 dark:bg-amber-900">
                            <ClockAlert class="w-5 h-5 text-amber-600 dark:text-amber-400" />
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
                            <span class="text-xs text-gray-400">{{ user.created_at }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
