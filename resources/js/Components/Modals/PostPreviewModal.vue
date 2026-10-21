<script setup>
import { computed, ref } from 'vue'
import { useTimeAgo } from '@/Composables/useTimeAgo'
import { useFormat } from '@/Composables/useFormat'
import { useModalA11y } from '@/Composables/useModalA11y'
import {
    Facebook,
    Instagram,
    X,
    Globe,
    ThumbsUp,
    MessageCircle,
    Share2,
    MoreHorizontal,
    ExternalLink,
    Heart,
    Send,
    Bookmark,
} from 'lucide-vue-next'

const props = defineProps({
    show: Boolean,
    network: { type: String, default: 'facebook' },
    post: Object,
    profile: Object,
})

const emit = defineEmits(['close'])

const { timeAgo } = useTimeAgo()

const isFacebook = computed(() => props.network === 'facebook')
const networkLabel = computed(() => (isFacebook.value ? 'Facebook' : 'Instagram'))

const pageName = computed(() => {
    if (isFacebook.value) return props.profile?.name || props.profile?.page_name || 'Página de Facebook'
    return props.post?.username || props.profile?.username || ''
})

const imageUrl = computed(() => {
    if (!props.post) return null
    if (isFacebook.value) return props.post.full_picture || null
    return props.post.media_url || props.post.thumbnail_url || null
})

const isVideo = computed(() =>
    !isFacebook.value && ['VIDEO', 'REELS'].includes(props.post?.type)
)

const { formatChartDate } = useFormat()

const igDate = computed(() => {
    if (!props.post?.timestamp) return ''
    return formatChartDate(props.post.timestamp).toUpperCase()
})

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
}

