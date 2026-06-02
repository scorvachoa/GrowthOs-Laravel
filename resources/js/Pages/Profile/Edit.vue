<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import TextInput from '@/Components/Forms/TextInput.vue'
import PrimaryButton from '@/Components/UI/PrimaryButton.vue'

import { Head } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
    user: Object,
})

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    password: '',
})
const submit = () => {
    form.patch('/profile')
}
</script>

<template>
    <AppLayout>

        <div class="max-w-3xl">

            <div class="mb-8">

                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                    My Profile
                </h1>

                <p class="text-gray-500 mt-2">
                    Manage your account settings
                </p>

            </div>

            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6">

                <form
                    @submit.prevent="submit"
                    class="space-y-6"
                >

                    <TextInput
                        v-model="form.name"
                        label="Name"
                        :error="form.errors.name"
                    />

                    <TextInput
                        v-model="form.email"
                        label="Email"
                        type="email"
                        :error="form.errors.email"
                    />

                    <TextInput
                        v-model="form.password"
                        label="New Password"
                        type="password"
                        :error="form.errors.password"
                    />

                    <PrimaryButton
                        :disabled="form.processing"
                    >
                        Save Changes
                    </PrimaryButton>

                </form>

            </div>

        </div>

    </AppLayout>
</template>
