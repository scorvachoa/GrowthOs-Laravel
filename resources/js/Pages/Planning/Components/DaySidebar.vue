<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Plus, ExternalLink, Trash2, X, StickyNote, ChevronDown, ChevronRight, Clock, Eye, Pencil, Users, Share2 } from 'lucide-vue-next'
import ShareModal from '@/Components/Modals/ShareModal.vue'

const page = usePage()
const currentUserId = page.props.auth?.user?.id
const isAdmin = computed(() => {
    const roles = page.props.auth?.user?.roles ?? []
    return roles.includes('Super Admin') || roles.includes('Admin')
})

const props = defineProps({
    selectedDate: String,
    dayTasks: Array,
    extraTasks: Array,
    statuses: Array,
    statusLabels: Object,
    holiday: String,
    observation: Object,
    canCreate: Boolean,
    canEdit: Boolean,
    canDelete: Boolean,
    absences: { type: Array, default: () => [] },
})

const emit = defineEmits([
    'close',
    'createTask',
    'viewTask',
    'editTask',
    'deleteTask',
    'updateStatus',
    'createSession',
    'completeSession',
    'openExtraModal',
    'deleteExtra',
    'updateExtraStatus',
    'saveObservation',
    'moveToPending',
    'editSession',
    'deleteSession',
])

const notes = ref(props.observation?.notes || '')
const editingObs = ref(false)
const showShareModal = ref(false)
const shareTarget = ref(null)
const showEditSessionModal = ref(false)
const editingSession = ref(null)
const editSessionDate = ref('')
const editSessionTimeRange = ref('')
const editSessionStatus = ref('in_progress')

function canDeleteTask(task) {
    return props.canDelete && (task.created_by === currentUserId || isAdmin.value)
}

function canEditTask(task) {
    if (task.created_by === currentUserId || isAdmin.value) return true
    if (task.shared_by_user_name && task.share_role === 'editor') return true
    return props.canEdit && !task.shared_by_user_name
}

const showVideoTasks = ref(true)
const showExtraTasks = ref(true)
const showObservations = ref(true)

watch(() => props.observation, (o) => {
    notes.value = o?.notes || ''
    editingObs.value = false
}, { immediate: true })

function saveNotes() {
    emit('saveObservation', notes.value)
    editingObs.value = false
}

function cancelEdit() {
    notes.value = props.observation?.notes || ''
    editingObs.value = false
}

function openShare(task) {
    shareTarget.value = task
    showShareModal.value = true
}

function onShareSaved() {
    showShareModal.value = false
    shareTarget.value = null
    emit('close')
}

function openEditSession(task) {
    editingSession.value = task
    editSessionDate.value = task.task_date || task.date || ''
    editSessionTimeRange.value = task.time_range || ''
    editSessionStatus.value = task.status || 'in_progress'
    showEditSessionModal.value = true
}

function saveEditSession() {
    emit('editSession', {
        task_id: editingSession.value.id,
        session_id: editingSession.value.session_id,
        date: editSessionDate.value,
        time_range: editSessionTimeRange.value,
        status: editSessionStatus.value,
    })
    showEditSessionModal.value = false
    editingSession.value = null
}

function confirmDeleteSession(task) {
    emit('deleteSession', { task_id: task.id, session_id: task.session_id })
}

function handleEscape(e) {
    if (e.key === 'Escape') {
        emit('close')
    }
}

onMounted(() => {
    document.addEventListener('keydown', handleEscape)
})

onUnmounted(() => {
    document.removeEventListener('keydown', handleEscape)
})
</script>

