<script setup>
import { ref, watch } from 'vue'
import { Trash2 } from 'lucide-vue-next'

const props = defineProps({
    show: Boolean,
    editingExtra: Object,
    selectedDate: String,
    canDelete: Boolean,
})

const emit = defineEmits(['close', 'save', 'delete'])

const form = ref({
    task_date: '',
    time_range: '',
    title: '',
    status: 'pending',
    location: 'oficina',
})
const timeStart = ref('09:00')
const timeEnd = ref('10:00')

watch([timeStart, timeEnd], ([s, e]) => {
    if (s && e) {
        form.value.time_range = `${s}-${e}`
    }
})

watch(() => props.show, (val) => {
    if (!val) return
    if (props.editingExtra) {
        form.value = {
            task_date: props.editingExtra.task_date,
            time_range: props.editingExtra.time_range,
            title: props.editingExtra.title,
            status: props.editingExtra.status,
            location: props.editingExtra.location,
        }
        const parsed = parseTimeRange(props.editingExtra.time_range)
        timeStart.value = parsed.start
        timeEnd.value = parsed.end
    } else {
        form.value = {
            task_date: props.selectedDate || '',
            time_range: '09:00-10:00',
            title: '',
            status: 'pending',
            location: 'oficina',
        }
        timeStart.value = '09:00'
        timeEnd.value = '10:00'
    }
})

function parseTimeRange(range) {
    if (!range) return { start: '08:00', end: '09:00' }
    const parts = range.split('-')
    if (parts.length !== 2) return { start: '08:00', end: '09:00' }
    return { start: parts[0], end: parts[1] }
}

function submit() {
    emit('save', { ...form.value })
}
</script>

<template>
    <transition name="fade">
        <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-lg p-6 mx-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                        {{ editingExtra ? 'Editar tarea extra' : 'Nueva tarea extra' }}
                    </h2>
                    <button @click="emit('close')" class="text-gray-500 hover:text-gray-700 text-xl leading-none">&times;</button>
                </div>
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Titulo</label>
                        <input v-model="form.title" type="text" required
                            class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
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
                                <input v-model="timeStart" type="time" required
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 dark:[color-scheme:dark]" />
                                <span class="text-gray-400 font-medium">a</span>
                                <input v-model="timeEnd" type="time" required
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 dark:[color-scheme:dark]" />
                            </div>
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
                            <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Ubicacion</label>
                            <select v-model="form.location"
                                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="oficina">Dentro de la oficina</option>
                                <option value="fuera">Fuera de la oficina</option>
                            </select>
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
</template>

<style scoped>
.fade-enter-active, .fade-leave-active {
    transition: opacity 0.2s;
}
.fade-enter-from, .fade-leave-to {
    opacity: 0;
}
</style>
