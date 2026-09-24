<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import ExportPdfModal from '@/Components/ExportPdfModal.vue'
import ConfirmDeleteModal from '@/Components/Modals/ConfirmDelete.vue'
import SkeletonLoader from '@/Components/UI/SkeletonLoader.vue'
import { ChevronLeft, ChevronRight, FileDown, Clock, Search, SearchX, Calendar } from 'lucide-vue-next'

import CalendarMonth from './Components/CalendarMonth.vue'
import CalendarWeek from './Components/CalendarWeek.vue'
import DaySidebar from './Components/DaySidebar.vue'
import ExtraTaskModal from './Components/ExtraTaskModal.vue'
import RestorePendingModal from './Components/RestorePendingModal.vue'
import { usePlanning } from './composables/usePlanning.js'
import { onMounted, onUnmounted, ref } from 'vue'

const props = defineProps({
    calendar: Object,
    initial_view: String,
})

const {
    can, statusColors, statusLabels, workingDays,
    currentYear, currentMonth, currentWeekStart, viewMode,
    snapshot, selectedDate, dayTasks, extraTasks, dayObservation,
    showSidebar, showDeleteModal, showExtraDeleteModal,
    deleteTarget, extraDeleteTarget, loading, showPdfModal,
    showExtraModal, editingExtra,
    pendingTasks, showRestoreModal, restoringTask,
    monthName, calendarDays,
    weekDays, weekName, hours, weekTaskPlacements,
    fetchSnapshot, fetchDayTasks, fetchPendingTasks,
    goToday, prevMonth, nextMonth, prevWeek, nextWeek, setView,
    openDay, closeSidebar,
    createTask, viewTask, editTask,
    confirmDeleteTask, executeDelete,
    updateTaskStatus, updateExtraTaskStatus, saveObservation,
    createSession, completeSession, editSession, deleteSession,
    openExtraModal, closeExtraModal, saveExtraTask,
    confirmDeleteExtra, executeExtraDelete,
    moveToPending, openRestoreModal, closeRestoreModal, restoreFromPending,
    searchQuery, searchResults, searchedDates, showSearchResults, highlightDate, searching,
    goToSearchResult, clearSearch,
} = usePlanning(props)

const searchContainer = ref(null)

function handleClickOutside(e) {
    if (searchContainer.value && !searchContainer.value.contains(e.target)) {
        showSearchResults.value = false
        highlightDate.value = null
    }
}

