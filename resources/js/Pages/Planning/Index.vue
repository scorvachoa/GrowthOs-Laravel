<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { router, Link, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import ExportPdfModal from '@/Components/ExportPdfModal.vue'
import { CalendarDays, ChevronLeft, ChevronRight, Plus, Trash2, ExternalLink, FileDown } from 'lucide-vue-next'

const props = defineProps({
    calendar: Object,
    initial_view: String,
})

const page = usePage()
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
const extraForm = ref({
    task_date: '',
    time_range: '',
    title: '',
    status: 'pending',
    location: 'oficina',
})

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
        const isSunday = dateObj.getDay() === 0
        const isToday = dateStr === snapshot.value.today
        const isHoliday = snapshot.value.holidays_map?.[dateStr]
        const blocks = snapshot.value.blocks_map?.[dateStr] || {}
        const tasks = snapshot.value.tasks_detail_map?.[dateStr] || []
        const count = snapshot.value.tasks_count?.[dateStr] || 0
        days.push({
            day: d,
            date: dateStr,
            isSunday,
            isToday,
            isHoliday,
            holidayName: isHoliday || null,
            blocks,
            tasks,
            count,
        })
    }
    return days
})

const weekDays = computed(() => {
    const start = new Date(currentWeekStart.value)
    const days = []
    for (let i = 0; i < 7; i++) {
        const d = new Date(start)
        d.setDate(start.getDate() + i)
        const dateStr = formatDate(d)
        const isSunday = d.getDay() === 0
        const isToday = dateStr === snapshot.value.today
        const isHoliday = snapshot.value.holidays_map?.[dateStr]
        const blocks = snapshot.value.week_blocks_map?.[dateStr] || {}
        const tasks = snapshot.value.week_tasks_detail_map?.[dateStr] || []
        days.push({
            day: d.getDate(),
            date: dateStr,
            weekday: d.toLocaleString('es', { weekday: 'short' }),
            isSunday,
            isToday,
            isHoliday,
            holidayName: isHoliday || null,
            blocks,
            tasks,
        })
    }
    return days
})

const weekName = computed(() => {
    const start = new Date(currentWeekStart.value)
    const end = new Date(start)
    end.setDate(start.getDate() + 6)
    const opts = { day: 'numeric', month: 'long' }
    return `${start.toLocaleString('es', opts)} - ${end.toLocaleString('es', opts)}`
})

function formatDate(date) {
    const y = date.getFullYear()
    const m = String(date.getMonth() + 1).padStart(2, '0')
    const d = String(date.getDate()).padStart(2, '0')
    return `${y}-${m}-${d}`
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
    const d = new Date(currentWeekStart.value)
    d.setDate(d.getDate() - 7)
    currentWeekStart.value = formatDate(d)
    if (d.getMonth() + 1 !== currentMonth.value || d.getFullYear() !== currentYear.value) {
        currentYear.value = d.getFullYear()
        currentMonth.value = d.getMonth() + 1
    }
    updateUrl()
}

function nextWeek() {
    const d = new Date(currentWeekStart.value)
    d.setDate(d.getDate() + 7)
    currentWeekStart.value = formatDate(d)
    if (d.getMonth() + 1 !== currentMonth.value || d.getFullYear() !== currentYear.value) {
        currentYear.value = d.getFullYear()
        currentMonth.value = d.getMonth() + 1
    }
    updateUrl()
}

function setView(mode) {
    viewMode.value = mode
    updateUrl()
}

