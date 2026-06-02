<script setup>
import { ref, watch } from 'vue'
import { router, Link } from '@inertiajs/vue3'

import AppLayout from '@/Layouts/AppLayout.vue'
import SearchInput from '@/Components/Forms/SearchInput.vue'
import PrimaryButton from '@/Components/UI/PrimaryButton.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import ConfirmDelete from '@/Components/Modals/ConfirmDelete.vue'

const props = defineProps({

    users: {
        type: Object,
        default: () => ({
            data: [],
            links: [],
        }),
    },

    filters: {
        type: Object,
        default: () => ({}),
    },

})

const search = ref(
    props.filters?.search || ''
)

const showDeleteModal = ref(false)

const selectedUser = ref(null)

const openDeleteModal = (user) => {
    selectedUser.value = user
    showDeleteModal.value = true
}

const confirmDelete = () => {
    router.delete(`/users/${selectedUser.value.id}`, {
        onSuccess: () => {
            showDeleteModal.value = false
        }
    })
}

watch(search, (value) => {
    router.get(
        '/users',
        { search: value },
        {
            preserveState: true,
            replace: true,
        }
    )
})
</script>

<template>
    <AppLayout>
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-6">

            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">

                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    Users
                </h1>

                <div class="flex gap-3">
                    <SearchInput v-model="search" />

                    <Link href="/users/create">
                        <PrimaryButton>
                            Create User
                        </PrimaryButton>
                    </Link>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="text-left py-4">Name</th>
                            <th class="text-left py-4">Email</th>
                            <th class="text-right py-4">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="user in users?.data || []"
                            :key="user.id"
                            class="border-b border-gray-100 dark:border-gray-700"
                        >
                            <td class="py-4">
                                {{ user.name }}
                            </td>

                            <td class="py-4">
                                {{ user.email }}
                            </td>

                            <td class="py-4">
                                <div class="flex justify-end gap-2">

                                    <Link
                                        :href="`/users/${user.id}/edit`"
                                        class="px-4 py-2 rounded-lg bg-amber-500 hover:bg-amber-600 text-white transition"
                                    >
                                        Edit
                                    </Link>

                                    <button
                                        @click="openDeleteModal(user)"
                                        class="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white transition"
                                    >
                                        Delete
                                    </button>

                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <!-- <Pagination :links="users?.links || []" /> -->
            </div>
            <ConfirmDelete
                :show="showDeleteModal"
                @close="showDeleteModal = false"
                @confirm="confirmDelete"
            />

        </div>
    </AppLayout>
</template>