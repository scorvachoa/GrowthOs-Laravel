<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { router, usePage } from '@inertiajs/vue3'
import UseTaskModal from '@/Components/AI/UseTaskModal.vue'
import axios from 'axios'
import { Sparkles, FileText, CopyCheck, Quote, Download, CalendarPlus, History, Wand2, Lightbulb, AudioLines, CheckCircle2, Loader2 } from 'lucide-vue-next'

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
const scriptRef = ref(null)

const toast = ref({ show: false, message: '', type: 'success' })
let toastTimer = null

function showToast(message, type = 'success') {
    toast.value = { show: true, message, type }
    clearTimeout(toastTimer)
    toastTimer = setTimeout(() => { toast.value.show = false }, 2600)
}

function autoResize(el) {
    if (!el) return
    el.style.height = 'auto'
    el.style.height = el.scrollHeight + 'px'
}

watch(script, () => {
    nextTick(() => {
        if (scriptRef.value) autoResize(scriptRef.value)
    })
})

onMounted(() => {
    const loadId = new URLSearchParams(window.location.search).get('load')
    if (loadId) {
        loadVideo(loadId)
    }
})

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
        showToast('No se pudo cargar el video del historial.', 'error')
    } finally {
        loadingHistory.value = false
    }
}

const hasValidScript = computed(() => script.value.trim().length >= 10)

const currentStep = computed(() => {
    if (!idea.value.trim()) return 0
    if (!script.value.trim()) return 1
    if (!copyTitle.value && !copyDescription.value) return 2
    return 3
})

const steps = [
    { label: 'Idea', icon: Lightbulb },
    { label: 'Guion', icon: FileText },
    { label: 'Copy', icon: CopyCheck },
    { label: 'Frases', icon: Quote },
]

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
        `Titulo: ${copyTitle.value || 'Sin titulo.'}`,
        '',
        'Descripcion',
        descText || 'Sin descripcion.',
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
        showToast('Escribe una idea mas especifica.', 'error')
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
        showToast('Guion generado correctamente.')
    } catch (error) {
        showToast(error.response?.data?.message || 'No se pudo generar el guion.', 'error')
    } finally {
        loadingScript.value = false
    }
}

function skeletonLines(count) {
    return Array.from({ length: count }, (_, i) => i)
}

async function generateCopyAction() {
    if (!hasValidScript.value) {
        showToast('Primero genera o escribe un guion mas completo.', 'error')
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
        showToast(error.response?.data?.message || 'No se pudo generar el copy.', 'error')
    } finally {
        loadingCopy.value = false
    }
}

async function generatePhrasesAction() {
    if (!hasValidScript.value) {
        showToast('Primero genera o escribe un guion mas completo.', 'error')
        return
    }

    loadingPhrases.value = true
    try {
        const response = await axios.post('/ai/phrases', buildPayload())
        currentVideoId.value = response.data.video_id ?? currentVideoId.value
        phrases.value = response.data.phrases || ''
        showToast('Frases generadas correctamente.')
    } catch (error) {
        showToast(error.response?.data?.message || 'No se pudieron generar las frases.', 'error')
    } finally {
        loadingPhrases.value = false
    }
}

async function downloadAudio() {
    if (!hasValidScript.value) {
        showToast('Primero genera o escribe un guion mas completo.', 'error')
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
        showToast(error.response?.data?.message || 'No se pudo generar el audio.', 'error')
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
        showToast('No hay contenido para exportar.', 'error')
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
        showToast(emptyMessage, 'error')
        return
    }
    try {
        await navigator.clipboard.writeText(text)
        showToast(successMessage)
    } catch {
        showToast('No se pudo copiar el contenido desde el navegador.', 'error')
    }
}
</script>

