<script setup>
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { ArrowLeft, History, User, Clock, Eye, EyeOff } from 'lucide-vue-next'

const props = defineProps({
    task: Object,
    activities: Array,
})

const statusColors = {
    pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    script_ready: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    editing: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
    review: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
    scheduled: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
    published: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
}

const statusLabel = (value) => {
    const labels = { pending: 'Pendiente', script_ready: 'Guion listo', editing: 'Editando', review: 'Revisión', scheduled: 'Programado', published: 'Publicado', cancelled: 'Cancelado' }
    return labels[value] || value
}

const formatDate = (dateStr) => {
    const d = new Date(dateStr + 'T12:00:00')
    return d.toLocaleDateString('es-PE', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
}

function readableChanges(props) {
    if (!props || !props.attributes) return null
    const changes = []
    const labels = {
        title: 'Título',
        script: 'Guion',
        copy: 'Copy / Descripción',
        status: 'Estado',
        time_range: 'Bloque horario',
        task_date: 'Fecha',
        youtube_url: 'URL del video',
        channel_id: 'Canal',
    }

    if (props.attributes.status) {
        changes.push(`Estado: ${statusLabel(props.attributes.status)}`)
    }
    for (const [key, val] of Object.entries(props.attributes)) {
        if (key === 'status') continue
        if (key === 'updated_at') continue
        if (key === 'created_by') continue
        const label = labels[key] || key
        const display = key === 'channel_id'
            ? `Canal #${val}`
            : typeof val === 'string' && val.length > 80
                ? val.substring(0, 80) + '...'
                : val
        changes.push(`${label}: ${display}`)
    }
    return changes.length > 0 ? changes : null
}
</script>

<template>
    <AppLayout>
        <div class="max-w-4xl mx-auto space-y-6">
            <button @click="router.get('/task-history')"
                class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                <ArrowLeft class="w-4 h-4" /> Volver al historial
            </button>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                <div class="flex items-start justify-between gap-4">
                    <div class="space-y-2">
                        <h1 class="text-xl font-bold text-gray-900 dark:text-white">{{ task.title }}</h1>
                        <div class="flex flex-wrap items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                            <span>{{ formatDate(task.task_date) }} — {{ task.time_range }}</span>
                            <span v-if="task.channel" class="inline-flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: task.channel.color }"></span>
                                {{ task.channel.name }}
                            </span>
                            <span>Creado por {{ task.created_by }}</span>
                        </div>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-medium shrink-0"
                        :class="statusColors[task.status] || 'bg-gray-100 text-gray-800'">
                        {{ statusLabel(task.status) }}
                    </span>
                </div>
            </div>

            <div class="space-y-3">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                    <History class="w-5 h-5 text-indigo-500" />
                    Historial de cambios
                </h2>

                <div v-if="activities.length === 0" class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-12 text-center">
                    <History class="w-10 h-10 text-gray-300 dark:text-gray-600 mx-auto mb-3" />
                    <p class="text-gray-400 dark:text-gray-500 italic">No hay cambios registrados aun</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Los cambios futuros quedaran registrados automaticamente</p>
                </div>

                <div v-for="activity in activities" :key="activity.id"
                    class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
                    <div class="flex items-start gap-4">
                        <div class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900/50 flex items-center justify-center shrink-0 mt-0.5">
                            <User class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-3 mb-1">
                                <span class="font-medium text-sm text-gray-900 dark:text-white">
                                    {{ activity.causer || 'Sistema' }}
                                </span>
                                <span class="flex items-center gap-1 text-xs text-gray-400 whitespace-nowrap">
                                    <Clock class="w-3 h-3" /> {{ activity.created_at }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 capitalize mb-2">{{ activity.description }}</p>

                            <div v-if="readableChanges(activity.properties)" class="space-y-1">
                                <div v-for="(change, ci) in readableChanges(activity.properties)" :key="ci"
                                    class="text-xs text-gray-600 dark:text-gray-400 pl-3 border-l-2 border-indigo-200 dark:border-indigo-800">
                                    {{ change }}
                                </div>
                            </div>

                            <details class="mt-2">
                                <summary class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 cursor-pointer inline-flex items-center gap-1">
                                    <Eye class="w-3 h-3" /> Ver datos completos
                                </summary>
                                <pre class="mt-2 p-3 bg-gray-50 dark:bg-gray-900 rounded-xl text-xs text-gray-600 dark:text-gray-400 overflow-x-auto max-h-48">{{ JSON.stringify(activity.properties, null, 2) }}</pre>
                            </details>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
