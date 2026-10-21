import { ref, computed, watch, nextTick } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { calendarStatusColors } from '@/config/statusConstants'
import { usePermissions } from '@/Composables/usePermissions'
import axios from 'axios'

export function usePlanning(props) {
    const page = usePage()
    const { can } = usePermissions()
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
    const errorMessage = ref(null)
    const restoringTask = ref(null)

    function errorText(e, fallback) {
        const data = e?.response?.data
        if (data && typeof data === 'object') {
            const fromErrors = data.errors ? Object.values(data.errors).flat().join(' ') : ''
            if (fromErrors) return fromErrors
            if (data.message) return data.message
        }
        return fallback
    }

    function notifyError(e, fallback) {
        console.error(fallback, e)
        errorMessage.value = errorText(e, fallback)
    }

    const statusLabels = computed(() => {
        const map = {}
        for (const s of snapshot.value.statuses || []) {
            map[s.value] = s.label
        }
        return map
    })

    // ---- Filtros (canal / estado / propiedad) ----
    const urlParams = new URLSearchParams(window.location.search)
    const filters = ref({
        channel_id: urlParams.get('channel_id') ? Number(urlParams.get('channel_id')) : null,
        status: urlParams.get('status') || null,
        owner: ['mine', 'shared'].includes(urlParams.get('owner')) ? urlParams.get('owner') : null,
    })

    const hasActiveFilters = computed(() =>
        !!filters.value.channel_id || !!filters.value.status || !!filters.value.owner
    )

    function filterParams() {
        const params = {}
        if (filters.value.channel_id) params.channel_id = filters.value.channel_id
        if (filters.value.status) params.status = filters.value.status
        if (filters.value.owner) params.owner = filters.value.owner
        return params
    }

    function setFilter(key, value) {
        filters.value[key] = value
        updateUrl()
        fetchSnapshot()
        if (selectedDate.value) fetchDayTasks(selectedDate.value)
        if (viewMode.value === 'pending') fetchPendingTasks()
    }

    function clearFilters() {
        filters.value = { channel_id: null, status: null, owner: null }
        updateUrl()
        fetchSnapshot()
        if (selectedDate.value) fetchDayTasks(selectedDate.value)
        if (viewMode.value === 'pending') fetchPendingTasks()
    }

    function currentUserId() {
        return page.props.auth?.user?.id
    }

    // DaySidebar (endpoints propios) y Pendientes: filtrado en cliente.
    function matchesOwner(task) {
        const owner = filters.value.owner
        if (!owner) return true
        if (owner === 'mine') return task.created_by === currentUserId()
        return !!task.shared_by_user_name
    }

    const filteredDayTasks = computed(() => {
        const f = filters.value
        if (!hasActiveFilters.value) return dayTasks.value
        return dayTasks.value.filter(task => {
            if (f.channel_id && task.channel_id !== f.channel_id) return false
            if (f.status && task.status !== f.status) return false
            return matchesOwner(task)
        })
    })

    const filteredExtraTasks = computed(() => {
        const f = filters.value
        if (!hasActiveFilters.value) return extraTasks.value
        // ExtraTask no tiene canal ni los mismos estados que VideoTask: solo se filtra por propiedad.
        if (f.channel_id || f.status) return []
        return extraTasks.value.filter(task => matchesOwner(task))
    })

    const filteredPendingTasks = computed(() => {
        const f = filters.value
        if (!hasActiveFilters.value) return pendingTasks.value
        return pendingTasks.value.filter(task => {
            if (f.channel_id && task.channel_id !== f.channel_id) return false
            if (f.status && task.status !== f.status) return false
            return matchesOwner(task)
        })
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
            const params = { year: currentYear.value, month: currentMonth.value, ...filterParams() }
            if (viewMode.value === 'week') {
                params.week_start = currentWeekStart.value
            }
            const res = await axios.get('/planning/calendar/snapshot', { params })
            snapshot.value = res.data
        } catch (e) {
            console.error('Failed to fetch snapshot', e)
            errorMessage.value = 'No se pudo cargar el calendario. Recarga la página.'
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
            errorMessage.value = 'No se pudo cargar la agenda del día.'
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
        const active = filterParams()
        if (active.channel_id) params.set('channel_id', active.channel_id)
        if (active.status) params.set('status', active.status)
        if (active.owner) params.set('owner', active.owner)
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
            notifyError(e, 'No se pudo actualizar el estado de la tarea.')
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
            notifyError(e, 'No se pudo actualizar el estado de la tarea extra.')
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
            notifyError(e, 'No se pudo guardar la observación.')
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
            notifyError(e, 'No se pudo completar la sesión.')
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
            notifyError(e, 'No se pudo actualizar la sesión.')
        }
    }

    async function deleteSession(data) {
        try {
            await axios.delete(`/tasks/${data.task_id}/sessions/${data.session_id}`)
            if (selectedDate.value) await fetchDayTasks(selectedDate.value)
            await fetchSnapshot()
        } catch (e) {
            notifyError(e, 'No se pudo eliminar la sesión.')
        }
    }

    function todayStr() {
        const d = new Date()
        return formatDate(d)
    }

    const showQuickSessionModal = ref(false)
    const quickSessionTask = ref(null)
    const quickSessionDate = ref('')
    const quickSessionTimeRange = ref(null)
    const quickSessionOccupied = ref([])
    const savingQuickSession = ref(false)

    async function fetchQuickSessionBlocks() {
        if (!quickSessionTask.value || !quickSessionDate.value) return
        try {
            const res = await axios.get('/planning/occupied-blocks', {
                params: { date: quickSessionDate.value, except_task_id: quickSessionTask.value.id },
            })
            quickSessionOccupied.value = res.data.occupied || []
            quickSessionTimeRange.value = res.data.available?.[0] || null
        } catch {
            quickSessionOccupied.value = []
        }
    }

    async function createSession(task) {
        if (!task.id) return
        quickSessionTask.value = task
        quickSessionDate.value = todayStr()
        quickSessionTimeRange.value = null
        showQuickSessionModal.value = true
        await fetchQuickSessionBlocks()
    }

    function closeQuickSession() {
        showQuickSessionModal.value = false
        quickSessionTask.value = null
    }

    async function confirmQuickSession() {
        if (!quickSessionTask.value || !quickSessionDate.value) return
        savingQuickSession.value = true
        try {
            await axios.post(`/tasks/${quickSessionTask.value.id}/sessions`, {
                date: quickSessionDate.value,
                time_range: quickSessionTimeRange.value,
                status: 'in_progress',
            })
            closeQuickSession()
            if (selectedDate.value) await fetchDayTasks(selectedDate.value)
            await fetchSnapshot()
        } catch (e) {
            notifyError(e, 'No se pudo crear la sesión.')
        } finally {
            savingQuickSession.value = false
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
            notifyError(e, 'No se pudo guardar la tarea extra.')
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
            notifyError(e, 'No se pudo eliminar la tarea extra.')
        }
    }

    async function fetchPendingTasks() {
        try {
            const res = await axios.get('/tasks/pending')
            pendingTasks.value = res.data
        } catch (e) {
            console.error('Failed to fetch pending tasks', e)
            errorMessage.value = 'No se pudo cargar las tareas pendientes.'
        }
    }

    async function moveToPending(task) {
        try {
            await axios.patch(`/tasks/${task.id}/pending`)
        } catch (e) {
            console.error('PATCH /pending failed:', e.response?.status, e.response?.data || e.message)
            errorMessage.value = e.response?.data?.message || 'Error al mover la tarea a pendientes.'
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
                errorMessage.value = 'La acción se guardó pero no se pudo actualizar la vista. Recarga la página.'
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
            errorMessage.value = msg
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
                errorMessage.value = 'La acción se guardó pero no se pudo actualizar la vista. Recarga la página.'
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
                errorMessage.value = 'La búsqueda falló. Intenta de nuevo.'
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
        pendingTasks, showRestoreModal, restoringTask, errorMessage,
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
        showQuickSessionModal, quickSessionTask, quickSessionDate,
        quickSessionTimeRange, quickSessionOccupied,
        savingQuickSession,
        fetchQuickSessionBlocks, closeQuickSession, confirmQuickSession,
        moveToPending, openRestoreModal, closeRestoreModal, restoreFromPending,
        searchQuery, searchResults, searchedDates, showSearchResults, highlightDate, searching,
        goToSearchResult, clearSearch,
        filters, hasActiveFilters, setFilter, clearFilters,
        filteredDayTasks, filteredExtraTasks, filteredPendingTasks,
    }
}
