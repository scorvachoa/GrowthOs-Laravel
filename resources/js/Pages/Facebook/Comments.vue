<script setup>
import { ref, computed } from 'vue'
import { router, usePage, Link } from '@inertiajs/vue3'
import { usePermissions } from '@/Composables/usePermissions'
import { useTimeAgo } from '@/Composables/useTimeAgo'
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import ConfirmDelete from '@/Components/Modals/ConfirmDelete.vue'
import { ArrowLeft, MessageCircle, Send, AlertCircle, FileText, Facebook, ThumbsUp, CheckCircle2, ExternalLink, Newspaper, RefreshCw, Loader2, Search, Inbox, CheckCheck, Eye, EyeOff, Trash2 } from 'lucide-vue-next'

const { can } = usePermissions()
const page = usePage()
const { timeAgo } = useTimeAgo()

const props = defineProps({
    connection: Object,
    comments: Object,
    organization_id: Number,
})

const filter = ref('all')
const search = ref('')
const hasActiveFilters = computed(() => Boolean(search.value.trim()) || filter.value !== 'all')
const clearFilters = () => { search.value = ''; filter.value = 'all' }
const replyingTo = ref(null)
const replyText = ref('')
const sending = ref(false)
const refreshing = ref(false)

const threads = computed(() => props.comments?.threads ?? [])
const commentsError = computed(() => props.comments?.error ?? false)

const visibleThreads = computed(() => {
    let list = threads.value

    if (filter.value === 'pending') list = list.filter(t => !t.responded)
    if (filter.value === 'responded') list = list.filter(t => t.responded)

    const q = search.value.trim().toLowerCase()
    if (q) {
        list = list.filter(t =>
            (t.message || '').toLowerCase().includes(q) ||
            (t.author || '').toLowerCase().includes(q)
        )
    }

    return list
})

const replyError = computed(() => page.props.errors?.message || null)

const deleteTarget = ref(null)
const moderatingId = ref(null)

function toggleHide(thread) {
    if (!props.organization_id || moderatingId.value) return

    moderatingId.value = thread.id
    router.post('/facebook/comments/hide', {
        comment_id: thread.id,
        hidden: !thread.is_hidden,
        organization_id: props.organization_id,
    }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { moderatingId.value = null },
    })
}

function askDelete(thread) {
    deleteTarget.value = thread
}

function confirmDelete() {
    if (!deleteTarget.value || moderatingId.value) return

    const thread = deleteTarget.value
    moderatingId.value = thread.id
    router.post('/facebook/comments/delete', {
        comment_id: thread.id,
        organization_id: props.organization_id,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { deleteTarget.value = null },
        onFinish: () => { moderatingId.value = null },
    })
}

function toggleReply(threadId) {
    replyingTo.value = replyingTo.value === threadId ? null : threadId
    replyText.value = ''
}

function submitReply(threadId) {
    const message = replyText.value.trim()
    if (!message || sending.value || !props.organization_id) return

    router.post('/facebook/comments/reply', {
        comment_id: threadId,
        message,
        organization_id: props.organization_id,
    }, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => { sending.value = true },
        onSuccess: () => {
            replyText.value = ''
            replyingTo.value = null
        },
        onFinish: () => { sending.value = false },
    })
}

function refreshComments() {
    if (refreshing.value) return
    router.reload({
        only: ['comments'],
        preserveState: true,
        onStart: () => { refreshing.value = true },
        onFinish: () => { refreshing.value = false },
    })
}
</script>

