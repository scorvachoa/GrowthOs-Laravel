<script setup>
import { Plus } from 'lucide-vue-next'

defineProps({
    days: Array,
    workBlocks: Array,
    canCreate: Boolean,
})

const emit = defineEmits(['openDay', 'createTask'])

const dayNames = ['Domingo', 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado']

function allBlocksFull(day, blocks) {
    return blocks?.every(b => (day.blocks?.[b] || 0) > 0) ?? false
}
</script>

<template>
    <div>
        <div class="grid grid-cols-7 mb-2">
            <div v-for="name in dayNames" :key="name"
                class="text-center font-semibold text-sm text-gray-500 dark:text-gray-400 py-2">
                {{ name }}
            </div>
        </div>
        <div class="grid grid-cols-7 border-l border-t border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
            <template v-for="(day, idx) in days" :key="idx">
                <div v-if="!day"
                    class="min-h-[130px] border-r border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                </div>
                <div v-else
                    @click="!day.isNonWorkingDay && emit('openDay', day.date)"
                    class="min-h-[130px] border-r border-b border-gray-200 dark:border-gray-700 p-2 cursor-pointer transition relative group"
                    :class="[
                        day.isNonWorkingDay ? 'bg-gray-50 dark:bg-gray-800/50 cursor-default' : 'hover:bg-gray-50 dark:hover:bg-gray-700/50',
                        day.isToday ? 'bg-indigo-50 dark:bg-indigo-900/20' : '',
                    ]">
                    <div class="flex items-start justify-between mb-1">
                        <span class="text-sm font-bold"
                            :class="[
                                day.isNonWorkingDay ? 'text-red-400' : day.isToday ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-700 dark:text-gray-300',
                            ]">
                            {{ day.day }}
                        </span>
                    </div>
                    <div v-if="day.holidayName" class="text-[10px] text-red-500 font-medium leading-tight mb-1">
                        {{ day.holidayName }}
                    </div>
                    <div class="flex flex-col gap-[2px] mt-1">
                        <div v-for="(task, tIdx) in day.tasks" :key="tIdx"
                            class="h-1.5 rounded-sm bg-indigo-500"
                            :title="task.time_range + ' - ' + task.title">
                        </div>
                        <div v-if="day.hasExtraTasks"
                            class="h-1.5 rounded-sm bg-amber-400"
                            title="Tareas extra">
                        </div>
                    </div>
                    <button v-if="canCreate && !day.isNonWorkingDay && !allBlocksFull(day, workBlocks)"
                        @click.stop="emit('createTask', day.date, '09:00-11:00')"
                        class="absolute top-1 right-1 p-1 rounded-lg opacity-0 group-hover:opacity-100 hover:bg-indigo-100 dark:hover:bg-indigo-800 transition">
                        <Plus class="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400" />
                    </button>
                </div>
            </template>
        </div>
    </div>
</template>
