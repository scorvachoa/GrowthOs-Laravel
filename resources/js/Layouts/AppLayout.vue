<script setup>
import { ref, provide } from 'vue'
import Sidebar from '@/Components/Navigation/Sidebar.vue'
import FlashMessage from '@/Components/UI/FlashMessage.vue'
import Topbar from '@/Components/Navigation/Topbar.vue'

const sidebarCollapsed = ref(localStorage.getItem('sidebar_collapsed') === 'true')

function toggleSidebar() {
    sidebarCollapsed.value = !sidebarCollapsed.value
    localStorage.setItem('sidebar_collapsed', sidebarCollapsed.value)
}

provide('sidebarCollapsed', sidebarCollapsed)
provide('toggleSidebar', toggleSidebar)
</script>

<template>
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
        <Sidebar />

        <div class="flex flex-col min-h-screen transition-all duration-300"
            :class="sidebarCollapsed ? 'ml-16' : 'ml-64'">
            <Topbar />

            <main class="p-6 flex-1 flex flex-col min-h-0">
                <FlashMessage />
                <slot />
            </main>
        </div>
    </div>
</template>