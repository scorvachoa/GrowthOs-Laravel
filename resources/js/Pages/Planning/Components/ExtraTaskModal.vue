<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Trash2, Users, X } from 'lucide-vue-next'
import axios from 'axios'

const page = usePage()
const currentUserId = page.props.auth?.user?.id

const props = defineProps({
    show: Boolean,
    editingExtra: Object,
    selectedDate: String,
    canDelete: Boolean,
})

function handleEscape(e) {
    if (e.key === 'Escape' && props.show) {
        emit('close')
    }
}

onMounted(() => document.addEventListener('keydown', handleEscape))
onUnmounted(() => document.removeEventListener('keydown', handleEscape))

const emit = defineEmits(['close', 'save', 'delete'])

const form = ref({
    task_date: '',
    time_range: '',
    title: '',
    description: '',
    status: 'pending',
    location: 'oficina',
    shared_user_ids: [],
})

const orgUsers = ref([])
const selectedUsers = ref([])
const shareRoles = ref({})
const shareSearch = ref('')

const filteredShareUsers = computed(() => {
    if (!shareSearch.value.trim()) return orgUsers.value
    const q = shareSearch.value.toLowerCase()
    return orgUsers.value.filter(u => u.name.toLowerCase().includes(q))
})

const isCreator = computed(() => {
    if (!props.editingExtra) return true
    return props.editingExtra.created_by === currentUserId
})

onMounted(async () => {
    try {
        const res = await axios.get('/task-shares/users')
        orgUsers.value = res.data
    } catch { /* ignore */ }
})

const timeStart = ref('09:00')
const timeEnd = ref('10:00')
const timeError = ref('')

function toMinutes(t) {
    const [h, m] = t.split(':').map(Number)
    return h * 60 + m
}

function buildTimeRange() {
    form.value.time_range = `${timeStart.value}-${timeEnd.value}`
}

watch(timeStart, () => buildTimeRange())
watch(timeEnd, () => buildTimeRange())

watch(() => props.show, (val) => {
    if (!val) { shareSearch.value = ''; shareRoles.value = {}; return }
    if (props.editingExtra) {
        form.value = {
            task_date: props.editingExtra.task_date,
            time_range: props.editingExtra.time_range,
            title: props.editingExtra.title,
            description: props.editingExtra.description || '',
            status: props.editingExtra.status,
            location: props.editingExtra.location,
            shared_user_ids: props.editingExtra.shared_user_ids || [],
            shared_roles: props.editingExtra.shared_roles || [],
        }
        selectedUsers.value = props.editingExtra.shared_user_ids || []
        shareRoles.value = {}
        if (props.editingExtra.shared_with_users) {
            props.editingExtra.shared_with_users.forEach(u => {
                shareRoles.value[u.id] = u.role || 'editor'
            })
        }
        const parsed = parseTimeRange(props.editingExtra.time_range)
        timeStart.value = parsed.start
        timeEnd.value = parsed.end
    } else {
        form.value = {
            task_date: props.selectedDate || '',
            time_range: '09:00-10:00',
            title: '',
            description: '',
            status: 'pending',
            location: 'oficina',
            shared_user_ids: [],
        }
        selectedUsers.value = []
        timeStart.value = '09:00'
        timeEnd.value = '10:00'
    }
})

function parseTimeRange(range) {
    if (!range) return { start: '09:00', end: '10:00' }
    const [s, e] = range.split('-')
    return { start: s || '09:00', end: e || '10:00' }
}

function validateTimes() {
    if (timeStart.value && timeEnd.value && toMinutes(timeEnd.value) <= toMinutes(timeStart.value)) {
        timeError.value = 'La hora fin debe ser mayor a la hora de inicio'
        return false
    }
    timeError.value = ''
    return true
}

function submit() {
    if (!validateTimes()) return
    form.value.shared_user_ids = selectedUsers.value
    form.value.shared_roles = selectedUsers.value.map(uid => shareRoles.value[uid] || 'editor')
    emit('save', { ...form.value })
}
</script>