function updateUrl() {
    const params = { year: currentYear.value, month: currentMonth.value, view: viewMode.value }
    if (viewMode.value === 'week') {
        params.week_start = currentWeekStart.value
    }
    router.get('/planning', params, { preserveState: true, replace: true })
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

function allBlocksFull(day, blocks) {
    return blocks?.every(b => (day.blocks?.[b] || 0) > 0) ?? false
}

function isBlockOccupied(day, block) {
    return day.tasks?.some(t => t.time_range === block) ?? false
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
    if (task) {
        editingExtra.value = task
        extraForm.value = {
            task_date: task.task_date,
            time_range: task.time_range,
            title: task.title,
            status: task.status,
            location: task.location,
        }
    } else {
        editingExtra.value = null
        extraForm.value = {
            task_date: selectedDate.value || '',
            time_range: '09:00-10:00',
            title: '',
            status: 'pending',
            location: 'oficina',
        }
    }
    showExtraModal.value = true
}

function closeExtraModal() {
    showExtraModal.value = false
    editingExtra.value = null
}

async function saveExtraTask() {
    const form = extraForm.value
    const payload = {
        task_date: form.task_date,
        time_range: form.time_range,
        title: form.title,
        status: form.status,
        location: form.location,
    }

    try {
        if (editingExtra.value) {
            await axios.patch(`/extra-tasks/${editingExtra.value.id}`, payload)
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
        await axios.delete(`/extra-tasks/${extraDeleteTarget.value.id}`)
        showExtraDeleteModal.value = false
        extraDeleteTarget.value = null
        if (selectedDate.value) fetchDayTasks(selectedDate.value)
        fetchSnapshot()
    } catch (e) {
        console.error('Failed to delete extra task', e)
    }
}

function blockCellColor(count) {
    if (count === 0) return ''
    if (count >= 1) return 'bg-indigo-500'
    return ''
}

const dayNames = ['Dom', 'Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab']
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
                    <button @click="showPdfModal = true"
                        class="px-5 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-medium transition flex items-center gap-2">
                        <FileDown class="w-4 h-4" />
                        Exportar PDF
                    </button>
                </div>

                <div v-if="loading" class="text-center py-12 text-gray-500">Cargando...</div>

                <template v-if="!loading">
                    <div v-if="viewMode === 'month'">
                        <div class="grid grid-cols-7 mb-2">
                            <div v-for="name in dayNames" :key="name"
                                class="text-center font-semibold text-sm text-gray-500 dark:text-gray-400 py-2">
                                {{ name }}
                            </div>
                        </div>
                        <div class="grid grid-cols-7 border-l border-t border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                            <template v-for="(day, idx) in calendarDays" :key="idx">
                                <div v-if="!day"
                                    class="min-h-[130px] border-r border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                                </div>
                                <div v-else
                                    @click="!day.isSunday && openDay(day.date)"
                                    class="min-h-[130px] border-r border-b border-gray-200 dark:border-gray-700 p-2 cursor-pointer transition relative group"
                                    :class="[
                                        day.isSunday ? 'bg-gray-50 dark:bg-gray-800/50 cursor-default' : 'hover:bg-gray-50 dark:hover:bg-gray-700/50',
                                        day.isToday ? 'bg-indigo-50 dark:bg-indigo-900/20' : '',
                                    ]">
                                    <div class="flex items-start justify-between mb-1">
                                        <span class="text-sm font-bold"
                                            :class="[
                                                day.isSunday ? 'text-red-400' : day.isToday ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-700 dark:text-gray-300',
                                            ]">
                                            {{ day.day }}
                                        </span>
                                    </div>
                                    <div v-if="day.holidayName" class="text-[10px] text-red-500 font-medium leading-tight mb-1">
                                        {{ day.holidayName }}
                                    </div>
                                    <div class="space-y-0.5">
                                        <div v-for="(block, bIdx) in snapshot.work_blocks || []" :key="bIdx">
                                            <div v-if="day.blocks?.[block] > 0"
                                                class="h-2 rounded-full"
                                                :class="blockCellColor(day.blocks[block])">
                                            </div>
                                        </div>
                                    </div>
                                    <div v-if="day.count > 0"
                                        class="mt-1 text-[10px] font-medium text-gray-500 dark:text-gray-400">
                                        {{ day.count }} tarea{{ day.count !== 1 ? 's' : '' }}
                                    </div>
                                    <button v-if="!day.isSunday && !allBlocksFull(day, snapshot.work_blocks)"
                                        @click.stop="createTask(day.date, '09:00-11:00')"
                                        class="absolute top-1 right-1 p-1 rounded-lg opacity-0 group-hover:opacity-100 hover:bg-indigo-100 dark:hover:bg-indigo-800 transition">
                                        <Plus class="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400" />
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div v-if="viewMode === 'week'">
                        <div class="grid grid-cols-[100px_repeat(7,1fr)] border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                            <div class="border-r border-b border-gray-200 dark:border-gray-700 p-2 bg-gray-50 dark:bg-gray-800/50"></div>
                            <div v-for="day in weekDays" :key="day.date"
                                class="border-r border-b border-gray-200 dark:border-gray-700 p-2 text-center"
                                :class="day.isToday ? 'bg-indigo-50 dark:bg-indigo-900/20' : 'bg-gray-50 dark:bg-gray-800/50'">
                                <div class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ day.weekday }}</div>
                                <div class="text-lg font-bold"
                                    :class="day.isToday ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-900 dark:text-white'">
                                    {{ day.day }}
                                </div>
                                <div v-if="day.holidayName" class="text-[10px] text-red-500 font-medium">{{ day.holidayName }}</div>
                            </div>
                            <template v-for="block in snapshot.work_blocks || []" :key="block">
                                <div class="border-r border-b border-gray-200 dark:border-gray-700 p-2 text-xs font-medium text-gray-600 dark:text-gray-400 flex items-center justify-center bg-gray-50 dark:bg-gray-800/50">
                                    {{ block }}
                                </div>
                                <div v-for="day in weekDays" :key="day.date + block"
                                    @click="!day.isSunday && openDay(day.date)"
                                    class="border-r border-b border-gray-200 dark:border-gray-700 p-1 min-h-[60px] cursor-pointer group relative"
                                    :class="day.isSunday ? 'bg-gray-50 dark:bg-gray-800/50 cursor-default' : 'hover:bg-gray-50 dark:hover:bg-gray-700/50'">
                                    <template v-for="task in day.tasks" :key="task.id">
                                        <div v-if="task.time_range === block"
                                            @click.stop="viewTask(task.id)"
                                            class="text-xs rounded px-1.5 py-0.5 mb-0.5 line-clamp-2 leading-tight text-white cursor-pointer"
                                            :class="statusColors[task.status] || 'bg-gray-500'"
                                            :title="task.title + ' (' + (statusLabels[task.status] || task.status) + ')'">
                                            {{ task.title }}
                                        </div>
                                    </template>
                                    <button v-if="!day.isSunday && !isBlockOccupied(day, block)"
                                        @click.stop="createTask(day.date, block)"
                                        class="absolute bottom-1 right-1 p-0.5 rounded opacity-0 group-hover:opacity-100 hover:bg-indigo-100 dark:hover:bg-indigo-800 transition">
                                        <Plus class="w-3 h-3 text-indigo-600 dark:text-indigo-400" />
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                <div class="flex items-center gap-4 mt-4 text-xs text-gray-500 dark:text-gray-400">
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-indigo-500"></span> Bloque ocupado</span>
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-yellow-500"></span> Pendiente</span>
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-blue-500"></span> Guion listo</span>
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-green-500"></span> Publicado</span>
                </div>
            </div>
        </div>

        <transition name="slide">
            <div v-if="showSidebar" class="fixed inset-0 z-40 flex justify-end">
                <div class="absolute inset-0 bg-black/30" @click="closeSidebar"></div>
                <div class="relative w-full max-w-lg bg-white dark:bg-gray-800 shadow-2xl h-full overflow-y-auto">
                    <div class="sticky top-0 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 p-4 flex items-center justify-between z-10">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ selectedDate }}</h3>
                            <p v-if="snapshot.holidays_map?.[selectedDate]" class="text-xs text-red-500 font-medium">
                                {{ snapshot.holidays_map[selectedDate] }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button @click="createTask(selectedDate, '09:00-11:00')"
                                class="p-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition">
                                <Plus class="w-4 h-4" />
                            </button>
                            <button @click="closeSidebar" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                                <span class="text-gray-500 text-xl leading-none">&times;</span>
                            </button>
                        </div>
                    </div>

                    <div class="p-4 space-y-3">
                        <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tareas de video</h4>
                        <div v-if="dayTasks.length === 0" class="text-center py-4 text-gray-400 dark:text-gray-500 text-sm">
                            Sin tareas de video
                        </div>
                        <div v-for="task in dayTasks" :key="task.id"
                            class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 hover:shadow-sm transition">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex-1 min-w-0">
                                    <h4 class="font-semibold text-gray-900 dark:text-white truncate">{{ task.title }}</h4>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ task.time_range }}</p>
                                </div>
                                <span class="text-[10px] font-medium px-2 py-0.5 rounded-full whitespace-nowrap ml-2"
                                    :class="{
                                        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': task.status === 'pending',
                                        'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200': task.status === 'script_ready',
                                        'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200': task.status === 'editing',
                                        'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200': task.status === 'review',
                                        'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200': task.status === 'scheduled',
                                        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': task.status === 'published',
                                        'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': task.status === 'cancelled',
                                    }">
                                    {{ statusLabels[task.status] || task.status }}
                                </span>
                            </div>

                            <div v-if="task.youtube_url" class="mb-2">
                                <a :href="task.youtube_url" target="_blank"
                                    class="text-xs text-indigo-600 hover:text-indigo-700 flex items-center gap-1 truncate">
                                    <ExternalLink class="w-3 h-3" />
                                    {{ task.youtube_url }}
                                </a>
                            </div>

                            <div class="flex items-center gap-2 mt-2 pt-2 border-t border-gray-100 dark:border-gray-700">
                                <select :value="task.status" @change="updateTaskStatus(task, $event.target.value)"
                                    class="text-xs rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white py-1 px-2">
                                    <option v-for="s in snapshot.statuses || []" :key="s.value" :value="s.value">{{ s.label }}</option>
                                </select>
                                <button @click="viewTask(task.id)"
                                    class="text-xs px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition">
                                    Ver
                                </button>
                                <button @click="editTask(task.id)"
                                    class="text-xs px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-white transition">
                                    Editar
                                </button>
                                <button @click="confirmDeleteTask(task)"
                                    class="text-xs px-3 py-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white transition">
                                    <Trash2 class="w-3 h-3" />
                                </button>
                            </div>
                        </div>

                        <div class="border-t border-gray-200 dark:border-gray-700 pt-4 mt-6">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tareas extra</h4>
                                <button @click="openExtraModal()"
                                    class="text-xs px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition flex items-center gap-1">
                                    <Plus class="w-3 h-3" /> Nueva
                                </button>
                            </div>
                            <div v-if="extraTasks.length === 0" class="text-center py-4 text-gray-400 dark:text-gray-500 text-sm">
                                Sin tareas extra
                            </div>
                            <div v-for="task in extraTasks" :key="'e' + task.id"
                                class="rounded-xl border border-dashed border-teal-300 dark:border-teal-700 p-3 mb-2 hover:shadow-sm transition bg-teal-50 dark:bg-teal-900/10">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1 min-w-0">
                                        <h4 class="font-semibold text-gray-900 dark:text-white text-sm truncate">{{ task.title }}</h4>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ task.time_range }}</p>
                                    </div>
                                    <span class="text-[10px] font-medium px-2 py-0.5 rounded-full whitespace-nowrap ml-2"
                                        :class="{
                                            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': task.status === 'pending',
                                            'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200': task.status === 'completado',
                                            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': task.status === 'published',
                                            'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200': task.status === 'grabando',
                                        }">
                                        {{ task.status }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-[10px] px-2 py-0.5 rounded-full"
                                        :class="task.location === 'fuera' ? 'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-200' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'">
                                        {{ task.location === 'fuera' ? 'Fuera de oficina' : 'En oficina' }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-2 mt-2 pt-2 border-t border-teal-200 dark:border-teal-800">
                                    <button @click="openExtraModal(task)"
                                        class="text-xs px-2 py-1 rounded-lg bg-amber-500 hover:bg-amber-600 text-white transition">
                                        Editar
                                    </button>
                                    <button @click="confirmDeleteExtra(task)"
                                        class="text-xs px-2 py-1 rounded-lg bg-red-600 hover:bg-red-700 text-white transition">
                                        <Trash2 class="w-3 h-3" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </transition>

        <transition name="fade">
            <div v-if="showExtraModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-lg p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                            {{ editingExtra ? 'Editar tarea extra' : 'Nueva tarea extra' }}
                        </h2>
                        <button @click="closeExtraModal" class="text-gray-500 hover:text-gray-700 text-xl leading-none">&times;</button>
                    </div>
                    <form @submit.prevent="saveExtraTask" class="space-y-4">
                        <div>
                            <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Titulo</label>
                            <input v-model="extraForm.title" type="text" required
                                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Fecha</label>
                                <input v-model="extraForm.task_date" type="date" required
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Rango horario</label>
                                <input v-model="extraForm.time_range" type="text" placeholder="09:00-10:00" required
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Estado</label>
                                <select v-model="extraForm.status"
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="pending">Pendiente</option>
                                    <option value="grabando">Grabando</option>
                                    <option value="completado">Completado</option>
                                    <option value="published">Publicado</option>
                                </select>
                            </div>
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Ubicacion</label>
                                <select v-model="extraForm.location"
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="oficina">Dentro de la oficina</option>
                                    <option value="fuera">Fuera de la oficina</option>
                                </select>
                            </div>
                        </div>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="closeExtraModal"
                                class="px-4 py-2 rounded-xl border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300">
                                Cancelar
                            </button>
                            <button type="submit"
                                class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white">
                                {{ editingExtra ? 'Actualizar' : 'Crear' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </transition>

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
