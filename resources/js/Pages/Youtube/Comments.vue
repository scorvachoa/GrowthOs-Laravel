<script setup>
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import SearchInput from '@/Components/Forms/SearchInput.vue'
import { useFormat } from '@/Composables/useFormat'
import { MessageCircle, MessageSquare, ThumbsUp, Youtube, ExternalLink, AlertCircle } from 'lucide-vue-next'

const props = defineProps({
    channels: Array,
    threads: Array,
    api_connected: Boolean,
})

const activeChannelId = ref(null)
const search = ref('')

const activeChannel = computed(() =>
    props.channels.find(c => c.id === activeChannelId.value) || null
)

const counts = computed(() => {
    const map = {}
    for (const t of props.threads) {
        map[t.channel_id] = (map[t.channel_id] || 0) + 1
    }
    return map
})

const filteredThreads = computed(() => {
    const q = search.value.trim().toLowerCase()
    return props.threads.filter(t => {
        if (activeChannelId.value !== null && t.channel_id !== activeChannelId.value) return false
        if (!q) return true
        return [t.author, t.text, t.video_title, t.channel_name]
            .some(v => (v || '').toLowerCase().includes(q))
    })
})

const subtitle = computed(() => {
    const total = props.threads.length
    const showing = filteredThreads.value.length
    const scope = activeChannel.value
        ? `en ${activeChannel.value.name}`
        : `en ${props.channels.length} ${props.channels.length === 1 ? 'canal' : 'canales'}`

    if (search.value.trim() || activeChannelId.value !== null) {
        return `${showing} de ${total} comentarios ${scope} · últimos 7 días`
    }
    return `${total} comentarios ${scope} · últimos 7 días`
})

const { formatDateTime: formatDate } = useFormat()

const threadUrl = (thread) =>
    `https://www.youtube.com/watch?v=${thread.video_id}&lc=${thread.id}`
</script>

