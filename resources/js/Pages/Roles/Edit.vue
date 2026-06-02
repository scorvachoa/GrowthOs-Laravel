<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, Link } from '@inertiajs/vue3'
import RoleForm from './Components/RoleForm.vue'
import { Pencil, ArrowLeft } from 'lucide-vue-next'

const props = defineProps({
    role: Object,
    permissions: Array,
    selectedPermissions: Array,
})

const form = useForm({
    name: props.role.name,
    permissions: props.selectedPermissions || [],
})

const submit = () => form.put(route('roles.update', props.role.id))
</script>

<template>
    <AppLayout>
        <div class="max-w-4xl mx-auto space-y-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="p-3 rounded-xl bg-amber-100 dark:bg-amber-900">
                        <Pencil class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Editar rol</h1>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ props.role.name }}</p>
                    </div>
                </div>
                <Link href="/roles" class="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-700">
                    <ArrowLeft class="w-4 h-4" /> Volver
                </Link>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                <RoleForm :form="form" :permissions="permissions" submit-label="Actualizar rol" @submit="submit" />
            </div>
        </div>
    </AppLayout>
</template>
