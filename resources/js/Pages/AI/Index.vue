<script setup>
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import UseTaskModal from '@/Components/AI/UseTaskModal.vue'
import axios from 'axios'

const props = defineProps({
    recent: Array,
    channels: Array,
    work_blocks: Array,
    statuses: Array,
})

const page = usePage()
const permissions = page.props.auth?.user?.permissions ?? []
const can = (perm) => permissions.includes(perm)

const showUseModal = ref(false)

const idea = ref('')
const script = ref('')
const currentVideoId = ref(null)

const copyTitle = ref('')
const copyDescription = ref('')
const copyCta = ref('')
const copyHashtags = ref('')
const copyTags = ref('')
const phrases = ref('')

const loadingScript = ref(false)
const loadingCopy = ref(false)
const loadingPhrases = ref(false)
const loadingAudio = ref(false)
const loadingHistory = ref(false)

const toast = ref({ show: false, message: '' })
let toastTimer = null

function showToast(message) {
    toast.value = { show: true, message }
    clearTimeout(toastTimer)
    toastTimer = setTimeout(() => { toast.value.show = false }, 2600)
}

async function loadVideo(id) {
    loadingHistory.value = true
    try {
        const res = await axios.get(`/ai/history/${id}`)
        const data = res.data
        currentVideoId.value = data.id
        idea.value = data.idea || ''
        script.value = data.script || ''
        copyTitle.value = data.copy_title || ''
        copyDescription.value = data.copy_description || ''
        copyCta.value = data.copy_cta || ''
        copyHashtags.value = data.copy_hashtags || ''
        copyTags.value = data.copy_tags || ''
        phrases.value = data.video_phrases || ''
        showToast('Registro cargado desde el historial.')
        window.scrollTo({ top: 0, behavior: 'smooth' })
    } catch (e) {
        showToast('No se pudo cargar el video del historial.')
    } finally {
        loadingHistory.value = false
    }
}

const hasValidScript = computed(() => script.value.trim().length >= 10)

const copyDescriptionText = computed(() => {
    return [copyDescription.value, copyCta.value, copyHashtags.value].filter(Boolean).join('\n\n')
})

function buildPayload() {
    return {
        idea: idea.value.trim(),
        script: script.value.trim(),
        video_id: currentVideoId.value,
    }
}

function buildExportContent() {
    const descText = copyDescriptionText.value
    return [
        'IDEA',
        idea.value.trim() || 'Sin idea.',
        '',
        'GUION',
        script.value.trim() || 'Sin guion.',
        '',
        'COPY',
        `Título: ${copyTitle.value || 'Sin título.'}`,
        '',
        'Descripción',
        descText || 'Sin descripción.',
        '',
        'Tags',
        copyTags.value || 'Sin tags.',
        '',
        'FRASES',
        phrases.value || 'Sin frases.',
    ].join('\n')
}

async function generateScript() {
    const ideaText = idea.value.trim()
    if (ideaText.length < 3) {
        showToast('Escribe una idea más específica.')
        return
    }

    loadingScript.value = true
    try {
        const response = await axios.post('/ai/generate', { idea: ideaText })
        currentVideoId.value = response.data.video_id
        script.value = response.data.script || ''
        copyTitle.value = ''
        copyDescription.value = ''
        copyCta.value = ''
        copyHashtags.value = ''
        copyTags.value = ''
        phrases.value = ''
        showToast('Guion generado correctamente. Ahora puedes generar copy o frases por separado.')
    } catch (error) {
        showToast(error.response?.data?.message || 'No se pudo generar el guion.')
    } finally {
        loadingScript.value = false
    }
}

function skeletonLines(count) {
    return Array.from({ length: count }, (_, i) => i)
}

async function generateCopyAction() {
    if (!hasValidScript.value) {
        showToast('Primero genera o escribe un guion más completo.')
        return
    }

    loadingCopy.value = true
    try {
        const response = await axios.post('/ai/copy', buildPayload())
        currentVideoId.value = response.data.video_id ?? currentVideoId.value
        const copyData = response.data.copy || {}
        copyTitle.value = copyData.title || ''
        copyDescription.value = copyData.description || ''
        copyCta.value = copyData.cta || ''
        copyHashtags.value = copyData.hashtags || ''
        copyTags.value = copyData.tags || ''
        showToast('Copy generado correctamente.')
    } catch (error) {
        showToast(error.response?.data?.message || 'No se pudo generar el copy.')
    } finally {
        loadingCopy.value = false
    }
}

async function generatePhrasesAction() {
    if (!hasValidScript.value) {
        showToast('Primero genera o escribe un guion más completo.')
        return
    }

    loadingPhrases.value = true
    try {
        const response = await axios.post('/ai/phrases', buildPayload())
        currentVideoId.value = response.data.video_id ?? currentVideoId.value
        phrases.value = response.data.phrases || ''
        showToast('Frases generadas correctamente.')
    } catch (error) {
        showToast(error.response?.data?.message || 'No se pudieron generar las frases.')
    } finally {
        loadingPhrases.value = false
    }
}

