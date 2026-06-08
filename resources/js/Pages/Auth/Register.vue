<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    invite_code: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Registrarse" />

        <h2 class="text-2xl font-bold text-white mb-1">Crear cuenta</h2>
        <p class="text-sm text-gray-400 mb-6">Únete a GrowthOS con tu código de invitación</p>

        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <InputLabel for="invite_code" value="Código de invitación" />
                <TextInput
                    id="invite_code"
                    type="text"
                    class="mt-1.5 block w-full"
                    v-model="form.invite_code"
                    required
                    autofocus
                    autocomplete="off"
                />
                <p class="mt-1 text-xs text-gray-500">Solicita el código a tu administrador</p>
                <InputError class="mt-1.5" :message="form.errors.invite_code" />
            </div>

            <div>
                <InputLabel for="name" value="Nombre completo" />
                <TextInput
                    id="name"
                    type="text"
                    class="mt-1.5 block w-full"
                    v-model="form.name"
                    required
                    autocomplete="name"
                />
                <InputError class="mt-1.5" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" value="Correo electrónico" />
                <TextInput
                    id="email"
                    type="email"
                    class="mt-1.5 block w-full"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />
                <InputError class="mt-1.5" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="password" value="Contraseña" />
                <TextInput
                    id="password"
                    type="password"
                    class="mt-1.5 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                />
                <InputError class="mt-1.5" :message="form.errors.password" />
            </div>

            <div>
                <InputLabel
                    for="password_confirmation"
                    value="Confirmar contraseña"
                />
                <TextInput
                    id="password_confirmation"
                    type="password"
                    class="mt-1.5 block w-full"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                />
                <InputError
                    class="mt-1.5"
                    :message="form.errors.password_confirmation"
                />
            </div>

            <div class="flex items-center justify-between pt-1">
                <Link
                    :href="route('login')"
                    class="text-sm text-indigo-400 hover:text-indigo-300 transition"
                >
                    ¿Ya tienes cuenta? Inicia sesión
                </Link>

                <PrimaryButton
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Registrarse
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
