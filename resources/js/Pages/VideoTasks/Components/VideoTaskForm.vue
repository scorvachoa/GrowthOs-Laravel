<script setup>
import { ref, computed, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import axios from 'axios'
import TextInput from '@/Components/Forms/TextInput.vue'
import PrimaryButton from '@/Components/UI/PrimaryButton.vue'

const page = usePage()
const userSettings = computed(() => page.props.auth?.user?.settings ?? {})

const props = defineProps({
    form: Object,
    statuses: Array,
    channels: Array,
    submitLabel: { type: String, default: 'Guardar' },
    exceptTaskId: { type: Number, default: null },
})

const emit = defineEmits(['submit'])

const useBlocks = computed(() => userSettings.value.use_blocks ?? true)

const MORNING_END = 13
const AFTERNOON_START = 14

const workBlocks = computed(() => {
    if (!useBlocks.value) return []
    const h = userSettings.value.block_hours ?? 2
    const startHour = parseInt(userSettings.value.default_work_start?.split(':')[0] || '9')
    const endHour = parseInt(userSettings.value.default_work_end?.split(':')[0] || '18')
    const blocks = []
    for (let m = startHour; m + h <= Math.min(endHour, MORNING_END); m += h) {
        blocks.push(`${String(m).padStart(2, '0')}:00-${String(m + h).padStart(2, '0')}:00`)
    }
    for (let m = Math.max(startHour, AFTERNOON_START); m + h <= endHour; m += h) {
        blocks.push(`${String(m).padStart(2, '0')}:00-${String(m + h).padStart(2, '0')}:00`)
    }
    return blocks
})

const occupiedBlocks = ref([])
const loadingBlocks = ref(false)

watch(() => props.form.task_date, async (date) => {
    if (!date || !useBlocks.value) {
        occupiedBlocks.value = []
        return
    }
    loadingBlocks.value = true
    try {
        const params = { date }
        if (props.exceptTaskId) {
            params.except_task_id = props.exceptTaskId
        }
        const res = await axios.get('/planning/occupied-blocks', { params })
        occupiedBlocks.value = res.data.occupied || []
        if (!props.form.time_range) {
            const free = workBlocks.value?.find(b => !occupiedBlocks.value.includes(b))
            if (free) props.form.time_range = free
        }
    } catch {
        occupiedBlocks.value = []
    } finally {
        loadingBlocks.value = false
    }
}, { immediate: true })

const embedUrl = computed(() => {
    const url = props.form.youtube_url
    if (!url) return null
    const ytMatch = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/)
    if (ytMatch) return `https://www.youtube.com/embed/${ytMatch[1]}`
    const ttMatch = url.match(/tiktok\.com\/@[\w.-]+\/video\/(\d+)/)
    if (ttMatch) return `https://www.tiktok.com/embed/v2/${ttMatch[1]}`
    return null
})

function parseTimeRange(range) {
    if (!range) return { start: '08:00', end: '09:00' }
    const parts = range.split('-')
    if (parts.length !== 2) return { start: '08:00', end: '09:00' }
    return { start: parts[0], end: parts[1] }
}

const parsed = parseTimeRange(props.form.time_range)
const timeStart = ref(parsed.start)
const timeEnd = ref(parsed.end)

watch([timeStart, timeEnd], ([s, e]) => {
    if (s && e) {
        props.form.time_range = `${s}-${e}`
    }
}, { immediate: true })

function blockDisabled(block) {
    return occupiedBlocks.value.includes(block) && props.form.time_range !== block
}
</script>

<template>
    <form @submit.prevent="emit('submit')" class="space-y-6">
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <div class="space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <TextInput v-model="form.task_date" label="Fecha" type="date" :error="form.errors.task_date" />
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ useBlocks ? 'Bloque horario' : 'Horario inicio / fin' }}
                            <span v-if="loadingBlocks" class="text-xs text-gray-400 ml-1">Verificando...</span>
                        </label>
                        <select v-if="useBlocks" v-model="form.time_range"
                            class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                            <option v-for="block in workBlocks" :key="block" :value="block"
                                :disabled="blockDisabled(block)"
                                :class="{ 'text-gray-400 dark:text-gray-600 line-through': blockDisabled(block) }">
                                {{ block }}
                                <span v-if="blockDisabled(block)">(ocupado)</span>
                            </option>
                        </select>
                        <div v-else class="flex items-center gap-2">
                            <input v-model="timeStart" type="time"
                                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 dark:[color-scheme:dark]" />
                            <span class="text-gray-400 font-medium">a</span>
                            <input v-model="timeEnd" type="time"
                                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 dark:[color-scheme:dark]" />
                        </div>
                        <div v-if="form.errors.time_range" class="mt-1 text-sm text-red-500">{{ form.errors.time_range }}</div>
                    </div>
                </div>

                <TextInput v-model="form.title" label="Titulo del video" :error="form.errors.title" />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Estado</label>
                        <select v-model="form.status"
                            class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                            <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                        <div v-if="form.errors.status" class="mt-1 text-sm text-red-500">{{ form.errors.status }}</div>
                    </div>
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Canal</label>
                        <select v-model="form.channel_id"
                            class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                            <option :value="null">Sin canal</option>
                            <option v-for="ch in channels" :key="ch.id" :value="ch.id">{{ ch.name }}</option>
                        </select>
                        <div v-if="form.errors.channel_id" class="mt-1 text-sm text-red-500">{{ form.errors.channel_id }}</div>
                    </div>
                </div>

                <TextInput v-model="form.youtube_url" label="URL de YouTube / TikTok" type="url" :error="form.errors.youtube_url" />
            </div>

            <div class="space-y-5">
                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Guion</label>
                    <textarea v-model="form.script" rows="10"
                        class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 font-mono text-sm"></textarea>
                    <div v-if="form.errors.script" class="mt-1 text-sm text-red-500">{{ form.errors.script }}</div>
                </div>

                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Copy / Descripcion</label>
                    <textarea v-model="form.copy" rows="6"
                        class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 font-mono text-sm"></textarea>
                    <div v-if="form.errors.copy" class="mt-1 text-sm text-red-500">{{ form.errors.copy }}</div>
                </div>

                <div v-if="embedUrl || form.youtube_url">
                    <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Vista previa</label>
                    <iframe v-if="embedUrl" :src="embedUrl"
                        class="w-full aspect-video rounded-xl"
                        frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen>
                    </iframe>
                    <p v-else class="text-xs text-gray-400">URL no reconocida para previsualizacion</p>
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <PrimaryButton :disabled="form.processing">{{ submitLabel }}</PrimaryButton>
        </div>
    </form>
</template>
