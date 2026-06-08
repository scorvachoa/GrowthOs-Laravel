<script setup>
import { Plus, ExternalLink, Trash2, X } from 'lucide-vue-next'

defineProps({
    selectedDate: String,
    dayTasks: Array,
    extraTasks: Array,
    statuses: Array,
    statusLabels: Object,
    holiday: String,
    canCreate: Boolean,
    canEdit: Boolean,
    canDelete: Boolean,
})

const emit = defineEmits([
    'close',
    'createTask',
    'viewTask',
    'editTask',
    'deleteTask',
    'updateStatus',
    'openExtraModal',
    'deleteExtra',
])
</script>

<template>
    <div class="fixed inset-y-0 right-0 z-50">
        <div class="w-[480px] h-full bg-white dark:bg-gray-800 shadow-2xl overflow-y-auto">
            <div class="sticky top-0 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 p-4 flex items-center justify-between z-10">
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ selectedDate }}</h3>
                    <p v-if="holiday" class="text-xs text-red-500 font-medium">{{ holiday }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <button v-if="canCreate" @click="emit('createTask', selectedDate, '09:00-11:00')"
                        class="p-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition">
                        <Plus class="w-4 h-4" />
                    </button>
                    <button @click="emit('close')" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                        <X class="w-5 h-5 text-gray-500" />
                    </button>
                </div>
            </div>

            <div class="p-4 space-y-3">
                <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tareas de video</h4>
                <div v-if="dayTasks.length === 0" class="text-center py-4 text-gray-400 dark:text-gray-500 text-sm">
                    Sin tareas de video
                </div>
                <div v-for="task in dayTasks" :key="task.id"
                    class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 hover:shadow-sm transition">
                    <div class="flex items-start justify-between mb-2">
                        <div class="flex-1 min-w-0">
                            <h4 class="font-semibold text-gray-900 dark:text-white truncate">{{ task.title }}</h4>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ task.time_range }}</p>
                        </div>
                        <span class="text-[10px] font-medium px-2 py-0.5 rounded-full whitespace-nowrap ml-2"
                            :class="{
                                'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': task.status === 'pending',
                                'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200': task.status === 'script_ready',
                                'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200': task.status === 'editing',
                                'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200': task.status === 'review',
                                'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200': task.status === 'scheduled',
                                'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': task.status === 'published',
                                'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': task.status === 'cancelled',
                            }">
                            {{ statusLabels[task.status] || task.status }}
                        </span>
                    </div>

                    <div v-if="task.youtube_url" class="mb-2">
                        <a :href="task.youtube_url" target="_blank"
                            class="text-xs text-indigo-600 hover:text-indigo-700 flex items-center gap-1 truncate">
                            <ExternalLink class="w-3 h-3" />
                            {{ task.youtube_url }}
                        </a>
                    </div>

                    <div class="flex items-center gap-2 mt-2 pt-2 border-t border-gray-100 dark:border-gray-700">
                        <select :value="task.status" @change="emit('updateStatus', task, $event.target.value)"
                            class="text-xs rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white py-1 px-2">
                            <option v-for="s in statuses || []" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                        <button @click="emit('viewTask', task.id)"
                            class="text-xs px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition">
                            Ver
                        </button>
                        <button v-if="canEdit" @click="emit('editTask', task.id)"
                            class="text-xs px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-white transition">
                            Editar
                        </button>
                        <button v-if="canDelete" @click="emit('deleteTask', task)"
                            class="text-xs px-3 py-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white transition">
                            <Trash2 class="w-3 h-3" />
                        </button>
                    </div>
                </div>

                <div class="border-t border-gray-200 dark:border-gray-700 pt-4 mt-6">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tareas extra</h4>
                        <button v-if="canCreate" @click="emit('openExtraModal', null)"
                            class="text-xs px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition flex items-center gap-1">
                            <Plus class="w-3 h-3" /> Nueva
                        </button>
                    </div>
                    <div v-if="extraTasks.length === 0" class="text-center py-4 text-gray-400 dark:text-gray-500 text-sm">
                        Sin tareas extra
                    </div>
                    <div v-for="task in extraTasks" :key="'e' + task.id"
                        class="rounded-xl border border-dashed border-teal-300 dark:border-teal-700 p-3 mb-2 hover:shadow-sm transition bg-teal-50 dark:bg-teal-900/10">
                        <div class="flex items-start justify-between">
                            <div class="flex-1 min-w-0">
                                <h4 class="font-semibold text-gray-900 dark:text-white text-sm truncate">{{ task.title }}</h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ task.time_range }}</p>
                            </div>
                            <span class="text-[10px] font-medium px-2 py-0.5 rounded-full whitespace-nowrap ml-2"
                                :class="{
                                    'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': task.status === 'pending',
                                    'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': task.status === 'completado',
                                }">
                                {{ task.status }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-[10px] px-2 py-0.5 rounded-full"
                                :class="task.location === 'fuera' ? 'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-200' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'">
                                {{ task.location === 'fuera' ? 'Fuera de oficina' : 'En oficina' }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2 mt-2 pt-2 border-t border-teal-200 dark:border-teal-800">
                            <button v-if="canEdit" @click="emit('openExtraModal', task)"
                                class="text-xs px-2 py-1 rounded-lg bg-amber-500 hover:bg-amber-600 text-white transition">
                                Editar
                            </button>
                            <button v-if="canDelete" @click="emit('deleteExtra', task)"
                                class="text-xs px-2 py-1 rounded-lg bg-red-600 hover:bg-red-700 text-white transition">
                                <Trash2 class="w-3 h-3" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
