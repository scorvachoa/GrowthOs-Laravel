<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { Menu, LogOut, Sun, Moon, HardDrive, Bell, Check, X } from 'lucide-vue-next'
import { useTheme } from '@/Composables/useTheme'

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
    fetchNotifications()
    notifInterval = setInterval(fetchNotifications, 30000)
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

            <Link v-if="user?.permissions?.includes('view backup')" href="/backup"
                class="p-2 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 transition shrink-0"
                title="Backup">
                <HardDrive class="w-5 h-5 text-gray-500 dark:text-gray-400" />
            </Link>
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

    </header>
</template>