async function downloadAudio() {
    if (!hasValidScript.value) {
        showToast('Primero genera o escribe un guion más completo.')
        return
    }

    loadingAudio.value = true
    try {
        const response = await axios.post('/ai/audio', buildPayload(), {
            responseType: 'blob',
        })

        const url = URL.createObjectURL(response.data)
        const link = document.createElement('a')
        const disposition = response.headers['content-disposition'] || ''
        const match = /filename="?([^"]+)"?/i.exec(disposition)
        link.href = url
        link.download = match?.[1] || 'guion-audio.mp3'
        document.body.appendChild(link)
        link.click()
        link.remove()
        URL.revokeObjectURL(url)
        showToast('Audio MP3 descargado.')
    } catch (error) {
        showToast(error.response?.data?.message || 'No se pudo generar el audio.')
    } finally {
        loadingAudio.value = false
    }
}

function exportTxt() {
    const hasCopy = Boolean(copyTitle.value || copyDescription.value || copyCta.value || copyHashtags.value || copyTags.value)
    const hasPhrases = Boolean(phrases.value.trim())
    const hasIdea = Boolean(idea.value.trim())
    const hasScript = Boolean(script.value.trim())

    if (!hasIdea && !hasScript && !hasCopy && !hasPhrases) {
        showToast('No hay contenido para exportar.')
        return
    }

    const content = buildExportContent()
    const blob = new Blob([content], { type: 'text/plain;charset=utf-8' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    const safeIdea = (idea.value.trim() || 'video-script').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 50) || 'video-script'
    link.href = url
    link.download = `${safeIdea}.txt`
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)
    showToast('Archivo TXT exportado.')
}

async function copyText(text, emptyMessage, successMessage) {
    if (!text) {
        showToast(emptyMessage)
        return
    }
    try {
        await navigator.clipboard.writeText(text)
        showToast(successMessage)
    } catch {
        showToast('No se pudo copiar el contenido desde el navegador.')
    }
}
</script>

