<script setup>
import { ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PrimaryButton from '@/Components/UI/PrimaryButton.vue'
import ConfirmDeleteModal from '@/Components/Modals/ConfirmDelete.vue'
import { Building2, Youtube, Plus, Trash2, Pencil, X, Check, ExternalLink, Upload, Key, Copy, Users, Eye, ArrowLeft } from 'lucide-vue-next'

const page = usePage()
const permissions = page.props.auth?.user?.permissions ?? []
const can = (perm) => permissions.includes(perm)
const user = page.props.auth?.user

const props = defineProps({
    companies: Array,
    isSuperAdmin: Boolean,
    organization: Object,
    channels: Array,
})

const orgForm = ref({
    name: props.organization?.name || '',
    primary_color: props.organization?.primary_color || '#4f46e5',
    logo: null,
    remove_logo: false,
})

const newChannel = ref({
    name: '',
    color: '#4f46e5',
    youtube_channel_id: '',
    channel_url: '',
})

const editingId = ref(null)
const editForm = ref({})
const logoPreview = ref(props.organization?.logo_url || null)
const selectedOrgId = ref(null)
const deleteTarget = ref(null)
const showDeleteModal = computed(() => deleteTarget.value !== null)
const copied = ref(false)
const deleteMessage = computed(() => {
    const t = deleteTarget.value
    if (!t) return ''
    const label = t.type === 'channel' ? 'el canal' : 'la empresa'
    return `¿Eliminar ${label} "${t.data?.name}"?`
})

const selectedOrg = computed(() => {
    if (!props.companies) return null
    return props.companies.find(o => o.id === selectedOrgId.value) || null
})

function handleLogoUpload(e) {
    const file = e.target.files?.[0]
    if (!file) return
    orgForm.value.logo = file
    orgForm.value.remove_logo = false
    const reader = new FileReader()
    reader.onload = (ev) => { logoPreview.value = ev.target?.result }
    reader.readAsDataURL(file)
}

function removeLogo() {
    orgForm.value.remove_logo = true
    orgForm.value.logo = null
    logoPreview.value = null
}

function submitCompany() {
    const form = new FormData()
    form.append('name', orgForm.value.name)
    form.append('primary_color', orgForm.value.primary_color)
    if (orgForm.value.logo) {
        form.append('logo', orgForm.value.logo)
    }
    if (orgForm.value.remove_logo) {
        form.append('remove_logo', '1')
    }
    const id = props.organization?.id
    router.put(`/company/${id}`, form, {
        preserveScroll: true,
        onSuccess: () => {
            orgForm.value.remove_logo = false
        },
    })
}

function addChannel() {
    router.post('/company/channels', newChannel.value, {
        preserveScroll: true,
        onSuccess: () => {
            newChannel.value = { name: '', color: '#4f46e5', youtube_channel_id: '', channel_url: '' }
        },
    })
}

function startEdit(channel) {
    editingId.value = channel.id
    editForm.value = { ...channel }
}

function cancelEdit() {
    editingId.value = null
    editForm.value = {}
}

function saveEdit() {
    router.post(`/company/channels/${editingId.value}`, editForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            editingId.value = null
            editForm.value = {}
        },
    })
}

function deleteChannel(channel) {
    deleteTarget.value = { type: 'channel', data: channel }
}

function generateInvite() {
    router.post('/company/generate-invite', { organization_id: props.organization?.id }, {
        preserveScroll: true,
    })
}

function copyInvite() {
    if (props.organization?.invite_code) {
        navigator.clipboard?.writeText(props.organization.invite_code)
        copied.value = true
        setTimeout(() => { copied.value = false }, 1500)
    }
}

function viewCompany(org) {
    router.get(`/company/${org.id}`)
}

function deleteCompany(org) {
    deleteTarget.value = { type: 'company', data: org }
}

function executeDelete() {
    const t = deleteTarget.value
    if (!t) return
    if (t.type === 'channel') {
        router.post(`/company/channels/${t.data.id}/delete`, {
            preserveScroll: true,
            onSuccess: () => { deleteTarget.value = null },
        })
    } else if (t.type === 'company') {
        router.delete(`/company/${t.data.id}`, {
            preserveScroll: true,
            onSuccess: () => { deleteTarget.value = null },
        })
    }
}

