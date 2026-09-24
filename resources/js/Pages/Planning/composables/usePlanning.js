import { ref, computed, watch, nextTick } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { calendarStatusColors } from '@/config/statusConstants'
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

    const statusColors = calendarStatusColors

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
    const pendingTasks = ref([])
    const showRestoreModal = ref(false)
    const restoringTask = ref(null)

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
                axios.get('/tasks/extra', { params: { fecha: date } }),
                axios.get('/planning/observation', { params: { fecha: date } }),
            ])
            dayTasks.value = tasksRes.data
            extraTasks.value = extraRes.data
            dayObservation.value = obsRes.data
        } catch (e) {
            console.error('Failed to fetch day tasks', e)
            dayTasks.value = []
            extraTasks.value = []
            dayObservation.value = { notes: '' }
        }
    }

    function goToday() {
        highlightDate.value = null
        const today = new Date()
        currentYear.value = today.getFullYear()
        currentMonth.value = today.getMonth() + 1
        const weekStart = new Date(today)
        weekStart.setDate(today.getDate() - ((today.getDay() + 6) % 7))
        currentWeekStart.value = formatDate(weekStart)
        if (viewMode.value === 'pending') {
            viewMode.value = 'month'
        }
        updateUrl()
        fetchSnapshot()
    }

    function prevMonth() {
        highlightDate.value = null
        currentMonth.value--
        if (currentMonth.value < 1) {
            currentMonth.value = 12
            currentYear.value--
        }
        updateUrl()
        fetchSnapshot()
    }

    function nextMonth() {
        highlightDate.value = null
        currentMonth.value++
        if (currentMonth.value > 12) {
            currentMonth.value = 1
            currentYear.value++
        }
        updateUrl()
        fetchSnapshot()
    }

    function prevWeek() {
        highlightDate.value = null
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
        highlightDate.value = null
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
        highlightDate.value = null
        viewMode.value = mode
        updateUrl()
        if (mode === 'pending') {
            fetchPendingTasks()
        } else {
            fetchSnapshot()
        }
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
        router.visit(`/tasks/create?${params}`)
    }

    function viewTask(id) {
        router.visit(`/tasks/${id}`)
    }

    function editTask(id) {
        router.visit(`/tasks/${id}/edit`)
    }

    function confirmDeleteTask(task) {
        deleteTarget.value = task
        showDeleteModal.value = true
    }

    function executeDelete() {
        if (!deleteTarget.value) return
        router.delete(`/tasks/${deleteTarget.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                showDeleteModal.value = false
                deleteTarget.value = null
                if (selectedDate.value) fetchDayTasks(selectedDate.value)
                fetchSnapshot()
            },
        })
    }

    async function updateTaskStatus(task, status, youtube_url = null) {
        try {
            const payload = { status }
            if (youtube_url) payload.youtube_url = youtube_url
            await axios.patch(`/tasks/${task.id}/status`, payload)
            if (selectedDate.value) await fetchDayTasks(selectedDate.value)
            await fetchSnapshot()
        } catch (e) {
            console.error('Failed to update task status', e)
        }
    }

    async function updateExtraTaskStatus(task, status) {
        try {
            await axios.patch(`/tasks/extra/${task.id}`, {
                task_date: task.task_date,
                time_range: task.time_range,
                title: task.title,
                description: task.description || '',
                status,
                location: task.location,
            })
            if (selectedDate.value) await fetchDayTasks(selectedDate.value)
            await fetchSnapshot()
        } catch (e) {
            console.error('Failed to update extra task status', e)
        }
    }

    async function saveObservation(notes) {
        try {
            await axios.post('/planning/observation', {
                fecha: selectedDate.value,
                notes,
            })
            dayObservation.value.notes = notes
            await fetchSnapshot()
        } catch (e) {
            console.error('Failed to save observation', e)
        }
    }

    async function completeSession(task, youtube_url = null) {
        if (!task.session_id) { console.warn('completeSession: no session_id', task); return }
        try {
            const patchDate = selectedDate.value
            const payload = { status: 'completed' }
            if (youtube_url) payload.youtube_url = youtube_url
            await axios.patch(`/tasks/${task.id}/sessions/${task.session_id}`, payload)
            if (patchDate) await fetchDayTasks(patchDate)
            await fetchSnapshot()
        } catch (e) {
            console.error('Failed to complete session', e)
        }
    }

    async function editSession(data) {
        try {
            await axios.patch(`/tasks/${data.task_id}/sessions/${data.session_id}`, {
                date: data.date,
                time_range: data.time_range,
                status: data.status,
            })
            if (selectedDate.value) await fetchDayTasks(selectedDate.value)
            await fetchSnapshot()
        } catch (e) {
            console.error('Failed to edit session', e)
        }
    }

    async function deleteSession(data) {
        try {
            await axios.delete(`/tasks/${data.task_id}/sessions/${data.session_id}`)
            if (selectedDate.value) await fetchDayTasks(selectedDate.value)
            await fetchSnapshot()
        } catch (e) {
            console.error('Failed to delete session', e)
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

            await axios.post(`/tasks/${task.id}/sessions`, {
                date: today,
                time_range: freeBlock,
                status: 'in_progress',
            })
            if (selectedDate.value) await fetchDayTasks(selectedDate.value)
            await fetchSnapshot()
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
            description: form.description || '',
            status: form.status,
            location: form.location,
            shared_user_ids: form.shared_user_ids || [],
        }
        try {
            if (editingExtra.value) {
                const id = String(editingExtra.value.id).replace(/^e/, '')
                await axios.patch(`/tasks/extra/${id}`, payload)
            } else {
                await axios.post('/tasks/extra', payload)
            }
            closeExtraModal()
            if (selectedDate.value) await fetchDayTasks(selectedDate.value)
            await fetchSnapshot()
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
            await axios.delete(`/tasks/extra/${id}`)
            showExtraDeleteModal.value = false
            extraDeleteTarget.value = null
            if (selectedDate.value) await fetchDayTasks(selectedDate.value)
            await fetchSnapshot()
        } catch (e) {
            console.error('Failed to delete extra task', e)
        }
    }

    async function fetchPendingTasks() {
        try {
            const res = await axios.get('/tasks/pending')
            pendingTasks.value = res.data
        } catch (e) {
            console.error('Failed to fetch pending tasks', e)
        }
    }

    async function moveToPending(task) {
        try {
            await axios.patch(`/tasks/${task.id}/pending`)
        } catch (e) {
            console.error('PATCH /pending failed:', e.response?.status, e.response?.data || e.message)
            alert(e.response?.data?.message || 'Error al mover la tarea a pendientes.')
            return
        }
        closeSidebar()
        setTimeout(async () => {
            try {
                if (viewMode.value === 'pending') {
                    await fetchPendingTasks()
                } else {
                    await fetchSnapshot()
                }
            } catch (e) {
                console.error('Refresh after moveToPending failed:', e.response?.status, e.response?.data || e.message)
            }
        }, 100)
    }

    function openRestoreModal(task) {
        restoringTask.value = task
        showRestoreModal.value = true
    }

    function closeRestoreModal() {
        showRestoreModal.value = false
        restoringTask.value = null
    }

    async function restoreFromPending(form) {
        if (!restoringTask.value) return
        try {
            await axios.patch(`/tasks/${restoringTask.value.id}/restore`, {
                task_date: form.task_date,
                time_range: form.time_range,
            })
        } catch (e) {
            console.error('PATCH /restore failed:', e.response?.status, e.response?.data || e.message)
            const msg = e.response?.data?.message
                || Object.values(e.response?.data?.errors || {}).flat().join('. ')
                || 'Error al restaurar la tarea.'
            alert(msg)
            return
        }
        closeRestoreModal()
        setTimeout(async () => {
            try {
                if (viewMode.value === 'pending') {
                    await fetchPendingTasks()
                } else {
                    await fetchSnapshot()
                }
            } catch (e) {
                console.error('Refresh after restoreFromPending failed:', e.response?.status, e.response?.data || e.message)
            }
        }, 100)
    }

    const searchQuery = ref('')
    const showSearchResults = ref(false)
    const highlightDate = ref(null)
    const searchResults = ref([])
    const searching = ref(false)
    let searchTimer = null
    let searchSeq = 0

    watch(searchQuery, (q) => {
        const query = q.trim()
        showSearchResults.value = q.length > 0
        if (q.length > 0) highlightDate.value = null
        clearTimeout(searchTimer)

        if (query.length < 2) {
            searchResults.value = []
            searching.value = false
            return
        }

        searching.value = true
        const seq = ++searchSeq
        searchTimer = setTimeout(async () => {
            try {
                const res = await axios.get('/planning/search', { params: { q: query } })
                if (seq !== searchSeq) return
                searchResults.value = res.data.results
            } catch (e) {
                if (seq === searchSeq) searchResults.value = []
                console.error('Search failed', e)
            } finally {
                if (seq === searchSeq) searching.value = false
            }
        }, 300)
    })

    const searchedDates = computed(() => {
        const dates = new Set()
        for (const r of searchResults.value) {
            dates.add(r.date)
        }
        return dates
    })

    async function goToSearchResult(task) {
        showSearchResults.value = false
        searchQuery.value = ''

        const [y, m] = task.date.split('-').map(Number)
        let needsFetch = false

        if (viewMode.value !== 'month' && viewMode.value !== 'week') {
            viewMode.value = 'month'
            currentYear.value = y
            currentMonth.value = m
            needsFetch = true
        } else if (viewMode.value === 'month') {
            if (y !== currentYear.value || m !== currentMonth.value) {
                currentYear.value = y
                currentMonth.value = m
                needsFetch = true
            }
        } else {
            const start = parseDate(currentWeekStart.value)
            const end = new Date(start)
            end.setDate(start.getDate() + 6)
            const target = parseDate(task.date)
            if (target < start || target > end) {
                const weekStart = new Date(target)
                weekStart.setDate(target.getDate() - ((target.getDay() + 6) % 7))
                currentWeekStart.value = formatDate(weekStart)
                currentYear.value = y
                currentMonth.value = m
                needsFetch = true
            }
        }

        if (needsFetch) {
            updateUrl()
            await fetchSnapshot()
        }

        await nextTick()
        const el = document.querySelector(`[data-date="${task.date}"]`)
        el?.scrollIntoView({ behavior: 'smooth', block: 'center' })
        highlightDate.value = task.date
    }

    function clearSearch() {
        searchQuery.value = ''
        showSearchResults.value = false
        searchResults.value = []
        searching.value = false
    }

    return {
        can,
        statusColors,
        statusLabels,
        workingDays,
        currentYear, currentMonth, currentWeekStart, viewMode,
        snapshot, selectedDate, dayTasks, extraTasks, dayObservation,
        showSidebar, showDeleteModal, showExtraDeleteModal,
        deleteTarget, extraDeleteTarget, loading, showPdfModal,
        showExtraModal, editingExtra,
        pendingTasks, showRestoreModal, restoringTask,
        monthName, daysInMonth, firstDayOfMonth, calendarDays,
        weekDays, weekName, hours, weekTaskPlacements,
        fetchSnapshot, fetchDayTasks, fetchPendingTasks,
        goToday, prevMonth, nextMonth, prevWeek, nextWeek, setView,
        openDay, closeSidebar,
        createTask, viewTask, editTask,
        confirmDeleteTask, executeDelete,
        updateTaskStatus, updateExtraTaskStatus, saveObservation,
        openExtraModal, closeExtraModal, saveExtraTask,
        confirmDeleteExtra, executeExtraDelete,
        createSession, completeSession, editSession, deleteSession,
        moveToPending, openRestoreModal, closeRestoreModal, restoreFromPending,
        searchQuery, searchResults, searchedDates, showSearchResults, highlightDate, searching,
        goToSearchResult, clearSearch,
    }
}
