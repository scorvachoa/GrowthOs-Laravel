<script setup>
import { ref, watch, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import ConfirmDeleteModal from '@/Components/Modals/ConfirmDelete.vue'

const page = usePage()
const permissions = page.props.auth?.user?.permissions ?? []
const can = (perm) => permissions.includes(perm)
import { Lightbulb, Search, Upload, Download, Plus, X, CheckCircle2, Circle, FileText, Pencil, Trash2, Copy, ArrowUpDown, ArrowUpZA, ArrowDownAZ, Clock, ArrowUp, ArrowDown, Check } from 'lucide-vue-next'

const props = defineProps({
    channels: Array,
    selected_channel_id: Number,
    ideas: Array,
    query: String,
    sort: { type: String, default: 'date_desc' },
})

const activeTab = ref(props.selected_channel_id)
const searchQuery = ref(props.query || '')
const currentSort = ref(props.sort || 'date_desc')
const showModal = ref(false)
const newIdeasText = ref('')
const editingIdea = ref(null)
const deletingIdea = ref(null)
const copiedId = ref(null)
const deleteMessage = computed(() => deletingIdea.value ? `¿Eliminar "${deletingIdea.value.content}"?` : '')

function nav(params) {
    router.get('/ideas', { channel_id: activeTab.value, q: searchQuery.value, sort: currentSort.value, ...params }, { preserveState: true, preserveScroll: true })
}

function switchChannel(channelId) {
    activeTab.value = channelId
    searchQuery.value = ''
    currentSort.value = 'date_desc'
    router.get('/ideas', { channel_id: channelId })
}

function setSort(sort) {
    currentSort.value = sort
    nav()
}

function toggleUsed(idea) {
    router.patch(`/ideas/${idea.id}/used`, { used: !idea.is_used }, {
        preserveState: true, preserveScroll: true,
        onSuccess: () => { idea.is_used = !idea.is_used },
    })
}

function submitIdeas() {
    router.post('/ideas', { channel_id: activeTab.value, content_lines: newIdeasText.value }, {
        preserveState: true, preserveScroll: true,
        onSuccess: () => { showModal.value = false; newIdeasText.value = '' },
    })
}

function startEdit(idea) { editingIdea.value = { ...idea } }

function saveEdit() {
    router.patch(`/ideas/${editingIdea.value.id}`, { content: editingIdea.value.content }, {
        preserveState: true, preserveScroll: true,
        onSuccess: () => { editingIdea.value = null },
    })
}

function confirmDelete(idea) { deletingIdea.value = idea }

function executeDelete() {
    router.delete(`/ideas/${deletingIdea.value.id}`, {
        preserveState: true, preserveScroll: true,
        onSuccess: () => { deletingIdea.value = null },
    })
}

function copyContent(idea) {
    navigator.clipboard.writeText(idea.content)
    copiedId.value = idea.id
    setTimeout(() => { copiedId.value = null }, 1500)
}

function importTxt(e) {
    const file = e.target.files[0]
    if (!file) return
    const form = new FormData()
    form.append('channel_id', activeTab.value)
    form.append('txt_file', file)
    router.post('/ideas/import', form, {
        preserveState: true, preserveScroll: true,
        onSuccess: () => { e.target.value = '' },
    })
}

function exportTxt() {
    window.open(`/ideas/export?channel_id=${activeTab.value}`, '_blank')
}

let debounceTimer
watch(searchQuery, () => {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => nav(), 400)
})

const sortOptions = [
    { value: 'date_desc', label: 'Nuevos', icon: ArrowDown },
    { value: 'date_asc', label: 'Antiguos', icon: ArrowUp },
    { value: 'alpha_asc', label: 'A-Z', icon: ArrowDownAZ },
    { value: 'alpha_desc', label: 'Z-A', icon: ArrowUpZA },
]
</script>

