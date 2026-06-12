<script setup>
import { computed, ref, onUnmounted } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { Menu, LogOut } from 'lucide-vue-next'

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

function onDocumentClick(e) {
    if (switcherOpen.value && switcherRef.value && !switcherRef.value.contains(e.target)) {
        switcherOpen.value = false
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

if (typeof document !== 'undefined') {
    document.addEventListener('click', onDocumentClick)
    onUnmounted(() => document.removeEventListener('click', onDocumentClick))
}
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
                :title="'Cerrar sesion'"
            >
                <span class="hidden sm:inline">Cerrar sesión</span>
                <LogOut class="w-4 h-4 sm:hidden" />
            </button>

        </div>

    </header>
</template>
