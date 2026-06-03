<script setup>
import { ref, watch } from 'vue'
import { router, Link, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import SearchInput from '@/Components/Forms/SearchInput.vue'
import PrimaryButton from '@/Components/UI/PrimaryButton.vue'
import { Shield, Plus, Pencil, Trash2, Users, KeyRound } from 'lucide-vue-next'

const props = defineProps({
    roles: Object,
    filters: Object,
})

const page = usePage()
const permissions = page.props.auth?.user?.permissions ?? []
const can = (perm) => permissions.includes(perm)

const search = ref(props.filters?.search || '')

watch(search, (value) => {
    router.get('/roles', { search: value }, { preserveState: true, replace: true })
})

const confirmDelete = (role) => {
    if (confirm(`¿Eliminar el rol "${role.name}"? Esta acción no se puede deshacer.`)) {
        router.delete(`/roles/${role.id}`)
    }
}
</script>

<template>
    <AppLayout>
        <div class="space-y-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="p-3 rounded-xl bg-purple-100 dark:bg-purple-900">
                        <Shield class="w-5 h-5 text-purple-600 dark:text-purple-400" />
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Roles</h1>
                        <p class="text-gray-500 dark:text-gray-400 mt-1">{{ roles.total || 0 }} roles en el sistema</p>
                    </div>
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

            <div class="grid gap-4">
                <div v-for="role in roles?.data || []" :key="role.id"
                    class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5 hover:shadow-md transition flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl bg-purple-100 dark:bg-purple-900 flex items-center justify-center flex-shrink-0">
                            <Shield class="w-5 h-5 text-purple-600 dark:text-purple-400" />
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-900 dark:text-white">{{ role.name }}</h3>
                            <div class="flex items-center gap-3 mt-1">
                                <span class="flex items-center gap-1 text-xs text-gray-500">
                                    <KeyRound class="w-3 h-3" />
                                    {{ role.permissions_count }} permiso{{ role.permissions_count !== 1 ? 's' : '' }}
                                </span>
                                <span class="flex items-center gap-1 text-xs text-gray-500">
                                    <Users class="w-3 h-3" />
                                    {{ role.users_count }} usuario{{ role.users_count !== 1 ? 's' : '' }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-1">
                        <Link v-if="can('edit roles')" :href="`/roles/${role.id}/edit`"
                            class="p-2 rounded-lg hover:bg-amber-50 dark:hover:bg-amber-900/20 text-gray-400 hover:text-amber-600 dark:hover:text-amber-400 transition">
                            <Pencil class="w-4 h-4" />
                        </Link>
                        <button v-if="can('delete roles')" @click="confirmDelete(role)"
                            class="p-2 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition">
                            <Trash2 class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <div v-if="!roles?.data?.length"
                    class="text-center py-16 text-gray-400 dark:text-gray-500 bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700">
                    <Shield class="w-10 h-10 mx-auto mb-2 opacity-40" />
                    No se encontraron roles
                </div>
            </div>
        </div>
    </AppLayout>
</template>
