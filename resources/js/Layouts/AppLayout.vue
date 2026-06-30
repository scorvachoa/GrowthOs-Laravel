<script setup>
import { ref, computed, provide, onUnmounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import Sidebar from '@/Components/Navigation/Sidebar.vue'
import FlashMessage from '@/Components/UI/FlashMessage.vue'
import Topbar from '@/Components/Navigation/Topbar.vue'
import { useTheme } from '@/Composables/useTheme'
import { Building2, ArrowRight } from 'lucide-vue-next'

const page = usePage()

const { isDark, toggleDark } = useTheme()
provide('isDark', isDark)
provide('toggleDark', toggleDark)

const sidebarCollapsed = ref(localStorage.getItem('sidebar_collapsed') === 'true')
const mobileSidebarOpen = ref(false)

const user = computed(() => page.props.auth?.user)
const isSuperAdmin = computed(() => user.value?.roles?.includes('Super Admin'))
const companies = computed(() => user.value?.companies ?? [])
const hasActiveCompany = computed(() => !!user.value?.active_company)

const showCompanyPicker = computed(() =>
    isSuperAdmin.value && companies.value.length > 0 && !hasActiveCompany.value
)

function selectCompany(companyId) {
    router.post('/company/switch', { company_id: companyId }, {
        preserveScroll: false,
        preserveState: false,
    })
}

function toggleSidebar() {
    sidebarCollapsed.value = !sidebarCollapsed.value
    localStorage.setItem('sidebar_collapsed', sidebarCollapsed.value)
}

function toggleMobileSidebar() {
    mobileSidebarOpen.value = !mobileSidebarOpen.value
    if (mobileSidebarOpen.value) {
        document.body.style.overflow = 'hidden'
    } else {
        document.body.style.overflow = ''
    }
}

function closeMobileSidebar() {
    mobileSidebarOpen.value = false
    document.body.style.overflow = ''
}

onUnmounted(() => {
    document.body.style.overflow = ''
})

provide('sidebarCollapsed', sidebarCollapsed)
provide('toggleSidebar', toggleSidebar)
provide('mobileSidebarOpen', mobileSidebarOpen)
provide('toggleMobileSidebar', toggleMobileSidebar)
provide('closeMobileSidebar', closeMobileSidebar)
</script>

<template>
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900 overflow-x-hidden">
        <Sidebar />

        <div class="flex flex-col min-h-screen transition-all duration-300"
            :class="sidebarCollapsed ? 'lg:ml-16' : 'lg:ml-64'">
            <Topbar @toggle-mobile-sidebar="toggleMobileSidebar"
                :sidebar-collapsed="sidebarCollapsed" />

            <main class="p-4 sm:p-6 flex-1 flex flex-col min-h-0 pt-[88px] sm:pt-[112px]">
                <FlashMessage />
                <slot />
            </main>
        </div>

        <Teleport to="body">
            <Transition name="fade">
                <div v-if="mobileSidebarOpen"
                    class="fixed inset-0 z-40 bg-black/50 lg:hidden"
                    @click="closeMobileSidebar"></div>
            </Transition>
        </Teleport>

        <Teleport to="body">
            <Transition name="fade">
                <div v-if="showCompanyPicker"
                    class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
                    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl border border-gray-200 dark:border-gray-700 w-full max-w-lg overflow-hidden">
                        <div class="p-6 text-center border-b border-gray-200 dark:border-gray-700">
                            <div class="w-14 h-14 rounded-xl bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center mx-auto mb-3">
                                <Building2 class="w-7 h-7 text-indigo-600 dark:text-indigo-400" />
                            </div>
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white">Selecciona una empresa</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                Elige con qué empresa deseas trabajar en esta sesión
                            </p>
                        </div>
                        <div class="p-4 space-y-2 max-h-80 overflow-y-auto">
                            <button v-for="company in companies" :key="company.id"
                                @click="selectCompany(company.id)"
                                class="w-full flex items-center gap-4 p-4 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-indigo-300 dark:hover:border-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition group text-left">
                                <div class="w-10 h-10 rounded-lg flex items-center justify-center text-white text-sm font-bold shrink-0"
                                    :style="{ backgroundColor: company.primary_color || '#4f46e5' }">
                                    {{ company.name?.charAt(0) || '?' }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ company.name }}</p>
                                </div>
                                <ArrowRight class="w-5 h-5 text-gray-300 dark:text-gray-600 group-hover:text-indigo-500 transition shrink-0" />
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active {
    transition: opacity 0.25s ease;
}
.fade-enter-from, .fade-leave-to {
    opacity: 0;
}
</style>