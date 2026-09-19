<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import PrimaryButton from '@/Components/UI/PrimaryButton.vue'
import { Building2, Upload } from 'lucide-vue-next'

const props = defineProps({
    company: { type: Object, default: null },
})

const form = ref({
    name: props.company?.name || '',
    primary_color: props.company?.primary_color || '#4f46e5',
    logo: null,
})

const logoPreview = ref(props.company?.logo_url || null)
const errors = ref({})

const isEditing = !!props.company

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
    if (isEditing) {
        if (!logoPreview.value && !props.company?.logo_url) {
            fd.append('remove_logo', '1')
        }
        router.post(`/company/${props.company.id}`, {
            ...Object.fromEntries(fd),
            _method: 'PUT',
        }, {
            onError: (err) => { errors.value = err },
        })
    } else {
        router.post('/company', fd, {
            onError: (err) => { errors.value = err },
        })
    }
}
</script>

<template>
    <form @submit.prevent="submit" class="space-y-6">
        <div>
            <label class="block mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">Nombre de la empresa</label>
            <input v-model="form.name" type="text" required :placeholder="isEditing ? '' : 'Ej: Mi Empresa S.A.'"
                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
            <p v-if="errors.name" class="mt-1 text-sm text-red-600">{{ errors.name }}</p>
        </div>

        <div>
            <label class="block mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">Logo de empresa</label>
            <div v-if="logoPreview" class="flex items-center gap-4 mb-3 p-3 rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700">
                <img :src="logoPreview" alt="Logo preview" class="w-16 h-16 object-contain rounded-lg bg-white" />
                <span class="text-sm text-gray-500">{{ isEditing ? 'Logo actual' : 'Previsualización' }}</span>
                <button type="button" @click="removeLogo"
                    class="ml-auto text-xs px-2 py-1 rounded-lg bg-red-600 text-white">
                    {{ isEditing ? 'Eliminar' : 'Quitar' }}
                </button>
            </div>
            <label class="flex items-center gap-2 px-4 py-3 rounded-xl border border-dashed border-gray-300 dark:border-gray-700 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                <Upload class="w-4 h-4 text-gray-500" />
                <span class="text-sm text-gray-500">{{ isEditing ? 'Cambiar imagen' : 'Seleccionar imagen (PNG, JPG, WEBP, max 2MB)' }}</span>
                <input type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="handleLogoUpload" />
            </label>
        </div>

        <div>
            <label class="block mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">Color principal</label>
            <div class="flex items-center gap-3">
                <div class="relative w-10 h-10">
                    <div class="w-10 h-10 rounded-full bg-white border border-gray-300 dark:border-gray-700 flex items-center justify-center">
                        <div class="w-[22px] h-[22px] rounded-full" :style="{ backgroundColor: form.primary_color }"></div>
                    </div>
                    <input type="color" v-model="form.primary_color"
                        class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" />
                </div>
                <input v-model="form.primary_color" type="text"
                    class="w-32 rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 text-sm">
            </div>
        </div>

        <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
            <p v-if="!isEditing" class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                Al crear la empresa se generará automáticamente un <strong>código de invitación para administrador</strong> (un solo uso) que podrás compartir con el responsable de la empresa.
            </p>
            <PrimaryButton type="submit" class="w-full justify-center">
                <Building2 class="w-4 h-4" /> {{ isEditing ? 'Guardar cambios' : 'Crear empresa' }}
            </PrimaryButton>
        </div>
    </form>
</template>
