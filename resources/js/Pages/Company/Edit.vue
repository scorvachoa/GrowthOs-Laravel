<script setup>
import { ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PrimaryButton from '@/Components/UI/PrimaryButton.vue'
import { Building2, Upload, ArrowLeft } from 'lucide-vue-next'

const page = usePage()

const props = defineProps({
    company: Object,
})

const form = ref({
    name: props.company?.name || '',
    primary_color: props.company?.primary_color || '#4f46e5',
    logo: null,
})

const logoPreview = ref(props.company?.logo_url || null)
const errors = ref({})

function handleLogoUpload(e) {
    const file = e.target.files?.[0]
    if (!file) return
    form.value.logo = file
    const reader = new FileReader()
    reader.onload = (ev) => { logoPreview.value = ev.target?.result }
    reader.readAsDataURL(file)
}

function removeLogo() {
    form.value.logo = null
    logoPreview.value = null
}

function submit() {
    const fd = new FormData()
    fd.append('name', form.value.name)
    fd.append('primary_color', form.value.primary_color)
    if (form.value.logo) {
        fd.append('logo', form.value.logo)
    }
    if (!logoPreview.value && !props.company?.logo_url) {
        fd.append('remove_logo', '1')
    }
    router.post(`/company/${props.company.id}`, {
        ...Object.fromEntries(fd),
        _method: 'PUT',
    }, {
        onError: (err) => { errors.value = err },
    })
}
</script>

<template>
    <AppLayout>
        <div class="max-w-2xl mx-auto">
            <div class="mb-6">
                <button @click="router.get(`/company/${props.company.id}`)"
                    class="flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 transition">
                    <ArrowLeft class="w-4 h-4" /> Volver a empresa
                </button>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-8">
                <div class="flex items-center gap-3 mb-8">
                    <div class="p-3 rounded-xl bg-indigo-100 dark:bg-indigo-900">
                        <Building2 class="w-6 h-6 text-indigo-600 dark:text-indigo-400" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Editar empresa</h1>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ props.company?.name }}</p>
                    </div>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <div>
                        <label class="block mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">Nombre de la empresa</label>
                        <input v-model="form.name" type="text" required
                            class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                        <p v-if="errors.name" class="mt-1 text-sm text-red-600">{{ errors.name }}</p>
                    </div>

                    <div>
                        <label class="block mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">Logo de empresa</label>
                        <div v-if="logoPreview" class="flex items-center gap-4 mb-3 p-3 rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700">
                            <img :src="logoPreview" alt="Logo" class="w-16 h-16 object-contain rounded-lg bg-white" />
                            <span class="text-sm text-gray-500">Logo actual</span>
                            <button type="button" @click="removeLogo"
                                class="ml-auto text-xs px-2 py-1 rounded-lg bg-red-600 text-white">Eliminar</button>
                        </div>
                        <label class="flex items-center gap-2 px-4 py-3 rounded-xl border border-dashed border-gray-300 dark:border-gray-700 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            <Upload class="w-4 h-4 text-gray-500" />
                            <span class="text-sm text-gray-500">Cambiar imagen</span>
                            <input type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="handleLogoUpload" />
                        </label>
                    </div>

                    <div>
                        <label class="block mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">Color principal</label>
                        <div class="flex items-center gap-3">
                            <input v-model="form.primary_color" type="color" class="w-12 h-10 rounded-xl border border-gray-300 dark:border-gray-700 cursor-pointer p-0.5" />
                            <input v-model="form.primary_color" type="text"
                                class="w-32 rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                        </div>
                    </div>

                    <div class="pt-4 border-t border-gray-200 dark:border-gray-700 flex gap-3">
                        <PrimaryButton type="submit" class="flex-1 justify-center">
                            Guardar cambios
                        </PrimaryButton>
                        <button type="button" @click="router.get('/company')"
                            class="px-6 py-2.5 rounded-xl bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 font-medium transition">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
