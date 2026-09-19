<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import VideoTaskForm from './Components/VideoTaskForm.vue'
import { useForm, Link, usePage } from '@inertiajs/vue3'
import { computed, provide } from 'vue'
import { ArrowLeft } from 'lucide-vue-next'

const page = usePage()
const currentUserId = page.props.auth?.user?.id

const props = defineProps({
    task: Object,
    statuses: Array,
    channels: Array,
})

const isCreator = computed(() => props.task.created_by === currentUserId)

const form = useForm({
    task_date: props.task.task_date || '',
    time_range: props.task.time_range || '',
    title: props.task.title || '',
    script: props.task.script || '',
    copy: props.task.copy || '',
    translations: props.task.translations || null,
    youtube_url: props.task.youtube_url || '',
    status: props.task.status || 'pending',
    channel_id: props.task.channel?.id || null,
    shared_user_ids: props.task.shared_user_ids || [],
})

provide('taskForm', form)

const submit = () => {
    form.put(route('tasks.update', props.task.id))
}
</script>

<template>
    <AppLayout>
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link :href="`/tasks/${props.task.id}`"
                        class="p-2 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700 transition text-gray-500 dark:text-gray-400">
                        <ArrowLeft class="w-5 h-5" />
                    </Link>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Editar tarea</h1>
                </div>
                <Link href="/planning"
                    class="text-sm text-gray-500 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                    Volver al calendario
                </Link>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                <VideoTaskForm :statuses="statuses" :channels="channels" :except-task-id="props.task.id" :task-id="props.task.id" :task-date="props.task.task_date" :sessions="props.task.sessions || []" :is-creator="isCreator" submit-label="Actualizar Tarea" @submit="submit" />
            </div>
        </div>
    </AppLayout>
</template>
