<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import VideoTaskForm from './Components/VideoTaskForm.vue'
import { useForm, Link } from '@inertiajs/vue3'
import { provide } from 'vue'

const props = defineProps({
    prefilled: Object,
    statuses: Array,
    channels: Array,
})

const form = useForm({
    task_date: props.prefilled?.task_date || '',
    time_range: props.prefilled?.time_range || '',
    title: '',
    script: '',
    copy: '',
    translations: null,
    youtube_url: '',
    status: 'pending',
    channel_id: null,
})

provide('taskForm', form)

const submit = () => {
    form.post('/video-tasks')
}
</script>

<template>
    <AppLayout>
        <div class="max-w-7xl mx-auto">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Crear Tarea</h1>
                <Link href="/planning" class="text-indigo-600 hover:text-indigo-700">Volver al calendario</Link>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-6">
                <VideoTaskForm :statuses="statuses" :channels="channels" submit-label="Crear Tarea" @submit="submit" />
            </div>
        </div>
    </AppLayout>
</template>
