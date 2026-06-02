<script setup>
import TextInput from '@/Components/Forms/TextInput.vue'
import PrimaryButton from '@/Components/UI/PrimaryButton.vue'
import { Shield, ShieldCheck } from 'lucide-vue-next'

const props = defineProps({
    form: Object,
    permissions: Array,
    submitLabel: { type: String, default: 'Guardar' },
})

const emit = defineEmits(['submit'])

const togglePermission = (permission) => {
    const exists = (props.form.permissions || []).includes(permission)
    if (exists) {
        props.form.permissions = props.form.permissions.filter(p => p !== permission)
        return
    }
    props.form.permissions = [...(props.form.permissions || []), permission]
}

const allSelected = () => {
    return props.permissions?.length > 0 && props.form.permissions?.length === props.permissions?.length
}

const toggleAll = () => {
    if (allSelected()) {
        props.form.permissions = []
    } else {
        props.form.permissions = props.permissions?.map(p => p.name) || []
    }
}
</script>

<template>
    <form @submit.prevent="emit('submit')" class="space-y-6">
        <TextInput v-model="form.name" label="Nombre del rol" :error="form.errors.name" />

        <div>
            <div class="flex items-center justify-between mb-4">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Permisos</label>
                <button type="button" @click="toggleAll"
                    class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 transition">
                    {{ allSelected() ? 'Deseleccionar todos' : 'Seleccionar todos' }}
                </button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <label v-for="permission in permissions" :key="permission.id"
                    class="flex items-center gap-3 bg-gray-50 dark:bg-gray-900 p-3.5 rounded-xl cursor-pointer border border-gray-100 dark:border-gray-700 hover:border-indigo-300 dark:hover:border-indigo-600 transition"
                    :class="{ 'border-indigo-400 dark:border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20': form.permissions.includes(permission.name) }">
                    <input type="checkbox" :checked="form.permissions.includes(permission.name)"
                        @change="togglePermission(permission.name)" class="sr-only" />
                    <div class="w-5 h-5 rounded-md border-2 flex items-center justify-center transition flex-shrink-0"
                        :class="form.permissions.includes(permission.name)
                            ? 'bg-indigo-600 border-indigo-600'
                            : 'border-gray-300 dark:border-gray-600'">
                        <ShieldCheck v-if="form.permissions.includes(permission.name)" class="w-3.5 h-3.5 text-white" />
                    </div>
                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ permission.name }}</span>
                </label>
            </div>
            <p v-if="form.errors.permissions" class="text-red-500 text-sm mt-2">{{ form.errors.permissions }}</p>
        </div>

        <div class="flex justify-end pt-2">
            <PrimaryButton :disabled="form.processing">
                <Shield class="w-4 h-4 mr-1.5" /> {{ submitLabel }}
            </PrimaryButton>
        </div>
    </form>
</template>
