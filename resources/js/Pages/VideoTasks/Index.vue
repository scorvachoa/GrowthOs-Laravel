<script setup>
import { ref, computed } from 'vue'

import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    tasks: {
        type: Object,
        default: () => ({}),
    },
})

const currentDate = ref(new Date())

const selectedDate = ref(null)

const showModal = ref(false)

const monthName = computed(() => {

    return currentDate.value.toLocaleString(
        'default',
        {
            month: 'long',
            year: 'numeric',
        }
    )

})

const daysInMonth = computed(() => {

    const year = currentDate.value.getFullYear()

    const month = currentDate.value.getMonth()

    return new Date(
        year,
        month + 1,
        0
    ).getDate()

})

const firstDayOfMonth = computed(() => {

    const year = currentDate.value.getFullYear()

    const month = currentDate.value.getMonth()

    return new Date(
        year,
        month,
        1
    ).getDay()

})

const calendarDays = computed(() => {

    const days = []

    for (
        let i = 0;
        i < firstDayOfMonth.value;
        i++
    ) {
        days.push(null)
    }

    for (
        let day = 1;
        day <= daysInMonth.value;
        day++
    ) {

        const date = new Date(
            currentDate.value.getFullYear(),
            currentDate.value.getMonth(),
            day
        )

        const formatted = date
            .toISOString()
            .split('T')[0]

        days.push({
            day,
            date: formatted,
            tasks: props.tasks[formatted] || [],
        })
    }

    return days

})

const openDay = (date) => {

    selectedDate.value = date

    showModal.value = true
}

const previousMonth = () => {

    currentDate.value = new Date(
        currentDate.value.getFullYear(),
        currentDate.value.getMonth() - 1,
        1
    )
}

const nextMonth = () => {

    currentDate.value = new Date(
        currentDate.value.getFullYear(),
        currentDate.value.getMonth() + 1,
        1
    )
}
</script>

<template>
    <AppLayout>

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-6">

            <div class="flex items-center justify-between mb-6">

                <button
                    @click="previousMonth"
                    class="px-4 py-2 rounded-lg bg-gray-200 dark:bg-gray-700"
                >
                    Anterior
                </button>

                <h1 class="text-2xl font-bold text-gray-900 dark:text-white capitalize">
                    {{ monthName }}
                </h1>

                <button
                    @click="nextMonth"
                    class="px-4 py-2 rounded-lg bg-gray-200 dark:bg-gray-700"
                >
                    Siguiente
                </button>

            </div>

            <div class="grid grid-cols-7 gap-4">

                <div
                    v-for="dayName in                 ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb']"
                    :key="dayName"
                    class="font-semibold text-center text-gray-500"
                >
                    {{ dayName }}
                </div>

                <div
                    v-for="(day, index) in calendarDays"
                    :key="index"
                    class="min-h-[120px] rounded-xl border border-gray-200 dark:border-gray-700 p-2"
                >

                    <div
                        v-if="day"
                        @click="openDay(day)"
                        class="cursor-pointer h-full"
                    >

                        <div class="font-bold mb-2">
                            {{ day.day }}
                        </div>

                        <div
                            v-for="task in day.tasks.slice(0, 3)"
                            :key="task.id"
                            class="text-xs rounded bg-indigo-500 text-white px-2 py-1 mb-1 truncate"
                        >
                            {{ task.title }}
                        </div>

                    </div>

                </div>

            </div>

            <div
                v-if="showModal"
                class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
            >

                <div class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-2xl p-6 mx-4 max-h-[90vh] overflow-y-auto">

                    <div class="flex items-center justify-between mb-4">

                        <h2 class="text-xl font-bold">
                            Tareas
                        </h2>

                        <button
                            @click="showModal = false"
                            class="text-gray-500"
                        >
                            ✕
                        </button>

                    </div>

                    <div class="space-y-3">

                        <div
                            v-for="task in selectedDate?.tasks || []"
                            :key="task.id"
                            class="p-4 rounded-xl border border-gray-200 dark:border-gray-700"
                        >

                            <div class="font-semibold">
                                {{ task.title }}
                            </div>

                            <div class="text-sm text-gray-500">
                                {{ task.time_range }}
                            </div>

                            <div class="mt-2 flex gap-2">

                                <button
                                    class="px-3 py-1 rounded bg-amber-500 text-white"
                                >
                                    Editar
                                </button>

                                <button
                                    class="px-3 py-1 rounded bg-red-600 text-white"
                                >
                                    Eliminar
                                </button>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </AppLayout>
</template>