<script setup>
import { Plus } from 'lucide-vue-next'

defineProps({
    days: Array,
    workBlocks: Array,
    canCreate: Boolean,
})

const emit = defineEmits(['openDay', 'createTask'])

const dayNames = ['Domingo', 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado']

const statusColors = {
    pending: 'bg-yellow-500',
    script_ready: 'bg-blue-500',
    editing: 'bg-purple-500',
    review: 'bg-orange-500',
    scheduled: 'bg-indigo-500',
    published: 'bg-green-500',
    cancelled: 'bg-red-500',
    in_progress: 'bg-amber-500',
    completed: 'bg-teal-500',
}

function allBlocksFull(day, blocks) {
    return blocks?.every(b => (day.blocks?.[b] || 0) > 0) ?? false
}
</script>

<template>
    <div>
        <div class="hidden sm:grid grid-cols-7 gap-1.5 mb-2 px-0.5">
            <div v-for="name in dayNames" :key="name"
                class="text-center font-semibold text-xs text-gray-500 dark:text-gray-400 py-2 uppercase tracking-wider">
                {{ name }}
            </div>
        </div>
        <div class="sm:grid sm:grid-cols-7 gap-1.5">
            <div v-for="(day, idx) in days" :key="idx"
                v-memo="[day.day, day.date, day.isOtherMonth, day.isToday, day.isNonWorkingDay, day.tasks.length, day.hasExtraTasks, day.extraTasksCount, day.holidayName, day.absences?.length]"
                @click="!day.isNonWorkingDay && !day.isOtherMonth && emit('openDay', day.date)"
                class="sm:min-h-[100px] bg-white dark:bg-gray-800 rounded-lg shadow-sm border p-2.5 cursor-pointer transition-all duration-150 relative group"
                :class="[
                    day.isOtherMonth
                        ? 'border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-900/50 cursor-default opacity-60'
                        : day.isNonWorkingDay
                            ? 'bg-gray-50 dark:bg-gray-800/50 border-gray-200 dark:border-gray-700 cursor-default'
                            : 'border-gray-200 dark:border-gray-700 hover:shadow-md hover:-translate-y-0.5',
                    day.isToday && !day.isOtherMonth
                        ? 'border-indigo-400 dark:border-indigo-500 shadow-indigo-100 dark:shadow-indigo-900/20'
                        : '',
                ]">
                <div class="flex items-start justify-between sm:flex-col sm:gap-1">
                    <div class="flex items-center gap-1.5">
                        <span class="text-sm font-semibold w-7 h-7 flex items-center justify-center rounded-full"
                            :class="[
                                day.isOtherMonth ? 'text-gray-300 dark:text-gray-600' :
                                day.isNonWorkingDay ? 'text-red-400' :
                                day.isToday ? 'text-white bg-indigo-600' : 'text-gray-700 dark:text-gray-300',
                            ]">
                            {{ day.day }}
                        </span>
                        <span v-if="!day.isOtherMonth && day.tasks.length > 0"
                            class="text-[10px] font-medium text-indigo-500 dark:text-indigo-400 hidden sm:inline">
                            {{ day.tasks.length }} tarea{{ day.tasks.length !== 1 ? 's' : '' }}
                        </span>
                    </div>
                    <div v-if="!day.isOtherMonth" class="flex-1 min-w-0 sm:hidden">
                        <div class="flex flex-wrap gap-1">
                            <span v-for="(task, tIdx) in day.tasks.slice(0, 2)" :key="tIdx"
                                class="text-xs text-gray-700 dark:text-gray-300 truncate block max-w-[120px]">
                                {{ task.title }}
                            </span>
                            <span v-if="day.tasks.length > 2" class="text-xs text-indigo-500 font-medium">
                                +{{ day.tasks.length - 2 }}
                            </span>
                            <span v-if="day.hasExtraTasks"
                                class="text-xs text-amber-600 font-medium">
                                {{ day.extraTasksCount }} extra
                            </span>
                            <span v-if="day.absences?.some(a => a.type === 'vacation')"
                                class="text-xs text-purple-600 font-medium">Vac</span>
                            <span v-if="day.absences?.some(a => a.type === 'time_off')"
                                class="text-xs text-orange-600 font-medium">Perm</span>
                        </div>
                    </div>
                </div>
                <div v-if="!day.isOtherMonth && day.holidayName"
                    class="text-[10px] text-red-500 font-medium leading-tight mb-1 mt-1 hidden sm:block">
                    {{ day.holidayName }}
                </div>
                <div v-if="!day.isOtherMonth" class="flex-col gap-[3px] mt-2 hidden sm:flex">
                    <div v-for="(task, tIdx) in day.tasks.slice(0, 4)" :key="tIdx"
                        class="h-2 rounded"
                        :class="statusColors[task.status] || 'bg-gray-400'"
                        :title="task.time_range + ' - ' + task.title">
                    </div>
                    <div v-if="day.tasks.length > 4"
                        class="text-[10px] text-gray-400 dark:text-gray-500 font-medium leading-tight pl-0.5">
                        +{{ day.tasks.length - 4 }} mas
                    </div>
                    <div v-if="day.hasExtraTasks" class="flex items-center gap-[2px] mt-1">
                        <span v-for="n in Math.min(day.extraTasksCount, 5)" :key="n"
                            class="h-2 rounded-sm w-4 bg-amber-400 dark:bg-amber-500"
                            :title="day.extraTasksCount + ' tarea' + (day.extraTasksCount !== 1 ? 's' : '') + ' extra'">
                        </span>
                        <span v-if="day.extraTasksCount > 5"
                            class="text-[10px] text-amber-600 dark:text-amber-400 font-medium ml-0.5">
                            +{{ day.extraTasksCount - 5 }}
                        </span>
                    </div>
                    <div v-if="day.absences?.length" class="flex flex-wrap gap-1 mt-1">
                        <span v-for="(a, aIdx) in day.absences" :key="aIdx"
                            class="inline-flex items-center gap-0.5 text-[10px] font-medium px-1.5 py-0.5 rounded-full"
                            :class="a.type === 'vacation' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300' : 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300'">
                            {{ a.label }}
                        </span>
                    </div>
                </div>
                <button v-if="!day.isOtherMonth && canCreate && !day.isNonWorkingDay && !allBlocksFull(day, workBlocks)"
                    @click.stop="emit('createTask', day.date, '09:00-11:00')"
                    class="absolute top-1 right-1 p-1 rounded-lg opacity-0 group-hover:opacity-100 hover:bg-indigo-100 dark:hover:bg-indigo-800 transition hidden sm:block">
                    <Plus class="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400" />
                </button>
            </div>
        </div>
    </div>
</template>
