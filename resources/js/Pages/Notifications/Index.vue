<script setup>
import { ref, computed, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import { Bell, Share2, Search, Eye, CheckCircle, XCircle, RefreshCw, Video, FileText, Clock } from 'lucide-vue-next'

const page = usePage()
const currentUserId = computed(() => page.props.auth?.user?.id)

const props = defineProps({
    notifications: Object,
    shares: Object,
    filters: { type: Object, default: () => ({}) },
})

const activeTab = ref('notifications')

const search = ref(props.filters?.search || '')
const statusFilter = ref(props.filters?.status || '')
const typeFilter = ref(props.filters?.type || '')
const perPage = ref(15)

const showDetail = ref(false)
const selectedNotif = ref(null)
const processing = ref(false)

function load() {
    const params = { search: search.value || '', per_page: perPage.value }
    if (activeTab.value === 'notifications') {
        params.status = statusFilter.value || ''
    } else {
        params.status = statusFilter.value || ''
        params.type = typeFilter.value || ''
    }
    router.get('/notifications', params, { preserveState: true, replace: true })
}

let debounceTimer
watch(search, () => {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(load, 400)
})
watch(statusFilter, load)
watch(typeFilter, load)
watch(perPage, load)

function switchTab(tab) {
    activeTab.value = tab
    search.value = ''
    statusFilter.value = ''
    typeFilter.value = ''
    load()
}

function viewDetail(notif) {
    selectedNotif.value = notif
    showDetail.value = true
}

async function acceptShare(notif) {
    if (!notif.data.task_share_id) return
    processing.value = true
    try {
        await axios.post(`/task-shares/${notif.data.task_share_id}/accept`)
        window.dispatchEvent(new CustomEvent('share-accepted'))
        showDetail.value = false
        load()
    } catch (e) {
        console.error(e)
    } finally {
        processing.value = false
    }
}

async function rejectShare(notif) {
    if (!notif.data.task_share_id) return
    processing.value = true
    try {
        await axios.post(`/task-shares/${notif.data.task_share_id}/reject`)
        window.dispatchEvent(new CustomEvent('share-rejected'))
        showDetail.value = false
        load()
    } catch (e) {
        console.error(e)
    } finally {
        processing.value = false
    }
}

async function reshare(share) {
    try {
        await axios.post('/task-shares', {
            shareable_type: share.shareable_type === 'VideoTask' ? 'video_tasks' : 'extra_tasks',
            shareable_id: share.shareable_id,
            user_ids: [share.shared_with.id],
            roles: [share.role],
        })
        load()
    } catch (e) {
        console.error(e)
    }
}

function typeLabel(type) {
    return type === 'VideoTask' ? 'Video' : 'Extra'
}

function typeIcon(type) {
    return type === 'VideoTask' ? Video : FileText
}

function statusConfig(status) {
    switch (status) {
        case 'accepted': return { label: 'Aceptado', class: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300', icon: CheckCircle }
        case 'pending': return { label: 'Pendiente', class: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300', icon: Clock }
        default: return { label: 'Desconocido', class: 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400', icon: Clock }
    }
}

const paginatedData = computed(() => activeTab.value === 'notifications' ? props.notifications : props.shares)
</script>

<template>
    <AppLayout>
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Notificaciones</h1>
            </div>

            <!-- Tabs -->
            <div class="flex gap-1 p-1 bg-gray-100 dark:bg-gray-800 rounded-xl w-fit">
                <button @click="switchTab('notifications')"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition"
                    :class="activeTab === 'notifications'
                        ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm'
                        : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'">
                    <Bell class="w-4 h-4" /> Notificaciones
                </button>
                <button @click="switchTab('shares')"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition"
                    :class="activeTab === 'shares'
                        ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm'
                        : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'">
                    <Share2 class="w-4 h-4" /> Compartidos
                </button>
            </div>

            <!-- Filters -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-4 border-b border-gray-100 dark:border-gray-700 flex flex-col sm:flex-row sm:items-center gap-3">
                    <div class="relative flex-1 max-w-md">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                        <input v-model="search" type="text"
                            :placeholder="activeTab === 'notifications' ? 'Buscar notificaciones...' : 'Buscar por usuario...'"
                            class="w-full pl-10 pr-4 py-2 rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Notification filters -->
                        <template v-if="activeTab === 'notifications'">
                            <button @click="statusFilter = statusFilter === 'unread' ? '' : 'unread'"
                                class="px-3 py-1.5 rounded-lg border text-xs font-medium transition"
                                :class="statusFilter === 'unread'
                                    ? 'bg-indigo-50 border-indigo-300 text-indigo-700 dark:bg-indigo-900/30 dark:border-indigo-700 dark:text-indigo-300'
                                    : 'bg-gray-50 border-gray-200 text-gray-500 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400'">
                                No leídas
                            </button>
                            <button @click="statusFilter = statusFilter === 'read' ? '' : 'read'"
                                class="px-3 py-1.5 rounded-lg border text-xs font-medium transition"
                                :class="statusFilter === 'read'
                                    ? 'bg-blue-50 border-blue-300 text-blue-700 dark:bg-blue-900/30 dark:border-blue-700 dark:text-blue-300'
                                    : 'bg-gray-50 border-gray-200 text-gray-500 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400'">
                                Leídas
                            </button>
                        </template>

                        <!-- Share filters -->
                        <template v-if="activeTab === 'shares'">
                            <button @click="statusFilter = statusFilter === 'accepted' ? '' : 'accepted'"
                                class="px-3 py-1.5 rounded-lg border text-xs font-medium transition"
                                :class="statusFilter === 'accepted'
                                    ? 'bg-green-50 border-green-300 text-green-700 dark:bg-green-900/30 dark:border-green-700 dark:text-green-300'
                                    : 'bg-gray-50 border-gray-200 text-gray-500 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400'">
                                Aceptadas
                            </button>
                            <button @click="statusFilter = statusFilter === 'pending' ? '' : 'pending'"
                                class="px-3 py-1.5 rounded-lg border text-xs font-medium transition"
                                :class="statusFilter === 'pending'
                                    ? 'bg-amber-50 border-amber-300 text-amber-700 dark:bg-amber-900/30 dark:border-amber-700 dark:text-amber-300'
                                    : 'bg-gray-50 border-gray-200 text-gray-500 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400'">
                                Pendientes
                            </button>
                            <span class="w-px h-6 bg-gray-200 dark:bg-gray-700 mx-1 hidden sm:block"></span>
                            <button @click="typeFilter = typeFilter === 'video_task' ? '' : 'video_task'"
                                class="px-3 py-1.5 rounded-lg border text-xs font-medium transition"
                                :class="typeFilter === 'video_task'
                                    ? 'bg-indigo-50 border-indigo-300 text-indigo-700 dark:bg-indigo-900/30 dark:border-indigo-700 dark:text-indigo-300'
                                    : 'bg-gray-50 border-gray-200 text-gray-500 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400'">
                                Videos
                            </button>
                            <button @click="typeFilter = typeFilter === 'extra_task' ? '' : 'extra_task'"
                                class="px-3 py-1.5 rounded-lg border text-xs font-medium transition"
                                :class="typeFilter === 'extra_task'
                                    ? 'bg-purple-50 border-purple-300 text-purple-700 dark:bg-purple-900/30 dark:border-purple-700 dark:text-purple-300'
                                    : 'bg-gray-50 border-gray-200 text-gray-500 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400'">
                                Extras
                            </button>
                        </template>

                        <span class="w-px h-6 bg-gray-200 dark:bg-gray-700 mx-1 hidden sm:block"></span>

                        <select v-model="perPage"
                            class="px-4 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-xs font-medium text-gray-500 dark:text-gray-400 focus:outline-none focus:ring-1 focus:ring-indigo-400 min-w-[80px]">
                            <option value="10">10</option>
                            <option value="15">15</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                    </div>
                </div>

                <!-- Notifications Tab -->
                <div v-if="activeTab === 'notifications'">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 w-[150px]">Fecha</th>
                                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 w-[150px]">Usuario</th>
                                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Compartió</th>
                                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 w-[120px]">Estado</th>
                                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 w-[100px]"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="notif in notifications?.data || []" :key="notif.id"
                                    class="border-b border-gray-100 dark:border-gray-700/50 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition last:border-0"
                                    :class="{ 'bg-indigo-50/40 dark:bg-indigo-900/15': !notif.read_at }">
                                    <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ notif.created_at }}</td>
                                    <td class="px-4 py-3 text-xs font-medium text-gray-900 dark:text-white">{{ notif.data.shared_by_user_name || '—' }}</td>
                                    <td class="px-4 py-3 text-xs text-gray-700 dark:text-gray-300 truncate max-w-[250px]">{{ notif.data.task_title || notif.data.message }}</td>
                                    <td class="px-4 py-3">
                                        <span v-if="notif.share_status === 'accepted'" class="px-2 py-0.5 text-[10px] font-medium rounded-full bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300">Aceptado</span>
                                        <span v-else-if="notif.share_status === 'rejected'" class="px-2 py-0.5 text-[10px] font-medium rounded-full bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300">Rechazado</span>
                                        <span v-else-if="notif.share_status === 'pending'" class="px-2 py-0.5 text-[10px] font-medium rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">Pendiente</span>
                                        <span v-else class="text-[10px] text-gray-400 dark:text-gray-500">—</span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <button @click="viewDetail(notif)" class="p-1.5 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition" title="Ver detalle">
                                                <Eye class="w-4 h-4" />
                                            </button>
                                            <template v-if="notif.share_status === 'pending' && notif.data.task_share_id">
                                                <button @click="acceptShare(notif)" :disabled="processing" class="p-1.5 rounded-lg hover:bg-green-100 dark:hover:bg-green-900/30 text-green-600 dark:text-green-400 transition disabled:opacity-50" title="Aceptar">
                                                    <CheckCircle class="w-4 h-4" />
                                                </button>
                                                <button @click="rejectShare(notif)" :disabled="processing" class="p-1.5 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/30 text-red-600 dark:text-red-400 transition disabled:opacity-50" title="Rechazar">
                                                    <XCircle class="w-4 h-4" />
                                                </button>
                                            </template>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!notifications?.data?.length">
                                    <td colspan="5" class="px-4 py-16 text-center text-gray-400 dark:text-gray-500 text-sm italic">No hay notificaciones</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Shares Tab -->
                <div v-if="activeTab === 'shares'">
                    <div v-if="shares?.data?.length" class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 w-[150px]">Fecha</th>
                                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 w-[150px]">Compartido por</th>
                                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 w-[150px]">Recibido por</th>
                                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Tarea</th>
                                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 w-[100px]">Tipo</th>
                                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 w-[100px]">Rol</th>
                                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 w-[110px]">Estado</th>
                                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 w-[80px]"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="share in shares.data" :key="share.id"
                                    class="border-b border-gray-100 dark:border-gray-700/50 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition last:border-0">
                                    <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ share.created_at }}</td>
                                    <td class="px-4 py-3 text-xs font-medium text-gray-900 dark:text-white">{{ share.shared_by.name }}</td>
                                    <td class="px-4 py-3 text-xs font-medium text-gray-900 dark:text-white">{{ share.shared_with.name }}</td>
                                    <td class="px-4 py-3 text-xs text-gray-700 dark:text-gray-300 truncate max-w-[250px]">{{ share.task?.title || 'Sin título' }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-1.5">
                                            <component :is="typeIcon(share.shareable_type)" class="w-3.5 h-3.5 text-gray-400 dark:text-gray-500" />
                                            <span class="text-xs text-gray-600 dark:text-gray-400">{{ typeLabel(share.shareable_type) }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3"><span class="text-xs text-gray-600 dark:text-gray-400 capitalize">{{ share.role }}</span></td>
                                    <td class="px-4 py-3">
                                        <span :class="[statusConfig(share.status).class, 'px-2 py-0.5 text-[10px] font-medium rounded-full inline-flex items-center gap-1']">
                                            <component :is="statusConfig(share.status).icon" class="w-3 h-3" />
                                            {{ statusConfig(share.status).label }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button v-if="share.shared_by.id === currentUserId" @click="reshare(share)"
                                            class="p-1.5 rounded-lg hover:bg-indigo-100 dark:hover:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 transition"
                                            title="Compartir de nuevo">
                                            <RefreshCw class="w-4 h-4" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="px-6 py-16 text-center">
                        <div class="w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center mx-auto mb-3">
                            <Share2 class="w-6 h-6 text-gray-300 dark:text-gray-500" />
                        </div>
                        <p class="text-gray-400 dark:text-gray-500 text-sm">No hay compartidos</p>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div class="flex items-center justify-between gap-4">
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    Mostrando {{ paginatedData?.from || 0 }} - {{ paginatedData?.to || 0 }} de {{ paginatedData?.total || 0 }}
                </div>
                <Pagination v-if="paginatedData?.links" :links="paginatedData.links" />
            </div>
        </div>

        <!-- Detail Modal (notifications only) -->
        <Teleport to="body">
            <transition name="fade">
                <div v-if="showDetail && selectedNotif" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50" @click.self="showDetail = false">
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-md mx-4 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Detalle</h3>
                            <button @click="showDetail = false" class="p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400 transition">
                                <X class="w-5 h-5" />
                            </button>
                        </div>
                        <div class="p-6 space-y-4">
                            <div>
                                <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1">Mensaje</p>
                                <p class="text-sm text-gray-900 dark:text-white">{{ selectedNotif.data.message }}</p>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1">Tipo</p>
                                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ selectedNotif.data.task_type === 'video_task' ? 'Tarea de video' : 'Tarea extra' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1">Fecha tarea</p>
                                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ selectedNotif.data.task_date || 'Sin fecha' }}</p>
                                </div>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1">Título</p>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ selectedNotif.data.task_title }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1">Compartido por</p>
                                <p class="text-sm text-gray-700 dark:text-gray-300">{{ selectedNotif.data.shared_by_user_name }}</p>
                            </div>
                            <div v-if="selectedNotif.data.task_share_id">
                                <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1">Estado</p>
                                <span v-if="selectedNotif.share_status === 'accepted'" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300">
                                    <CheckCircle class="w-3 h-3" /> Aceptado
                                </span>
                                <span v-else-if="selectedNotif.share_status === 'rejected'" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300">
                                    <XCircle class="w-3 h-3" /> Rechazado
                                </span>
                                <span v-else class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                                    Pendiente
                                </span>
                            </div>
                        </div>
                        <div v-if="selectedNotif.share_status === 'pending' && selectedNotif.data.task_share_id"
                            class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-end gap-3">
                            <button @click="rejectShare(selectedNotif)" :disabled="processing"
                                class="px-4 py-2 rounded-xl border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition disabled:opacity-50">
                                Rechazar
                            </button>
                            <button @click="acceptShare(selectedNotif)" :disabled="processing"
                                class="px-4 py-2 rounded-xl bg-green-600 hover:bg-green-700 text-white text-sm font-medium transition disabled:opacity-50">
                                Aceptar
                            </button>
                        </div>
                    </div>
                </div>
            </transition>
        </Teleport>
    </AppLayout>
</template>
