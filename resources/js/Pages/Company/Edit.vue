<script setup>
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import CompanyForm from '@/Pages/Company/Components/CompanyForm.vue'
import { Building2, ArrowLeft } from 'lucide-vue-next'

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
                <div class="flex items-center gap-3 mb-8">
                    <div class="p-3 rounded-xl bg-indigo-100 dark:bg-indigo-900">
                        <Building2 class="w-6 h-6 text-indigo-600 dark:text-indigo-400" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Editar empresa</h1>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ props.company?.name }}</p>
                    </div>
                </div>

                <CompanyForm :company="props.company" />
            </div>
        </div>
    </AppLayout>
</template>
