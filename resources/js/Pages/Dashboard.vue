<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatCard from '@/Components/UI/StatCard.vue'
</script>

<template>
    <AppLayout>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <StatCard
                title="Usuarios"
                :value="10"
            />

            <StatCard
                title="Tareas"
                :value="25"
            />

            <StatCard
                title="Reportes"
                :value="8"
            />
        </div>
    </AppLayout>
</template>