<template>
    <div class="fixed inset-y-0 right-0 z-50 max-w-full">
        <div class="w-full sm:w-[480px] h-full bg-white dark:bg-gray-800 shadow-2xl overflow-y-auto">
            <div class="sticky top-0 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 p-4 flex items-center justify-between z-10">
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ selectedDate }}</h3>
                    <p v-if="holiday" class="text-xs text-red-500 font-medium">{{ holiday }}</p>
                </div>
                <button @click="emit('close')" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                    <X class="w-5 h-5 text-gray-500" />
                </button>
            </div>

            <div class="p-4 space-y-3">
                <div class="flex items-center justify-between mb-3">
                    <button @click="showVideoTasks = !showVideoTasks" class="flex items-center gap-1.5 text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider hover:text-gray-700 dark:hover:text-gray-300 transition">
                        <component :is="showVideoTasks ? ChevronDown : ChevronRight" class="w-4 h-4" />
                        Tareas de video
                    </button>
                    <button v-if="canCreate" @click="emit('createTask', selectedDate, '09:00-11:00')"
                        class="text-xs px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition flex items-center gap-1">
                        <Plus class="w-3 h-3" /> Nueva
                    </button>
                </div>
                <div v-show="showVideoTasks">
                    <div v-if="dayTasks.length === 0" class="text-center py-4 text-gray-400 dark:text-gray-500 text-sm">
                        Sin tareas de video
                    </div>
                    <div v-for="task in dayTasks" :key="task.id"
                        class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 hover:shadow-sm transition">
                        <div class="flex items-start justify-between mb-2">
                            <div class="flex-1 min-w-0">
                                <h4 class="font-semibold text-gray-900 dark:text-white truncate">{{ task.title }}</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ task.time_range }}</p>
                                <p v-if="task.channel" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 flex items-center gap-1">
                                    <span class="w-2 h-2 rounded-full inline-flex flex-shrink-0" :style="{ backgroundColor: task.channel.color }"></span>
                                    {{ task.channel.name }}
                                </p>
                                <p v-if="task.shared_by_user_name" class="text-xs text-indigo-500 dark:text-indigo-400 mt-0.5 flex items-center gap-1">
                                    <Users class="w-3 h-3" />
                                    Compartido por {{ task.shared_by_user_name }}
                                </p>
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
                                    'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200': task.status === 'in_progress',
                                    'bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-200': task.status === 'completed',
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

                        <div v-if="task.is_session" class="flex items-center gap-2 mt-2 pt-2 border-t border-gray-100 dark:border-gray-700">
                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300 font-medium">Sesión</span>
                            <span v-if="task.status === 'completed'"
                                class="text-[10px] px-2 py-0.5 rounded-full bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300 font-medium">
                                Completado
                            </span>
                            <button v-if="canEdit && task.status !== 'completed'" @click="emit('completeSession', task)"
                                class="text-xs px-3 py-1.5 rounded-lg bg-green-600 hover:bg-green-700 text-white transition">
                                Completar
                            </button>
                            <button v-if="canEdit" @click="openEditSession(task)"
                                class="p-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-white transition"
                                title="Editar sesión">
                                <Pencil class="w-3.5 h-3.5" />
                            </button>
                            <button v-if="canDelete" @click="confirmDeleteSession(task)"
                                class="p-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white transition"
                                title="Eliminar sesión">
                                <Trash2 class="w-3.5 h-3.5" />
                            </button>
                            <button @click="emit('viewTask', task.id)"
                                class="text-xs px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition">
                                Ver tarea
                            </button>
                        </div>
                        <div v-else class="flex items-center gap-2 mt-2 pt-2 border-t border-gray-100 dark:border-gray-700">
                            <select :value="task.status" @change="emit('updateStatus', task, $event.target.value)"
                                class="text-xs rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white py-1 px-2 min-w-[130px]">
                                <option v-for="s in statuses || []" :key="s.value" :value="s.value">{{ s.label }}</option>
                            </select>
                            <button @click="emit('viewTask', task.id)"
                                class="p-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition"
                                title="Ver tarea">
                                <Eye class="w-4 h-4" />
                            </button>
                            <button v-if="canEditTask(task)" @click="emit('editTask', task.id)"
                                class="p-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-white transition"
                                title="Editar tarea">
                                <Pencil class="w-4 h-4" />
                            </button>
                            <button v-if="canEditTask(task)" @click="emit('moveToPending', task)"
                                class="p-1.5 rounded-lg bg-purple-600 hover:bg-purple-700 text-white transition"
                                title="Mover a pendiente">
                                <Clock class="w-4 h-4" />
                            </button>
                            <button v-if="canDeleteTask(task)" @click="emit('deleteTask', task)"
                                class="p-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white transition"
                                title="Eliminar tarea">
                                <Trash2 class="w-4 h-4" />
                            </button>
                            <button @click="openShare(task)"
                                class="p-1.5 rounded-lg bg-indigo-500 hover:bg-indigo-600 text-white transition"
                                title="Compartir tarea">
                                <Share2 class="w-4 h-4" />
                            </button>
                            <button v-if="canEditTask(task) && task.status !== 'published' && task.status !== 'cancelled'" @click="emit('createSession', task)"
                                class="text-xs px-3 py-1.5 rounded-lg bg-cyan-600 hover:bg-cyan-700 text-white transition whitespace-nowrap">
                                + Sesión
                            </button>
                        </div>
                    </div>
                </div>

                <div class="border-t border-gray-200 dark:border-gray-700 pt-4 mt-6">
                    <div class="flex items-center justify-between mb-3">
                        <button @click="showExtraTasks = !showExtraTasks" class="flex items-center gap-1.5 text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider hover:text-gray-700 dark:hover:text-gray-300 transition">
                            <component :is="showExtraTasks ? ChevronDown : ChevronRight" class="w-4 h-4" />
                            Tareas extra
                        </button>
                        <button v-if="canCreate" @click="emit('openExtraModal', null)"
                            class="text-xs px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition flex items-center gap-1">
                            <Plus class="w-3 h-3" /> Nueva
                        </button>
                    </div>
                    <div v-show="showExtraTasks">
                        <div v-if="extraTasks.length === 0" class="text-center py-4 text-gray-400 dark:text-gray-500 text-sm">
                            Sin tareas extra
                        </div>
                        <div v-for="task in extraTasks" :key="'e' + task.id"
                            class="rounded-xl border border-dashed border-teal-300 dark:border-teal-700 p-3 mb-2 hover:shadow-sm transition bg-teal-50 dark:bg-teal-900/10">
                            <div class="flex-1 min-w-0">
                                <h4 class="font-semibold text-gray-900 dark:text-white text-sm truncate">{{ task.title }}</h4>
                                <p v-if="task.description" class="text-xs text-gray-600 dark:text-gray-300 mt-1 whitespace-pre-wrap">{{ task.description }}</p>
                                <p v-if="task.shared_by_user_name" class="text-xs text-indigo-500 dark:text-indigo-400 mt-0.5 flex items-center gap-1">
                                    <Users class="w-3 h-3" />
                                    Compartido por {{ task.shared_by_user_name }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ task.time_range }}</p>
                            </div>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-[10px] px-2 py-0.5 rounded-full"
                                    :class="task.location === 'fuera' ? 'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-200' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'">
                                    {{ task.location === 'fuera' ? 'Fuera de oficina' : 'En oficina' }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2 mt-2 pt-2 border-t border-teal-200 dark:border-teal-800">
                                <select :value="task.status" @change="emit('updateExtraStatus', task, $event.target.value)"
                                    class="text-xs rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white py-1 px-2 min-w-[130px]">
                                    <option value="pending">Pendiente</option>
                                    <option value="completado">Completado</option>
                                </select>
                                <button v-if="canEdit" @click="emit('openExtraModal', task)"
                                    class="text-xs px-2 py-1 rounded-lg bg-amber-500 hover:bg-amber-600 text-white transition">
                                    Editar
                                </button>
                                <button @click="openShare(task)"
                                    class="p-1.5 rounded-lg bg-indigo-500 hover:bg-indigo-600 text-white transition"
                                    title="Compartir tarea">
                                    <Share2 class="w-3.5 h-3.5" />
                                </button>
                                <button v-if="canDeleteTask(task)" @click="emit('deleteExtra', task)"
                                    class="text-xs px-2 py-1 rounded-lg bg-red-600 hover:bg-red-700 text-white transition">
                                    <Trash2 class="w-3 h-3" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-t border-gray-200 dark:border-gray-700 pt-4 mt-6" v-if="absences.length > 0">
                    <div class="flex items-center gap-1.5 mb-3">
                        <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Ausencias</h4>
                    </div>
                    <div v-for="(a, i) in absences" :key="i"
                        class="rounded-lg p-2.5 mb-1.5 text-sm flex items-center gap-2"
                        :class="a.type === 'vacation' ? 'bg-purple-50 dark:bg-purple-900/10 border border-purple-200 dark:border-purple-800 text-purple-700 dark:text-purple-300' : 'bg-orange-50 dark:bg-orange-900/10 border border-orange-200 dark:border-orange-800 text-orange-700 dark:text-orange-300'">
                        <span class="w-2 h-2 rounded-full inline-flex flex-shrink-0"
                            :class="a.type === 'vacation' ? 'bg-purple-500' : 'bg-orange-500'"></span>
                        {{ a.label }}
                    </div>
                </div>

                <div class="border-t border-gray-200 dark:border-gray-700 pt-4 mt-6">
                    <div class="flex items-center justify-between mb-2">
                        <button @click="showObservations = !showObservations" class="flex items-center gap-1.5 text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider hover:text-gray-700 dark:hover:text-gray-300 transition">
                            <component :is="showObservations ? ChevronDown : ChevronRight" class="w-4 h-4" />
                            <StickyNote class="w-3.5 h-3.5" /> Observaciones
                        </button>
                        <button v-if="!editingObs && canEdit" @click="editingObs = true"
                            class="text-xs px-2 py-1 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400 transition">
                            {{ notes ? 'Editar' : 'Añadir' }}
                        </button>
                    </div>
                    <div v-show="showObservations">
                        <div v-if="!editingObs">
                            <p v-if="notes" class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ notes }}</p>
                            <p v-else class="text-xs text-gray-400 dark:text-gray-500 italic">Sin observaciones para este día</p>
                        </div>
                        <div v-else class="space-y-2">
                            <textarea v-model="notes" rows="3"
                                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white text-sm resize-none"
                                placeholder="Observaciones"></textarea>
                            <div class="flex items-center gap-2">
                                <button @click="saveNotes"
                                    class="text-xs px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition">
                                    Guardar
                                </button>
                                <button @click="cancelEdit"
                                    class="text-xs px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400 transition">
                                    Cancelar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <ShareModal
        :show="showShareModal"
        :shareable-type="shareTarget?.is_extra_task ? 'extra_tasks' : 'video_tasks'"
        :shareable-id="shareTarget?.id"
        :current-shares="shareTarget?.shared_with_users || []"
        @close="showShareModal = false"
        @saved="onShareSaved"
    />

    <Teleport to="body">
        <transition name="fade">
            <div v-if="showEditSessionModal"
                class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50"
                @click.self="showEditSessionModal = false">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-sm p-6 mx-4">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Editar sesión</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha</label>
                            <input v-model="editSessionDate" type="date"
                                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Bloque</label>
                            <input v-model="editSessionTimeRange" type="text" placeholder="09:00-11:00"
                                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Estado</label>
                            <select v-model="editSessionStatus"
                                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white text-sm">
                                <option value="in_progress">En progreso</option>
                                <option value="completed">Completado</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 mt-5">
                        <button @click="showEditSessionModal = false"
                            class="px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 text-sm hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                            Cancelar
                        </button>
                        <button @click="saveEditSession"
                            class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium transition">
                            Guardar
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>
</template>
