<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatCard from '@/Components/UI/StatCard.vue'

defineProps({
    stats: Object,
})
</script>

<template>
    <AppLayout>

        <div class="space-y-8">

            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                    Dashboard
                </h1>

                <p class="text-gray-500 dark:text-gray-400 mt-1">
                    Welcome back to GrowthOS
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">

                <StatCard
                    title="Total Users"
                    :value="stats.total_users"
                />

                <StatCard
                    title="New Users Today"
                    :value="stats.new_users"
                />

            </div>

            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6">

                <h2 class="text-xl font-bold mb-6 text-gray-900 dark:text-white">
                    Recent Users
                </h2>

                <div class="space-y-4">

                    <div
                        v-for="user in stats.recent_users"
                        :key="user.id"
                        class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-4"
                    >

                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">
                                {{ user.name }}
                            </p>

                            <p class="text-sm text-gray-500">
                                {{ user.email }}
                            </p>
                        </div>

                        <div class="text-sm text-gray-400">
                            {{ user.created_at }}
                        </div>

                    </div>

                </div>

            </div>

        </div>

    </AppLayout>
</template>