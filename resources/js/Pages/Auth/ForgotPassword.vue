<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Recuperar contraseña" />

        <h2 class="text-2xl font-bold text-white mb-1">Recuperar contraseña</h2>
        <p class="text-sm text-gray-400 mb-6">
            Ingresa tu correo electrónico y te enviaremos un enlace para restablecer tu contraseña.
        </p>

        <div
            v-if="status"
            class="mb-4 text-sm font-medium text-green-400"
        >
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

            <div class="flex items-center justify-between">
                <Link
                    :href="route('login')"
                    class="text-sm text-indigo-400 hover:text-indigo-300 transition"
                >
                    Volver al inicio de sesión
                </Link>

                <PrimaryButton
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Enviar enlace
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