<template>
    <AppLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
                        <div class="p-2 bg-indigo-100 dark:bg-indigo-900/50 rounded-xl">
                            <Wand2 class="w-6 h-6 text-indigo-600 dark:text-indigo-400" />
                        </div>
                        Generador de guiones
                    </h1>
                    <p class="text-gray-500 dark:text-gray-400 mt-1 ml-11">
                        Genera guiones, copy y frases para YouTube Shorts con Gemini IA
                    </p>
                </div>
                <button v-if="can('view ai history')" @click="router.get('/ai/history')"
                    class="shrink-0 px-4 py-2.5 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition flex items-center gap-2 shadow-sm">
                    <History class="w-4 h-4" />
                    Historial
                </button>
            </div>

            <!-- Step Indicator -->
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-4">
                <div class="flex items-center justify-between max-w-2xl mx-auto">
                    <template v-for="(step, idx) in steps" :key="idx">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold transition-all duration-300"
                                :class="currentStep >= idx
                                    ? 'bg-indigo-600 text-white shadow-md shadow-indigo-200 dark:shadow-indigo-900/50'
                                    : 'bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500'">
                                <CheckCircle2 v-if="currentStep > idx" class="w-4 h-4" />
                                <component v-else :is="step.icon" class="w-4 h-4" />
                            </div>
                            <span class="text-sm font-medium hidden sm:block"
                                :class="currentStep >= idx ? 'text-gray-900 dark:text-white' : 'text-gray-400 dark:text-gray-500'">
                                {{ step.label }}
                            </span>
                        </div>
                        <div v-if="idx < steps.length - 1" class="flex-1 h-0.5 mx-3 rounded-full transition-all duration-300"
                            :class="currentStep > idx ? 'bg-indigo-600' : 'bg-gray-100 dark:bg-gray-800'"></div>
                    </template>
                </div>
            </div>

            <!-- Recent Generations -->
            <div v-if="recent && recent.length > 0"
                class="bg-gradient-to-r from-indigo-50 to-purple-50 dark:from-indigo-900/20 dark:to-purple-900/20 border border-indigo-100 dark:border-indigo-800/50 rounded-2xl p-4">
                <h3 class="text-sm font-semibold text-indigo-700 dark:text-indigo-300 mb-3 flex items-center gap-2">
                    <Sparkles class="w-4 h-4" />
                    Generaciones recientes
                </h3>
                <div class="flex flex-wrap gap-2">
                    <button v-for="r in recent" :key="r.id" @click="loadVideo(r.id)"
                        class="group px-3 py-2 text-xs bg-white dark:bg-gray-800/80 border border-indigo-200/50 dark:border-indigo-700/50 text-indigo-700 dark:text-indigo-300 rounded-xl hover:bg-indigo-100 dark:hover:bg-indigo-800/50 transition-all truncate max-w-[280px] shadow-sm hover:shadow-md">
                        <span class="font-mono text-indigo-400 dark:text-indigo-500 mr-1">#{{ r.id }}</span>
                        {{ r.idea }}
                    </button>
                </div>
            </div>

            <!-- Main Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <!-- Idea + Guion Column -->
                <div class="space-y-4">
                    <!-- Idea Card -->
                    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center gap-3">
                            <div class="p-1.5 bg-amber-100 dark:bg-amber-900/50 rounded-lg">
                                <Lightbulb class="w-4 h-4 text-amber-600 dark:text-amber-400" />
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Idea del video</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Define el tema y angulo del Short</p>
                            </div>
                        </div>
                        <div class="p-5">
                            <textarea
                                v-model="idea"
                                placeholder="Ejemplo: Los 3 errores mas comunes al visitar Machu Picchu..."
                                rows="4"
                                class="w-full resize-y rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 p-3 text-sm text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition placeholder:text-gray-400 dark:placeholder:text-gray-500"
                            ></textarea>
                            <button
                                @click="generateScript"
                                :disabled="loadingScript"
                                class="mt-3 w-full px-4 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition flex items-center justify-center gap-2 shadow-sm shadow-indigo-200 dark:shadow-indigo-900/50"
                            >
                                <Loader2 v-if="loadingScript" class="w-4 h-4 animate-spin" />
                                <Wand2 v-else class="w-4 h-4" />
                                {{ loadingScript ? 'Generando guion...' : 'Generar guion' }}
                            </button>
                        </div>
                    </div>

                    <!-- Guion Card -->
                    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="p-1.5 bg-blue-100 dark:bg-blue-900/50 rounded-lg">
                                    <FileText class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">Guion de voz</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Texto para narrar con voz IA</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <button
                                    @click="copyText(script.trim(), 'Todavia no hay guion para copiar.', 'Guion copiado.')"
                                    class="px-2.5 py-1.5 text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 transition"
                                >Copiar</button>
                                <button
                                    @click="generateScript"
                                    :disabled="loadingScript"
                                    class="px-2.5 py-1.5 text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 disabled:opacity-50 transition"
                                >Regenerar</button>
                                <button
                                    @click="downloadAudio"
                                    :disabled="loadingAudio || !hasValidScript"
                                    class="px-2.5 py-1.5 text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 disabled:opacity-50 transition flex items-center gap-1"
                                >
                                    <AudioLines class="w-3 h-3" />
                                    {{ loadingAudio ? '...' : 'Audio' }}
                                </button>
                            </div>
                        </div>
                        <div class="p-5">
                            <div v-if="loadingHistory" class="space-y-3 animate-pulse">
                                <div v-for="i in skeletonLines(6)" :key="i"
                                    class="h-4 bg-gray-200 dark:bg-gray-700 rounded-lg" :style="{ width: (70 + Math.random() * 30) + '%' }">
                                </div>
                            </div>
                            <textarea v-else
                                ref="scriptRef"
                                v-model="script"
                                @input="autoResize($event.target)"
                                placeholder="Aqui aparecera el guion editable para narrar con voz IA..."
                                class="w-full resize-none overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 p-3 text-sm text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition placeholder:text-gray-400 dark:placeholder:text-gray-500 font-mono"
                            ></textarea>
                            <p v-if="loadingAudio" class="mt-2 text-xs text-indigo-500 flex items-center gap-1">
                                <Loader2 class="w-3 h-3 animate-spin" /> Generando audio con ElevenLabs...
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Copy + Frases Column -->
                <div class="space-y-4">
                    <!-- Copy Card -->
                    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="p-1.5 bg-purple-100 dark:bg-purple-900/50 rounded-lg">
                                    <CopyCheck class="w-4 h-4 text-purple-600 dark:text-purple-400" />
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">Copy para redes</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Titulo, descripcion, hashtags y tags</p>
                                </div>
                            </div>
                            <button
                                @click="generateCopyAction"
                                :disabled="loadingCopy || !hasValidScript"
                                class="px-3 py-1.5 text-xs font-semibold bg-purple-600 text-white rounded-lg hover:bg-purple-700 disabled:opacity-50 disabled:cursor-not-allowed transition flex items-center gap-1.5"
                            >
                                <Loader2 v-if="loadingCopy" class="w-3 h-3 animate-spin" />
                                <Wand2 v-else class="w-3 h-3" />
                                {{ loadingCopy ? 'Generando...' : 'Generar copy' }}
                            </button>
                        </div>
                        <div class="p-5">
                            <p v-if="loadingCopy" class="text-xs text-purple-500 mb-3 flex items-center gap-1">
                                <Loader2 class="w-3 h-3 animate-spin" /> Generando copy con Gemini...
                            </p>

                            <div v-if="loadingHistory" class="space-y-3 animate-pulse">
                                <div v-for="i in skeletonLines(4)" :key="i"
                                    class="h-4 bg-gray-200 dark:bg-gray-700 rounded-lg" :style="{ width: (60 + Math.random() * 30) + '%' }">
                                </div>
                            </div>
                            <template v-else>
                                <!-- Title -->
                                <div class="mb-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Titulo</span>
                                        <button @click="copyText(copyTitle, 'No hay titulo para copiar.', 'Titulo copiado.')"
                                            class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition">Copiar</button>
                                    </div>
                                    <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-700/50">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ copyTitle || 'El titulo aparecera aqui.' }}</p>
                                    </div>
                                </div>

                                <!-- Description -->
                                <div class="mb-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Descripcion</span>
                                        <button @click="copyText(copyDescriptionText, 'No hay descripcion para copiar.', 'Descripcion copiada.')"
                                            class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition">Copiar</button>
                                    </div>
                                    <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-700/50">
                                        <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap leading-relaxed">{{ copyDescriptionText || 'La descripcion aparecera aqui.' }}</p>
                                    </div>
                                </div>

                                <!-- Tags -->
                                <div>
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tags SEO</span>
                                        <button @click="copyText(copyTags, 'No hay tags para copiar.', 'Tags copiados.')"
                                            class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition">Copiar</button>
                                    </div>
                                    <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-700/50">
                                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ copyTags || 'Los tags SEO apareceran aqui.' }}</p>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Frases Card -->
                    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="p-1.5 bg-emerald-100 dark:bg-emerald-900/50 rounded-lg">
                                    <Quote class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">Frases para video</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Texto en pantalla para edicion</p>
                                </div>
                            </div>
                            <button
                                @click="generatePhrasesAction"
                                :disabled="loadingPhrases || !hasValidScript"
                                class="px-3 py-1.5 text-xs font-semibold bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed transition flex items-center gap-1.5"
                            >
                                <Loader2 v-if="loadingPhrases" class="w-3 h-3 animate-spin" />
                                <Wand2 v-else class="w-3 h-3" />
                                {{ loadingPhrases ? 'Generando...' : 'Generar frases' }}
                            </button>
                        </div>
                        <div class="p-5">
                            <p v-if="loadingPhrases" class="text-xs text-emerald-500 mb-3 flex items-center gap-1">
                                <Loader2 class="w-3 h-3 animate-spin" /> Generando frases con Gemini...
                            </p>

                            <div v-if="loadingHistory" class="space-y-3 animate-pulse">
                                <div v-for="i in skeletonLines(6)" :key="i"
                                    class="h-4 bg-gray-200 dark:bg-gray-700 rounded-lg" :style="{ width: (50 + Math.random() * 40) + '%' }">
                                </div>
                            </div>
                            <template v-else>
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Frases</span>
                                    <button @click="copyText(phrases, 'No hay frases para copiar.', 'Frases copiadas.')"
                                        class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition">Copiar</button>
                                </div>
                                <div class="w-full rounded-xl border border-gray-100 dark:border-gray-700/50 bg-gray-50 dark:bg-gray-800/50 p-4">
                                    <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap leading-relaxed font-mono">{{ phrases || 'Aqui apareceran frases clave, hooks visuales y texto en pantalla para edicion.' }}</p>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Actions -->
            <div class="flex flex-col sm:flex-row justify-end gap-3">
                <button
                    @click="exportTxt"
                    class="px-5 py-2.5 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-xl hover:bg-gray-200 dark:hover:bg-gray-700 transition flex items-center justify-center gap-2"
                >
                    <Download class="w-4 h-4" />
                    Exportar TXT
                </button>
                <button
                    v-if="currentVideoId"
                    @click="showUseModal = true"
                    class="px-5 py-2.5 bg-emerald-600 text-white text-sm font-semibold rounded-xl hover:bg-emerald-700 transition flex items-center justify-center gap-2 shadow-sm shadow-emerald-200 dark:shadow-emerald-900/50"
                >
                    <CalendarPlus class="w-4 h-4" />
                    Usar en planificador
                </button>
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
                class="fixed top-4 right-4 max-w-sm px-4 py-3 text-sm rounded-xl shadow-lg z-50 flex items-center gap-2"
                :class="toast.type === 'error'
                    ? 'bg-red-600 text-white'
                    : 'bg-gray-900 dark:bg-gray-800 text-white border border-gray-700'"
            >
                <CheckCircle2 v-if="toast.type !== 'error'" class="w-4 h-4 text-emerald-400 shrink-0" />
                <span>{{ toast.message }}</span>
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