function linkify(text) {
    let out = escapeHtml(text)
    out = out.replace(
        /(https?:\/\/[^\s<]+)/g,
        '<a href="$1" target="_blank" rel="noopener noreferrer" class="text-blue-600 dark:text-blue-400 hover:underline break-all">$1</a>'
    )
    out = out.replace(
        /(^|\s)(#[\p{L}\p{N}_]+)/gu,
        '$1<span class="text-blue-600 dark:text-blue-400">$2</span>'
    )
    return out
}

const formatNumber = (n) => {
    if (n === null || n === undefined) return '—'
    if (n >= 1000000) return (n / 1000000).toFixed(1) + 'M'
    if (n >= 1000) return (n / 1000).toFixed(1) + 'K'
    return n.toLocaleString()
}

const dialogRef = ref(null)

useModalA11y(dialogRef, () => props.show && !!props.post, () => emit('close'))
</script>

<template>
    <Teleport to="body">
        <transition name="fade">
            <div
                v-if="show && post"
                class="fixed inset-0 z-[70] flex items-center justify-center bg-black/60 p-4"
                @click.self="emit('close')"
            >
                <div
                    ref="dialogRef"
                    role="dialog"
                    aria-modal="true"
                    aria-label="Vista previa de la publicación"
                    tabindex="-1"
                    class="w-full max-w-[600px] max-h-[92vh] overflow-y-auto rounded-2xl bg-gray-100 dark:bg-gray-950 shadow-2xl outline-none"
                >
                    <!-- Modal chrome -->
                    <div class="sticky top-0 z-10 flex items-center justify-between px-4 py-3 bg-white/95 dark:bg-gray-900/95 backdrop-blur border-b border-gray-200 dark:border-gray-800 rounded-t-2xl">
                        <span class="inline-flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200">
                            <Facebook v-if="isFacebook" class="w-4 h-4 text-[#1877f2]" />
                            <Instagram v-else class="w-4 h-4 text-pink-500" />
                            Así se ve en {{ networkLabel }}
                        </span>
                        <button
                            type="button"
                            title="Cerrar (Esc)"
                            class="p-1.5 rounded-full text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition"
                            @click="emit('close')"
                        >
                            <X class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="p-4 sm:p-6">
                        <!-- Facebook post preview -->
                        <div v-if="isFacebook" class="max-w-[500px] mx-auto bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                            <div class="flex items-center gap-3 px-4 pt-4 pb-3">
                                <img
                                    v-if="profile?.picture"
                                    :src="profile.picture"
                                    :alt="pageName"
                                    class="w-10 h-10 rounded-full object-cover bg-gray-100"
                                    @error="$event.target.style.visibility = 'hidden'"
                                />
                                <span v-else class="w-10 h-10 rounded-full bg-[#1877f2] flex items-center justify-center flex-shrink-0">
                                    <Facebook class="w-5 h-5 text-white" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-[15px] font-semibold text-gray-900 dark:text-white truncate leading-tight">{{ pageName }}</p>
                                    <p class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        {{ timeAgo(post.created_time) }}
                                        <span aria-hidden="true">·</span>
                                        <Globe class="w-3 h-3" />
                                    </p>
                                </div>
                                <MoreHorizontal class="w-5 h-5 text-gray-400 flex-shrink-0" />
                            </div>

                            <p
                                v-if="post.message"
                                class="px-4 pb-3 text-[15px] leading-relaxed text-gray-900 dark:text-white whitespace-pre-line break-words"
                                v-html="linkify(post.message)"
                            ></p>
                            <p v-else class="px-4 pb-3 text-[15px] italic text-gray-400 dark:text-gray-500">(sin texto)</p>

                            <div v-if="post.video_url" class="bg-black">
                                <video
                                    :src="post.video_url"
                                    :poster="post.full_picture || undefined"
                                    controls
                                    autoplay
                                    muted
                                    loop
                                    playsinline
                                    class="w-full max-h-[480px] bg-black"
                                ></video>
                            </div>
                            <div v-else-if="imageUrl" class="relative">
                                <img
                                    :src="imageUrl"
                                    class="w-full max-h-[480px] object-cover bg-gray-100 dark:bg-black"
                                />
                                <span v-if="post.is_video" class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                    <span class="w-14 h-14 rounded-full bg-black/50 flex items-center justify-center">
                                        <span class="w-0 h-0 border-y-[10px] border-y-transparent border-l-[16px] border-l-white ml-1"></span>
                                    </span>
                                </span>
                            </div>

                            <div class="px-4 pt-3 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="w-5 h-5 rounded-full bg-[#1877f2] flex items-center justify-center flex-shrink-0">
                                        <ThumbsUp class="w-3 h-3 text-white" />
                                    </span>
                                    {{ formatNumber(post.likes) }}
                                </span>
                                <span>{{ formatNumber(post.comments) }} comentarios</span>
                            </div>

                            <div class="mx-4 mt-2.5 h-px bg-gray-200 dark:bg-gray-700"></div>

                            <div class="p-1.5 flex text-gray-600 dark:text-gray-300">
                                <div class="flex-1 select-none flex items-center justify-center gap-2 py-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 text-sm font-medium transition cursor-default">
                                    <ThumbsUp class="w-5 h-5" /> Me gusta
                                </div>
                                <div class="flex-1 select-none flex items-center justify-center gap-2 py-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 text-sm font-medium transition cursor-default">
                                    <MessageCircle class="w-5 h-5" /> Comentar
                                </div>
                                <div class="flex-1 select-none flex items-center justify-center gap-2 py-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 text-sm font-medium transition cursor-default">
                                    <Share2 class="w-5 h-5" /> Compartir
                                </div>
                            </div>
                        </div>

                        <!-- Instagram post preview -->
                        <div v-else class="max-w-[420px] mx-auto bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                            <div class="flex items-center gap-3 px-3 py-3">
                                <span class="p-[2px] rounded-full bg-gradient-to-tr from-[#feda75] via-[#d62976] to-[#962fbf] flex-shrink-0">
                                    <img
                                        v-if="profile?.picture"
                                        :src="profile.picture"
                                        :alt="pageName"
                                        class="w-8 h-8 rounded-full object-cover border-2 border-white dark:border-gray-800 bg-white"
                                        @error="$event.target.style.visibility = 'hidden'"
                                    />
                                    <span v-else class="w-8 h-8 rounded-full border-2 border-white dark:border-gray-800 bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-xs font-bold text-gray-500">
                                        {{ (pageName || 'I').charAt(0).toUpperCase() }}
                                    </span>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white truncate leading-tight">{{ pageName }}</p>
                                    <p v-if="igDate" class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">{{ igDate }}</p>
                                </div>
                                <MoreHorizontal class="w-5 h-5 text-gray-500 dark:text-gray-400 flex-shrink-0" />
                            </div>

                            <div class="relative bg-black">
                                <video
                                    v-if="isVideo && post.media_url"
                                    :src="post.media_url"
                                    :poster="post.thumbnail_url || undefined"
                                    controls
                                    autoplay
                                    muted
                                    loop
                                    playsinline
                                    class="w-full max-h-[500px] bg-black"
                                ></video>
                                <img
                                    v-else-if="imageUrl"
                                    :src="imageUrl"
                                    class="w-full max-h-[500px] object-contain bg-black"
                                />
                                <div v-else class="h-60 flex items-center justify-center">
                                    <Instagram class="w-12 h-12 text-white/30" />
                                </div>
                                <span
                                    v-if="isVideo && !post.media_url && imageUrl"
                                    class="absolute inset-0 flex items-center justify-center pointer-events-none"
                                >
                                    <span class="w-14 h-14 rounded-full bg-black/50 flex items-center justify-center">
                                        <span class="w-0 h-0 border-y-[10px] border-y-transparent border-l-[16px] border-l-white ml-1"></span>
                                    </span>
                                </span>
                            </div>

                            <div class="flex items-center gap-4 px-3 pt-3 pb-1 text-gray-900 dark:text-gray-100">
                                <Heart class="w-6 h-6 cursor-default" />
                                <MessageCircle class="w-6 h-6 cursor-default" />
                                <Send class="w-6 h-6 cursor-default" />
                                <Bookmark class="w-6 h-6 ml-auto cursor-default" />
                            </div>

                            <p class="px-3 text-sm font-semibold text-gray-900 dark:text-white">
                                {{ formatNumber(post.likes) }} me gusta
                            </p>

                            <p v-if="post.caption" class="px-3 mt-1 text-sm text-gray-900 dark:text-white break-words">
                                <span class="font-semibold mr-1">{{ pageName }}</span>
                                <span v-html="linkify(post.caption)"></span>
                            </p>

                            <p v-if="post.comments > 0" class="px-3 mt-1.5 text-sm text-gray-500 dark:text-gray-400 select-none cursor-default">
                                Ver todos los comentarios ({{ post.comments }})
                            </p>

                            <p v-if="igDate" class="px-3 mt-2 mb-3 text-[10px] uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                {{ igDate }}
                            </p>
                        </div>

                        <a
                            v-if="isFacebook ? post.permalink_url : post.permalink"
                            :href="isFacebook ? post.permalink_url : post.permalink"
                            target="_blank"
                            class="mt-4 mx-auto max-w-[600px] w-fit inline-flex items-center gap-1.5 text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline"
                        >
                            Ver en {{ networkLabel }}
                            <ExternalLink class="w-3.5 h-3.5" />
                        </a>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity .2s;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