<template>
    <AppLayout>
        <div class="space-y-6">
            <!-- Header -->
            <PageHeader title="Comentarios" subtitle="Responde los comentarios de tus publicaciones">
                <template #title>
                    <span class="flex items-center gap-3">
                        <Link href="/facebook"
                            class="p-2 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                            <ArrowLeft class="w-4 h-4" />
                        </Link>
                        <span>
                            <span class="block text-3xl font-bold text-gray-900 dark:text-white">Comentarios</span>
                            <span class="block text-gray-500 dark:text-gray-400 mt-1">Responde los comentarios de tus publicaciones</span>
                        </span>
                    </span>
                </template>
                <button v-if="connection" @click="refreshComments" :disabled="refreshing"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 disabled:opacity-60 transition">
                    <RefreshCw class="w-4 h-4" :class="refreshing ? 'animate-spin' : ''" />
                    Actualizar
                </button>
                <div v-if="connection"
                    class="flex items-center gap-2 px-4 py-2 rounded-xl bg-blue-50 dark:bg-blue-900/30 border border-blue-100 dark:border-blue-800 text-sm text-blue-700 dark:text-blue-300">
                    <Facebook class="w-4 h-4" /> {{ connection.page_name }}
                </div>
            </PageHeader>

            <!-- Not connected -->
            <div v-if="!connection"
                class="text-center py-16 text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800">
                <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center">
                    <Inbox class="w-8 h-8 text-blue-500" />
                </div>
                <p class="text-base font-medium text-gray-900 dark:text-white">
                    {{ !organization_id ? 'Selecciona una empresa activa' : 'No hay una Página de Facebook conectada' }}
                </p>
                <p class="text-sm mt-1">{{ !organization_id ? 'Los comentarios se muestran por empresa.' : 'Conecta la Página para ver y responder sus comentarios.' }}</p>
                <Link href="/facebook"
                    class="inline-flex items-center gap-2 mt-6 px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium transition">
                    <Facebook class="w-4 h-4" /> Ir a Facebook
                </Link>
            </div>

            <template v-else>
                <!-- Stat filters -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <button @click="filter = 'all'"
                        class="flex items-center gap-3 p-4 rounded-2xl border text-left transition"
                        :class="filter === 'all'
                            ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20 shadow-sm'
                            : 'border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 hover:border-blue-200 dark:hover:border-blue-800/60 shadow-sm'">
                        <div class="p-2.5 rounded-xl bg-blue-100 dark:bg-blue-900/40">
                            <MessageCircle class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div>
                            <p class="text-xl font-bold text-gray-900 dark:text-white leading-tight">{{ comments.total }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Comentarios</p>
                        </div>
                    </button>

                    <button @click="filter = 'pending'"
                        class="flex items-center gap-3 p-4 rounded-2xl border text-left transition"
                        :class="filter === 'pending'
                            ? 'border-amber-500 bg-amber-50 dark:bg-amber-900/20 shadow-sm'
                            : 'border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 hover:border-amber-200 dark:hover:border-amber-800/60 shadow-sm'">
                        <div class="p-2.5 rounded-xl bg-amber-100 dark:bg-amber-900/40">
                            <AlertCircle class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                        </div>
                        <div>
                            <p class="text-xl font-bold text-gray-900 dark:text-white leading-tight">{{ comments.unresponded }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Sin responder</p>
                        </div>
                        <span v-if="comments.unresponded > 0" class="ml-auto relative flex h-2.5 w-2.5 self-start mt-1">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-500"></span>
                        </span>
                    </button>

                    <button @click="filter = 'responded'"
                        class="flex items-center gap-3 p-4 rounded-2xl border text-left transition"
                        :class="filter === 'responded'
                            ? 'border-green-500 bg-green-50 dark:bg-green-900/20 shadow-sm'
                            : 'border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 hover:border-green-200 dark:hover:border-green-800/60 shadow-sm'">
                        <div class="p-2.5 rounded-xl bg-green-100 dark:bg-green-900/40">
                            <CheckCheck class="w-5 h-5 text-green-600 dark:text-green-400" />
                        </div>
                        <div>
                            <p class="text-xl font-bold text-gray-900 dark:text-white leading-tight">{{ comments.total - comments.unresponded }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Respondidos</p>
                        </div>
                    </button>
                </div>

                <!-- Graph error -->
                <div v-if="commentsError"
                    class="flex flex-col gap-2 p-4 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-sm text-amber-700 dark:text-amber-400">
                    <div class="flex items-start gap-2">
                        <AlertCircle class="w-4 h-4 flex-shrink-0 mt-0.5" />
                        <span>No se pudieron cargar los comentarios. Se reintentará en unos minutos.</span>
                    </div>
                    <span class="text-[11px]">Si el error menciona permisos, verifica que el token tenga <code class="font-mono font-semibold">pages_read_engagement</code> en Meta Graph Explorer.</span>
                </div>

                <!-- Empty: no comments at all -->
                <div v-else-if="threads.length === 0"
                    class="text-center py-16 text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center">
                        <Inbox class="w-8 h-8 text-blue-500" />
                    </div>
                    <p class="text-base font-medium text-gray-900 dark:text-white">Aún no hay comentarios</p>
                    <p class="text-sm mt-1">Cuando alguien comente tus publicaciones aparecerá aquí.</p>
                    <Link href="/facebook"
                        class="inline-flex items-center gap-2 mt-6 px-5 py-2 rounded-xl border border-gray-200 dark:border-gray-700 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                        <Newspaper class="w-4 h-4" /> Ver publicaciones
                    </Link>
                </div>

                <!-- Empty: filter/search with no results -->
                <div v-else-if="visibleThreads.length === 0"
                    class="text-center py-16 text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-green-50 dark:bg-green-900/30 flex items-center justify-center">
                        <CheckCircle2 class="w-8 h-8 text-green-500" />
                    </div>
                    <p class="text-base font-medium text-gray-900 dark:text-white">
                        {{ search.trim() ? 'No hay resultados para tu búsqueda' : filter === 'pending' ? 'No hay comentarios sin responder' : 'No hay comentarios en esta vista' }}
                    </p>
                    <p class="text-sm mt-1">
                        {{ search.trim() ? 'Prueba con otra palabra o el nombre del autor.' : '¡Buen trabajo, estás al día!' }}
                    </p>
                    <button v-if="hasActiveFilters" @click="clearFilters"
                        class="mt-4 px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium transition">
                        Limpiar filtros
                    </button>
                </div>

                <!-- Threads -->
                <div v-else>
                    <!-- Search bar -->
                    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-4">
                        <div class="relative flex-1 sm:max-w-xs">
                            <Search class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" />
                            <input v-model="search" type="text" placeholder="Buscar por mensaje o autor..."
                                class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 dark:text-white text-sm focus:ring-blue-500 focus:border-blue-500" />
                        </div>
                        <p class="text-xs text-gray-400 dark:text-gray-500 sm:ml-auto">
                            Mostrando {{ visibleThreads.length }} de {{ threads.length }}
                        </p>
                    </div>

                    <TransitionGroup tag="div" name="thread" class="space-y-4"
                        enter-active-class="transition duration-200 ease-out"
                        enter-from-class="opacity-0 -translate-y-2"
                        enter-to-class="opacity-100 translate-y-0"
                        leave-active-class="transition duration-150 ease-in"
                        leave-from-class="opacity-100"
                        leave-to-class="opacity-0 -translate-y-2">
                        <article v-for="thread in visibleThreads" :key="thread.id"
                            class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm hover:shadow-md transition-shadow overflow-hidden border"
                            :class="thread.responded
                                ? 'border-gray-100 dark:border-gray-800 border-l-4 border-l-green-400 dark:border-l-green-500'
                                : 'border-gray-100 dark:border-gray-800 border-l-4 border-l-amber-400 dark:border-l-amber-500'">
                            <div class="p-5 sm:p-6">
                                <!-- Author row -->
                                <div class="flex items-start gap-3">
                                    <img v-if="thread.author_picture" :src="thread.author_picture" :alt="thread.author"
                                        class="w-11 h-11 rounded-full object-cover bg-gray-100 ring-2 ring-white dark:ring-gray-900 shadow-sm flex-shrink-0"
                                        @error="$event.target.style.display='none'" />
                                    <div v-else
                                        class="w-11 h-11 rounded-full bg-gradient-to-br from-blue-500 to-blue-700 text-white flex items-center justify-center font-bold text-sm flex-shrink-0 shadow-sm">
                                        {{ (thread.author || 'U').charAt(0).toUpperCase() }}
                                    </div>

                                    <div class="flex-1 min-w-0">
                                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                            <span class="font-semibold text-[15px] text-gray-900 dark:text-white">{{ thread.author }}</span>
                                            <span v-if="thread.responded"
                                                class="inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 rounded-full bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400">
                                                <CheckCircle2 class="w-3 h-3" /> Respondido
                                            </span>
                                            <span v-else
                                                class="inline-flex items-center text-[11px] font-semibold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400">
                                                Sin responder
                                            </span>
                                            <span class="text-xs text-gray-400 dark:text-gray-500 ml-auto">{{ timeAgo(thread.created_time) }}</span>
                                        </div>

                                        <p class="text-[15px] leading-relaxed text-gray-800 dark:text-gray-200 mt-2 whitespace-pre-line break-words">{{ thread.message || '(sin mensaje)' }}</p>

                                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-3 text-xs text-gray-500 dark:text-gray-400">
                                            <span class="inline-flex items-center gap-1.5">
                                                <ThumbsUp class="w-3.5 h-3.5" /> {{ thread.likes }}
                                            </span>
                                            <span v-if="thread.is_hidden"
                                                class="inline-flex items-center text-amber-600 dark:text-amber-400 font-medium">Oculto</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Post context -->
                                <a v-if="thread.post.permalink_url" :href="thread.post.permalink_url" target="_blank"
                                    class="group mt-4 flex items-center gap-3 p-2.5 rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700 hover:border-blue-200 dark:hover:border-blue-800/60 transition">
                                    <img v-if="thread.post.picture" :src="thread.post.picture"
                                        class="w-11 h-11 rounded-lg object-cover bg-white border border-gray-200 dark:border-gray-700 flex-shrink-0"
                                        @error="$event.target.style.display='none'" />
                                    <div v-else
                                        class="w-11 h-11 rounded-lg bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center flex-shrink-0">
                                        <Newspaper class="w-5 h-5 text-blue-500" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Publicación</p>
                                        <p class="text-xs text-gray-600 dark:text-gray-300 truncate">{{ thread.post.snippet || '(sin texto)' }}</p>
                                    </div>
                                    <ExternalLink class="w-4 h-4 text-gray-300 dark:text-gray-600 group-hover:text-blue-500 flex-shrink-0 transition" />
                                </a>

                                <!-- Replies -->
                                <div v-if="thread.replies.length" class="mt-4 ml-1 pl-4 space-y-3 border-l-2 border-dashed border-gray-200 dark:border-gray-700">
                                    <div v-for="reply in thread.replies" :key="reply.id" class="flex items-start gap-2.5">
                                        <img v-if="reply.author_is_page && connection.page_picture" :src="connection.page_picture"
                                            class="w-7 h-7 rounded-full object-cover ring-2 ring-white dark:ring-gray-900 flex-shrink-0" />
                                        <img v-else-if="reply.author_picture" :src="reply.author_picture"
                                            class="w-7 h-7 rounded-full object-cover ring-2 ring-white dark:ring-gray-900 flex-shrink-0"
                                            @error="$event.target.style.display='none'" />
                                        <div v-else
                                            class="w-7 h-7 rounded-full flex items-center justify-center text-[10px] font-bold flex-shrink-0"
                                            :class="reply.author_is_page
                                                ? 'bg-blue-600 text-white'
                                                : 'bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300'">
                                            {{ (reply.author || 'U').charAt(0).toUpperCase() }}
                                        </div>

                                        <div class="flex-1 min-w-0 px-3 py-2 rounded-xl"
                                            :class="reply.author_is_page
                                                ? 'bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800/60'
                                                : 'bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700'">
                                            <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs">
                                                <span class="font-semibold"
                                                    :class="reply.author_is_page ? 'text-blue-700 dark:text-blue-300' : 'text-gray-700 dark:text-gray-300'">
                                                    {{ reply.author }}
                                                </span>
                                                <span v-if="reply.author_is_page"
                                                    class="px-1.5 py-0.5 rounded-full bg-blue-600 text-white text-[10px] font-bold">Página</span>
                                                <span class="text-gray-400 dark:text-gray-500">{{ timeAgo(reply.created_time) }}</span>
                                            </div>
                                            <p class="text-sm text-gray-800 dark:text-gray-200 mt-1 whitespace-pre-line break-words">{{ reply.message }}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div v-if="can('edit empresa')" class="mt-4">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <button v-if="replyingTo !== thread.id" @click="toggleReply(thread.id)"
                                            :disabled="!thread.can_reply"
                                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed text-white text-xs font-medium shadow-sm transition">
                                            <Send class="w-3.5 h-3.5" /> Responder
                                        </button>
                                        <p v-if="!thread.can_reply" class="text-xs text-gray-400 dark:text-gray-500">
                                            Este comentario no admite respuestas
                                        </p>

                                        <div class="flex items-center gap-1.5 ml-auto">
                                            <button @click="toggleHide(thread)"
                                                :disabled="!!moderatingId"
                                                :title="thread.is_hidden ? 'Mostrar comentario' : 'Ocultar comentario'"
                                                class="inline-flex items-center justify-center w-8 h-8 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-500 hover:bg-gray-50 hover:text-amber-600 dark:hover:bg-gray-800 disabled:opacity-50 transition">
                                                <Loader2 v-if="moderatingId === thread.id" class="w-4 h-4 animate-spin" />
                                                <EyeOff v-else-if="thread.is_hidden" class="w-4 h-4 text-amber-500" />
                                                <Eye v-else class="w-4 h-4" />
                                            </button>
                                            <button @click="askDelete(thread)"
                                                :disabled="!!moderatingId"
                                                title="Eliminar comentario"
                                                class="inline-flex items-center justify-center w-8 h-8 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-500 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 disabled:opacity-50 transition">
                                                <Trash2 class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </div>

                                    <Transition
                                        enter-active-class="transition duration-200 ease-out"
                                        enter-from-class="opacity-0 -translate-y-1"
                                        enter-to-class="opacity-100 translate-y-0"
                                        leave-active-class="transition duration-150 ease-in"
                                        leave-from-class="opacity-100"
                                        leave-to-class="opacity-0 -translate-y-1">
                                        <div v-if="replyingTo === thread.id" class="mt-3 space-y-2.5">
                                            <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                                <img v-if="connection.page_picture" :src="connection.page_picture"
                                                    class="w-5 h-5 rounded-full object-cover" />
                                                <span>Respondes como <strong class="text-gray-700 dark:text-gray-300">{{ connection.page_name }}</strong></span>
                                            </div>
                                            <textarea v-model="replyText" rows="3" maxlength="1000"
                                                placeholder="Escribe una respuesta..."
                                                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-blue-500 focus:border-blue-500 text-sm"
                                                :class="{ 'border-red-400 focus:border-red-500 focus:ring-red-500': replyError }"
                                                @keyup.ctrl.enter="submitReply(thread.id)" />
                                            <p v-if="replyError" class="text-xs text-red-600 dark:text-red-400">{{ replyError }}</p>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <button @click="submitReply(thread.id)"
                                                    :disabled="!replyText.trim() || sending || replyText.length > 1000"
                                                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-xs font-medium shadow-sm transition">
                                                    <Loader2 v-if="sending" class="w-3.5 h-3.5 animate-spin" />
                                                    <Send v-else class="w-3.5 h-3.5" />
                                                    {{ sending ? 'Enviando...' : 'Enviar respuesta' }}
                                                </button>
                                                <button @click="replyingTo = null"
                                                    class="px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 text-xs font-medium hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                                                    Cancelar
                                                </button>
                                                <span class="text-[10px] text-gray-400 dark:text-gray-500 ml-auto">
                                                    {{ replyText.length }}/1000 · Ctrl+Enter
                                                </span>
                                            </div>
                                        </div>
                                    </Transition>
                                </div>
                            </div>
                        </article>
                    </TransitionGroup>
                </div>
            </template>

            <!-- Delete comment confirm -->
            <ConfirmDelete :show="!!deleteTarget" title="Eliminar comentario"
                :message="deleteTarget ? 'Se eliminará de Facebook permanentemente: ' + (deleteTarget.message || '(sin texto)').slice(0, 120) : null"
                @confirm="confirmDelete" @close="deleteTarget = null" />
        </div>
    </AppLayout>
</template>