<template>
    <AppLayout>
        <div class="max-w-7xl mx-auto">
            <div class="mb-6 flex items-start justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Generador de guiones IA - BETA</h1>
                    <p class="text-gray-600 dark:text-gray-400 mt-1">
                        Genera guiones, copy y frases para YouTube Shorts con Gemini IA.
                    </p>
                </div>
                <button v-if="can('view ai history')" @click="router.get('/ai/history')"
                    class="shrink-0 px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition flex items-center gap-2">
                    Historial
                </button>
            </div>

            <div v-if="recent && recent.length > 0" class="mb-6 p-4 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-800 rounded-xl">
                <h3 class="text-sm font-semibold text-indigo-700 dark:text-indigo-300 mb-2">Generaciones recientes</h3>
                <div class="flex flex-wrap gap-2">
                    <button v-for="r in recent" :key="r.id" @click="loadVideo(r.id)"
                        class="px-3 py-1.5 text-xs bg-white dark:bg-gray-800 border border-indigo-200 dark:border-indigo-700 text-indigo-700 dark:text-indigo-300 rounded-lg hover:bg-indigo-100 dark:hover:bg-indigo-800 transition truncate max-w-[280px]">
                        #{{ r.id }} {{ r.idea }}
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-4 gap-4">
                <!-- Columna 1: Idea -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-3">Idea</h3>
                    <textarea
                        v-model="idea"
                        placeholder="Escribe la idea del video..."
                        class="w-full min-h-[200px] resize-y rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 p-3 text-sm text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500"
                    ></textarea>
                    <button
                        @click="generateScript"
                        :disabled="loadingScript"
                        class="mt-3 w-full px-4 py-2.5 bg-indigo-600 text-white font-semibold rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
                    >
                        {{ loadingScript ? 'Generando...' : 'Generar guion' }}
                    </button>
                    <p v-if="loadingScript" class="mt-2 text-sm text-indigo-500">Generando guion con Gemini...</p>
                </div>

                <!-- Columna 2: Guion -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-3">Guion de voz</h3>
                    <div class="flex flex-wrap gap-2 mb-3">
                        <button
                            @click="copyText(script.trim(), 'Todavía no hay guion para copiar.', 'Guion copiado.')"
                            class="px-3 py-1.5 text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition"
                        >Copiar</button>
                        <button
                            @click="generateScript"
                            :disabled="loadingScript"
                            class="px-3 py-1.5 text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 disabled:opacity-50 transition"
                        >Regenerar guion</button>
                        <button
                            @click="downloadAudio"
                            :disabled="loadingAudio || !hasValidScript"
                            class="px-3 py-1.5 text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 disabled:opacity-50 transition"
                        >{{ loadingAudio ? 'Generando...' : 'Descargar audio' }}</button>
                    </div>
                    <div v-if="loadingHistory" class="space-y-3 animate-pulse">
                        <div v-for="i in skeletonLines(6)" :key="i"
                            class="h-4 bg-gray-200 dark:bg-gray-700 rounded" :style="{ width: (70 + Math.random() * 30) + '%' }">
                        </div>
                    </div>
                    <textarea v-else
                        v-model="script"
                        placeholder="Aquí aparecerá el guion editable para narrar con voz IA..."
                        class="w-full min-h-[200px] resize-y rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 p-3 text-sm text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500"
                    ></textarea>
                    <p v-if="loadingAudio" class="mt-2 text-sm text-indigo-500">Generando audio con ElevenLabs...</p>
                </div>

                <!-- Columna 3: Copy -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Copy</h3>
                        </div>
                        <button
                            @click="generateCopyAction"
                            :disabled="loadingCopy || !hasValidScript"
                            class="px-3 py-1.5 text-xs font-semibold bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
                        >{{ loadingCopy ? 'Generando...' : 'Generar copy' }}</button>
                    </div>
                    <p v-if="loadingCopy" class="text-sm text-indigo-500 mb-2">Generando copy con Gemini...</p>

                    <div class="mb-3">
                        <button
                            @click="copyText(copyTitle, 'Todavía no hay título para copiar.', 'Título copiado.')"
                            class="px-3 py-1.5 text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition"
                        >Copiar título</button>
                    </div>
                    <div v-if="loadingHistory" class="space-y-3 animate-pulse">
                        <div v-for="i in skeletonLines(4)" :key="i"
                            class="h-4 bg-gray-200 dark:bg-gray-700 rounded" :style="{ width: (60 + Math.random() * 30) + '%' }">
                        </div>
                    </div>
                    <template v-else>
                        <div class="p-3 rounded-lg border border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/50 mb-3">
                            <pre class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap font-sans"><strong>Título</strong><br>{{ copyTitle || 'El título aparecerá aquí.' }}</pre>
                        </div>

                        <div class="mb-3">
                            <button
                                @click="copyText(copyDescriptionText, 'Todavía no hay descripción para copiar.', 'Descripción copiada.')"
                                class="px-3 py-1.5 text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition"
                            >Copiar descripción</button>
                        </div>
                        <div class="p-3 rounded-lg border border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/50 mb-3">
                            <pre class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap font-sans"><strong>Descripción · CTA · Hashtags</strong><br>{{ copyDescriptionText || 'La descripción aparecerá aquí.' }}</pre>
                        </div>

                        <div class="mb-3">
                            <button
                                @click="copyText(copyTags, 'Todavía no hay tags para copiar.', 'Tags copiados.')"
                                class="px-3 py-1.5 text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition"
                            >Copiar tags</button>
                        </div>
                        <div class="p-3 rounded-lg border border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/50">
                            <pre class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap font-sans"><strong>Tags SEO</strong><br>{{ copyTags || 'Los tags SEO aparecerán aquí.' }}</pre>
                        </div>
                    </template>
                </div>

                <!-- Columna 4: Frases -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Frases video</h3>
                        </div>
                        <button
                            @click="generatePhrasesAction"
                            :disabled="loadingPhrases || !hasValidScript"
                            class="px-3 py-1.5 text-xs font-semibold bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
                        >{{ loadingPhrases ? 'Generando...' : 'Generar frases' }}</button>
                    </div>
                    <p v-if="loadingPhrases" class="text-sm text-indigo-500 mb-2">Generando frases con Gemini...</p>

                    <div v-if="loadingHistory" class="space-y-3 animate-pulse">
                        <div v-for="i in skeletonLines(6)" :key="i"
                            class="h-4 bg-gray-200 dark:bg-gray-700 rounded" :style="{ width: (50 + Math.random() * 40) + '%' }">
                        </div>
                    </div>
                    <template v-else>
                        <div class="mb-3">
                            <button
                                @click="copyText(phrases, 'Todavía no hay frases para copiar.', 'Frases copiadas.')"
                                class="px-3 py-1.5 text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition"
                            >Copiar frases</button>
                        </div>
                        <div class="w-full min-h-[200px] overflow-y-auto rounded-lg border border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/50 p-3">
                            <pre class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap font-sans">{{ phrases || 'Aquí aparecerán frases clave, frases grandes y hooks visuales para edición.' }}</pre>
                        </div>
                    </template>
                </div>
            </div>

            <div class="mt-4 flex justify-end gap-3">
                <button
                    v-if="currentVideoId"
                    @click="showUseModal = true"
                    class="px-4 py-2 bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700 transition flex items-center gap-2"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    Usar
                </button>
                <button
                    @click="exportTxt"
                    class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 font-medium rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition"
                >Exportar TXT</button>
            </div>
        </div>

        <UseTaskModal
            :show="showUseModal"
            :video-id="currentVideoId"
            :idea="idea"
            :channels="channels"
            :work-blocks="work_blocks"
            :statuses="statuses"
            @close="showUseModal = false"
        />

        <!-- Toast -->
        <Transition name="toast">
            <div
                v-if="toast.show"
                class="fixed top-4 right-4 max-w-sm px-4 py-3 bg-gray-900 dark:bg-gray-800 text-white text-sm rounded-xl shadow-lg border border-gray-700 z-50"
            >
                {{ toast.message }}
            </div>
        </Transition>
    </AppLayout>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 0.22s ease;
}
.toast-enter-from {
    opacity: 0;
    transform: translateY(-10px);
}
.toast-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}
</style>