<template>
    <AppLayout>
        <div class="space-y-6">
            <PageHeader title="Comentarios" :subtitle="subtitle">
                <SearchInput v-model="search" placeholder="Buscar comentarios..." />
                <div v-if="!api_connected"
                    class="flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-sm text-amber-700 dark:text-amber-400 whitespace-nowrap">
                    <AlertCircle class="w-4 h-4 flex-shrink-0" />
                    <span>API key no configurada</span>
                </div>
            </PageHeader>

            <div v-if="channels.length === 0"
                class="text-center py-16 text-gray-400 dark:text-gray-500 bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800">
                <MessageSquare class="w-16 h-16 mx-auto mb-4 opacity-40" />
                <p class="text-base font-medium">No hay canales conectados a YouTube</p>
                <p class="text-sm mt-1">Configura el ID del canal en Empresa &gt; Canales administrados</p>
            </div>

            <template v-else>
                <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800">
                    <div class="overflow-x-auto">
                        <div class="flex border-b border-gray-200 dark:border-gray-700">
                            <button @click="activeChannelId = null"
                                class="px-4 py-3 transition border-b-2 flex-shrink-0 inline-flex items-center gap-2"
                                :class="activeChannelId === null
                                    ? 'border-red-500 bg-red-50 dark:bg-red-900/10'
                                    : 'border-transparent hover:bg-gray-50 dark:hover:bg-gray-800'">
                                <span class="w-9 h-9 rounded-full flex-shrink-0 ring-2 flex items-center justify-center transition"
                                    :class="activeChannelId === null
                                        ? 'ring-red-500 bg-red-500 text-white'
                                        : 'ring-transparent bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400'">
                                    <MessageSquare class="w-4 h-4" />
                                </span>
                                <template v-if="activeChannelId === null">
                                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Todos</span>
                                    <span class="text-[11px] px-1.5 py-0.5 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400">{{ threads.length }}</span>
                                </template>
                            </button>

                            <button v-for="channel in channels" :key="channel.id"
                                @click="activeChannelId = channel.id"
                                class="px-4 py-3 transition border-b-2 flex-shrink-0 inline-flex items-center gap-2"
                                :class="activeChannelId === channel.id
                                    ? 'border-red-500 bg-red-50 dark:bg-red-900/10'
                                    : 'border-transparent hover:bg-gray-50 dark:hover:bg-gray-800'">
                                <img v-if="channel.thumbnail" :src="channel.thumbnail" :alt="channel.name"
                                    class="w-9 h-9 rounded-full flex-shrink-0 object-cover ring-2 transition"
                                    :class="activeChannelId === channel.id ? 'ring-red-500' : 'ring-transparent'"
                                    @error="channel.thumbnail = null" />
                                <span v-else
                                    class="w-9 h-9 rounded-full flex-shrink-0 ring-2 flex items-center justify-center text-xs font-bold text-white transition"
                                    :class="activeChannelId === channel.id ? 'ring-red-500' : 'ring-transparent'"
                                    :style="{ backgroundColor: channel.color }">
                                    {{ channel.name.charAt(0).toUpperCase() }}
                                </span>
                                <template v-if="activeChannelId === channel.id">
                                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-300 truncate max-w-[140px]">{{ channel.name }}</span>
                                    <span class="text-[11px] px-1.5 py-0.5 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400">{{ counts[channel.id] || 0 }}</span>
                                </template>
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="filteredThreads.length === 0"
                    class="text-center py-16 text-gray-400 dark:text-gray-500 bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800">
                    <MessageCircle class="w-16 h-16 mx-auto mb-4 opacity-40" />
                    <p class="text-base font-medium">
                        {{ search ? 'Sin resultados para «' + search + '»' : 'No hay comentarios en los últimos 7 días' }}
                    </p>
                    <p v-if="!search" class="text-sm mt-1">Los comentarios más recientes de tus videos aparecerán aquí</p>
                </div>

                <div v-else class="space-y-4">
                    <article v-for="thread in filteredThreads" :key="thread.id"
                        class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden hover:shadow-md transition">
                        <div class="h-1" :style="{ backgroundColor: thread.channel_color }"></div>

                        <div class="p-5">
                            <div class="flex items-start gap-3">
                                <img v-if="thread.avatar" :src="thread.avatar" :alt="thread.author"
                                    class="w-10 h-10 rounded-full flex-shrink-0 object-cover ring-2 ring-gray-100 dark:ring-gray-800"
                                    @error="thread.avatar = null" />
                                <div v-else
                                    class="w-10 h-10 rounded-full flex-shrink-0 flex items-center justify-center text-sm font-bold text-white ring-2 ring-gray-100 dark:ring-gray-800"
                                    :style="{ backgroundColor: thread.channel_color }">
                                    {{ (thread.author || '?').charAt(0).toUpperCase() }}
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ thread.author }}</p>
                                            <span class="inline-flex items-center gap-1.5 mt-1 px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-800 text-[11px] font-medium text-gray-500 dark:text-gray-400">
                                                <span class="w-2 h-2 rounded-full flex-shrink-0" :style="{ backgroundColor: thread.channel_color }"></span>
                                                {{ thread.channel_name }}
                                            </span>
                                        </div>
                                        <span class="text-xs text-gray-400 dark:text-gray-500 whitespace-nowrap flex-shrink-0">{{ formatDate(thread.published_at) }}</span>
                                    </div>
                                </div>
                            </div>

                            <a :href="thread.video_url" target="_blank"
                                class="flex items-center gap-2.5 mt-4 px-3 py-2 rounded-xl bg-gray-50 dark:bg-gray-800 hover:bg-red-50 dark:hover:bg-red-900/20 transition group">
                                <span class="p-1.5 rounded-lg bg-red-100 dark:bg-red-900 text-red-600 dark:text-red-400 flex-shrink-0">
                                    <Youtube class="w-3.5 h-3.5" />
                                </span>
                                <span class="text-xs font-medium text-gray-600 dark:text-gray-300 truncate group-hover:text-red-600 dark:group-hover:text-red-400 transition">
                                    {{ thread.video_title || 'Ver video en YouTube' }}
                                </span>
                                <ExternalLink class="w-3 h-3 ml-auto flex-shrink-0 text-gray-400 group-hover:text-red-500 transition" />
                            </a>

                            <p class="mt-3 text-sm text-gray-800 dark:text-gray-200 whitespace-pre-line break-words leading-relaxed">{{ thread.text }}</p>

                            <div class="flex items-center gap-4 mt-4 pt-3 border-t border-gray-100 dark:border-gray-800 text-xs text-gray-400 dark:text-gray-500">
                                <span class="inline-flex items-center gap-1.5">
                                    <ThumbsUp class="w-3.5 h-3.5" /> {{ thread.likes }}
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <MessageCircle class="w-3.5 h-3.5" /> {{ thread.total_replies }}
                                    {{ thread.total_replies === 1 ? 'respuesta' : 'respuestas' }}
                                </span>
                                <a :href="threadUrl(thread)" target="_blank"
                                    class="ml-auto inline-flex items-center gap-1.5 font-medium text-red-600 dark:text-red-400 hover:underline">
                                    <ExternalLink class="w-3 h-3" /> Responder en YouTube
                                </a>
                            </div>

                            <div v-if="thread.replies && thread.replies.length > 0"
                                class="mt-4 pl-4 border-l-2 border-gray-200 dark:border-gray-800 space-y-4">
                                <div v-for="reply in thread.replies" :key="reply.id" class="flex items-start gap-2.5">
                                    <img v-if="reply.avatar" :src="reply.avatar" :alt="reply.author"
                                        class="w-7 h-7 rounded-full flex-shrink-0 object-cover mt-0.5"
                                        @error="reply.avatar = null" />
                                    <div v-else
                                        class="w-7 h-7 rounded-full flex-shrink-0 flex items-center justify-center text-[10px] font-bold text-white mt-0.5"
                                        :style="{ backgroundColor: thread.channel_color }">
                                        {{ (reply.author || '?').charAt(0).toUpperCase() }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs">
                                            <span class="font-semibold text-gray-900 dark:text-white">{{ reply.author }}</span>
                                            <span class="text-gray-400 dark:text-gray-500 ml-1.5">{{ formatDate(reply.published_at) }}</span>
                                        </p>
                                        <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line break-words mt-0.5 leading-relaxed">{{ reply.text }}</p>
                                    </div>
                                </div>

                                <p v-if="thread.total_replies > thread.replies.length" class="text-xs">
                                    <a :href="threadUrl(thread)" target="_blank"
                                        class="font-medium text-red-600 dark:text-red-400 hover:underline">
                                        Ver las otras {{ thread.total_replies - thread.replies.length }} respuestas en YouTube
                                    </a>
                                </p>
                            </div>
                        </div>
                    </article>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
