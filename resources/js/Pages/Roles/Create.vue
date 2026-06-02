<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'

import { useForm } from '@inertiajs/vue3'

import RoleForm
from './Components/RoleForm.vue'

const props = defineProps({
    permissions: Array,
})

const form = useForm({
    name: '',
    permissions: [],
})

const submit = () => {
    form.post('/roles')
}
</script>

<template>
    <AppLayout>

        <div class="max-w-4xl space-y-8">

            <div>

                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                    Create Role
                </h1>

                <p class="text-gray-500 mt-2">
                    Create a new system role
                </p>

            </div>

            <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 p-6">

                <RoleForm
                    :form="form"
                    :permissions="permissions"
                    submit-label="Create Role"
                    @submit="submit"
                />

            </div>

        </div>

    </AppLayout>
</template>