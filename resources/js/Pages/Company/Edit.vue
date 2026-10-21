<script setup>
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import CompanyForm from '@/Pages/Company/Components/CompanyForm.vue'
import { ArrowLeft } from 'lucide-vue-next'

const props = defineProps({
    company: Object,
})
</script>

<template>
    <AppLayout>
        <div>
            <div class="mb-6">
                <button @click="router.get(`/company/${props.company.id}`)"
                    class="flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 transition">
                    <ArrowLeft class="w-4 h-4" /> Volver a empresa
                </button>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-8">
                <PageHeader title="Editar empresa" :subtitle="props.company?.name" class="mb-8" />

                <CompanyForm :company="props.company" />
            </div>
        </div>
    </AppLayout>
</template>
