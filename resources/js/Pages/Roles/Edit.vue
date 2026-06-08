<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, Link } from '@inertiajs/vue3'
import RoleForm from './Components/RoleForm.vue'
import { Pencil, ArrowLeft, Users, ShieldCheck, CalendarDays, Building2 } from 'lucide-vue-next'

const props = defineProps({
    role: Object,
    permissions: Array,
    selectedPermissions: Array,
    roleUsers: Array,
    organization: Object,
})

const form = useForm({
    name: props.role.name,
    permissions: props.selectedPermissions || [],
})

const submit = () => form.put(route('roles.update', props.role.id))
</script>

<template>
    <AppLayout>
        <div class="max-w-4xl mx-auto space-y-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="p-3 rounded-xl bg-amber-100 dark:bg-amber-900">
                        <Pencil class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Editar rol</h1>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ props.role.name }}</p>
                    </div>
                </div>
                <Link href="/roles" class="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-700">
                    <ArrowLeft class="w-4 h-4" /> Volver
                </Link>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 flex items-center gap-3">
                    <div class="p-2.5 rounded-lg bg-indigo-100 dark:bg-indigo-900">
                        <ShieldCheck class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Permisos</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-white">{{ selectedPermissions?.length || 0 }} / {{ permissions?.length || 0 }}</p>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 flex items-center gap-3">
                    <div class="p-2.5 rounded-lg bg-emerald-100 dark:bg-emerald-900">
                        <Users class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Usuarios</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-white">{{ role.users_count || 0 }}</p>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 flex items-center gap-3">
                    <div class="p-2.5 rounded-lg bg-amber-100 dark:bg-amber-900">
                        <CalendarDays class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Creado</p>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ role.created_at ? new Date(role.created_at).toLocaleDateString() : '-' }}</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <div class="xl:col-span-2 bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                    <RoleForm :form="form" :permissions="permissions" submit-label="Actualizar rol" @submit="submit" />
                </div>

                <div class="space-y-4">
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
                        <div class="flex items-center gap-2 mb-4">
                            <Users class="w-4 h-4 text-gray-500 dark:text-gray-400" />
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Usuarios con este rol</h3>
                            <span class="text-xs text-gray-400">({{ role.users_count || 0 }})</span>
                        </div>
                        <div v-if="roleUsers?.length" class="space-y-2">
                            <Link v-for="u in roleUsers" :key="u.id"
                                :href="`/users/${u.id}/edit`"
                                class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700/50 transition group">
                                <span class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center text-xs font-bold text-indigo-600 dark:text-indigo-400 flex-shrink-0">
                                    {{ u.name?.charAt(0) }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ u.name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ u.email }}</p>
                                </div>
                            </Link>
                        </div>
                        <p v-else class="text-sm text-gray-400 dark:text-gray-500 text-center py-4">Ningún usuario tiene este rol</p>
                        <Link v-if="role.users_count > 5" href="/users"
                            class="block text-center text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 pt-3 mt-2 border-t border-gray-100 dark:border-gray-700">
                            Ver todos los usuarios
                        </Link>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
                        <div class="flex items-center gap-2 mb-3">
                            <Building2 class="w-4 h-4 text-gray-500 dark:text-gray-400" />
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Empresa</h3>
                        </div>
                        <p class="text-sm text-gray-900 dark:text-white">{{ organization?.name || 'Global (Super Admin)' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
