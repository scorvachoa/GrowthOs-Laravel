<script setup>
import { computed, ref, watch, onMounted, onUnmounted } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { Menu, LogOut, Sun, Moon, Bell, Check, X, CalendarDays, AlertTriangle, ArrowRight } from 'lucide-vue-next'
import { useTheme } from '@/Composables/useTheme'
import Modal from '@/Components/Modals/Modal.vue'

const { isDark, toggleDark } = useTheme()

defineProps({
    sidebarCollapsed: Boolean,
})

const emit = defineEmits(['toggleMobileSidebar'])

const page = usePage()

const user = computed(() =>
    page.props.auth?.user
)

const isSuperAdmin = computed(() =>
    user.value?.roles?.includes('Super Admin')
)

const companies = computed(() =>
    user.value?.companies ?? []
)

const activeCompany = computed(() =>
    user.value?.active_company
)

const appName = import.meta.env.VITE_APP_NAME || 'GrowthOS'
const switcherOpen = ref(false)
const switcherRef = ref(null)

const notifications = ref([])
const unreadCount = ref(0)
const notifOpen = ref(false)
const notifRef = ref(null)
let notifInterval = null

const todaySummary = ref({ today: 0, overdue: 0 })

async function fetchTodaySummary() {
    try {
        const res = await fetch('/tasks/today-summary', {
            headers: { 'Accept': 'application/json' }
        })
        if (!res.ok) return
        todaySummary.value = await res.json()
    } catch (e) {}
}

function goToPlanning() {
    notifOpen.value = false
    summaryOpen.value = false
    router.get('/planning')
}

const summaryOpen = ref(false)
const summaryLoading = ref(false)
const todayTaskList = ref([])
const overdueTaskList = ref([])

async function fetchSummaryLists() {
    summaryLoading.value = true
    try {
        const res = await fetch('/tasks/today-tasks', {
            headers: { 'Accept': 'application/json' }
        })
        if (!res.ok) return
        const data = await res.json()
        todayTaskList.value = data.today
        overdueTaskList.value = data.overdue
    } catch (e) {
    } finally {
        summaryLoading.value = false
    }
}

function openSummary() {
    notifOpen.value = false
    summaryOpen.value = true
    fetchSummaryLists()
}

async function fetchNotifications() {
    try {
        const res = await fetch('/notifications/unread', {
            headers: { 'Accept': 'application/json' }
        })
        if (!res.ok) return
        const data = await res.json()
        notifications.value = data.notifications
        unreadCount.value = data.unread_count
    } catch (e) {}
}

function refreshTopbar() {
    fetchNotifications()
    fetchTodaySummary()
}

async function markAsRead(id) {
    try {
        const res = await fetch(`/notifications/${id}/read`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'Accept': 'application/json'
            }
        })
        if (!res.ok) return
        const data = await res.json()
        unreadCount.value = data.unread_count
        const notif = notifications.value.find(n => n.id === id)
        if (notif) notif.read_at = new Date().toISOString().replace('T', ' ').slice(0, 16)
    } catch (e) {}
}

async function markAllAsRead() {
    try {
        const res = await fetch('/notifications/read-all', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'Accept': 'application/json'
            }
        })
        if (!res.ok) return
        notifications.value.forEach(n => { if (!n.read_at) n.read_at = new Date().toISOString().replace('T', ' ').slice(0, 16) })
        unreadCount.value = 0
    } catch (e) {}
}

async function clearAllNotifications() {
    try {
        const res = await fetch('/notifications/clear-all', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'Accept': 'application/json'
            }
        })
        if (!res.ok) return
        notifications.value = []
        unreadCount.value = 0
    } catch (e) {}
}

async function acceptShare(notif) {
    const shareId = notif.data?.task_share_id
    if (!shareId) return
    try {
        const res = await fetch(`/task-shares/${shareId}/accept`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'Accept': 'application/json'
            }
        })
        if (res.ok) {
            notif.data._accepted = true
            if (!notif.read_at) await markAsRead(notif.id)
            window.dispatchEvent(new CustomEvent('share-accepted'))
        }
    } catch (e) {}
}

async function rejectShare(notif) {
    const shareId = notif.data?.task_share_id
    if (!shareId) return
    try {
        const res = await fetch(`/task-shares/${shareId}/reject`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'Accept': 'application/json'
            }
        })
        if (res.ok) {
            notif.data._rejected = true
            if (!notif.read_at) await markAsRead(notif.id)
        }
    } catch (e) {}
}

