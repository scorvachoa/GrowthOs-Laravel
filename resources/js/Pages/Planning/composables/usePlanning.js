import { ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import axios from 'axios'

export function usePlanning(props) {
    const page = usePage()
    const permissions = page.props.auth?.user?.permissions ?? []
    const can = (perm) => permissions.includes(perm)
    const workingDays = computed(() => page.props.auth?.user?.settings?.working_days ?? [1,2,3,4,5])

    function isNonWorkingDay(dayOfWeek) {
        return !workingDays.value.includes(dayOfWeek)
    }

    function parseDate(str) {
        const [y, m, d] = str.split('-').map(Number)
        return new Date(y, m - 1, d)
    }

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

    const currentYear = ref(props.calendar.year)
    const currentMonth = ref(props.calendar.month)
    const currentWeekStart = ref(props.calendar.week_start)
    const viewMode = ref(props.initial_view || 'month')
    const snapshot = ref(props.calendar)
    const selectedDate = ref(null)
    const dayTasks = ref([])
    const extraTasks = ref([])
    const dayObservation = ref({ notes: '' })
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

        const prevMonth = currentMonth.value === 1 ? 12 : currentMonth.value - 1
        const prevYear = currentMonth.value === 1 ? currentYear.value - 1 : currentYear.value
        const lastDayPrev = new Date(prevYear, prevMonth, 0).getDate()

        for (let i = firstDayOfMonth.value - 1; i >= 0; i--) {
            const d = lastDayPrev - i
            const dateObj = new Date(prevYear, prevMonth - 1, d)
            const dateStr = formatDate(dateObj)
            days.push({
                day: d,
                date: dateStr,
                isNonWorkingDay: isNonWorkingDay(dateObj.getDay()),
                isOtherMonth: true,
                isToday: false,
                isHoliday: null,
                holidayName: null,
                blocks: {},
                tasks: [],
                count: 0,
                hasExtraTasks: false,
                absences: [],
            })
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
            const extraTasksRaw = snapshot.value.has_extra_tasks_map?.[dateStr]
            const extraTasksCount = typeof extraTasksRaw === 'number' ? extraTasksRaw : (extraTasksRaw ? 1 : 0)
            const absences = snapshot.value.absences_map?.[dateStr] || []
            days.push({
                day: d,
                date: dateStr,
                isNonWorkingDay: isNonWorkingDay(dayOfWeek),
                isOtherMonth: false,
                isToday,
                isHoliday,
                holidayName: isHoliday || null,
                blocks,
                tasks,
                count,
                hasExtraTasks,
                extraTasksCount,
                absences,
            })
        }

        const nextMonth = currentMonth.value === 12 ? 1 : currentMonth.value + 1
        const nextYear = currentMonth.value === 12 ? currentYear.value + 1 : currentYear.value
        const remaining = (7 - (days.length % 7)) % 7
        for (let d = 1; d <= remaining; d++) {
            const dateObj = new Date(nextYear, nextMonth - 1, d)
            const dateStr = formatDate(dateObj)
            days.push({
                day: d,
                date: dateStr,
                isNonWorkingDay: isNonWorkingDay(dateObj.getDay()),
                isOtherMonth: true,
                isToday: false,
                isHoliday: null,
                holidayName: null,
                blocks: {},
                tasks: [],
                count: 0,
                hasExtraTasks: false,
                extraTasksCount: 0,
                absences: [],
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
            const absences = snapshot.value.absences_map?.[dateStr] || []
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
                absences,
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
            const [tasksRes, extraRes, obsRes] = await Promise.all([
                axios.get('/planning/tasks', { params: { fecha: date } }),
                axios.get('/extra-tasks', { params: { fecha: date } }),
                axios.get('/planning/observation', { params: { fecha: date } }),
            ])
            dayTasks.value = tasksRes.data
            extraTasks.value = extraRes.data
            dayObservation.value = obsRes.data
        } catch (e) {
            dayTasks.value = []
            extraTasks.value = []
            dayObservation.value = { notes: '' }
        }
    }

    function goToday() {
        const today = new Date()
        currentYear.value = today.getFullYear()
        currentMonth.value = today.getMonth() + 1
        const weekStart = new Date(today)
        weekStart.setDate(today.getDate() - ((today.getDay() + 6) % 7))
        currentWeekStart.value = formatDate(weekStart)
        updateUrl()
        fetchSnapshot()
    }

    function prevMonth() {
        currentMonth.value--
        if (currentMonth.value < 1) {
            currentMonth.value = 12
            currentYear.value--
        }
        updateUrl()
        fetchSnapshot()
    }

    function nextMonth() {
        currentMonth.value++
        if (currentMonth.value > 12) {
            currentMonth.value = 1
            currentYear.value++
        }
        updateUrl()
        fetchSnapshot()
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
        fetchSnapshot()
    }

    function updateUrl() {
        const params = new URLSearchParams({ year: currentYear.value, month: currentMonth.value, view: viewMode.value })
        if (viewMode.value === 'week') {
            params.set('week_start', currentWeekStart.value)
        }
        window.history.replaceState({}, '', `/planning?${params}`)
    }

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
        router.visit(`/video-tasks/create?${params}`)
    }

    function viewTask(id) {
        router.visit(`/video-tasks/${id}`)
    }

    function editTask(id) {
        router.visit(`/video-tasks/${id}/edit`)
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

    function updateExtraTaskStatus(task, status) {
        axios.patch(`/extra-tasks/${task.id}`, {
            task_date: task.task_date,
            time_range: task.time_range,
            title: task.title,
            status,
            location: task.location,
        }).then(() => {
            if (selectedDate.value) fetchDayTasks(selectedDate.value)
            fetchSnapshot()
        })
    }

    function saveObservation(notes) {
        axios.post('/planning/observation', {
            fecha: selectedDate.value,
            notes,
        }).then(() => {
            dayObservation.value.notes = notes
            fetchSnapshot()
        })
    }

    async function completeSession(task) {
        if (!task.session_id) return
        try {
            await axios.patch(`/video-tasks/${task.id}/sessions/${task.session_id}`, {
                status: 'completed',
            })
            if (selectedDate.value) fetchDayTasks(selectedDate.value)
            fetchSnapshot()
        } catch (e) {
            console.error('Failed to complete session', e)
        }
    }

    function todayStr() {
        const d = new Date()
        return formatDate(d)
    }

    async function createSession(task) {
        if (!task.id) return
        const today = todayStr()
        try {
            const blocksRes = await axios.get('/planning/occupied-blocks', {
                params: { date: today, except_task_id: task.id }
            })
            const freeBlock = blocksRes.data.available?.[0] || null

            await axios.post(`/video-tasks/${task.id}/sessions`, {
                date: today,
                time_range: freeBlock,
                status: 'in_progress',
            })
            if (selectedDate.value) fetchDayTasks(selectedDate.value)
            fetchSnapshot()
        } catch (e) {
            console.error('Failed to create session', e)
        }
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

    return {
        can,
        statusColors,
        statusLabels,
        currentYear, currentMonth, currentWeekStart, viewMode,
        snapshot, selectedDate, dayTasks, extraTasks, dayObservation,
        showSidebar, showDeleteModal, showExtraDeleteModal,
        deleteTarget, extraDeleteTarget, loading, showPdfModal,
        showExtraModal, editingExtra,
        monthName, daysInMonth, firstDayOfMonth, calendarDays,
        weekDays, weekName, hours, weekTaskPlacements,
        fetchSnapshot, fetchDayTasks,
        goToday, prevMonth, nextMonth, prevWeek, nextWeek, setView,
        openDay, closeSidebar,
        createTask, viewTask, editTask,
        confirmDeleteTask, executeDelete,
        updateTaskStatus, updateExtraTaskStatus, saveObservation,
        openExtraModal, closeExtraModal, saveExtraTask,
        confirmDeleteExtra, executeExtraDelete,
        createSession, completeSession,
    }
}
