<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import TextInput from '@/Components/Forms/TextInput.vue'
import PrimaryButton from '@/Components/UI/PrimaryButton.vue'

import { useForm, Link } from '@inertiajs/vue3'

const props = defineProps({
    roles: Array,
})

const form = useForm({
    name: '',
    email: '',
    password: '',
    role: '',
})

const submit = () => {
    form.post('/users')
}
</script>

<template>
    <AppLayout>
        <div class="max-w-3xl mx-auto">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    Create User
                </h1>

                <Link
                    href="/users"
                    class="text-indigo-600 hover:text-indigo-700"
                >
                    Back
                </Link>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-6">
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
                        label="Password"
                        type="password"
                        :error="form.errors.password"
                    />

                    <div>
                        <label
                            class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Role
                        </label>

                        <select
                            v-model="form.role"
                            class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        >
                            <option value="">
                                Select role
                            </option>

                            <option
                                v-for="role in roles"
                                :key="role"
                                :value="role"
                            >
                                {{ role }}
                            </option>
                        </select>
                    </div>

                    <PrimaryButton :disabled="form.processing">
                        Create User
                    </PrimaryButton>
                </form>
            </div>
        </div>
    </AppLayout>
</template>