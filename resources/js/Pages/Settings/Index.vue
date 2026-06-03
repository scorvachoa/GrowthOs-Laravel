<script setup>
import { ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PrimaryButton from '@/Components/UI/PrimaryButton.vue'
import { Building2, Youtube, Plus, Trash2, Pencil, X, Check, ExternalLink, Upload } from 'lucide-vue-next'

const page = usePage()
const permissions = page.props.auth?.user?.permissions ?? []
const can = (perm) => permissions.includes(perm)

const props = defineProps({
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
    router.post('/settings/company', form, {
        preserveScroll: true,
        onSuccess: () => {
            orgForm.value.remove_logo = false
        },
    })
}

function addChannel() {
    router.post('/settings/channels', newChannel.value, {
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
    router.post(`/settings/channels/${editingId.value}`, editForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            editingId.value = null
            editForm.value = {}
        },
    })
}

function deleteChannel(channel) {
    if (confirm(`Eliminar el canal "${channel.name}"?`)) {
        router.post(`/settings/channels/${channel.id}/delete`, { preserveScroll: true })
    }
}
</script>

<template>
    <AppLayout>
        <div class="max-w-5xl mx-auto space-y-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Configuración</h1>
                <p class="text-gray-500 dark:text-gray-400 mt-1">Administra tu empresa y canales</p>
            </div>

            <div v-if="page.props.flash?.success"
                class="p-4 rounded-xl bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 text-sm">
                {{ page.props.flash.success }}
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-6">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="p-3 rounded-xl bg-indigo-100 dark:bg-indigo-900">
                            <Building2 class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                        </div>
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Empresa</h2>
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
                                <input v-model="orgForm.primary_color" type="color" class="w-12 h-10 rounded-xl border border-gray-300 dark:border-gray-700 cursor-pointer p-0.5" />
                                <span class="text-sm text-gray-500">{{ orgForm.primary_color }}</span>
                            </div>
                        </div>

                        <PrimaryButton v-if="can('edit empresa')" type="submit">Guardar empresa</PrimaryButton>
                    </form>
                </div>

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
                            <input v-model="newChannel.name" type="text" placeholder="Canal principal" required
                                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                        </div>
                        <div>
                            <label class="block mb-1 text-xs font-medium text-gray-500">Color</label>
                            <div class="flex items-center gap-2">
                                <input v-model="newChannel.color" type="color" class="w-10 h-9 rounded-lg border border-gray-300 dark:border-gray-700 cursor-pointer p-0.5" />
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
            </div>

            <div v-if="channels.length > 0" class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-6">
                <div class="flex items-center gap-3 mb-6">
                    <div class="p-3 rounded-xl bg-red-100 dark:bg-red-900">
                        <Youtube class="w-5 h-5 text-red-600 dark:text-red-400" />
                    </div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Canales agregados</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="text-left py-3 px-3 font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider text-[10px] w-10"></th>
                                <th class="text-left py-3 px-3 font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider text-[10px]">Nombre</th>
                                <th class="text-left py-3 px-3 font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider text-[10px]">ID YouTube</th>
                                <th class="text-left py-3 px-3 font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider text-[10px]">URL</th>
                                <th class="text-right py-3 px-3 font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider text-[10px]">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="channel in channels" :key="channel.id">
                                <tr v-if="editingId !== channel.id"
                                    class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                                    <td class="py-3 px-3">
                                        <span class="block w-4 h-8 rounded-full" :style="{ backgroundColor: channel.color }"></span>
                                    </td>
                                    <td class="py-3 px-3 font-medium text-gray-900 dark:text-white">{{ channel.name }}</td>
                                    <td class="py-3 px-3 text-gray-500 font-mono text-xs">{{ channel.youtube_channel_id || '—' }}</td>
                                    <td class="py-3 px-3">
                                        <a v-if="channel.channel_url" :href="channel.channel_url" target="_blank"
                                            class="inline-flex items-center gap-1 text-xs text-red-600 dark:text-red-400 hover:underline">
                                            <ExternalLink class="w-3 h-3" /> Abrir
                                        </a>
                                        <span v-else class="text-gray-400 text-xs">—</span>
                                    </td>
                                    <td class="py-3 px-3 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <button v-if="can('edit empresa')" @click="startEdit(channel)"
                                                class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 hover:text-amber-600 dark:hover:text-amber-400 transition">
                                                <Pencil class="w-4 h-4" />
                                            </button>
                                            <button v-if="can('delete empresa')" @click="deleteChannel(channel)"
                                                class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 hover:text-red-600 dark:hover:text-red-400 transition">
                                                <Trash2 class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-else class="bg-gray-50 dark:bg-gray-900">
                                    <td colspan="5" class="py-4 px-4">
                                        <div class="flex items-center gap-4 mb-3">
                                            <span class="w-4 h-8 rounded-full" :style="{ backgroundColor: editForm.color }"></span>
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
                                                    <input v-model="editForm.color" type="color" class="w-9 h-8 rounded-lg border border-gray-300 dark:border-gray-700 cursor-pointer p-0.5" />
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
    </AppLayout>
</template>