function toggleNotif() {
    notifOpen.value = !notifOpen.value
    if (notifOpen.value) fetchNotifications()
}

function onDocClick(e) {
    if (switcherOpen.value && switcherRef.value && !switcherRef.value.contains(e.target)) {
        switcherOpen.value = false
    }
    if (notifOpen.value && notifRef.value && !notifRef.value.contains(e.target)) {
        notifOpen.value = false
    }
}

function switchCompany(companyId) {
    switcherOpen.value = false
    router.post('/company/switch', { company_id: companyId }, {
        preserveScroll: true,
        preserveState: false,
    })
}

const logout = () => {
    router.post('/logout')
}

function toggleSwitcher() {
    switcherOpen.value = !switcherOpen.value
}

function onShareUpdated() {
    fetchNotifications()
}

onMounted(() => {
    document.addEventListener('click', onDocClick)
    window.addEventListener('share-accepted', onShareUpdated)
    window.addEventListener('share-rejected', onShareUpdated)
    refreshTopbar()
    notifInterval = setInterval(refreshTopbar, 30000)
})

watch(activeCompany, () => {
    fetchTodaySummary()
})

onUnmounted(() => {
    document.removeEventListener('click', onDocClick)
    window.removeEventListener('share-accepted', onShareUpdated)
    window.removeEventListener('share-rejected', onShareUpdated)
    if (notifInterval) clearInterval(notifInterval)
})
</script>

