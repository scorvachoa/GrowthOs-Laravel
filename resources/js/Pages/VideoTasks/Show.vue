<script setup>
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import ConfirmDeleteModal from '@/Components/Modals/ConfirmDelete.vue'
import { Copy, Check, ExternalLink, Globe } from 'lucide-vue-next'

const page = usePage()
const permissions = page.props.auth?.user?.permissions ?? []
const can = (perm) => permissions.includes(perm)

const props = defineProps({
    task: Object,
    statuses: Array,
    channels: Array,
})

const statusLabel = (value) => {
    const found = props.statuses.find(s => s.value === value)
    return found ? found.label : value
}

const statusColor = (value) => {
    const colors = {
        pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        script_ready: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        editing: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
        review: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
        scheduled: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
        published: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    }
    return colors[value] || 'bg-gray-100 text-gray-800'
}

const showDeleteModal = ref(false)
const confirmDelete = () => {
    router.delete(route('video-tasks.destroy', props.task.id), {
        onSuccess: () => showDeleteModal.value = false,
    })
}

const copiedKey = ref(null)
function copyText(text, key) {
    navigator.clipboard.writeText(text)
    copiedKey.value = key
    setTimeout(() => { copiedKey.value = null }, 1500)
}

const userSettings = computed(() => page.props.auth?.user?.settings ?? {})
const translations = computed(() => props.task.translations || {})
const langs = computed(() => {
    const keys = Object.keys(translations.value)
    const withContent = keys.filter(k =>
        translations.value[k]?.title ||
        translations.value[k]?.script ||
        translations.value[k]?.copy ||
        translations.value[k]?.youtube_url
    )
    return ['es', ...withContent]
})
const langLabels = { es: 'ES', en: 'EN', pt: 'PT', fr: 'FR', de: 'DE', it: 'IT', ja: 'JA', ko: 'KO', zh: 'ZH' }
const currentLang = ref('es')

const currentTitle = computed(() => {
    if (currentLang.value === 'es') return props.task.title
    return translations.value[currentLang.value]?.title || ''
})
const currentScript = computed(() => {
    if (currentLang.value === 'es') return props.task.script
    return translations.value[currentLang.value]?.script || ''
})
const currentCopy = computed(() => {
    if (currentLang.value === 'es') return props.task.copy
    return translations.value[currentLang.value]?.copy || ''
})

const currentYoutubeUrl = computed(() => {
    if (currentLang.value === 'es') return props.task.youtube_url
    return translations.value[currentLang.value]?.youtube_url || ''
})

