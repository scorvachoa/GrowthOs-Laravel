<script setup>
import { ref, provide, onUnmounted } from 'vue'
import Sidebar from '@/Components/Navigation/Sidebar.vue'
import FlashMessage from '@/Components/UI/FlashMessage.vue'
import Topbar from '@/Components/Navigation/Topbar.vue'

const sidebarCollapsed = ref(localStorage.getItem('sidebar_collapsed') === 'true')
const mobileSidebarOpen = ref(false)

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