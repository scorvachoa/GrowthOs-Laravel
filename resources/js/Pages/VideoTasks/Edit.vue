<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import VideoTaskForm from './Components/VideoTaskForm.vue'
import { useForm, Link } from '@inertiajs/vue3'

const props = defineProps({
    task: Object,
    work_blocks: Array,
    statuses: Array,
    channels: Array,
})

const form = useForm({
    task_date: props.task.task_date || '',
    time_range: props.task.time_range || '',
    title: props.task.title || '',
    script: props.task.script || '',
    copy: props.task.copy || '',
    youtube_url: props.task.youtube_url || '',
    status: props.task.status || 'pending',
    channel_id: props.task.channel?.id || null,
})

const submit = () => {
    form.put(route('video-tasks.update', props.task.id))
}
</script>

<template>
    <AppLayout>
        <div class="max-w-7xl mx-auto">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Editar Tarea</h1>
                <Link href="/planning" class="text-indigo-600 hover:text-indigo-700">Volver al calendario</Link>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-6">
                <VideoTaskForm :form="form" :work-blocks="work_blocks" :statuses="statuses" :channels="channels" :except-task-id="props.task.id" submit-label="Actualizar Tarea" @submit="submit" />
            </div>
        </div>
    </AppLayout>
</template>
