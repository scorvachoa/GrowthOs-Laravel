<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { router, Link, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import ExportPdfModal from '@/Components/ExportPdfModal.vue'
import { ChevronLeft, ChevronRight, FileDown } from 'lucide-vue-next'

import CalendarMonth from './Components/CalendarMonth.vue'
import CalendarWeek from './Components/CalendarWeek.vue'
import DaySidebar from './Components/DaySidebar.vue'
import ExtraTaskModal from './Components/ExtraTaskModal.vue'

const props = defineProps({
    calendar: Object,
    initial_view: String,
})

const page = usePage()
const permissions = page.props.auth?.user?.permissions ?? []
const can = (perm) => permissions.includes(perm)
const workingDays = computed(() => page.props.auth?.user?.settings?.working_days ?? [1,2,3,4,5])

function isNonWorkingDay(dayOfWeek) {
    return !workingDays.value.includes(dayOfWeek)
}

const currentYear = ref(props.calendar.year)
const currentMonth = ref(props.calendar.month)
const currentWeekStart = ref(props.calendar.week_start)
const viewMode = ref(props.initial_view || 'month')
const snapshot = ref(props.calendar)
const selectedDate = ref(null)
const dayTasks = ref([])
const extraTasks = ref([])
const showSidebar = ref(false)
const showDeleteModal = ref(false)
const showExtraDeleteModal = ref(false)
const deleteTarget = ref(null)
const extraDeleteTarget = ref(null)
const loading = ref(false)
const showPdfModal = ref(false)
const dragging = ref(null)
const showExtraModal = ref(false)
const editingExtra = ref(null)

function parseDate(str) {
    const [y, m, d] = str.split('-').map(Number)
    return new Date(y, m - 1, d)
}

const statusColors = {
    pending: 'bg-yellow-500',
    script_ready: 'bg-blue-500',
    editing: 'bg-purple-500',
    review: 'bg-orange-500',
    scheduled: 'bg-indigo-500',
    published: 'bg-green-500',
    cancelled: 'bg-red-500',
}

const statusLabels = computed(() => {
    const map = {}
    for (const s of snapshot.value.statuses || []) {
        map[s.value] = s.label
    }
    return map
})

const monthName = computed(() => {
    const date = new Date(currentYear.value, currentMonth.value - 1, 1)
    return date.toLocaleString('es', { month: 'long', year: 'numeric' })
})

const daysInMonth = computed(() => {
    return new Date(currentYear.value, currentMonth.value, 0).getDate()
})

const firstDayOfMonth = computed(() => {
    return new Date(currentYear.value, currentMonth.value - 1, 1).getDay()
})

const calendarDays = computed(() => {
    const days = []
    for (let i = 0; i < firstDayOfMonth.value; i++) {
        days.push(null)
    }
    for (let d = 1; d <= daysInMonth.value; d++) {
        const dateObj = new Date(currentYear.value, currentMonth.value - 1, d)
        const dateStr = formatDate(dateObj)
        const dayOfWeek = dateObj.getDay()
        const isToday = dateStr === snapshot.value.today
        const isHoliday = snapshot.value.holidays_map?.[dateStr]
        const blocks = snapshot.value.blocks_map?.[dateStr] || {}
        const tasks = snapshot.value.tasks_detail_map?.[dateStr] || []
        const count = snapshot.value.tasks_count?.[dateStr] || 0
        const hasExtraTasks = !!snapshot.value.has_extra_tasks_map?.[dateStr]
        days.push({
            day: d,
            date: dateStr,
            isNonWorkingDay: isNonWorkingDay(dayOfWeek),
            isToday,
            isHoliday,
            holidayName: isHoliday || null,
            blocks,
            tasks,
            count,
            hasExtraTasks,
        })
    }
    return days
})

const weekDays = computed(() => {
    const start = parseDate(currentWeekStart.value)
    const days = []
    for (let i = 0; i < 7; i++) {
        const d = new Date(start)
        d.setDate(start.getDate() + i)
        const dateStr = formatDate(d)
        const dayOfWeek = d.getDay()
        const isToday = dateStr === snapshot.value.today
        const isHoliday = snapshot.value.holidays_map?.[dateStr]
        const blocks = snapshot.value.week_blocks_map?.[dateStr] || {}
        const tasks = snapshot.value.week_tasks_detail_map?.[dateStr] || []
        const extras = snapshot.value.week_extra_tasks_detail_map?.[dateStr] || []
        days.push({
            day: d.getDate(),
            date: dateStr,
            weekday: d.toLocaleString('es', { weekday: 'short' }),
            isNonWorkingDay: isNonWorkingDay(dayOfWeek),
            isToday,
            isHoliday,
            holidayName: isHoliday || null,
            blocks,
            tasks,
            extraTasks: extras,
        })
    }
    return days
})

const weekName = computed(() => {
    const start = parseDate(currentWeekStart.value)
    const end = new Date(start)
    end.setDate(start.getDate() + 6)
    const opts = { day: 'numeric', month: 'long' }
    return `${start.toLocaleString('es', opts)} - ${end.toLocaleString('es', opts)}`
})

const hours = computed(() => {
    const settings = page.props.auth?.user?.settings || {}
    const startHour = parseInt(settings.default_work_start?.split(':')[0] || '9')
    const endHour = parseInt(settings.default_work_end?.split(':')[0] || '18')
    const h = []
    for (let i = startHour; i < endHour; i++) {
        h.push(i)
    }
    return h
})

const weekTaskPlacements = computed(() => {
    const placements = []
    const hList = hours.value
    const byDayHour = {}
    weekDays.value.forEach((day, dayIdx) => {
        const col = dayIdx + 2
        const key = day.date
        byDayHour[key] = {}
        day.tasks.forEach(task => {
            const startH = taskStartHour(task)
            const endH = taskEndHour(task)
            const startRow = hList.indexOf(startH)
            if (startRow === -1) return
            const duration = Math.max(1, endH - startH)
            if (!byDayHour[key][startH]) byDayHour[key][startH] = []
            byDayHour[key][startH].push({ ...task, _type: 'video', _col: col, _row: `${startRow + 2} / span ${duration}`, _duration: duration })
        })
        day.extraTasks.forEach(task => {
            const startH = taskStartHour(task)
            const endH = taskEndHour(task)
            const startRow = hList.indexOf(startH)
            if (startRow === -1) return
            const duration = Math.max(1, endH - startH)
            if (!byDayHour[key][startH]) byDayHour[key][startH] = []
            byDayHour[key][startH].push({ ...task, _type: 'extra', _col: col, _row: `${startRow + 2} / span ${duration}`, _duration: duration })
        })
    })
    for (const dayKey of Object.keys(byDayHour)) {
        for (const startH of Object.keys(byDayHour[dayKey])) {
            const group = byDayHour[dayKey][startH]
            if (group.length === 1) {
                placements.push(group[0])
            } else {
                group.forEach((p, i) => {
                    placements.push({ ...p, _offset: i, _total: group.length })
                })
            }
        }
    }
    return placements
})

function formatDate(date) {
    const y = date.getFullYear()
    const m = String(date.getMonth() + 1).padStart(2, '0')
    const d = String(date.getDate()).padStart(2, '0')
    return `${y}-${m}-${d}`
}

function taskStartHour(task) {
    return parseInt(task.time_range?.split('-')[0]?.split(':')[0]) || 0
}

function taskEndHour(task) {
    return parseInt(task.time_range?.split('-')[1]?.split(':')[0]) || 0
}

async function fetchSnapshot() {
    loading.value = true
    try {
        const params = { year: currentYear.value, month: currentMonth.value }
        if (viewMode.value === 'week') {
            params.week_start = currentWeekStart.value
        }
        const res = await axios.get('/planning/calendar/snapshot', { params })
        snapshot.value = res.data
    } catch (e) {
        console.error('Failed to fetch snapshot', e)
    } finally {
        loading.value = false
    }
}

async function fetchDayTasks(date) {
    try {
        const [tasksRes, extraRes] = await Promise.all([
            axios.get('/planning/tasks', { params: { fecha: date } }),
            axios.get('/extra-tasks', { params: { fecha: date } }),
        ])
        dayTasks.value = tasksRes.data
        extraTasks.value = extraRes.data
    } catch (e) {
        dayTasks.value = []
        extraTasks.value = []
    }
}

function goToday() {
    const today = new Date()
    currentYear.value = today.getFullYear()
    currentMonth.value = today.getMonth() + 1
    const mon = today.getDay() === 0 ? 6 : today.getDay() - 1
    const weekStart = new Date(today)
    weekStart.setDate(today.getDate() - mon)
    currentWeekStart.value = formatDate(weekStart)
    updateUrl()
}

function prevMonth() {
    currentMonth.value--
    if (currentMonth.value < 1) {
        currentMonth.value = 12
        currentYear.value--
    }
    updateUrl()
}

function nextMonth() {
    currentMonth.value++
    if (currentMonth.value > 12) {
        currentMonth.value = 1
        currentYear.value++
    }
    updateUrl()
}

function prevWeek() {
    const d = parseDate(currentWeekStart.value)
    d.setDate(d.getDate() - 7)
    currentWeekStart.value = formatDate(d)
    if (d.getMonth() + 1 !== currentMonth.value || d.getFullYear() !== currentYear.value) {
        currentYear.value = d.getFullYear()
        currentMonth.value = d.getMonth() + 1
    }
    updateUrl()
    fetchSnapshot()
}

function nextWeek() {
    const d = parseDate(currentWeekStart.value)
    d.setDate(d.getDate() + 7)
    currentWeekStart.value = formatDate(d)
    if (d.getMonth() + 1 !== currentMonth.value || d.getFullYear() !== currentYear.value) {
        currentYear.value = d.getFullYear()
        currentMonth.value = d.getMonth() + 1
    }
    updateUrl()
    fetchSnapshot()
}

function setView(mode) {
    viewMode.value = mode
    updateUrl()
}

function updateUrl() {
    const params = new URLSearchParams({ year: currentYear.value, month: currentMonth.value, view: viewMode.value })
    if (viewMode.value === 'week') {
        params.set('week_start', currentWeekStart.value)
    }
    window.history.replaceState({}, '', `/planning?${params}`)
}

watch([currentYear, currentMonth], () => {
    fetchSnapshot()
})

watch(viewMode, () => {
    fetchSnapshot()
})

async function openDay(date) {
    selectedDate.value = date
    await fetchDayTasks(date)
    showSidebar.value = true
}

function closeSidebar() {
    showSidebar.value = false
    selectedDate.value = null
    dayTasks.value = []
}

function createTask(fecha, bloque) {
    const params = new URLSearchParams({ fecha, bloque }).toString()
    window.location.href = `/video-tasks/create?${params}`
}

function viewTask(id) {
    window.location.href = `/video-tasks/${id}`
}

function editTask(id) {
    window.location.href = `/video-tasks/${id}/edit`
}

function confirmDeleteTask(task) {
    deleteTarget.value = task
    showDeleteModal.value = true
}

function executeDelete() {
    if (!deleteTarget.value) return
    router.delete(`/video-tasks/${deleteTarget.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false
            deleteTarget.value = null
            if (selectedDate.value) fetchDayTasks(selectedDate.value)
            fetchSnapshot()
        },
    })
}

function updateTaskStatus(task, status) {
    axios.patch(`/video-tasks/${task.id}/status`, { status }).then(() => {
        if (selectedDate.value) fetchDayTasks(selectedDate.value)
        fetchSnapshot()
    })
}

function openExtraModal(task = null) {
    editingExtra.value = task
    showExtraModal.value = true
}

function closeExtraModal() {
    showExtraModal.value = false
    editingExtra.value = null
}

async function saveExtraTask(form) {
    const payload = {
        task_date: form.task_date,
        time_range: form.time_range,
        title: form.title,
        status: form.status,
        location: form.location,
    }
    try {
        if (editingExtra.value) {
            const id = String(editingExtra.value.id).replace(/^e/, '')
            await axios.patch(`/extra-tasks/${id}`, payload)
        } else {
            await axios.post('/extra-tasks', payload)
        }
        closeExtraModal()
        if (selectedDate.value) fetchDayTasks(selectedDate.value)
        fetchSnapshot()
    } catch (e) {
        console.error('Failed to save extra task', e)
    }
}

function confirmDeleteExtra(task) {
    extraDeleteTarget.value = task
    showExtraDeleteModal.value = true
}

async function executeExtraDelete() {
    if (!extraDeleteTarget.value) return
    try {
        const id = String(extraDeleteTarget.value.id).replace(/^e/, '')
        await axios.delete(`/extra-tasks/${id}`)
        showExtraDeleteModal.value = false
        extraDeleteTarget.value = null
        if (selectedDate.value) fetchDayTasks(selectedDate.value)
        fetchSnapshot()
    } catch (e) {
        console.error('Failed to delete extra task', e)
    }
}
</script>

<template>
    <AppLayout>
        <div class="space-y-6">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-6">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
                    <div class="flex items-center gap-3">
                        <div class="flex rounded-xl overflow-hidden border border-gray-300 dark:border-gray-700">
                            <button @click="setView('month')"
                                class="px-4 py-2 text-sm font-medium transition"
                                :class="viewMode === 'month' ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300'">
                                Mes
                            </button>
                            <button @click="setView('week')"
                                class="px-4 py-2 text-sm font-medium transition"
                                :class="viewMode === 'week' ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300'">
                                Semana
                            </button>
                        </div>
                        <button @click="goToday"
                            class="px-4 py-2 rounded-xl border border-gray-300 dark:border-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                            Hoy
                        </button>
                    </div>

                    <div class="flex items-center gap-4">
                        <button @click="viewMode === 'month' ? prevMonth() : prevWeek()"
                            class="p-2 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                            <ChevronLeft class="w-5 h-5 text-gray-600 dark:text-gray-400" />
                        </button>
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white capitalize min-w-[220px] text-center">
                            {{ viewMode === 'month' ? monthName : weekName }}
                        </h2>
                        <button @click="viewMode === 'month' ? nextMonth() : nextWeek()"
                            class="p-2 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                            <ChevronRight class="w-5 h-5 text-gray-600 dark:text-gray-400" />
                        </button>
                    </div>
                    <button v-if="can('export planning')" @click="showPdfModal = true"
                        class="px-5 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-medium transition flex items-center gap-2">
                        <FileDown class="w-4 h-4" />
                        Exportar PDF
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
            <DaySidebar v-if="showSidebar"
                :selected-date="selectedDate"
                :day-tasks="dayTasks"
                :extra-tasks="extraTasks"
                :statuses="snapshot.statuses"
                :status-labels="statusLabels"
                :holiday="snapshot.holidays_map?.[selectedDate]"
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
                @deleteExtra="confirmDeleteExtra" />
        </transition>

        <ExtraTaskModal
            :show="showExtraModal"
            :editing-extra="editingExtra"
            :selected-date="selectedDate"
            :can-delete="can('delete planning')"
            @close="closeExtraModal"
            @save="saveExtraTask"
            @delete="confirmDeleteExtra" />

        <transition name="fade">
            <div v-if="showExtraDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-md p-6">
                    <h2 class="text-xl font-bold mb-4 text-gray-900 dark:text-white">Confirmar eliminacion</h2>
                    <p class="text-gray-600 dark:text-gray-300 mb-2">Se eliminara la tarea extra:</p>
                    <p class="font-semibold text-gray-900 dark:text-white mb-6">{{ extraDeleteTarget?.title }}</p>
                    <div class="flex justify-end gap-3">
                        <button @click="showExtraDeleteModal = false"
                            class="px-4 py-2 rounded-xl border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300">
                            Cancelar
                        </button>
                        <button @click="executeExtraDelete"
                            class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white">
                            Eliminar
                        </button>
                    </div>
                </div>
            </div>
        </transition>

        <transition name="fade">
            <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-md p-6">
                    <h2 class="text-xl font-bold mb-4 text-gray-900 dark:text-white">Confirmar eliminacion</h2>
                    <p class="text-gray-600 dark:text-gray-300 mb-2">Se eliminara la tarea:</p>
                    <p class="font-semibold text-gray-900 dark:text-white mb-6">{{ deleteTarget?.title }}</p>
                    <div class="flex justify-end gap-3">
                        <button @click="showDeleteModal = false"
                            class="px-4 py-2 rounded-xl border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300">
                            Cancelar
                        </button>
                        <button @click="executeDelete"
                            class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white">
                            Eliminar
                        </button>
                    </div>
                </div>
            </div>
        </transition>

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
