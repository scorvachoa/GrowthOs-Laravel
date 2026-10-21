<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import { usePermissions } from '@/Composables/usePermissions'
import { useTimeAgo } from '@/Composables/useTimeAgo'
import { ArrowLeft, ArrowLeftCircle, Hammer, Instagram, Inbox, Loader2, MessageCircle, RefreshCw, Search, Send } from 'lucide-vue-next'

const props = defineProps({
    organization_id: { type: Number, default: null },
    connection: { type: Object, default: null },
    account: { type: Object, default: null },
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
    })
}

function sendMessage() {
    const text = messageText.value.trim()
    if (!text || sending.value || !props.conversation_id || !props.organization_id) return

    router.post(
        '/instagram/messages/send',
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
            <PageHeader title="Mensajes de Instagram" subtitle="Lee y responde los mensajes directos de tu cuenta — pendiente de aprobación de Meta">
                <template #title>
                    <span class="flex items-center gap-3 flex-wrap">
                        <Link href="/instagram"
                            class="p-2 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                            <ArrowLeft class="w-4 h-4" />
                        </Link>
                        <span class="flex items-center gap-3 flex-wrap">
                            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Mensajes de Instagram</h1>
                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 text-[11px] font-semibold text-amber-700 dark:text-amber-400">
                                <Hammer class="w-3.5 h-3.5" /> En implementación
                            </span>
                        </span>
                    </span>
                </template>
                <template #subtitle>
                    Lee y responde los mensajes directos de tu cuenta — pendiente de aprobación de Meta
                </template>
                <button v-if="connection" @click="refresh"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                    <RefreshCw class="w-4 h-4" />
                    Actualizar
                </button>
                <div v-if="connection"
                    class="flex items-center gap-2 px-4 py-2 rounded-xl bg-pink-50 dark:bg-pink-900/30 border border-pink-100 dark:border-pink-800 text-sm text-pink-700 dark:text-pink-300">
                    <Instagram class="w-4 h-4" /> @{{ account?.username || 'Instagram' }}
                </div>
            </PageHeader>

            <div v-if="!connection"
                class="text-center py-16 text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800">
                <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-pink-50 dark:bg-pink-900/30 flex items-center justify-center">
                    <Inbox class="w-8 h-8 text-pink-500" />
                </div>
                <p class="text-base font-medium text-gray-900 dark:text-white">
                    {{ !organization_id ? 'Selecciona una empresa activa' : 'No hay una Página de Facebook conectada' }}
                </p>
                <p class="text-sm mt-1">{{ !organization_id ? 'Los mensajes se muestran por empresa.' : 'Los mensajes de Instagram dependen de la Página conectada.' }}</p>
                <Link href="/facebook"
                    class="inline-flex items-center gap-2 mt-6 px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium transition">
                    Ir a Facebook
                </Link>
            </div>

            <div v-else-if="connection && account && !account.linked"
                class="text-center py-16 text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800">
                <Instagram class="w-16 h-16 mx-auto mb-4 text-pink-500 opacity-60" />
                <p class="text-base font-medium text-gray-900 dark:text-white">No hay una cuenta de Instagram vinculada</p>
                <p class="text-sm mt-1">{{ account.error ? 'Hubo un error al leer la cuenta de Instagram.' : 'Vincula tu cuenta profesional a la Página de Facebook para recibir sus mensajes aquí.' }}</p>
                <Link href="/instagram"
                    class="inline-flex items-center gap-2 mt-6 px-5 py-2 rounded-xl border border-pink-300 text-pink-600 hover:bg-pink-50 dark:border-pink-800 dark:text-pink-400 dark:hover:bg-pink-900/30 text-sm font-medium transition">
                    <Instagram class="w-4 h-4" /> Ir a Instagram
                </Link>
            </div>

            <div v-else class="grid items-start gap-6 lg:grid-cols-[340px_1fr]">
                <div
                    class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
                    :class="mobileView === 'thread' ? 'hidden lg:block' : ''"
                >
                    <div class="border-b border-gray-100 p-4 dark:border-gray-800">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">Conversaciones</p>
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                {{ convList.length }}
                            </span>
                        </div>
                        <div class="relative mt-3">
                            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Buscar..."
                                class="w-full rounded-lg border border-gray-200 py-2 pl-9 pr-3 text-sm text-gray-900 placeholder-gray-400 focus:border-pink-500 focus:outline-none focus:ring-1 focus:ring-pink-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            />
                        </div>
                    </div>

                    <div v-if="convError" class="p-6 text-sm text-amber-700 dark:text-amber-400">
                        <div class="flex items-start gap-3">
                            <Hammer class="w-5 h-5 shrink-0 mt-0.5" />
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Bandeja en implementación</p>
                                <p class="mt-1 leading-relaxed">
                                    Los mensajes directos de Instagram estarán disponibles próximamente. Meta exige aprobar
                                    la app (App Review → Advanced Access de
                                    <code class="font-mono font-semibold">instagram_manage_messages</code>) antes de poder
                                    leer las conversaciones reales de la cuenta; mientras tanto, esta función está en
                                    implementación.
                                </p>
                                <details v-if="conversations?.error_message" class="mt-2">
                                    <summary class="cursor-pointer text-[11px] text-amber-600 dark:text-amber-500">Detalle técnico</summary>
                                    <code class="mt-1 block font-mono text-[11px] leading-relaxed break-words">{{ conversations.error_message }}</code>
                                </details>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="convList.length === 0" class="p-8 text-center">
                        <Inbox class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                        <p class="mt-3 text-sm font-medium text-gray-900 dark:text-white">No hay conversaciones</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Cuando alguien envíe un DM a tu cuenta aparecerá aquí.
                        </p>
                    </div>

                    <div v-else-if="filteredConvs.length === 0" class="p-8 text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Sin resultados para "{{ search }}"</p>
                    </div>

                    <div v-else class="max-h-[60vh] overflow-y-auto">
                        <button
                            v-for="conv in filteredConvs"
                            :key="conv.id"
                            class="flex w-full items-start gap-3 border-l-4 border-transparent p-4 text-left transition hover:bg-gray-50 dark:hover:bg-gray-800/60"
                            :class="
                                conv.id === conversation_id
                                    ? 'border-pink-600 bg-pink-50 dark:bg-pink-900/20'
                                    : ''
                            "
                            @click="selectConversation(conv.id)"
                        >
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-[#833ab4] to-[#e1306c] text-sm font-bold text-white"
                            >
                                {{ (conv.title || '?').charAt(0).toUpperCase() }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ conv.title }}</p>
                                    <span class="shrink-0 text-[10px] text-gray-400">{{ timeAgo(conv.updated_time) }}</span>
                                </div>
                                <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">{{ conv.snippet }}</p>
                            </div>
                            <span
                                v-if="conv.unread_count > 0"
                                class="mt-1 shrink-0 rounded-full bg-pink-600 px-2 py-0.5 text-[10px] font-bold text-white"
                            >
                                {{ conv.unread_count }}
                            </span>
                        </button>
                    </div>
                </div>

                <div
                    class="flex min-h-[500px] flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
                    :class="mobileView === 'list' && conversation_id ? 'hidden lg:flex' : ''"
                >
                    <template v-if="!conversation_id">
                        <div class="flex flex-1 flex-col items-center justify-center p-12 text-center">
                            <MessageCircle class="h-12 w-12 text-gray-300 dark:text-gray-600" />
                            <p class="mt-4 text-sm font-semibold text-gray-900 dark:text-white">Selecciona una conversación</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Elige un chat de la lista para leer y responder.</p>
                        </div>
                    </template>

                    <template v-else>
                        <div class="flex items-center gap-3 border-b border-gray-100 p-4 dark:border-gray-800">
                            <button
                                class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 lg:hidden"
                                @click="backToList"
                            >
                                <ArrowLeft class="h-4 w-4" />
                            </button>
                            <div
                                class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-[#833ab4] to-[#e1306c] text-sm font-bold text-white"
                            >
                                {{ ((currentConv && currentConv.title) || '?').charAt(0).toUpperCase() }}
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ currentConv ? currentConv.title : 'Conversación' }}
                                </p>
                                <p v-if="currentConv" class="text-xs text-gray-400">
                                    {{ currentConv.message_count }} mensaje{{ currentConv.message_count === 1 ? '' : 's' }}
                                </p>
                            </div>
                        </div>

                        <div v-if="threadError" class="flex flex-1 items-center justify-center p-8">
                            <div class="rounded-lg bg-amber-50 p-4 text-sm text-amber-700 dark:bg-amber-900/20 dark:text-amber-400">
                                No se pudieron cargar los mensajes de esta conversación.
                            </div>
                        </div>

                        <div v-else class="flex-1 overflow-y-auto p-4" style="max-height: 55vh" ref="messagesBox">
                            <div v-if="threadLoading" class="flex h-full items-center justify-center">
                                <Loader2 class="h-6 w-6 animate-spin text-gray-300 dark:text-gray-600" />
                            </div>
                            <div v-else-if="threadMessages.length === 0" class="flex h-full flex-col items-center justify-center text-center">
                                <ArrowLeftCircle class="h-10 w-10 text-gray-300 dark:text-gray-600" />
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
                                            class="rounded-2xl px-4 py-2 text-sm"
                                            :class="
                                                msg.is_page
                                                    ? 'rounded-br-sm bg-gradient-to-r from-[#833ab4] to-[#e1306c] text-white'
                                                    : 'rounded-bl-sm bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100'
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

                        <div
                            v-if="can('edit empresa') && !threadError"
                            class="border-t border-gray-100 p-4 dark:border-gray-800"
                        >
                            <div class="mb-2 flex items-center gap-2 text-xs text-gray-400">
                                <ArrowLeftCircle class="h-3 w-3" />
                                Respondes como @{{ account?.username || 'tu cuenta' }}
                            </div>
                            <div class="flex items-end gap-2">
                                <textarea
                                    v-model="messageText"
                                    rows="2"
                                    placeholder="Escribe una respuesta... (Enter para enviar)"
                                    class="flex-1 resize-none rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-pink-500 focus:outline-none focus:ring-1 focus:ring-pink-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                    @keydown.enter.exact.prevent="sendMessage"
                                ></textarea>
                                <button
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-pink-600 text-white transition hover:bg-pink-700 disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="sending || !messageText.trim()"
                                    title="Enviar"
                                    @click="sendMessage"
                                >
                                    <Loader2 v-if="sending" class="h-4 w-4 animate-spin" />
                                    <Send v-else class="h-4 w-4" />
                                </button>
                            </div>
                            <p class="mt-2 text-[10px] text-gray-400 dark:text-gray-500">
                                Instagram solo permite responder dentro de las 24 h posteriores al último mensaje del cliente.
                            </p>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
