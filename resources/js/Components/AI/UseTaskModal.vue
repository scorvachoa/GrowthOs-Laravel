<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'
import { router } from '@inertiajs/vue3'
import { X } from 'lucide-vue-next'

const props = defineProps({
    show: Boolean,
    videoId: [Number, null],
    idea: String,
    channels: Array,
    workBlocks: Array,
    statuses: Array,
})

const emit = defineEmits(['close'])

const form = ref({
    task_date: new Date().toISOString().split('T')[0],
    time_range: '09:00-11:00',
    status: 'pending',
    channel_id: '',
})

const loading = ref(false)
const errors = ref({})

watch(() => props.show, (val) => {
    if (val) {
        form.value.task_date = new Date().toISOString().split('T')[0]
        form.value.time_range = '09:00-11:00'
        form.value.status = 'pending'
        form.value.channel_id = ''
        errors.value = {}
    }
})

async function submit() {
    if (!props.videoId) return
    loading.value = true
    errors.value = {}

    try {
        const res = await axios.post('/ai/create-task', {
            generated_video_id: props.videoId,
            task_date: form.value.task_date,
            time_range: form.value.time_range,
            status: form.value.status,
            channel_id: form.value.channel_id || null,
        })
        emit('close')
        router.visit(res.data.redirect)
    } catch (e) {
        if (e.response?.status === 422 && e.response?.data?.errors) {
            errors.value = e.response.data.errors
        } else {
            errors.value = { _error: [e.response?.data?.message || 'Error al crear la tarea.'] }
        }
    } finally {
        loading.value = false
    }
}

function close() {
    if (!loading.value) emit('close')
}
</script>

<template>
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
                @click.self="close">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 w-full max-w-lg overflow-hidden">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Usar en planificador</h3>
                        <button @click="close" class="p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400 transition">
                            <X class="w-5 h-5" />
                        </button>
                    </div>

                    <div v-if="errors._error" class="mx-6 mt-4 px-4 py-2 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-lg text-sm text-red-700 dark:text-red-300">
                        {{ errors._error[0] }}
                    </div>

                    <form @submit.prevent="submit" class="p-6 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Idea / Título</label>
                            <input :value="idea" disabled
                                class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 text-sm cursor-not-allowed" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha</label>
                            <input v-model="form.task_date" type="date"
                                class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                            <p v-if="errors.task_date" class="mt-1 text-xs text-red-500">{{ errors.task_date[0] }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Bloque horario</label>
                            <select v-model="form.time_range"
                                class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                <option v-for="b in workBlocks" :key="b" :value="b">{{ b }}</option>
                            </select>
                            <p v-if="errors.time_range" class="mt-1 text-xs text-red-500">{{ errors.time_range[0] }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Estado</label>
                            <select v-model="form.status"
                                class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Canal</label>
                            <select v-model="form.channel_id"
                                class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                <option value="">Sin canal</option>
                                <option v-for="c in channels" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>

                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="close" :disabled="loading"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition disabled:opacity-50">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="loading"
                                class="px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition disabled:opacity-50 flex items-center gap-2">
                                <svg v-if="loading" class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                </svg>
                                {{ loading ? 'Creando...' : 'Crear tarea' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
