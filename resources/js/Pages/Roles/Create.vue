<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import { useForm, Link } from '@inertiajs/vue3'
import RoleForm from './Components/RoleForm.vue'
import { ArrowLeft } from 'lucide-vue-next'

const props = defineProps({ permissions: Array })

const form = useForm({ name: '', permissions: [] })
const submit = () => form.post('/roles')
</script>

<template>
    <AppLayout>
        <div class="space-y-6">
            <PageHeader title="Nuevo rol" subtitle="Crear un nuevo rol con permisos">
                <Link href="/roles" class="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-700">
                    <ArrowLeft class="w-4 h-4" /> Volver
                </Link>
            </PageHeader>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                <RoleForm :form="form" :permissions="permissions" submit-label="Crear rol" @submit="submit" />
            </div>
        </div>
    </AppLayout>
</template>
