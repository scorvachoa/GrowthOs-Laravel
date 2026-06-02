<script setup>
import { ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PrimaryButton from '@/Components/UI/PrimaryButton.vue'
import { Building2, Youtube, Plus, Trash2, Save, Upload } from 'lucide-vue-next'

const page = usePage()

const props = defineProps({
    organization: Object,
    channels: Array,
})

const orgForm = ref({
    name: props.organization.name,
    primary_color: props.organization.primary_color,
    logo: null,
    remove_logo: false,
})

const newChannel = ref({
    name: '',
    color: '#4f46e5',
    youtube_channel_id: '',
    channel_url: '',
})

const logoPreview = ref(props.organization.logo_url || null)

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

function updateChannel(channel) {
    router.post(`/settings/channels/${channel.id}`, channel, { preserveScroll: true })
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

                        <PrimaryButton type="submit">Guardar empresa</PrimaryButton>
                    </form>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-6">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="p-3 rounded-xl bg-red-100 dark:bg-red-900">
                            <Youtube class="w-5 h-5 text-red-600 dark:text-red-400" />
                        </div>
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Canales administrados</h2>
                    </div>

                    <form @submit.prevent="addChannel" class="space-y-4 p-4 rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 mb-6">
                        <h3 class="text-sm font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Nuevo canal</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block mb-1 text-xs font-medium text-gray-500">Nombre</label>
                                <input v-model="newChannel.name" type="text" placeholder="Canal principal" required
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            </div>
                            <div>
                                <label class="block mb-1 text-xs font-medium text-gray-500">Color</label>
                                <div class="flex items-center gap-2">
                                    <input v-model="newChannel.color" type="color" class="w-10 h-9 rounded-lg border border-gray-300 dark:border-gray-700 cursor-pointer p-0.5" />
                                    <input v-model="newChannel.color" type="text" placeholder="#4f46e5"
                                        class="flex-1 rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                </div>
                            </div>
                            <div>
                                <label class="block mb-1 text-xs font-medium text-gray-500">ID YouTube</label>
                                <input v-model="newChannel.youtube_channel_id" type="text" placeholder="UC..."
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            </div>
                            <div>
                                <label class="block mb-1 text-xs font-medium text-gray-500">URL del canal</label>
                                <input v-model="newChannel.channel_url" type="url" placeholder="https://youtube.com/@canal"
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium transition flex items-center gap-1.5">
                                <Plus class="w-4 h-4" /> Añadir canal
                            </button>
                        </div>
                    </form>

                    <div class="space-y-3">
                        <div v-if="channels.length === 0" class="text-center py-6 text-gray-400 dark:text-gray-500 text-sm">
                            No hay canales configurados
                        </div>
                        <div v-for="channel in channels" :key="channel.id"
                            class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 hover:shadow-sm transition">
                            <form @submit.prevent="updateChannel(channel)" class="space-y-3">
                                <div class="flex items-center gap-3 mb-2">
                                    <span class="w-3 h-8 rounded-full flex-shrink-0" :style="{ backgroundColor: channel.color }"></span>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ channel.name }}</span>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <input v-model="channel.name" type="text" required
                                        class="text-sm rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                                    <div class="flex items-center gap-2">
                                        <input v-model="channel.color" type="color" class="w-9 h-8 rounded-lg border border-gray-300 dark:border-gray-700 cursor-pointer p-0.5" />
                                        <input v-model="channel.color" type="text"
                                            class="flex-1 text-sm rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                                    </div>
                                    <input v-model="channel.youtube_channel_id" type="text" placeholder="ID YouTube"
                                        class="text-sm rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                                    <input v-model="channel.channel_url" type="url" placeholder="URL del canal"
                                        class="text-sm rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                                </div>
                                <div class="flex justify-end gap-2">
                                    <button type="submit"
                                        class="px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-xs font-medium transition flex items-center gap-1">
                                        <Save class="w-3 h-3" /> Guardar
                                    </button>
                                    <button type="button" @click="deleteChannel(channel)"
                                        class="px-3 py-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-xs font-medium transition flex items-center gap-1">
                                        <Trash2 class="w-3 h-3" /> Eliminar
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
