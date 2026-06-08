<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Iniciar sesión" />

        <h2 class="text-2xl font-bold text-white mb-1">Iniciar sesión</h2>
        <p class="text-sm text-gray-400 mb-6">Accede a tu cuenta de GrowthOS</p>

        <div v-if="status" class="mb-4 text-sm font-medium text-green-400">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <InputLabel for="email" value="Correo electrónico" />
                <TextInput
                    id="email"
                    type="email"
                    class="mt-1.5 block w-full"
                    v-model="form.email"
                    required
                    autofocus
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
                    autocomplete="current-password"
                />
                <InputError class="mt-1.5" :message="form.errors.password" />
            </div>

            <div class="flex items-center">
                <Checkbox name="remember" v-model:checked="form.remember" />
                <span class="ml-2 text-sm text-gray-400">Recordar sesión</span>
            </div>

            <div class="flex items-center justify-between">
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-sm text-indigo-400 hover:text-indigo-300 transition"
                >
                    ¿Olvidaste tu contraseña?
                </Link>

                <PrimaryButton
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Ingresar
                </PrimaryButton>
            </div>

            <p class="text-center text-sm text-gray-500 pt-2">
                ¿No tienes cuenta?
                <Link :href="route('register')" class="text-indigo-400 hover:text-indigo-300 transition font-medium">Regístrate</Link>
            </p>
        </form>
    </GuestLayout>
</template>