onMounted(() => {
    if (viewMode.value === 'pending') fetchPendingTasks()
    window.addEventListener('share-accepted', fetchSnapshot)
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    window.removeEventListener('share-accepted', fetchSnapshot)
    document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
    <AppLayout>
        <div class="space-y-4 sm:space-y-6">
            <!-- Skeleton loader while data loads -->
            <template v-if="loading && !snapshot.tasks_count">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-4 sm:p-6">
                    <SkeletonLoader type="calendar" />
                </div>
            </template>

            <div v-else class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-4 sm:p-6">
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
                            <button @click="setView('pending')"
                                class="px-3 sm:px-4 py-2 text-xs sm:text-sm font-medium transition flex items-center gap-1.5"
                                :class="viewMode === 'pending' ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300'">
                                <Clock class="w-4 h-4" />
                                Pendientes
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

                    <!-- Search -->
                    <div v-if="viewMode !== 'pending'" class="relative" ref="searchContainer">
                        <div class="relative">
                            <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                            <input v-model="searchQuery" type="text" placeholder="Buscar tarea..."
                                @focus="showSearchResults = searchQuery.length > 0"
                                @keydown.escape="clearSearch"
                                class="w-full sm:w-64 pl-9 pr-8 py-2 text-sm rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition" />
                            <button v-if="searchQuery" @click="clearSearch"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                <SearchX class="w-4 h-4" />
                            </button>
                        </div>
                        <transition name="fade">
                            <div v-if="showSearchResults"
                                class="absolute top-full left-0 right-0 mt-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-lg z-50 max-h-80 overflow-y-auto">
                                <div v-if="searching" class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 text-center">
                                    Buscando...
                                </div>
                                <div v-else-if="searchQuery.trim().length < 2" class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 text-center">
                                    Escribe al menos 2 caracteres
                                </div>
                                <div v-else-if="searchResults.length === 0" class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 text-center">
                                    Sin resultados para "{{ searchQuery.trim() }}"
                                </div>
                                <template v-else>
                                    <div v-for="result in searchResults" :key="`${result.type}-${result.date}-${result.id}`"
                                        @click="goToSearchResult(result)"
                                        class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer transition border-b border-gray-100 dark:border-gray-700 last:border-0">
                                        <div class="p-1.5 bg-indigo-100 dark:bg-indigo-900/50 rounded-lg shrink-0">
                                            <Calendar class="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400" />
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ result.title }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ result.date }} · {{ result.time_range }}</p>
                                        </div>
                                        <span class="text-[10px] font-medium px-1.5 py-0.5 rounded-full shrink-0"
                                            :class="statusColors[result.status] || 'bg-gray-100 text-gray-600'">
                                            {{ statusLabels[result.status] || result.status }}
                                        </span>
                                    </div>
                                </template>
                            </div>
                        </transition>
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
                        :searched-dates="searchedDates"
                        :highlight-date="highlightDate"
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
                        :highlight-date="highlightDate"
                        @openDay="openDay"
                        @createTask="createTask"
                        @openExtraModal="openExtraModal"
                        @viewTask="viewTask" />

                    <div v-if="viewMode === 'pending'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                        <div v-if="pendingTasks.length === 0" class="col-span-full text-center py-12 text-gray-500 dark:text-gray-400">
                            No hay tareas pendientes
                        </div>
                        <div v-for="task in pendingTasks" :key="task.id"
                            class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4 border border-gray-200 dark:border-gray-600 hover:shadow-md transition">
                            <div class="flex items-start justify-between mb-2">
                                <h4 class="font-medium text-gray-900 dark:text-white text-sm line-clamp-2">{{ task.title }}</h4>
                                <span class="w-3 h-3 rounded-full shrink-0 ml-2" :class="statusColors[task.status]"></span>
                            </div>
                            <div class="space-y-1 text-xs text-gray-500 dark:text-gray-400 mb-3">
                                <p v-if="task.channel">
                                    <span class="inline-block w-2 h-2 rounded-full mr-1" :style="{ backgroundColor: task.channel.color }"></span>
                                    {{ task.channel.name }}
                                </p>
                                <p>Original: {{ task.original_date }}</p>
                                <p>Bloque: {{ task.time_range }}</p>
                                <p>Creado: {{ task.created_at }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button @click="openRestoreModal(task)"
                                    class="flex-1 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded-lg transition">
                                    Restaurar
                                </button>
                                <button @click="viewTask(task.id)"
                                    class="px-3 py-1.5 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 text-xs font-medium rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                                    Ver
                                </button>
                            </div>
                        </div>
                    </div>
                </template>

                <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-gray-500 dark:text-gray-400">
                        <span class="font-medium text-gray-600 dark:text-gray-300 mr-1">Tareas:</span>
                        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-yellow-500"></span> Pendiente</span>
                        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-blue-500"></span> Guion</span>
                        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-purple-500"></span> Edición</span>
                        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-orange-500"></span> Revision</span>
                        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-indigo-500"></span> Programado</span>
                        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-green-500"></span> Publicado</span>
                        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-red-500"></span> Cancelado</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                        <span class="font-medium text-gray-600 dark:text-gray-300 mr-1">Sesiones:</span>
                        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-amber-500"></span> En progreso</span>
                        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-teal-500"></span> Completado</span>
                        <span class="flex items-center gap-2 ml-2"><span class="w-2 h-2 rounded-sm bg-amber-400"></span> Extra</span>
                    </div>
                </div>
            </div>
        </div>

        <Teleport to="body">
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
                    :work-blocks="snapshot.work_blocks || []"
                    :can-create="can('create planning')"
                    :can-edit="can('edit planning')"
                    :can-delete="can('delete planning')"
                    @close="closeSidebar"
                    @createTask="createTask"
                    @viewTask="viewTask"
                    @editTask="editTask"
                    @deleteTask="confirmDeleteTask"
                    @updateStatus="updateTaskStatus"
                    @createSession="createSession"
                    @completeSession="completeSession"
                    @editSession="editSession"
                    @deleteSession="deleteSession"
                    @openExtraModal="openExtraModal"
                    @deleteExtra="confirmDeleteExtra"
                    @updateExtraStatus="updateExtraTaskStatus"
                    @saveObservation="saveObservation"
                    @moveToPending="moveToPending" />
            </transition>
        </Teleport>

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
            :message="'Se eliminará la tarea: ' + (deleteTarget?.title || '')"
            @close="showDeleteModal = false"
            @confirm="executeDelete" />

        <ConfirmDeleteModal
            :show="showExtraDeleteModal" title="Eliminar tarea extra"
            :message="'Se eliminará la tarea extra: ' + (extraDeleteTarget?.title || '')"
            @close="showExtraDeleteModal = false"
            @confirm="executeExtraDelete" />

        <ExportPdfModal :show="showPdfModal" @close="showPdfModal = false" />

        <RestorePendingModal
            :show="showRestoreModal"
            :task="restoringTask"
            :work-blocks="snapshot.work_blocks"
            :working-days="workingDays"
            @close="closeRestoreModal"
            @restore="restoreFromPending" />
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