<template>
    <Teleport to="body">
        <transition name="fade">
            <div v-if="show" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50" @click.self="emit('close')">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-lg p-6 mx-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                        {{ editingExtra ? 'Editar tarea extra' : 'Nueva tarea extra' }}
                    </h2>
                    <button @click="emit('close')" class="text-gray-500 hover:text-gray-700 text-xl leading-none">&times;</button>
                </div>
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Título</label>
                        <input v-model="form.title" type="text" required
                            class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Descripción</label>
                        <textarea v-model="form.description" rows="3" placeholder="Detalla lo que se hizo..."
                            class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 resize-none"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Fecha</label>
                            <input v-model="form.task_date" type="date" required
                                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Rango horario</label>
                            <div class="flex items-center gap-2">
                                <input v-model="timeStart" type="time" required @blur="validateTimes"
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 dark:[color-scheme:dark]" />
                                <span class="text-gray-400 font-medium">a</span>
                                <input v-model="timeEnd" type="time" required @blur="validateTimes"
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 dark:[color-scheme:dark]" />
                            </div>
                            <p v-if="timeError" class="mt-1 text-sm text-red-500">{{ timeError }}</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Estado</label>
                            <select v-model="form.status"
                                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="pending">Pendiente</option>
                                <option value="completado">Completado</option>
                            </select>
                        </div>
                        <div>
                            <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Ubicación</label>
                            <select v-model="form.location"
                                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="oficina">Dentro de la oficina</option>
                                <option value="fuera">Fuera de la oficina</option>
                            </select>
                        </div>
                    </div>
                    <div v-if="isCreator && orgUsers.length" class="p-4 rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-2 mb-3">
                            <Users class="w-4 h-4 text-gray-400" />
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Compartir con</span>
                        </div>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mb-3">Solo los usuarios seleccionados podran ver esta tarea</p>
                        <div class="relative mb-3">
                            <input v-model="shareSearch" type="text" placeholder="Buscar usuario..."
                                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                        </div>
                        <div v-if="selectedUsers.length" class="space-y-2 mb-3">
                            <div v-for="uid in selectedUsers" :key="uid"
                                class="flex items-center gap-2 px-3 py-2 rounded-lg bg-indigo-50 dark:bg-indigo-900/30">
                                <span class="flex-1 text-sm font-medium text-indigo-700 dark:text-indigo-300">
                                    {{ orgUsers.find(u => u.id === uid)?.name || uid }}
                                </span>
                                <select :value="shareRoles[uid] || 'editor'"
                                    @change="shareRoles[uid] = $event.target.value"
                                    class="text-xs rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white py-1 px-2">
                                    <option value="editor">Editor</option>
                                    <option value="reader">Lector</option>
                                </select>
                                <button type="button" @click="selectedUsers = selectedUsers.filter(id => id !== uid)"
                                    class="p-1 rounded hover:bg-indigo-200 dark:hover:bg-indigo-800 transition text-indigo-500">
                                    <X class="w-3 h-3" />
                                </button>
                            </div>
                        </div>
                        <div class="max-h-40 overflow-y-auto space-y-1">
                            <label v-for="user in filteredShareUsers" :key="user.id"
                                class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm cursor-pointer transition"
                                :class="selectedUsers.includes(user.id)
                                    ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300'
                                    : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800'">
                                <input type="checkbox" :value="user.id" v-model="selectedUsers"
                                    class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500" />
                                {{ user.name }}
                            </label>
                            <p v-if="shareSearch && filteredShareUsers.length === 0" class="text-xs text-gray-400 dark:text-gray-500 text-center py-2">
                                Sin resultados
                            </p>
                        </div>
                    </div>
                    <div class="flex justify-between gap-3 pt-2">
                        <button v-if="editingExtra && canDelete" type="button" @click="emit('delete', editingExtra)"
                            class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white flex items-center gap-1">
                            <Trash2 class="w-3.5 h-3.5" /> Eliminar
                        </button>
                        <div class="flex gap-3 ml-auto">
                            <button type="button" @click="emit('close')"
                                class="px-4 py-2 rounded-xl border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300">
                                Cancelar
                            </button>
                            <button type="submit"
                                class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white">
                                {{ editingExtra ? 'Actualizar' : 'Crear' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </transition>
    </Teleport>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active {
    transition: opacity 0.2s;
}
.fade-enter-from, .fade-leave-to {
    opacity: 0;
}
</style>
