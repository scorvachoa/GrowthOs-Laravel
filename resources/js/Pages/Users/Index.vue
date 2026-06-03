<script setup>
import { ref, watch } from 'vue'
import { router, Link, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import SearchInput from '@/Components/Forms/SearchInput.vue'
import PrimaryButton from '@/Components/UI/PrimaryButton.vue'
import ConfirmDelete from '@/Components/Modals/ConfirmDelete.vue'
import { Users, Plus, Pencil, Trash2, Shield, Mail, CalendarDays } from 'lucide-vue-next'

const page = usePage()
const permissions = page.props.auth?.user?.permissions ?? []
const can = (perm) => permissions.includes(perm)
const isSuperAdmin = page.props.auth?.user?.roles?.includes('Super Admin')

const props = defineProps({
    users: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
})

const search = ref(props.filters?.search || '')
const showDeleteModal = ref(false)
const selectedUser = ref(null)

const openDeleteModal = (user) => {
    selectedUser.value = user
    showDeleteModal.value = true
}

const confirmDelete = () => {
    router.delete(`/users/${selectedUser.value.id}`, {
        onSuccess: () => { showDeleteModal.value = false },
    })
}

watch(search, (value) => {
    router.get('/users', { search: value }, { preserveState: true, replace: true })
})

const initials = (name) => {
    return name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2)
}

const roleColor = (role) => {
    const colors = {
        'Super Admin': 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300',
        'Employee': 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
    }
    return colors[role] || 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300'
}
</script>

<template>
    <AppLayout>
        <div class="space-y-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="p-3 rounded-xl bg-indigo-100 dark:bg-indigo-900">
                        <Users class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Usuarios</h1>
                        <p class="text-gray-500 dark:text-gray-400 mt-1">{{ users.total || 0 }} registrados</p>
                    </div>
                </div>
                <div class="flex gap-3">
                    <SearchInput v-model="search" />
                    <Link v-if="can('create users')" href="/users/create">
                        <PrimaryButton class="inline-flex items-center gap-2">
                            <Plus class="w-4 h-4 mr-1.5" /> Nuevo usuario
                        </PrimaryButton>
                    </Link>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                            <th class="text-left py-3.5 px-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Usuario</th>
                            <th class="text-left py-3.5 px-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider hidden md:table-cell">Email</th>
                            <th class="text-left py-3.5 px-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider hidden lg:table-cell">Rol</th>
                            <th class="text-left py-3.5 px-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider hidden lg:table-cell">Creado</th>
                            <th class="text-right py-3.5 px-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in users?.data || []" :key="user.id"
                            class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                            <td class="py-3.5 px-4">
                                <div class="flex items-center gap-3">
                                    <span class="w-9 h-9 rounded-full bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center text-xs font-bold text-indigo-600 dark:text-indigo-400 flex-shrink-0">
                                        {{ initials(user.name) }}
                                    </span>
                                    <span class="font-medium text-gray-900 dark:text-white text-sm">{{ user.name }}</span>
                                </div>
                            </td>
                            <td class="py-3.5 px-4 text-sm text-gray-500 dark:text-gray-400 hidden md:table-cell">
                                <div class="flex items-center gap-1.5">
                                    <Mail class="w-3.5 h-3.5 text-gray-400" />
                                    {{ user.email }}
                                </div>
                            </td>
                            <td class="py-3.5 px-4 hidden lg:table-cell">
                                <span v-if="user.role" class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full"
                                    :class="roleColor(user.role)">
                                    <Shield class="w-3 h-3" />
                                    {{ user.role }}
                                </span>
                                <span v-else class="text-xs text-gray-400">—</span>
                            </td>
                            <td class="py-3.5 px-4 text-xs text-gray-400 dark:text-gray-500 hidden lg:table-cell">
                                <div class="flex items-center gap-1.5">
                                    <CalendarDays class="w-3.5 h-3.5" />
                                    {{ user.created_at }}
                                </div>
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <Link v-if="can('edit users') && (user.role !== 'Super Admin' || isSuperAdmin)"
                                        :href="`/users/${user.id}/edit`"
                                        class="p-2 rounded-lg hover:bg-amber-50 dark:hover:bg-amber-900/20 text-gray-400 hover:text-amber-600 dark:hover:text-amber-400 transition">
                                        <Pencil class="w-4 h-4" />
                                    </Link>
                                    <button v-if="can('delete users') && (user.role !== 'Super Admin' || isSuperAdmin)"
                                        @click="openDeleteModal(user)"
                                        class="p-2 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition">
                                        <Trash2 class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!users?.data?.length">
                            <td colspan="5" class="text-center py-12 text-gray-400 dark:text-gray-500 text-sm">
                                <Users class="w-10 h-10 mx-auto mb-2 opacity-40" />
                                No se encontraron usuarios
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <ConfirmDelete :show="showDeleteModal" @close="showDeleteModal = false" @confirm="confirmDelete" />
        </div>
    </AppLayout>
</template>
