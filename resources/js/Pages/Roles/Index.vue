<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'

defineProps({
    roles: Object,
})
</script>

<template>
    <AppLayout>

        <div class="space-y-6">

            <div class="flex items-center justify-between">

                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                        Roles
                    </h1>

                    <p class="text-gray-500">
                        Manage system roles
                    </p>
                </div>

                <a
                    href="/roles/create"
                    class="px-5 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white"
                >
                    Create Role
                </a>

            </div>

            <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 overflow-hidden">

                <table class="w-full">

                    <thead class="bg-gray-50 dark:bg-gray-800">

                        <tr>

                            <th class="text-left p-4">
                                Name
                            </th>

                            <th class="text-left p-4">
                                Actions
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        <tr
                            v-for="role in roles?.data || []"
                            :key="role.id"
                            class="border-t border-gray-100 dark:border-gray-800"
                        >

                            <td class="p-4">
                                {{ role.name }}
                            </td>

                            <td class="p-4 flex gap-3">

                                <a
                                    :href="`/roles/${role.id}/edit`"
                                    class="text-indigo-600"
                                >
                                    Edit
                                </a>

                            </td>

                        </tr>

                    </tbody>

                </table>

            </div>

        </div>

    </AppLayout>
</template>