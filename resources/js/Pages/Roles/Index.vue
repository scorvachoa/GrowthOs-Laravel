<script setup>
import { ref, watch, computed } from 'vue'
import { router, Link, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import SearchInput from '@/Components/Forms/SearchInput.vue'
import PrimaryButton from '@/Components/UI/PrimaryButton.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import ConfirmDeleteModal from '@/Components/Modals/ConfirmDelete.vue'
import { Shield, Plus, Pencil, Trash2, Users, KeyRound, Building2 } from 'lucide-vue-next'

const props = defineProps({
    roles: Object,
    filters: Object,
    companies: Array,
})

const page = usePage()
const user = page.props.auth?.user
const permissions = user?.permissions ?? []
const can = (perm) => permissions.includes(perm)
const isSuperAdmin = computed(() => user?.roles?.includes('Super Admin'))

const search = ref(props.filters?.search || '')

let debounceTimer
watch(search, (value) => {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
        router.get('/roles', { search: value || '' }, { preserveState: true, replace: true })
    }, 400)
})

const deleteTarget = ref(null)
const showDeleteModal = computed(() => deleteTarget.value !== null)
const deleteMessage = computed(() => deleteTarget.value ? `¿Eliminar el rol "${deleteTarget.value.name}"?` : '')

const confirmDelete = (role) => {
    deleteTarget.value = role
}

const executeDelete = () => {
    if (!deleteTarget.value) return
    router.delete(`/roles/${deleteTarget.value.id}`, {
        onSuccess: () => { deleteTarget.value = null },
    })
}
</script>

<template>
    <AppLayout>
        <div class="max-w-7xl mx-auto space-y-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Roles</h1>
                </div>
                <div class="flex gap-3">
                    <SearchInput v-model="search" />
                    <Link v-if="can('create roles')" href="/roles/create">
                        <PrimaryButton class="inline-flex items-center gap-2">
                            <Plus class="w-4 h-4 mr-1.5" /> Nuevo rol
                        </PrimaryButton>
                    </Link>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="overflow-x-auto">
                <table class="w-full text-sm table-fixed">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="text-left px-4 py-4 font-semibold text-gray-500 dark:text-gray-400">Rol</th>
                            <th class="text-left px-4 py-4 font-semibold text-gray-500 dark:text-gray-400 hidden sm:table-cell w-[200px]">Empresa</th>
                            <th class="text-left px-4 py-4 font-semibold text-gray-500 dark:text-gray-400 w-[80px] sm:w-[100px]">Permisos</th>
                            <th class="text-left px-4 py-4 font-semibold text-gray-500 dark:text-gray-400 w-[80px] sm:w-[100px]">Usuarios</th>
                            <th class="text-right px-4 py-4 font-semibold text-gray-500 dark:text-gray-400 w-[80px] sm:w-[100px]">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="role in roles?.data || []" :key="role.id"
                            class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                            <td class="px-4 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-xl bg-purple-100 dark:bg-purple-900 flex items-center justify-center flex-shrink-0">
                                        <Shield class="w-4 h-4 text-purple-600 dark:text-purple-400" />
                                    </div>
                                    <span class="font-medium text-gray-900 dark:text-white text-sm truncate">{{ role.name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-4 text-sm hidden sm:table-cell">
                                <span v-if="role.organization_id" class="inline-flex items-center gap-1.5 text-gray-500 dark:text-gray-400">
                                    <Building2 class="w-3.5 h-3.5 flex-shrink-0" />
                                    <span class="truncate">{{ companies?.find(c => c.id === role.organization_id)?.name || '—' }}</span>
                                </span>
                                <span v-else class="inline-flex items-center gap-1 text-xs font-semibold text-purple-500">
                                    <Shield class="w-3 h-3" />
                                    Global
                                </span>
                            </td>
                            <td class="px-4 py-4">
                                <span class="inline-flex items-center gap-1 text-[10px] sm:text-xs text-gray-500">
                                    <KeyRound class="w-3 h-3 sm:w-3.5 sm:h-3.5" />
                                    {{ role.permissions_count }}
                                </span>
                            </td>
                            <td class="px-4 py-4">
                                <span class="inline-flex items-center gap-1 text-[10px] sm:text-xs text-gray-500">
                                    <Users class="w-3 h-3 sm:w-3.5 sm:h-3.5" />
                                    {{ role.users_count }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-right">
                                <div class="flex items-center justify-end gap-0 sm:gap-1">
                                    <Link v-if="can('edit roles')" :href="`/roles/${role.id}/edit`"
                                        class="p-1.5 sm:p-2 rounded-lg hover:bg-amber-50 dark:hover:bg-amber-900/20 text-gray-400 hover:text-amber-600 dark:hover:text-amber-400 transition">
                                        <Pencil class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
                                    </Link>
                                    <button v-if="can('delete roles')" @click="confirmDelete(role)"
                                        class="p-1.5 sm:p-2 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition">
                                        <Trash2 class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!roles?.data?.length">
                            <td colspan="5" class="text-center py-12 text-gray-400 dark:text-gray-500 text-sm">
                                <Shield class="w-10 h-10 mx-auto mb-2 opacity-40" />
                                No se encontraron roles
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </div>

            <Pagination v-if="roles?.links" :links="roles.links" />
        </div>

        <ConfirmDeleteModal
            :show="showDeleteModal"
            title="Eliminar rol"
            :message="deleteMessage"
            @close="deleteTarget = null"
            @confirm="executeDelete"
        />
    </AppLayout>
</template>
