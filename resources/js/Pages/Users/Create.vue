<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import TextInput from '@/Components/Forms/TextInput.vue'
import PrimaryButton from '@/Components/UI/PrimaryButton.vue'
import { useForm, Link } from '@inertiajs/vue3'
import { UserPlus, ArrowLeft } from 'lucide-vue-next'

const props = defineProps({ roles: Array })

const form = useForm({
    name: '',
    email: '',
    password: '',
    role: '',
})

const submit = () => form.post('/users')
</script>

<template>
    <AppLayout>
        <div class="space-y-6">
            <PageHeader title="Nuevo usuario" subtitle="Crear un nuevo usuario en el sistema">
                <Link href="/users" class="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-700">
                    <ArrowLeft class="w-4 h-4" /> Volver
                </Link>
            </PageHeader>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                <form @submit.prevent="submit" class="space-y-5">
                    <TextInput v-model="form.name" label="Nombre" :error="form.errors.name" />
                    <TextInput v-model="form.email" label="Email" type="email" :error="form.errors.email" />
                    <TextInput v-model="form.password" label="Contraseña" type="password" :error="form.errors.password" />
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
                            <UserPlus class="w-4 h-4 mr-1.5" /> Crear usuario
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
