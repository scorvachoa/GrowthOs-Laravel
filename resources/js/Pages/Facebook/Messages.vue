<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import { usePermissions } from '@/Composables/usePermissions'
import { useTimeAgo } from '@/Composables/useTimeAgo'
import { ArrowLeft, ArrowLeftCircle, Facebook, Inbox, Loader2, MessageCircle, RefreshCw, Search, Send } from 'lucide-vue-next'

const props = defineProps({
    organization_id: { type: Number, default: null },
    connection: { type: Object, default: null },
    conversations: { type: Object, default: null },
    thread: { type: Object, default: null },
    conversation_id: { type: String, default: null },
})

const { can } = usePermissions()
const { timeAgo } = useTimeAgo()

const search = ref('')
const messageText = ref('')
const sending = ref(false)
const threadLoading = ref(false)
const refreshing = ref(false)
const mobileView = ref(props.conversation_id ? 'thread' : 'list')
const messagesBox = ref(null)

const convList = computed(() => props.conversations?.conversations ?? [])
const convError = computed(() => props.conversations?.error ?? false)
const threadMessages = computed(() => props.thread?.messages ?? [])
const threadError = computed(() => props.thread?.error ?? false)

const currentConv = computed(() => convList.value.find((c) => c.id === props.conversation_id) || null)

const filteredConvs = computed(() => {
    const q = search.value.trim().toLowerCase()
    if (!q) return convList.value
    return convList.value.filter(
        (c) => (c.title || '').toLowerCase().includes(q) || (c.snippet || '').toLowerCase().includes(q),
    )
})

const avatarGradients = ['from-blue-500 to-blue-700', 'from-indigo-500 to-blue-600', 'from-sky-500 to-blue-600', 'from-blue-600 to-cyan-500']

function avatarGradient(title) {
    let h = 0
    for (const ch of title || '') h = (h * 31 + ch.charCodeAt(0)) >>> 0
    return avatarGradients[h % avatarGradients.length]
}

watch(
    () => props.conversation_id,
    () => {
        mobileView.value = props.conversation_id ? 'thread' : 'list'
    },
)

watch(threadMessages, async () => {
    await nextTick()
    if (messagesBox.value) messagesBox.value.scrollTop = messagesBox.value.scrollHeight
})

function selectConversation(id) {
    if (threadLoading.value || id === props.conversation_id) return
    router.reload({
        only: ['thread', 'conversation_id'],
        data: { conversation: id },
        preserveState: true,
        preserveScroll: true,
        onStart: () => {
            threadLoading.value = true
        },
        onFinish: () => {
            threadLoading.value = false
        },
    })
}

function backToList() {
    mobileView.value = 'list'
}

function refresh() {
    router.reload({
        only: ['conversations', 'thread'],
        preserveState: true,
        preserveScroll: true,
        onStart: () => {
            refreshing.value = true
        },
        onFinish: () => {
            refreshing.value = false
        },
    })
}

function sendMessage() {
    const text = messageText.value.trim()
    if (!text || sending.value || !props.conversation_id || !props.organization_id) return

    router.post(
        '/facebook/messages/send',
        {
            conversation_id: props.conversation_id,
            message: text,
            organization_id: props.organization_id,
        },
        {
            preserveState: true,
            preserveScroll: true,
            onStart: () => {
                sending.value = true
            },
            onSuccess: () => {
                messageText.value = ''
                router.reload({ only: ['thread'], preserveState: true, preserveScroll: true })
            },
            onFinish: () => {
                sending.value = false
            },
        },
    )
}
</script>

