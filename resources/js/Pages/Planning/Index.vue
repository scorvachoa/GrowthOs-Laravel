<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import ExportPdfModal from '@/Components/ExportPdfModal.vue'
import ConfirmDeleteModal from '@/Components/Modals/ConfirmDelete.vue'
import { ChevronLeft, ChevronRight, FileDown } from 'lucide-vue-next'

import CalendarMonth from './Components/CalendarMonth.vue'
import CalendarWeek from './Components/CalendarWeek.vue'
import DaySidebar from './Components/DaySidebar.vue'
import ExtraTaskModal from './Components/ExtraTaskModal.vue'
import { usePlanning } from './composables/usePlanning.js'

const props = defineProps({
    calendar: Object,
    initial_view: String,
})

const {
    can, statusColors, statusLabels,
    currentYear, currentMonth, currentWeekStart, viewMode,
    snapshot, selectedDate, dayTasks, extraTasks, dayObservation,
    showSidebar, showDeleteModal, showExtraDeleteModal,
    deleteTarget, extraDeleteTarget, loading, showPdfModal,
    showExtraModal, editingExtra,
    monthName, calendarDays,
    weekDays, weekName, hours, weekTaskPlacements,
    fetchSnapshot, fetchDayTasks,
    goToday, prevMonth, nextMonth, prevWeek, nextWeek, setView,
    openDay, closeSidebar,
    createTask, viewTask, editTask,
    confirmDeleteTask, executeDelete,
    updateTaskStatus, updateExtraTaskStatus, saveObservation,
    openExtraModal, closeExtraModal, saveExtraTask,
    confirmDeleteExtra, executeExtraDelete,
} = usePlanning(props)
</script>

<template>
    <AppLayout>
        <div class="space-y-4 sm:space-y-6">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-4 sm:p-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4 mb-4 sm:mb-6">
                    <div class="flex items-center gap-2 sm:gap-3">
                        <div class="flex rounded-xl overflow-hidden border border-gray-300 dark:border-gray-700">
                            <button @click="setView('month')"
                                class="px-3 sm:px-4 py-2 text-xs sm:text-sm font-medium transition"
                                :class="viewMode === 'month' ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300'">
                                Mes
                            </button>
                            <button @click="setView('week')"
                                class="px-3 sm:px-4 py-2 text-xs sm:text-sm font-medium transition"
                                :class="viewMode === 'week' ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300'">
                                Semana
                            </button>
                        </div>
                        <button @click="goToday"
                            class="px-3 sm:px-4 py-2 rounded-xl border border-gray-300 dark:border-gray-700 text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                            Hoy
                        </button>
                    </div>

                    <div class="flex items-center justify-center gap-2 sm:gap-4 order-first sm:order-none">
                        <button @click="viewMode === 'month' ? prevMonth() : prevWeek()"
                            class="p-2 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700 transition shrink-0">
                            <ChevronLeft class="w-5 h-5 text-gray-600 dark:text-gray-400" />
                        </button>
                        <h2 class="text-sm sm:text-lg font-bold text-gray-900 dark:text-white capitalize text-center truncate max-w-[160px] sm:max-w-none">
                            {{ viewMode === 'month' ? monthName : weekName }}
                        </h2>
                        <button @click="viewMode === 'month' ? nextMonth() : nextWeek()"
                            class="p-2 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700 transition shrink-0">
                            <ChevronRight class="w-5 h-5 text-gray-600 dark:text-gray-400" />
                        </button>
                    </div>
                    <button v-if="can('export planning')" @click="showPdfModal = true"
                        class="px-4 sm:px-5 py-2 sm:py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-medium transition flex items-center gap-2 text-sm">
                        <FileDown class="w-4 h-4" />
                        <span class="hidden sm:inline">Exportar PDF</span>
                    </button>
                </div>

                <div v-if="loading" class="text-center py-12 text-gray-500">Cargando...</div>

                <template v-if="!loading">
                    <CalendarMonth v-if="viewMode === 'month'"
                        :days="calendarDays"
                        :work-blocks="snapshot.work_blocks"
                        :can-create="can('create planning')"
                        @openDay="openDay"
                        @createTask="createTask" />

                    <CalendarWeek v-if="viewMode === 'week'"
                        :days="weekDays"
                        :hours="hours"
                        :work-blocks="snapshot.work_blocks"
                        :task-placements="weekTaskPlacements"
                        :status-colors="statusColors"
                        :status-labels="statusLabels"
                        :can-create="can('create planning')"
                        @openDay="openDay"
                        @createTask="createTask"
                        @openExtraModal="openExtraModal"
                        @viewTask="viewTask" />
                </template>

                <div class="flex items-center gap-4 mt-4 text-xs text-gray-500 dark:text-gray-400">
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-indigo-500"></span> Tarea de video</span>
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-amber-400"></span> Tarea extra</span>
                </div>
            </div>
        </div>

        <transition name="fade">
            <div v-if="showSidebar" class="fixed inset-0 z-40 bg-black/30" @click="closeSidebar"></div>
        </transition>

        <transition name="slide">
            <DaySidebar v-if="showSidebar && selectedDate"
                :selected-date="selectedDate"
                :day-tasks="dayTasks"
                :extra-tasks="extraTasks"
                :statuses="snapshot.statuses"
                :status-labels="statusLabels"
                :holiday="snapshot.holidays_map?.[selectedDate]"
                :observation="dayObservation"
                :absences="snapshot.absences_map?.[selectedDate] || []"
                :can-create="can('create planning')"
                :can-edit="can('edit planning')"
                :can-delete="can('delete planning')"
                @close="closeSidebar"
                @createTask="createTask"
                @viewTask="viewTask"
                @editTask="editTask"
                @deleteTask="confirmDeleteTask"
                @updateStatus="updateTaskStatus"
                @openExtraModal="openExtraModal"
                @deleteExtra="confirmDeleteExtra"
                @updateExtraStatus="updateExtraTaskStatus"
                @saveObservation="saveObservation" />
        </transition>

        <ExtraTaskModal
            :show="showExtraModal"
            :editing-extra="editingExtra"
            :selected-date="selectedDate"
            :can-delete="can('delete planning')"
            @close="closeExtraModal"
            @save="saveExtraTask"
            @delete="confirmDeleteExtra" />

        <ConfirmDeleteModal
            :show="showDeleteModal" title="Eliminar tarea"
            :message="'Se eliminara la tarea: ' + (deleteTarget?.title || '')"
            @close="showDeleteModal = false"
            @confirm="executeDelete" />

        <ConfirmDeleteModal
            :show="showExtraDeleteModal" title="Eliminar tarea extra"
            :message="'Se eliminara la tarea extra: ' + (extraDeleteTarget?.title || '')"
            @close="showExtraDeleteModal = false"
            @confirm="executeExtraDelete" />

        <ExportPdfModal :show="showPdfModal" @close="showPdfModal = false" />
    </AppLayout>
</template>

<style scoped>
.slide-enter-active, .slide-leave-active {
    transition: transform 0.25s ease;
}
.slide-enter-from, .slide-leave-to {
    transform: translateX(100%);
}
.fade-enter-active, .fade-leave-active {
    transition: opacity 0.2s;
}
.fade-enter-from, .fade-leave-to {
    opacity: 0;
}
</style>