<template>
    <AppLayout>
        <div class="flex flex-col min-h-0 flex-1 overflow-hidden">
            <div class="flex items-center justify-between mb-6 flex-shrink-0">
                <div class="flex items-center gap-3">
                    <div class="p-3 rounded-xl bg-amber-100 dark:bg-amber-900">
                        <Lightbulb class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Ideas</h1>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Gestion masiva de ideas por canal</p>
                    </div>
                </div>
            </div>

            <div v-if="channels.length === 0"
                class="text-center py-16 text-gray-400 dark:text-gray-500 bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800">
                <Lightbulb class="w-16 h-16 mx-auto mb-4 opacity-40" />
                <p class="text-base font-medium">No hay canales configurados</p>
                <p class="text-sm mt-1">Agrega canales en Empresa &gt; Canales administrados</p>
            </div>

            <template v-else>
                <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 flex flex-col min-h-0 flex-1 overflow-hidden">
                    <div class="lg:hidden overflow-x-auto flex-shrink-0 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex">
                            <button v-for="channel in channels" :key="channel.id"
                                @click="switchChannel(channel.id)"
                                class="flex items-center gap-2 px-5 py-4 text-sm font-medium whitespace-nowrap transition border-b-2 flex-shrink-0"
                                :class="activeTab === channel.id
                                    ? 'border-amber-500 text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/10'
                                    : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800'">
                                <span class="w-3 h-3 rounded-full flex-shrink-0" :style="{ backgroundColor: channel.color }"></span>
                                {{ channel.name }}
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-[220px_1fr_280px] gap-0 min-h-0 flex-1 overflow-hidden">
                        <div class="hidden lg:flex flex-col border-r border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/30">
                            <div class="p-4 border-b border-gray-100 dark:border-gray-800 flex-shrink-0">
                                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Canales</h3>
                            </div>
                            <div class="p-2 space-y-0.5">
                                <button v-for="channel in channels" :key="channel.id"
                                    @click="switchChannel(channel.id)"
                                    class="flex items-center gap-2.5 w-full px-3 py-2.5 text-sm font-medium rounded-lg transition text-left"
                                    :class="activeTab === channel.id
                                        ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300'
                                        : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700'">
                                    <span class="w-3 h-3 rounded-full flex-shrink-0" :style="{ backgroundColor: channel.color }"></span>
                                    <span class="truncate">{{ channel.name }}</span>
                                </button>
                            </div>
                        </div>

                        <div class="flex flex-col min-h-0 h-full overflow-hidden">
                            <div class="p-6 pb-0 border-b border-gray-100 dark:border-gray-800 flex-shrink-0">
                                <div class="flex gap-2 pb-4">
                                    <div class="relative flex-1">
                                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                                        <input v-model="searchQuery" type="text" placeholder="Buscar idea..."
                                            class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white text-sm focus:ring-amber-500 focus:border-amber-500" />
                                    </div>
                                    <div class="flex rounded-xl border border-gray-300 dark:border-gray-700 overflow-hidden">
                                        <button v-for="opt in sortOptions" :key="opt.value"
                                            @click="setSort(opt.value)"
                                            class="p-2.5 text-xs font-medium transition"
                                            :class="currentSort === opt.value
                                                ? 'bg-amber-600 text-white'
                                                : 'bg-white dark:bg-gray-900 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800'"
                                            :title="opt.label">
                                            <component :is="opt.icon" class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <template v-if="ideas.length === 0">
                                <div class="flex-1 flex items-center justify-center px-6 py-4">
                                    <div class="text-center py-12 text-gray-400 dark:text-gray-500 text-sm">
                                        <FileText class="w-10 h-10 mx-auto mb-2 opacity-40" />
                                        No hay ideas en este canal
                                    </div>
                                </div>
                            </template>
                            <div v-else class="flex-1 overflow-y-auto px-6 py-4 space-y-1">
                                <div v-for="idea in ideas" :key="idea.id"
                                    class="group flex items-start gap-3 p-3 rounded-xl transition relative"
                                    :class="idea.is_used
                                        ? 'bg-gray-50 dark:bg-gray-800/50 opacity-60'
                                        : 'hover:bg-gray-50 dark:hover:bg-gray-800'">
                                    <div class="mt-0.5 flex-shrink-0 cursor-pointer" @click="toggleUsed(idea)">
                                        <CheckCircle2 v-if="idea.is_used" class="w-5 h-5 text-green-500" />
                                        <Circle v-else class="w-5 h-5 text-gray-300 dark:text-gray-600" />
                                    </div>
                                    <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition flex-shrink-0">
                                        <button @click="copyContent(idea)"
                                            class="p-1.5 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 transition relative"
                                            :class="copiedId === idea.id ? 'text-green-500' : 'text-gray-400 hover:text-indigo-600'"
                                            title="Copiar">
                                            <transition name="pop" mode="out-in">
                                                <Check v-if="copiedId === idea.id" key="check" class="w-3.5 h-3.5" />
                                                <Copy v-else key="copy" class="w-3.5 h-3.5" />
                                            </transition>
                                        </button>
                                        <button v-if="can('edit ideas')" @click="startEdit(idea)"
                                            class="p-1.5 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-400 hover:text-amber-600 transition"
                                            title="Editar">
                                            <Pencil class="w-3.5 h-3.5" />
                                        </button>
                                        <button v-if="can('delete ideas')" @click="confirmDelete(idea)"
                                            class="p-1.5 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-400 hover:text-red-600 transition"
                                            title="Eliminar">
                                            <Trash2 class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm text-gray-900 dark:text-white" :class="{ 'line-through': idea.is_used }">{{ idea.content }}</p>
                                        <p class="text-xs text-gray-400 mt-0.5">
                                            {{ idea.created_at }}
                                            <span v-if="idea.tags" class="ml-2">· {{ idea.tags }}</span>
                                            <span class="ml-2" :class="idea.is_used ? 'text-green-500' : 'text-amber-500'">
                                                · {{ idea.is_used ? 'usada' : 'pendiente' }}
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="p-6 space-y-4 border-l border-gray-200 dark:border-gray-700">
                            <button v-if="can('create ideas')" @click="showModal = true"
                                class="w-full px-4 py-3 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-medium transition flex items-center justify-center gap-2">
                                <Plus class="w-4 h-4" /> Crear ideas
                            </button>

                            <div v-if="can('import ideas')" class="p-4 rounded-xl bg-gray-50 dark:bg-gray-800 border border-dashed border-gray-200 dark:border-gray-700">
                                <label class="flex flex-col items-center gap-2 cursor-pointer">
                                    <Upload class="w-5 h-5 text-gray-400" />
                                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Importar TXT</span>
                                    <input type="file" accept=".txt,text/plain" @change="importTxt" class="hidden" />
                                </label>
                            </div>

                            <button v-if="can('export ideas')" @click="exportTxt"
                                class="w-full px-4 py-3 rounded-xl border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 font-medium transition hover:bg-gray-50 dark:hover:bg-gray-800 flex items-center justify-center gap-2">
                                <Download class="w-4 h-4" /> Exportar TXT
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <transition name="fade">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showModal = false">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-lg p-6 mx-4 max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Crear ideas</h3>
                        <button @click="showModal = false" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                            <X class="w-5 h-5" />
                        </button>
                    </div>
                    <form @submit.prevent="submitIdeas" class="space-y-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Una idea por linea</label>
                        <textarea v-model="newIdeasText" rows="16" placeholder="Idea 1&#10;Idea 2&#10;Idea 3"
                            class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-amber-500 focus:border-amber-500"
                            required></textarea>
                        <button type="submit"
                            class="w-full px-4 py-3 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-medium transition flex items-center justify-center gap-2">
                            <Plus class="w-4 h-4" /> Guardar ideas
                        </button>
                    </form>
                </div>
            </div>
        </transition>

        <transition name="fade">
            <div v-if="editingIdea" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="editingIdea = null">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-lg p-6 mx-4">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Editar idea</h3>
                        <button @click="editingIdea = null" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                            <X class="w-5 h-5" />
                        </button>
                    </div>
                    <form @submit.prevent="saveEdit" class="space-y-4">
                        <textarea v-model="editingIdea.content" rows="4"
                            class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-amber-500 focus:border-amber-500"
                            required></textarea>
                        <div class="flex justify-end gap-3">
                            <button type="button" @click="editingIdea = null"
                                class="px-4 py-2 rounded-xl border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300">
                                Cancelar
                            </button>
                            <button type="submit"
                                class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white flex items-center gap-2">
                                <Pencil class="w-4 h-4" /> Guardar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </transition>

        <ConfirmDeleteModal
            :show="deletingIdea !== null"
            title="Eliminar idea"
            :message="deleteMessage"
            @close="deletingIdea = null"
            @confirm="executeDelete"
        />
    </AppLayout>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity .2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.pop-enter-active { animation: pop-in .25s ease-out; }
.pop-leave-active { animation: pop-in .15s ease-in reverse; }
@keyframes pop-in {
    0% { transform: scale(0); opacity: 0; }
    70% { transform: scale(1.15); }
    100% { transform: scale(1); opacity: 1; }
}
</style>
