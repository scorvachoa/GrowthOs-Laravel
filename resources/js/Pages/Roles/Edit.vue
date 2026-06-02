<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'

import { useForm } from '@inertiajs/vue3'

import RoleForm
from './Components/RoleForm.vue'

const props = defineProps({
    role: Object,
    permissions: Array,
    selectedPermissions: Array,
})

const form = useForm({
    name: props.role.name,

    permissions: props.selectedPermissions || [],
})

const submit = () => {
    form.put(route('roles.update', props.role.id))
}
</script>

<template>
    <AppLayout>

        <div class="max-w-4xl space-y-8">

            <div>

                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                    Edit Role
                </h1>

                <p class="text-gray-500 mt-2">
                    Update role permissions
                </p>

            </div>

            <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 p-6">

                <RoleForm
                    :form="form"
                    :permissions="permissions"
                    submit-label="Update Role"
                    @submit="submit"
                />

            </div>

        </div>

    </AppLayout>
</template>