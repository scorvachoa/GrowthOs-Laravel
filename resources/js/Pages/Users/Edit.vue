<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import TextInput from '@/Components/Forms/TextInput.vue'
import PrimaryButton from '@/Components/UI/PrimaryButton.vue'
import { useForm, Link } from '@inertiajs/vue3'
import { Pencil, ArrowLeft } from 'lucide-vue-next'

const props = defineProps({ user: Object, roles: Array })

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    password: '',
    role: props.user.role || '',
})

const submit = () => form.put(route('users.update', props.user.id))
</script>

<template>
    <AppLayout>
        <div class="space-y-6">
            <PageHeader title="Editar usuario" :subtitle="props.user.email">
                <Link href="/users" class="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-700">
                    <ArrowLeft class="w-4 h-4" /> Volver
                </Link>
            </PageHeader>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                <form @submit.prevent="submit" class="space-y-5">
                    <TextInput v-model="form.name" label="Nombre" :error="form.errors.name" />
                    <TextInput v-model="form.email" label="Email" type="email" :error="form.errors.email" />
                    <TextInput v-model="form.password" label="Nueva contraseña (dejar vacío para mantener)" type="password" :error="form.errors.password" />
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Rol</label>
                        <select v-model="form.role"
                            class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">Seleccionar rol</option>
                            <option v-for="role in roles" :key="role" :value="role">{{ role }}</option>
                        </select>
                    </div>
                    <div class="flex justify-end pt-2">
                        <PrimaryButton :disabled="form.processing">
                            <Pencil class="w-4 h-4 mr-1.5" /> Actualizar usuario
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
