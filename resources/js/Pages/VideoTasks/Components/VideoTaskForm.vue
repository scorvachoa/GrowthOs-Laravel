<script setup>
import TextInput from '@/Components/Forms/TextInput.vue'
import PrimaryButton from '@/Components/UI/PrimaryButton.vue'

defineProps({
    form: Object,
    workBlocks: Array,
    statuses: Array,
    submitLabel: { type: String, default: 'Guardar' },
})

const emit = defineEmits(['submit'])
</script>

<template>
    <form @submit.prevent="emit('submit')" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <TextInput v-model="form.task_date" label="Fecha" type="date" :error="form.errors.task_date" />
            <div>
                <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Bloque horario</label>
                <select v-model="form.time_range"
                    class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">Seleccionar bloque</option>
                    <option v-for="block in workBlocks" :key="block" :value="block">{{ block }}</option>
                </select>
                <div v-if="form.errors.time_range" class="mt-1 text-sm text-red-500">{{ form.errors.time_range }}</div>
            </div>
        </div>

        <TextInput v-model="form.title" label="Titulo del video" :error="form.errors.title" />

        <div>
            <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Guion</label>
            <textarea v-model="form.script" rows="5"
                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500"></textarea>
            <div v-if="form.errors.script" class="mt-1 text-sm text-red-500">{{ form.errors.script }}</div>
        </div>

        <div>
            <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Copy / Descripcion</label>
            <textarea v-model="form.copy" rows="4"
                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500"></textarea>
            <div v-if="form.errors.copy" class="mt-1 text-sm text-red-500">{{ form.errors.copy }}</div>
        </div>

        <div>
            <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Frases clave</label>
            <textarea v-model="form.key_phrases" rows="3"
                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500"></textarea>
            <div v-if="form.errors.key_phrases" class="mt-1 text-sm text-red-500">{{ form.errors.key_phrases }}</div>
        </div>

        <TextInput v-model="form.youtube_url" label="URL de YouTube / TikTok" type="url" :error="form.errors.youtube_url" />

        <div>
            <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Estado</label>
            <select v-model="form.status"
                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
            <div v-if="form.errors.status" class="mt-1 text-sm text-red-500">{{ form.errors.status }}</div>
        </div>

        <PrimaryButton :disabled="form.processing">{{ submitLabel }}</PrimaryButton>
    </form>
</template>
