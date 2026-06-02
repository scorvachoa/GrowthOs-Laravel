<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import VideoTaskForm from './Components/VideoTaskForm.vue'
import { useForm, Link } from '@inertiajs/vue3'

const props = defineProps({
    prefilled: Object,
    work_blocks: Array,
    statuses: Array,
})

const form = useForm({
    task_date: props.prefilled?.task_date || '',
    time_range: props.prefilled?.time_range || '',
    title: '',
    script: '',
    copy: '',
    key_phrases: '',
    youtube_url: '',
    status: 'pending',
})

const submit = () => {
    form.post('/video-tasks')
}
</script>

<template>
    <AppLayout>
        <div class="max-w-3xl mx-auto">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Crear Tarea</h1>
                <Link href="/planning" class="text-indigo-600 hover:text-indigo-700">Volver al calendario</Link>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-6">
                <VideoTaskForm :form="form" :work-blocks="work_blocks" :statuses="statuses" submit-label="Crear Tarea" @submit="submit" />
            </div>
        </div>
    </AppLayout>
</template>
