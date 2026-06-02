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
                    Task Reports
                </h1>

                <p class="text-gray-500">
                    Productivity and task analytics
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">

                <StatCard
                    title="Total Tasks"
                    :value="stats.total"
                />

                <StatCard
                    title="Completed"
                    :value="stats.completed"
                />

                <StatCard
                    title="Pending"
                    :value="stats.pending"
                />

                <StatCard
                    title="Overdue"
                    :value="stats.overdue"
                />

            </div>

        </div>

    </AppLayout>
</template>