const embedUrl = computed(() => {
    const url = currentYoutubeUrl.value
    if (!url) return null
    const ytMatch = url.match(/(?:youtube\.com\/(?:watch\?v=|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/)
    if (ytMatch) return { src: `https://www.youtube.com/embed/${ytMatch[1]}`, type: 'youtube', shorts: url.includes('/shorts/') }
    const ttMatch = url.match(/tiktok\.com\/@[\w.-]+\/video\/(\d+)/)
    if (ttMatch) return { src: `https://www.tiktok.com/player/v1/${ttMatch[1]}`, type: 'tiktok' }
    return null
})
</script>

<template>
    <AppLayout>
        <div class="max-w-7xl mx-auto space-y-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ task.title }}</h1>
                    <span class="px-3 py-0.5 rounded-full text-xs font-medium" :class="statusColor(task.status)">
                        {{ statusLabel(task.status) }}
                    </span>
                </div>
                <Link href="/planning" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 text-sm">Volver al calendario</Link>
            </div>

            <div class="text-sm text-gray-500 dark:text-gray-400">
                {{ task.task_date }} &middot; {{ task.time_range }}
                <span v-if="task.channel" class="ml-2 inline-flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full" :style="{ backgroundColor: task.channel.color }"></span>
                    {{ task.channel.name }}
                </span>
            </div>

            <div v-if="langs.length > 1" class="flex items-center gap-1">
                <button v-for="lang in langs" :key="lang" @click="currentLang = lang"
                    class="px-3 py-1.5 text-xs font-medium rounded-lg transition flex items-center gap-1.5"
                    :class="currentLang === lang
                        ? 'bg-indigo-600 text-white shadow-sm ring-1 ring-indigo-500'
                        : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800'">
                    <Globe class="w-3 h-3" />
                    {{ langLabels[lang] || lang.toUpperCase() }}
                </button>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Título del video</h2>
                    <button @click="copyText(currentTitle, 'title')"
                        class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                        <Check v-if="copiedKey === 'title'" class="w-4 h-4 text-green-500" />
                        <Copy v-else class="w-4 h-4" />
                    </button>
                </div>
                <p class="text-gray-900 dark:text-white font-medium">{{ currentTitle }}</p>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Guion</h2>
                        <button v-if="currentScript" @click="copyText(currentScript, 'script')"
                            class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                            <Check v-if="copiedKey === 'script'" class="w-4 h-4 text-green-500" />
                            <Copy v-else class="w-4 h-4" />
                        </button>
                    </div>
                    <p v-if="currentScript" class="text-gray-900 dark:text-white whitespace-pre-wrap text-sm leading-relaxed">{{ currentScript }}</p>
                    <p v-else class="text-gray-400 dark:text-gray-500 text-sm italic">Sin guion</p>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Copy / Descripción</h2>
                        <button v-if="currentCopy" @click="copyText(currentCopy, 'copy')"
                            class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                            <Check v-if="copiedKey === 'copy'" class="w-4 h-4 text-green-500" />
                            <Copy v-else class="w-4 h-4" />
                        </button>
                    </div>
                    <p v-if="currentCopy" class="text-gray-900 dark:text-white whitespace-pre-wrap text-sm leading-relaxed">{{ currentCopy }}</p>
                    <p v-else class="text-gray-400 dark:text-gray-500 text-sm italic">Sin copy</p>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5 flex flex-col">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Video</h2>
                        <button v-if="currentYoutubeUrl" @click="copyText(currentYoutubeUrl, 'url')"
                            class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                            <Check v-if="copiedKey === 'url'" class="w-4 h-4 text-green-500" />
                            <Copy v-else class="w-4 h-4" />
                        </button>
                    </div>
                    <iframe v-if="embedUrl && embedUrl.type === 'youtube' && !embedUrl.shorts" :src="embedUrl.src"
                        class="w-full aspect-video rounded-xl"
                        frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                        referrerpolicy="strict-origin-when-cross-origin"
                        allowfullscreen>
                    </iframe>
                    <iframe v-else-if="embedUrl" :src="embedUrl.src"
                        class="rounded-xl mx-auto w-full max-w-[325px] aspect-[9/16]"
                        frameborder="0"
                        allowfullscreen>
                    </iframe>
                    <a v-if="currentYoutubeUrl" :href="currentYoutubeUrl" target="_blank"
                        class="mt-2 text-xs text-gray-400 hover:text-red-600 dark:hover:text-red-400 truncate inline-flex items-center gap-1">
                        <ExternalLink class="w-3 h-3 flex-shrink-0" />
                        {{ currentYoutubeUrl }}
                    </a>
                    <p v-if="!currentYoutubeUrl" class="text-gray-400 dark:text-gray-500 text-sm italic text-center py-10 mt-auto">Sin enlace de video</p>
                </div>
            </div>

            <div v-if="task.sessions?.length" class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
                <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Sesiones de trabajo</h2>
                <div class="space-y-2">
                    <div class="flex items-center gap-3 text-sm bg-gray-50 dark:bg-gray-900 rounded-xl px-4 py-3">
                        <span class="text-gray-700 dark:text-gray-300 font-medium">{{ task.task_date }}</span>
                        <span class="text-gray-500 dark:text-gray-400">{{ task.time_range || '—' }}</span>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300 font-medium">
                            Inicial
                        </span>
                    </div>
                    <div v-for="session in task.sessions" :key="session.id"
                        class="flex items-center gap-3 text-sm bg-gray-50 dark:bg-gray-900 rounded-xl px-4 py-3">
                        <span class="text-gray-700 dark:text-gray-300 font-medium">{{ session.date }}</span>
                        <span class="text-gray-500 dark:text-gray-400">{{ session.time_range || '—' }}</span>
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                            :class="session.status === 'completed'
                                ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                                : 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300'">
                            {{ session.status === 'completed' ? 'Completado' : 'En progreso' }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="flex gap-3">
                <Link v-if="can('edit planning')" :href="`/video-tasks/${task.id}/edit`"
                    class="px-5 py-3 rounded-xl bg-amber-500 hover:bg-amber-600 text-white font-medium transition text-sm">
                    Editar
                </Link>
                <button v-if="can('delete planning')" @click="showDeleteModal = true"
                    class="px-5 py-3 rounded-xl bg-red-600 hover:bg-red-700 text-white font-medium transition text-sm">
                    Eliminar
                </button>
            </div>

            <ConfirmDeleteModal
                :show="showDeleteModal"
                title="Eliminar tarea"
                message="¿Eliminar esta tarea de planificación?"
                @close="showDeleteModal = false"
                @confirm="confirmDelete"
            />
        </div>
    </AppLayout>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity .2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
