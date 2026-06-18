<script setup>
import { Plus } from 'lucide-vue-next'

defineProps({
    days: Array,
    hours: Array,
    workBlocks: Array,
    taskPlacements: Array,
    statusColors: Object,
    statusLabels: Object,
    canCreate: Boolean,
})

const emit = defineEmits(['openDay', 'createTask', 'openExtraModal', 'viewTask'])

function hourRow(h) {
    return h + 2
}

function taskStartHour(task) {
    return parseInt(task.time_range?.split('-')[0]?.split(':')[0]) || 0
}

function taskEndHour(task) {
    return parseInt(task.time_range?.split('-')[1]?.split(':')[0]) || 0
}

function blockForHour(hour, blocks) {
    return blocks?.find(b => {
        const [s, e] = b.split('-').map(p => parseInt(p.split(':')[0]))
        return hour >= s && hour < e
    }) || blocks?.[0] || '09:00-11:00'
}

function isHourOccupied(day, hour) {
    return day.tasks?.some(t => taskStartHour(t) === hour) ?? false
}
</script>

<template>
    <div class="overflow-x-auto -mx-4 sm:mx-0">
        <div class="min-w-[640px] grid grid-cols-[80px_repeat(7,1fr)] border border-gray-200 dark:border-gray-700 rounded-xl sm:rounded-xl overflow-hidden">
        <div class="border-r border-b border-gray-200 dark:border-gray-700 p-2 bg-gray-50 dark:bg-gray-800/50"
            :style="{ gridColumn: 1, gridRow: 1 }"></div>
        <template v-for="(day, dIdx) in days" :key="day.date">
            <div :style="{ gridColumn: dIdx + 2, gridRow: 1 }"
                class="border-r border-b border-gray-200 dark:border-gray-700 p-2 text-center min-w-0"
                :class="day.isToday ? 'bg-indigo-50 dark:bg-indigo-900/20' : 'bg-gray-50 dark:bg-gray-800/50'">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ day.weekday }}</div>
                <div class="text-lg font-bold"
                    :class="day.isToday ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-900 dark:text-white'">
                    {{ day.day }}
                </div>
                <div v-if="day.holidayName" class="text-[10px] text-red-500 font-medium">{{ day.holidayName }}</div>
                <div v-if="day.absences?.length" class="flex flex-wrap justify-center gap-0.5 mt-0.5">
                    <span v-for="(a, aIdx) in day.absences" :key="aIdx"
                        class="text-[9px] font-medium px-1 py-px rounded-full leading-tight"
                        :class="a.type === 'vacation' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300' : 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300'">
                        {{ a.label }}
                    </span>
                </div>
            </div>
        </template>
        <template v-for="(hour, hIdx) in hours" :key="'h' + hour">
            <div :style="{ gridColumn: 1, gridRow: hourRow(hIdx) }"
                class="border-r border-b border-gray-200 dark:border-gray-700 p-1 text-xs font-medium text-gray-500 dark:text-gray-400 flex items-start justify-center pt-2 bg-gray-50 dark:bg-gray-800/50">
                {{ String(hour).padStart(2, '0') }}:00
            </div>
            <template v-for="(day, dIdx) in days" :key="day.date + 'h' + hour">
                <div :style="{ gridColumn: dIdx + 2, gridRow: hourRow(hIdx) }"
                    @click="!day.isNonWorkingDay && emit('openDay', day.date)"
                    class="border-r border-b border-gray-200 dark:border-gray-700 p-0.5 cursor-pointer group relative min-w-0 min-h-[28px]"
                    :class="day.isNonWorkingDay ? 'bg-gray-50 dark:bg-gray-800/50 cursor-default' : 'hover:bg-gray-50 dark:hover:bg-gray-700/50'">
                    <button v-if="canCreate && !day.isNonWorkingDay && !isHourOccupied(day, hour)"
                        @click.stop="emit('createTask', day.date, blockForHour(hour, workBlocks))"
                        class="absolute top-0 right-0 p-px rounded opacity-0 group-hover:opacity-100 hover:bg-indigo-100 dark:hover:bg-indigo-800 transition z-20">
                        <Plus class="w-2.5 h-2.5 text-indigo-600 dark:text-indigo-400" />
                    </button>
                </div>
            </template>
        </template>
        <div v-for="p in taskPlacements" :key="p.id"
            :style="{
                gridColumn: p._col,
                gridRow: p._row,
                ...(p._offset !== undefined
                    ? { position: 'relative', width: `calc(100% / ${p._total})`, left: `calc(100% / ${p._total} * ${p._offset})` }
                    : {}),
            }"
            @click.stop="p._type === 'video' ? emit('viewTask', p.id) : emit('openExtraModal', p)"
            class="z-10 rounded-sm px-1 py-0.5 m-[3px] text-[10px] leading-[14px] cursor-pointer overflow-hidden break-words"
            :class="p._type === 'video'
                ? (statusColors[p.status] || 'bg-gray-500') + ' text-white'
                : (p.location === 'fuera'
                    ? 'bg-orange-50 dark:bg-orange-900/20 border border-dashed border-orange-300 dark:border-orange-700 text-orange-700 dark:text-orange-300'
                    : 'bg-teal-50 dark:bg-teal-900/20 border border-dashed border-teal-300 dark:border-teal-700 text-teal-700 dark:text-teal-300')"
            :title="(p._type === 'extra' ? '[Extra] ' : '') + p.title + ' (' + p.time_range + ', ' + (statusLabels[p.status] || p.status) + ')'">
            {{ p.title }}
            </div>
        </div>
    </div>
</template>
