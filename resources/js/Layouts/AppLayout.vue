<script setup>
import Sidebar from '@/Components/Layout/Sidebar.vue'
import Topbar from '@/Components/Layout/Topbar.vue'
import FlashMessage from '@/Components/UI/FlashMessage.vue'
</script>

<template>
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
        <div class="flex">
            <Sidebar />

            <div class="flex-1 flex flex-col min-h-screen">
                <Topbar />

                <main class="p-6">
                    <FlashMessage />
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>