<template>
    <AppLayout>
        <div class="space-y-6">
            <!-- Header -->
            <PageHeader title="Mensajes" subtitle="Lee y responde los mensajes de Messenger de tu Página">
                <template #title>
                    <span class="flex items-center gap-3">
                        <Link href="/facebook"
                            class="p-2 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                            <ArrowLeft class="w-4 h-4" />
                        </Link>
                        <span class="flex items-center gap-2.5">
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-blue-700 text-white shadow-sm">
                                <MessageCircle class="h-5 w-5" />
                            </span>
                            <span>
                                <span class="block text-3xl font-bold text-gray-900 dark:text-white">Mensajes</span>
                                <span class="block text-gray-500 dark:text-gray-400 mt-1">Lee y responde los mensajes de Messenger de tu Página</span>
                            </span>
                        </span>
                    </span>
                </template>
                <button v-if="connection" @click="refresh" :disabled="refreshing"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 disabled:opacity-60 transition">
                    <RefreshCw class="w-4 h-4" :class="refreshing ? 'animate-spin' : ''" />
                    {{ refreshing ? 'Actualizando…' : 'Actualizar' }}
                </button>
                <div v-if="connection"
                    class="flex items-center gap-2.5 pl-1.5 pr-4 py-1.5 rounded-full bg-blue-50 dark:bg-blue-900/30 border border-blue-100 dark:border-blue-800 text-sm text-blue-700 dark:text-blue-300">
                    <img v-if="connection.page_picture" :src="connection.page_picture" alt=""
                        class="h-7 w-7 rounded-full object-cover ring-2 ring-white dark:ring-blue-900"
                        @error="$event.target.style.display = 'none'" />
                    <span v-else
                        class="flex h-7 w-7 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-blue-700 text-xs font-bold text-white">
                        {{ (connection.page_name || 'P').charAt(0).toUpperCase() }}
                    </span>
                    <span class="font-medium max-w-[220px] truncate">{{ connection.page_name }}</span>
                </div>
            </PageHeader>

            <!-- No connection -->
            <div v-if="!connection"
                class="text-center py-16 text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800">
                <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center">
                    <Inbox class="w-8 h-8 text-blue-500" />
                </div>
                <p class="text-base font-medium text-gray-900 dark:text-white">
                    {{ !organization_id ? 'Selecciona una empresa activa' : 'No hay una Página de Facebook conectada' }}
                </p>
                <p class="text-sm mt-1">{{ !organization_id ? 'Los mensajes se muestran por empresa.' : 'Conecta la Página para ver y responder sus mensajes.' }}</p>
                <Link href="/facebook"
                    class="inline-flex items-center gap-2 mt-6 px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium transition">
                    <Facebook class="w-4 h-4" /> Ir a Facebook
                </Link>
            </div>

            <!-- Inbox -->
            <div v-else class="grid gap-6 lg:grid-cols-[340px_1fr]">
                <!-- Conversation list -->
                <div
                    class="flex h-[72vh] min-h-[520px] flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
                    :class="mobileView === 'thread' ? 'hidden lg:flex' : ''"
                >
                    <div class="border-b border-gray-100 p-4 dark:border-gray-800">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/40">
                                    <MessageCircle class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                </span>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">Conversaciones</p>
                            </div>
                            <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                {{ convList.length }}
                            </span>
                        </div>
                        <div class="relative mt-3">
                            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Buscar por nombre o mensaje..."
                                class="w-full rounded-full border border-gray-200 bg-gray-50 py-2 pl-9 pr-4 text-sm text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:focus:bg-gray-900"
                            />
                        </div>
                    </div>

                    <div v-if="convError" class="p-4">
                        <div class="rounded-xl bg-amber-50 dark:bg-amber-900/20 p-3 text-sm text-amber-700 dark:text-amber-400">
                            <p class="font-medium">No se pudieron cargar las conversaciones de Facebook.</p>
                            <code v-if="conversations?.error_message"
                                class="mt-2 block font-mono text-[11px] leading-relaxed break-words text-amber-600 dark:text-amber-500">{{ conversations.error_message }}</code>
                        </div>
                    </div>

                    <div v-else-if="convList.length === 0" class="p-8 text-center">
                        <div class="mx-auto h-12 w-12 rounded-2xl bg-gray-50 dark:bg-gray-800 flex items-center justify-center">
                            <Inbox class="h-6 w-6 text-gray-300 dark:text-gray-600" />
                        </div>
                        <p class="mt-3 text-sm font-medium text-gray-900 dark:text-white">No hay conversaciones</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Cuando alguien escriba a tu Página aparecerá aquí.
                        </p>
                    </div>

                    <div v-else-if="filteredConvs.length === 0" class="p-8 text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Sin resultados para "{{ search }}"</p>
                    </div>

                    <div v-else class="flex-1 min-h-0 overflow-y-auto">
                        <button
                            v-for="conv in filteredConvs"
                            :key="conv.id"
                            class="flex w-full items-start gap-3 border-b border-l-4 border-transparent p-4 text-left transition last:border-b-0 hover:bg-gray-50 dark:hover:bg-gray-800/60"
                            :class="
                                conv.id === conversation_id
                                    ? 'border-l-blue-600 bg-blue-50/70 dark:bg-blue-900/20'
                                    : conv.unread_count > 0
                                        ? 'bg-blue-50/40 dark:bg-blue-900/10'
                                        : ''
                            "
                            @click="selectConversation(conv.id)"
                        >
                            <div class="relative shrink-0">
                                <div
                                    class="flex h-11 w-11 items-center justify-center rounded-full bg-gradient-to-br text-sm font-bold text-white shadow-sm"
                                    :class="avatarGradient(conv.title)"
                                >
                                    {{ (conv.title || '?').charAt(0).toUpperCase() }}
                                </div>
                                <span v-if="conv.unread_count > 0"
                                    class="absolute -bottom-0.5 -right-0.5 h-3.5 w-3.5 rounded-full bg-blue-600 ring-2 ring-white dark:ring-gray-900" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="truncate text-sm text-gray-900 dark:text-white"
                                        :class="conv.unread_count > 0 ? 'font-bold' : 'font-semibold'">
                                        {{ conv.title }}
                                    </p>
                                    <span class="shrink-0 text-[10px]" :class="conv.unread_count > 0 ? 'text-blue-600 dark:text-blue-400 font-medium' : 'text-gray-400'">
                                        {{ timeAgo(conv.updated_time) }}
                                    </span>
                                </div>
                                <div class="mt-0.5 flex items-center gap-2">
                                    <p class="truncate text-xs flex-1"
                                        :class="conv.unread_count > 0 ? 'text-gray-800 dark:text-gray-200 font-medium' : 'text-gray-500 dark:text-gray-400'">
                                        {{ conv.snippet }}
                                    </p>
                                    <span
                                        v-if="conv.unread_count > 0"
                                        class="shrink-0 rounded-full bg-blue-600 px-2 py-0.5 text-[10px] font-bold text-white"
                                    >
                                        {{ conv.unread_count }}
                                    </span>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Thread -->
                <div
                    class="flex h-[72vh] min-h-[520px] flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
                    :class="mobileView === 'list' && conversation_id ? 'hidden lg:flex' : ''"
                >
                    <template v-if="!conversation_id">
                        <div class="flex flex-1 flex-col items-center justify-center p-12 text-center">
                            <div class="flex h-20 w-20 items-center justify-center rounded-full bg-blue-50 dark:bg-blue-900/30">
                                <MessageCircle class="h-10 w-10 text-blue-400 dark:text-blue-500" />
                            </div>
                            <p class="mt-4 text-sm font-semibold text-gray-900 dark:text-white">Selecciona una conversación</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Elige un chat de la lista para leer y responder.</p>
                        </div>
                    </template>

                    <template v-else>
                        <!-- Thread header -->
                        <div class="flex items-center gap-3 border-b border-gray-100 p-4 dark:border-gray-800">
                            <button
                                class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 lg:hidden"
                                @click="backToList"
                            >
                                <ArrowLeft class="h-4 w-4" />
                            </button>
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br text-sm font-bold text-white shadow-sm"
                                :class="avatarGradient(currentConv && currentConv.title)"
                            >
                                {{ ((currentConv && currentConv.title) || '?').charAt(0).toUpperCase() }}
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ currentConv ? currentConv.title : 'Conversación' }}
                                </p>
                                <p v-if="currentConv" class="flex items-center gap-1.5 text-xs text-gray-400">
                                    <MessageCircle class="h-3 w-3 text-blue-500" />
                                    Messenger · {{ currentConv.message_count }} mensaje{{ currentConv.message_count === 1 ? '' : 's' }}
                                </p>
                            </div>
                            <span v-if="currentConv && currentConv.unread_count > 0"
                                class="ml-auto shrink-0 rounded-full bg-blue-600 px-2.5 py-1 text-[11px] font-bold text-white">
                                {{ currentConv.unread_count }} sin leer
                            </span>
                        </div>

                        <div v-if="threadError" class="flex flex-1 items-center justify-center p-8">
                            <div class="rounded-xl bg-amber-50 p-4 text-sm text-amber-700 dark:bg-amber-900/20 dark:text-amber-400">
                                No se pudieron cargar los mensajes de esta conversación.
                            </div>
                        </div>

                        <!-- Chat canvas -->
                        <div v-else class="flex-1 min-h-0 overflow-y-auto p-4 bg-gray-50 dark:bg-gray-950/50" ref="messagesBox">
                            <div v-if="threadLoading" class="flex h-full items-center justify-center">
                                <Loader2 class="h-6 w-6 animate-spin text-gray-300 dark:text-gray-600" />
                            </div>
                            <div v-else-if="threadMessages.length === 0" class="flex h-full flex-col items-center justify-center text-center">
                                <div class="flex h-14 w-14 items-center justify-center rounded-full bg-white shadow-sm dark:bg-gray-800">
                                    <ArrowLeftCircle class="h-7 w-7 text-gray-300 dark:text-gray-600" />
                                </div>
                                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">Sin mensajes en esta conversación</p>
                            </div>
                            <div v-else class="space-y-3">
                                <div
                                    v-for="msg in threadMessages"
                                    :key="msg.id"
                                    class="flex"
                                    :class="msg.is_page ? 'justify-end' : 'justify-start'"
                                >
                                    <div class="max-w-[75%]">
                                        <div
                                            class="rounded-3xl px-4 py-2.5 text-sm shadow-sm"
                                            :class="
                                                msg.is_page
                                                    ? 'rounded-br-lg bg-gradient-to-br from-blue-600 to-blue-500 text-white'
                                                    : 'rounded-bl-lg border border-gray-100 bg-white text-gray-800 dark:border-gray-700/60 dark:bg-gray-800 dark:text-gray-100'
                                            "
                                        >
                                            <p class="whitespace-pre-wrap break-words">{{ msg.message }}</p>
                                        </div>
                                        <p
                                            class="mt-1 px-1 text-[10px] text-gray-400"
                                            :class="msg.is_page ? 'text-right' : 'text-left'"
                                        >
                                            {{ timeAgo(msg.created_time) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Composer -->
                        <div
                            v-if="can('edit empresa') && !threadError"
                            class="border-t border-gray-100 p-4 dark:border-gray-800"
                        >
                            <div class="mb-2.5 flex items-center gap-2 text-xs text-gray-400">
                                <img v-if="connection.page_picture" :src="connection.page_picture" alt=""
                                    class="h-5 w-5 rounded-full object-cover" @error="$event.target.style.display = 'none'" />
                                <span v-else
                                    class="flex h-5 w-5 items-center justify-center rounded-full bg-blue-600 text-[10px] font-bold text-white">
                                    {{ (connection.page_name || 'P').charAt(0).toUpperCase() }}
                                </span>
                                Respondes como <span class="font-semibold text-gray-500 dark:text-gray-300">{{ connection.page_name }}</span>
                            </div>
                            <div class="flex items-end gap-2">
                                <textarea
                                    v-model="messageText"
                                    rows="2"
                                    placeholder="Escribe una respuesta... (Enter para enviar)"
                                    class="flex-1 resize-none rounded-2xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:focus:bg-gray-900"
                                    @keydown.enter.exact.prevent="sendMessage"
                                ></textarea>
                                <button
                                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-blue-600 to-blue-500 text-white shadow-sm transition hover:from-blue-700 hover:to-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="sending || !messageText.trim()"
                                    title="Enviar"
                                    @click="sendMessage"
                                >
                                    <Loader2 v-if="sending" class="h-4 w-4 animate-spin" />
                                    <Send v-else class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