function createCompany() {
    router.get('/company/create')
}
</script>

<template>
    <AppLayout>
        <div class="max-w-7xl mx-auto space-y-8">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Empresas</h1>
                    <p class="text-gray-500 dark:text-gray-400 mt-1">Administra empresas y canales</p>
                </div>
                <div v-if="isSuperAdmin && companies && can('create empresa')" class="flex gap-2 shrink-0">
                    <PrimaryButton @click="createCompany">
                        <Plus class="w-4 h-4" /> Nueva empresa
                    </PrimaryButton>
                </div>
            </div>

            <!-- Super Admin: company list -->
            <div v-if="isSuperAdmin && companies" class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm table-fixed">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="text-left px-4 py-4 font-semibold text-gray-500 dark:text-gray-400">Empresa</th>
                                <th class="text-left px-4 py-4 font-semibold text-gray-500 dark:text-gray-400 w-[80px] sm:w-[100px]">Usuarios</th>
                                <th class="text-left px-4 py-4 font-semibold text-gray-500 dark:text-gray-400 hidden lg:table-cell w-[170px] whitespace-nowrap">Código admin</th>
                                <th class="text-left px-4 py-4 font-semibold text-gray-500 dark:text-gray-400 hidden lg:table-cell w-[170px] whitespace-nowrap">Código empleados</th>
                                <th class="text-left px-4 py-4 font-semibold text-gray-500 dark:text-gray-400 hidden sm:table-cell w-[120px]">Creada</th>
                                <th class="text-right px-4 py-4 font-semibold text-gray-500 dark:text-gray-400 w-[90px] sm:w-[130px]">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="org in companies" :key="org.id"
                                class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
                                            :style="{ backgroundColor: org.primary_color || '#4f46e5' }">
                                            {{ org.name.charAt(0).toUpperCase() }}
                                        </div>
                                        <div>
                                            <span class="font-medium text-gray-900 dark:text-white text-sm truncate max-w-[120px] sm:max-w-none">{{ org.name }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex items-center gap-1 text-xs text-gray-500">
                                        <Users class="w-3 h-3 sm:w-3.5 sm:h-3.5" /> {{ org.users_count }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 hidden lg:table-cell">
                                    <code v-if="org.admin_invite_code" class="text-xs font-mono px-2 py-1 rounded bg-gray-100 dark:bg-gray-900 text-amber-600 dark:text-amber-400">{{ org.admin_invite_code }}</code>
                                    <span v-else class="text-xs text-gray-400">Consumido</span>
                                </td>
                                <td class="px-4 py-4 hidden lg:table-cell">
                                    <code v-if="org.invite_code" class="text-xs font-mono px-2 py-1 rounded bg-gray-100 dark:bg-gray-900 text-green-600 dark:text-green-400">{{ org.invite_code }}</code>
                                    <span v-else class="text-xs text-gray-400">—</span>
                                </td>
                                <td class="px-4 py-4 text-gray-500 text-xs hidden sm:table-cell">{{ org.created_at?.substring(0, 10) }}</td>
                                <td class="px-4 py-4 text-right">
                                    <div class="flex items-center justify-end gap-0 sm:gap-1">
                                        <button @click="viewCompany(org)"
                                            class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 hover:text-indigo-600 dark:hover:text-indigo-400 transition"
                                            title="Ver empresa">
                                            <Eye class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
                                        </button>
                                        <button v-if="can('edit empresa')" @click="router.get(`/company/${org.id}/edit`)"
                                            class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 hover:text-amber-600 dark:hover:text-amber-400 transition"
                                            title="Editar">
                                            <Pencil class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
                                        </button>
                                        <button v-if="can('delete empresa')" @click="deleteCompany(org)"
                                            class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 hover:text-red-600 dark:hover:text-red-400 transition"
                                            title="Eliminar">
                                            <Trash2 class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="companies.length === 0">
                                <td colspan="6" class="px-4 py-16 text-center text-gray-400 dark:text-gray-500 italic">
                                    No hay empresas registradas
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Company detail / settings -->
            <div v-if="organization" class="space-y-6">
                <div v-if="isSuperAdmin" class="flex items-center gap-2 text-sm text-gray-500">
                    <button @click="router.get('/company')" class="hover:text-gray-700 dark:hover:text-gray-300 flex items-center gap-1">
                        <ArrowLeft class="w-4 h-4" /> Volver a empresas
                    </button>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Company info form -->
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-6">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-3 rounded-xl bg-indigo-100 dark:bg-indigo-900">
                                <Building2 class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                            </div>
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ isSuperAdmin ? organization.name : 'Empresa' }}</h2>
                        </div>

                        <form @submit.prevent="submitCompany" class="space-y-5">
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Nombre de la empresa</label>
                                <input v-model="orgForm.name" type="text" required
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                            </div>

                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Logo de empresa</label>
                                <div v-if="logoPreview" class="flex items-center gap-4 mb-3 p-3 rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700">
                                    <img :src="logoPreview" alt="Logo" class="w-12 h-12 object-contain rounded-lg bg-white" />
                                    <span class="text-sm text-gray-500">Logo actual</span>
                                    <button type="button" @click="removeLogo" class="ml-auto text-xs px-2 py-1 rounded-lg bg-red-600 text-white">Eliminar</button>
                                </div>
                                <label class="flex items-center gap-2 px-4 py-3 rounded-xl border border-dashed border-gray-300 dark:border-gray-700 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                    <Upload class="w-4 h-4 text-gray-500" />
                                    <span class="text-sm text-gray-500">Seleccionar imagen (PNG, JPG, WEBP, max 2MB)</span>
                                    <input type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="handleLogoUpload" />
                                </label>
                            </div>

                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Color principal</label>
                                <div class="flex items-center gap-3">
                                    <div class="relative w-10 h-10">
                                        <div class="w-10 h-10 rounded-full bg-white border-2 border-gray-300 dark:border-gray-700 flex items-center justify-center">
                                            <div class="w-7 h-7 rounded-full"
                                                :style="{ backgroundColor: orgForm.primary_color }"></div>
                                        </div>
                                        <input type="color" v-model="orgForm.primary_color"
                                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" />
                                    </div>
                                    <span class="text-sm text-gray-500">{{ orgForm.primary_color }}</span>
                                </div>
                            </div>

                            <PrimaryButton v-if="can('edit empresa')" type="submit">Guardar empresa</PrimaryButton>
                        </form>
                    </div>

                    <!-- Add channel -->
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-6">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-3 rounded-xl bg-red-100 dark:bg-red-900">
                                <Youtube class="w-5 h-5 text-red-600 dark:text-red-400" />
                            </div>
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white">Agregar canal</h2>
                        </div>

                        <form @submit.prevent="addChannel" class="space-y-4">
                            <div>
                                <label class="block mb-1 text-xs font-medium text-gray-500">Nombre del canal</label>
                                <input v-model="newChannel.name" type="text" placeholder="Canal" required
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            </div>
                            <div>
                                <label class="block mb-1 text-xs font-medium text-gray-500">Color</label>
                                <div class="flex items-center gap-2">
                                    <div class="relative w-10 h-10 flex-shrink-0">
                                        <div class="w-10 h-10 rounded-full bg-white border-2 border-gray-300 dark:border-gray-700 flex items-center justify-center">
                                            <div class="w-7 h-7 rounded-full"
                                                :style="{ backgroundColor: newChannel.color }"></div>
                                        </div>
                                        <input type="color" v-model="newChannel.color"
                                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" />
                                    </div>
                                    <input v-model="newChannel.color" type="text" placeholder="#4f46e5"
                                        class="flex-1 rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                </div>
                            </div>
                            <div>
                                <label class="block mb-1 text-xs font-medium text-gray-500">ID del canal de YouTube</label>
                                <input v-model="newChannel.youtube_channel_id" type="text" placeholder="UC..."
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            </div>
                            <div>
                                <label class="block mb-1 text-xs font-medium text-gray-500">URL del canal</label>
                                <input v-model="newChannel.channel_url" type="url" placeholder="https://youtube.com/@canal"
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            </div>
                            <div class="flex justify-end">
                                <button v-if="can('create empresa')" type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium transition flex items-center gap-1.5">
                                    <Plus class="w-4 h-4" /> Añadir canal
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Invitations -->
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-6">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-3 rounded-xl bg-amber-100 dark:bg-amber-900">
                                <Key class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                            </div>
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white">Invitaciones</h2>
                        </div>

                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                            Comparte este código con tus empleados para que se registren en tu empresa.
                        </p>

                        <div v-if="props.organization?.invite_code" class="mb-4">
                            <label class="block mb-1 text-xs font-medium text-gray-500 uppercase tracking-wider">Código activo</label>
                            <div class="flex items-center gap-2">
                                <code class="flex-1 px-3 py-2 rounded-xl bg-gray-100 dark:bg-gray-900 text-sm sm:text-lg font-bold text-amber-600 dark:text-amber-400 tracking-wider text-center select-all truncate">{{ props.organization.invite_code }}</code>
                                <button @click="copyInvite"
                                    class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 transition relative">
                                    <transition name="pop" mode="out-in">
                                        <Check v-if="copied" key="check" class="w-4 h-4 text-green-500" />
                                        <Copy v-else key="copy" class="w-4 h-4" />
                                    </transition>
                                </button>
                            </div>
                        </div>

                        <div v-else class="mb-4 p-3 rounded-xl bg-gray-50 dark:bg-gray-900 text-sm text-gray-400 text-center">
                            No hay código activo. Genera uno nuevo.
                        </div>

                        <button v-if="can('edit empresa')" @click="generateInvite"
                            class="w-full px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium transition flex items-center justify-center gap-1.5">
                            <Key class="w-4 h-4" /> Generar nuevo código
                        </button>
                    </div>
                </div>

                <!-- Channels table -->
                <div v-if="channels.length > 0" class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                    <div class="p-4 border-b border-gray-100 dark:border-gray-700">
                        <div class="flex items-center gap-3">
                            <div class="p-2 rounded-xl bg-red-100 dark:bg-red-900">
                                <Youtube class="w-4 h-4 text-red-600 dark:text-red-400" />
                            </div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Canales agregados</h2>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm table-fixed">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="text-left px-4 py-4 font-semibold text-gray-500 dark:text-gray-400 w-10"></th>
                                    <th class="text-left px-4 py-4 font-semibold text-gray-500 dark:text-gray-400 w-[160px] sm:w-[200px]">Nombre</th>
                                    <th class="text-left px-4 py-4 font-semibold text-gray-500 dark:text-gray-400 hidden md:table-cell">ID YouTube</th>
                                    <th class="text-left px-4 py-4 font-semibold text-gray-500 dark:text-gray-400 hidden sm:table-cell w-[100px]">URL</th>
                                    <th class="text-right px-4 py-4 font-semibold text-gray-500 dark:text-gray-400 w-[80px] sm:w-[100px]">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="channel in channels" :key="channel.id">
                                    <tr v-if="editingId !== channel.id"
                                        class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                        <td class="px-4 py-4">
                                            <span class="" :style="{ backgroundColor: channel.color }"></span>
                                        </td>
                                        <td class="px-4 py-4 font-medium text-gray-900 dark:text-white truncate">{{ channel.name }}</td>
                                        <td class="px-4 py-4 text-gray-500 font-mono text-xs truncate hidden md:table-cell">{{ channel.youtube_channel_id || '—' }}</td>
                                        <td class="px-4 py-4 hidden sm:table-cell">
                                            <a v-if="channel.channel_url" :href="channel.channel_url" target="_blank"
                                                class="inline-flex items-center gap-1 text-xs text-red-600 dark:text-red-400 hover:underline">
                                                <ExternalLink class="w-3 h-3" /> Abrir
                                            </a>
                                            <span v-else class="text-gray-400 text-xs">—</span>
                                        </td>
                                        <td class="px-4 py-4 text-right">
                                            <div class="flex items-center justify-end gap-0 sm:gap-1">
                                                <button v-if="can('edit empresa')" @click="startEdit(channel)"
                                                    class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 hover:text-amber-600 dark:hover:text-amber-400 transition">
                                                    <Pencil class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
                                                </button>
                                                <button v-if="can('delete empresa')" @click="deleteChannel(channel)"
                                                    class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 hover:text-red-600 dark:hover:text-red-400 transition">
                                                    <Trash2 class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-else class="bg-gray-50 dark:bg-gray-900">
                                        <td colspan="5" class="px-4 py-4">
                                            <div class="flex items-center gap-4 mb-3">
                                                <span class="w-5 h-5 rounded-full flex-shrink-0" :style="{ backgroundColor: editForm.color }"></span>
                                                <span class="text-sm font-semibold text-gray-900 dark:text-white">Editando: {{ editForm.name }}</span>
                                            </div>
                                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
                                                <div>
                                                    <label class="block mb-1 text-[10px] font-medium text-gray-500 uppercase tracking-wider">Nombre</label>
                                                    <input v-model="editForm.name" type="text" required
                                                        class="w-full text-sm rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                                                </div>
                                                <div>
                                                    <label class="block mb-1 text-[10px] font-medium text-gray-500 uppercase tracking-wider">Color</label>
                                                    <div class="flex items-center gap-2">
                                                        <div class="relative w-10 h-10 flex-shrink-0">
                                                            <div class="w-10 h-10 rounded-full bg-white border-2 border-gray-300 dark:border-gray-700 flex items-center justify-center">
                                                                <div class="w-7 h-7 rounded-full"
                                                                    :style="{ backgroundColor: editForm.color }"></div>
                                                            </div>
                                                            <input type="color" v-model="editForm.color"
                                                                class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" />
                                                        </div>
                                                        <input v-model="editForm.color" type="text"
                                                            class="flex-1 text-sm rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="block mb-1 text-[10px] font-medium text-gray-500 uppercase tracking-wider">ID YouTube</label>
                                                    <input v-model="editForm.youtube_channel_id" type="text" placeholder="UC..."
                                                        class="w-full text-sm rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                                                </div>
                                                <div>
                                                    <label class="block mb-1 text-[10px] font-medium text-gray-500 uppercase tracking-wider">URL</label>
                                                    <input v-model="editForm.channel_url" type="url" placeholder="https://..."
                                                        class="w-full text-sm rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                                                </div>
                                            </div>
                                            <div class="flex justify-end gap-2">
                                                <button @click="cancelEdit"
                                                    class="px-3 py-1.5 rounded-lg bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 text-xs font-medium transition flex items-center gap-1">
                                                    <X class="w-3 h-3" /> Cancelar
                                                </button>
                                                <button @click="saveEdit"
                                                    class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium transition flex items-center gap-1">
                                                    <Check class="w-3 h-3" /> Guardar
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Empty state for non-super-admin without org -->
            <div v-else-if="!isSuperAdmin && !organization"
                class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-12 text-center">
                <Building2 class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-4" />
                <p class="text-gray-500 dark:text-gray-400">No tienes una empresa asignada</p>
            </div>
        </div>

        <ConfirmDeleteModal
            :show="showDeleteModal"
            :title="deleteTarget?.type === 'channel' ? 'Eliminar canal' : 'Eliminar empresa'"
            :message="deleteMessage"
            @close="deleteTarget = null"
            @confirm="executeDelete"
        />
    </AppLayout>
</template>

<style scoped>
.pop-enter-active { animation: pop-in .25s ease-out; }
.pop-leave-active { animation: pop-in .15s ease-in reverse; }
@keyframes pop-in {
    0% { transform: scale(0); opacity: 0; }
    70% { transform: scale(1.15); }
    100% { transform: scale(1); opacity: 1; }
}
</style>