<template>
    <header class="fixed top-0 right-0 left-0 z-20 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 px-4 sm:px-8 py-3 sm:py-4 flex items-center justify-between gap-2 transition-all duration-300"
        :class="sidebarCollapsed ? 'lg:left-16' : 'lg:left-64'">

        <div class="flex items-center gap-2 sm:gap-4 min-w-0">
            <button @click="$emit('toggleMobileSidebar')"
                class="lg:hidden p-2 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 transition shrink-0">
                <Menu class="w-5 h-5 text-gray-600 dark:text-gray-400" />
            </button>

            <div class="relative" v-if="isSuperAdmin && companies.length > 0" ref="switcherRef">
                <button @click="toggleSwitcher"
                    class="flex items-center gap-2 px-3 py-1.5 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 transition text-left">
                    <div class="w-7 h-7 rounded-lg flex items-center justify-center text-white text-xs font-bold shrink-0"
                        :style="{ backgroundColor: activeCompany?.primary_color || '#4f46e5' }">
                        {{ activeCompany?.name?.charAt(0) || '?' }}
                    </div>
                    <div class="hidden sm:block">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white truncate max-w-[150px]">{{ activeCompany?.name || 'Seleccionar empresa' }}</p>
                        <p class="text-[10px] text-gray-400 uppercase tracking-wider">Super Admin</p>
                    </div>
                    <svg class="w-4 h-4 text-gray-400 hidden sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div v-if="switcherOpen"
                    class="absolute top-full left-0 mt-1 w-64 bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 py-1 z-50 max-h-60 overflow-y-auto">
                    <p class="px-3 py-2 text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Cambiar empresa</p>
                    <button v-for="company in companies" :key="company.id" @click="switchCompany(company.id)"
                        class="w-full flex items-center gap-3 px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 transition text-left"
                        :class="{ 'bg-indigo-50 dark:bg-indigo-900/30': company.id === activeCompany?.id }">
                        <div class="w-6 h-6 rounded-md flex items-center justify-center text-white text-[10px] font-bold flex-shrink-0"
                            :style="{ backgroundColor: company.primary_color || '#4f46e5' }">
                            {{ company.name.charAt(0) }}
                        </div>
                        <span class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ company.name }}</span>
                        <svg v-if="company.id === activeCompany?.id" class="w-4 h-4 ml-auto text-indigo-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </button>
                </div>
            </div>

            <div v-else class="min-w-0">
                <h2 class="text-base sm:text-lg font-semibold text-gray-900 dark:text-white truncate">
                    {{ activeCompany?.name || appName }}
                </h2>
            </div>
        </div>

        <div class="flex items-center gap-2 sm:gap-4 shrink-0">

            <button @click="toggleDark"
                class="p-2 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 transition"
                :title="isDark ? 'Modo claro' : 'Modo oscuro'">
                <Sun v-if="isDark" class="w-5 h-5 text-gray-400" />
                <Moon v-else class="w-5 h-5 text-gray-500" />
            </button>

            <div class="relative" ref="notifRef">
                <button @click="toggleNotif"
                    class="relative p-2 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 transition"
                    title="Notificaciones">
                    <Bell class="w-5 h-5 text-gray-500 dark:text-gray-400" />
                    <span v-if="unreadCount > 0"
                        class="absolute -top-0.5 -right-0.5 w-5 h-5 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center">
                        {{ unreadCount > 9 ? '9+' : unreadCount }}
                    </span>
                </button>

                <div v-if="notifOpen"
                    class="absolute top-full right-0 mt-1 w-80 sm:w-96 bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 z-50 max-h-[70vh] flex flex-col">

                    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Notificaciones</h3>
                        <div class="flex items-center gap-2">
                            <button v-if="notifications.length > 0" @click="clearAllNotifications"
                                class="text-xs text-red-500 hover:text-red-700 dark:text-red-400">
                                Limpiar
                            </button>
                            <button v-if="unreadCount > 0" @click="markAllAsRead"
                                class="text-xs text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                Marcar todas como leídas
                            </button>
                        </div>
                    </div>

                    <div v-if="user?.permissions?.includes('view planning')"
                        class="border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
                        <button @click="openSummary"
                            class="w-full flex items-center justify-between gap-3 px-4 py-3 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition text-left">
                            <div class="flex items-center gap-2 min-w-0">
                                <CalendarDays class="w-4 h-4 text-indigo-500 shrink-0" />
                                <span class="text-sm font-medium text-gray-900 dark:text-white">Tareas de hoy</span>
                                <span class="text-sm font-bold text-indigo-600 dark:text-indigo-400">{{ todaySummary.today }}</span>
                            </div>
                            <div class="flex items-center gap-1.5 shrink-0">
                                <AlertTriangle class="w-4 h-4" :class="todaySummary.overdue > 0 ? 'text-red-500' : 'text-gray-300 dark:text-gray-600'" />
                                <span class="text-sm font-bold"
                                    :class="todaySummary.overdue > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-400'">
                                    {{ todaySummary.overdue }}
                                </span>
                                <span class="text-[10px] text-gray-400 uppercase tracking-wide">vencidas</span>
                                <ArrowRight class="w-3.5 h-3.5 text-gray-300 dark:text-gray-600" />
                            </div>
                        </button>
                    </div>

                    <div class="overflow-y-auto flex-1">
                        <div v-if="notifications.length === 0" class="px-4 py-8 text-center text-sm text-gray-400">
                            No hay notificaciones
                        </div>
                        <div v-for="notif in notifications" :key="notif.id"
                            class="px-4 py-3 border-b border-gray-100 dark:border-gray-700/50 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition"
                            :class="{ 'bg-indigo-50/50 dark:bg-indigo-900/20': !notif.read_at }">
                            <div class="flex items-start gap-3">
                                <div class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center shrink-0 mt-0.5">
                                    <Check class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm text-gray-900 dark:text-white">{{ notif.data.message }}</p>
                                    <p class="text-xs text-gray-400 mt-1">{{ notif.created_at }}</p>

                                    <div v-if="notif.data.task_share_id && !notif.data._accepted && !notif.data._rejected"
                                        class="flex items-center gap-2 mt-2">
                                        <button @click.stop="acceptShare(notif)"
                                            class="px-3 py-1 text-xs font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg transition">
                                            Aceptar
                                        </button>
                                        <button @click.stop="rejectShare(notif)"
                                            class="px-3 py-1 text-xs font-medium text-white bg-red-500 hover:bg-red-600 rounded-lg transition">
                                            Rechazar
                                        </button>
                                    </div>

                                    <p v-else-if="notif.data._accepted" class="text-xs text-green-600 dark:text-green-400 mt-1 font-medium">
                                        Aceptada
                                    </p>
                                    <p v-else-if="notif.data._rejected" class="text-xs text-red-500 dark:text-red-400 mt-1 font-medium">
                                        Rechazada
                                    </p>
                                </div>
                                <span v-if="!notif.read_at" class="w-2 h-2 bg-indigo-500 rounded-full shrink-0 mt-2"></span>
                            </div>
                        </div>
                    </div>

                    <Link href="/notifications"
                        class="block px-4 py-2.5 text-xs text-center text-indigo-600 hover:bg-gray-50 dark:hover:bg-gray-700/50 border-t border-gray-200 dark:border-gray-700 transition font-medium">
                        Ver historial
                    </Link>
                </div>
            </div>

            <Link
                href="/profile"
                class="flex items-center gap-2 sm:gap-3 hover:bg-gray-100 dark:hover:bg-gray-800 px-2 sm:px-3 py-2 rounded-xl transition"
            >

                <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full bg-indigo-600 flex items-center justify-center text-white font-bold text-sm sm:text-base">
                    {{ user?.name?.charAt(0) }}
                </div>

                <div class="text-left hidden sm:block">
                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate max-w-[120px]">
                        {{ user?.name }}
                    </p>
                    <p class="text-xs text-gray-500 truncate max-w-[120px]">
                        {{ user?.email }}
                    </p>
                </div>

            </Link>

            <button
                @click="logout"
                class="px-3 sm:px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white transition text-sm"
                :title="'Cerrar sesión'"
            >
                <span class="hidden sm:inline">Cerrar sesión</span>
                <LogOut class="w-4 h-4 sm:hidden" />
            </button>

        </div>

        <Modal :show="summaryOpen" @close="summaryOpen = false">
            <div class="space-y-5">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Resumen de tareas</h3>
                    <button @click="summaryOpen = false"
                        class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div v-if="summaryLoading" class="py-8 text-center text-sm text-gray-400 dark:text-gray-500">
                    Cargando tareas...
                </div>

                <template v-else>
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <CalendarDays class="w-4 h-4 text-indigo-500" />
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Tareas de hoy</h4>
                            <span class="text-sm font-bold text-indigo-600 dark:text-indigo-400">{{ todayTaskList.length }}</span>
                        </div>
                        <p v-if="todayTaskList.length === 0" class="text-sm text-gray-400 dark:text-gray-500 italic">
                            Sin tareas para hoy
                        </p>
                        <div v-else class="space-y-1.5">
                            <Link v-for="t in todayTaskList" :key="`today-${t.id}`" :href="`/tasks/${t.id}`"
                                @click="summaryOpen = false"
                                class="flex items-center gap-3 px-3 py-2 rounded-xl border border-gray-100 dark:border-gray-700 hover:border-indigo-300 dark:hover:border-indigo-700 hover:bg-indigo-50/50 dark:hover:bg-indigo-900/20 transition group">
                                <span class="text-xs text-gray-500 dark:text-gray-400 font-mono shrink-0">{{ t.time_range }}</span>
                                <span class="text-sm text-gray-900 dark:text-white truncate flex-1">{{ t.title }}</span>
                                <span class="w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: t.channel?.color || '#6366f1' }"></span>
                                <ArrowRight class="w-4 h-4 text-gray-300 group-hover:text-indigo-500 shrink-0" />
                            </Link>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <AlertTriangle class="w-4 h-4 text-red-500" />
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Tareas vencidas</h4>
                            <span class="text-sm font-bold text-red-600 dark:text-red-400">{{ overdueTaskList.length }}</span>
                        </div>
                        <p v-if="overdueTaskList.length === 0" class="text-sm text-gray-400 dark:text-gray-500 italic">
                            Sin tareas vencidas
                        </p>
                        <div v-else class="space-y-1.5">
                            <Link v-for="t in overdueTaskList" :key="`overdue-${t.id}`" :href="`/tasks/${t.id}`"
                                @click="summaryOpen = false"
                                class="flex items-center gap-3 px-3 py-2 rounded-xl border border-red-100 dark:border-red-900/40 hover:border-red-300 dark:hover:border-red-800 hover:bg-red-50/50 dark:hover:bg-red-900/20 transition group">
                                <span class="text-xs text-gray-500 dark:text-gray-400 font-mono shrink-0">{{ t.time_range }}</span>
                                <span class="text-sm text-gray-900 dark:text-white truncate flex-1">{{ t.title }}</span>
                                <span class="text-[10px] font-semibold text-red-600 dark:text-red-400 shrink-0">
                                    hace {{ t.days_overdue }} {{ t.days_overdue === 1 ? 'día' : 'días' }}
                                </span>
                                <span class="w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: t.channel?.color || '#6366f1' }"></span>
                                <ArrowRight class="w-4 h-4 text-gray-300 group-hover:text-red-500 shrink-0" />
                            </Link>
                        </div>
                    </div>
                </template>

                <button @click="goToPlanning"
                    class="w-full px-3 py-2 text-xs font-medium text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800 rounded-xl hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition">
                    Ver calendario completo
                </button>
            </div>
        </Modal>

    </header>
</template>
