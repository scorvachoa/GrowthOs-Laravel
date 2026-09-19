<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
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
    current_password: '',
    password: '',
    password_confirmation: '',
})
const submit = () => {
    form.patch('/profile')
}
</script>

<template>
    <AppLayout>

        <div class="">

            <div class="mb-8">

                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                    Mi Perfil
                </h1>

                <p class="text-gray-500 dark:text-gray-400 mt-2">
                    Gestiona la configuración de tu cuenta
                </p>

            </div>

            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6">

                <form
                    @submit.prevent="submit"
                    class="space-y-6"
                >

                    <TextInput
                        v-model="form.name"
                        label="Nombre"
                        :error="form.errors.name"
                    />

                    <TextInput
                        v-model="form.email"
                        label="Correo electrónico"
                        type="email"
                        :error="form.errors.email"
                    />

                    <div class="border-t border-gray-100 dark:border-gray-800 pt-6">
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                            Para cambiar la contraseña, primero ingresa la contraseña actual.
                        </p>

                        <TextInput
                            v-model="form.current_password"
                            label="Contraseña actual"
                            type="password"
                            :error="form.errors.current_password"
                        />

                        <TextInput
                            v-model="form.password"
                            label="Nueva contraseña"
                            type="password"
                            :error="form.errors.password"
                            class="mt-4"
                        />

                        <TextInput
                            v-model="form.password_confirmation"
                            label="Confirmar nueva contraseña"
                            type="password"
                            class="mt-4"
                        />
                    </div>

                    <PrimaryButton
                        :disabled="form.processing"
                    >
                        Guardar cambios
                    </PrimaryButton>

                </form>

            </div>

        </div>

    </AppLayout>
</template>
