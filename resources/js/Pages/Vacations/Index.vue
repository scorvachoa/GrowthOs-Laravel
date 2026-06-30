<script setup>
import { ref, computed, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { Plus, X, Check, AlertTriangle, Umbrella, Pencil, Search, Eye } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import SearchInput from '@/Components/Forms/SearchInput.vue'
import PrimaryButton from '@/Components/UI/PrimaryButton.vue'
import ConfirmDelete from '@/Components/Modals/ConfirmDelete.vue'

const props = defineProps({
    vacations: Array,
    remaining_days: Number,
    can_approve: Boolean,
    filters: {
        type: Object,
        default: () => ({}),
    },
})

const page = usePage()
const permissions = page.props.auth?.user?.permissions ?? []
const can = (perm) => permissions.includes(perm)

const search = ref(props.filters?.search || '')

let debounceTimer
watch(search, (value) => {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
        router.get('/vacations', { search: value }, { preserveState: true, replace: true })
    }, 400)
})

const showForm = ref(false)
const form = ref({ type: 'parcial', start_date: '', reason: '' })
const submitting = ref(false)

const typeDays = { parcial: 15, completa: 30 }

const previewEndDate = computed(() => {
    if (!form.value.start_date || !form.value.type) return ''
    const [y, m, day] = form.value.start_date.split('-').map(Number)
    const d = new Date(y, m - 1, day)
    d.setDate(d.getDate() + typeDays[form.value.type] - 1)
    const yy = d.getFullYear()
    const mm = String(d.getMonth() + 1).padStart(2, '0')
    const dd = String(d.getDate()).padStart(2, '0')
    return `${dd}-${mm}-${yy}`
})

const typeOptions = [
    { value: 'parcial', label: 'Parcial (15 días)' },
    { value: 'completa', label: 'Completa (30 días)' },
]

function openForm() {
    form.value = { type: 'parcial', start_date: '', end_date: '', reason: '' }
    showForm.value = true
}

function submitForm() {
    if (submitting.value) return
    submitting.value = true
    router.post('/vacations', form.value, {
        preserveScroll: true,
        onSuccess: () => { showForm.value = false; submitting.value = false },
        onError: () => { submitting.value = false },
    })
}

const showDetail = ref(false)
const detailItem = ref(null)

function openDetail(v) {
    detailItem.value = v
    showDetail.value = true
}

const showApprove = ref(false)
const showReject = ref(false)
const showDelete = ref(false)
const targetItem = ref(null)

function confirmApprove(v) {
    targetItem.value = v
    showApprove.value = true
}

function executeApprove() {
    if (!targetItem.value) return
    router.patch(`/vacations/${targetItem.value.id}/approve`, {}, {
        preserveScroll: true,
        onSuccess: () => { showApprove.value = false; targetItem.value = null },
    })
}

function confirmReject(v) {
    targetItem.value = v
    showReject.value = true
}

function executeReject() {
    if (!targetItem.value) return
    router.patch(`/vacations/${targetItem.value.id}/reject`, {}, {
        preserveScroll: true,
        onSuccess: () => { showReject.value = false; targetItem.value = null },
    })
}

function confirmDelete(v) {
    targetItem.value = v
    showDelete.value = true
}

function executeDelete() {
    if (!targetItem.value) return
    router.delete(`/vacations/${targetItem.value.id}`, {
        onSuccess: () => { showDelete.value = false; targetItem.value = null },
    })
}

const showEdit = ref(false)
const editingId = ref(null)
const editForm = ref({ type: 'parcial', start_date: '', reason: '' })
const editSubmitting = ref(false)

const editPreviewEndDate = computed(() => {
    if (!editForm.value.start_date || !editForm.value.type) return ''
    const [y, m, day] = editForm.value.start_date.split('-').map(Number)
    const d = new Date(y, m - 1, day)
    d.setDate(d.getDate() + typeDays[editForm.value.type] - 1)
    const yy = d.getFullYear()
    const mm = String(d.getMonth() + 1).padStart(2, '0')
    const dd = String(d.getDate()).padStart(2, '0')
    return `${dd}-${mm}-${yy}`
})

function openEdit(v) {
    editingId.value = v.id
    editForm.value = { type: v.type, start_date: v.start_date, reason: v.reason || '' }
    showEdit.value = true
}

function submitEdit() {
    if (editSubmitting.value || !editingId.value) return
    editSubmitting.value = true
    router.patch(`/vacations/${editingId.value}`, editForm.value, {
        preserveScroll: true,
        onSuccess: () => { showEdit.value = false; editSubmitting.value = false; editingId.value = null },
        onError: () => { editSubmitting.value = false },
    })
}

const statusColors = {
    pendiente: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    aprobado: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    rechazado: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
}

const statusLabels = { pendiente: 'Pendiente', aprobado: 'Aprobado', rechazado: 'Rechazado' }
</script>

<template>
    <AppLayout>
        <div class="max-w-7xl mx-auto space-y-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Vacaciones</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Dias disponibles: <span class="font-semibold text-indigo-600 dark:text-indigo-400">{{ remaining_days }}</span> / 30
                    </p>
                    <div v-if="remaining_days === 0"
                        class="mt-3 px-4 py-2 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg text-sm text-amber-700 dark:text-amber-300">
                        Ya has utilizado los 30 días de vacaciones de este año. Contacta con el administrador si deseas vacaciones anticipadas.
                    </div>
                </div>
                <div class="flex gap-3">
                    <SearchInput v-model="search" />
                    <button @click="openForm" :disabled="remaining_days === 0"
                        class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium flex items-center gap-2 transition disabled:opacity-50 disabled:cursor-not-allowed">
                        <Plus class="w-4 h-4" /> Solicitar
                    </button>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div v-if="vacations.length === 0" class="text-center py-12 text-gray-400 dark:text-gray-500">
                    <Umbrella class="w-10 h-10 mx-auto mb-2 opacity-40" />
                    <p class="text-sm">Sin solicitudes de vacaciones</p>
                </div>
                <table v-else class="w-full text-sm table-fixed">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="text-left px-4 py-4 font-semibold text-gray-500 dark:text-gray-400" v-if="can('edit vacations')">Usuario</th>
                            <th class="text-left px-4 py-4 font-semibold text-gray-500 dark:text-gray-400">Periodo</th>
                            <th class="text-left px-4 py-4 font-semibold text-gray-500 dark:text-gray-400 w-[100px]">Tipo</th>
                            <th class="text-left px-4 py-4 font-semibold text-gray-500 dark:text-gray-400 w-[80px]">Dias</th>
                            <th class="text-left px-4 py-4 font-semibold text-gray-500 dark:text-gray-400 w-[120px]">Estado</th>
                            <th class="text-right px-4 py-4 font-semibold text-gray-500 dark:text-gray-400 w-[120px]">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="v in vacations" :key="v.id"
                            class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                            <td class="px-4 py-4 text-gray-900 dark:text-white text-sm" v-if="can('edit vacations')">{{ v.user_name }}</td>
                            <td class="px-4 py-4 text-gray-900 dark:text-white text-sm">{{ v.start_date }} al {{ v.end_date }}</td>
                            <td class="px-4 py-4 text-gray-600 dark:text-gray-400 text-sm capitalize">{{ v.type === 'completa' ? 'Completa' : 'Parcial' }}</td>
                            <td class="px-4 py-4 text-gray-900 dark:text-white font-medium text-sm">{{ v.days_used }}</td>
                            <td class="px-4 py-4">
                                <span class="text-[11px] font-medium px-2.5 py-1 rounded-full" :class="statusColors[v.status]">
                                    {{ statusLabels[v.status] }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button @click="openDetail(v)"
                                        class="p-2 rounded-lg hover:bg-indigo-50 dark:hover:bg-indigo-900/20 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition"
                                        title="Ver detalle">
                                        <Eye class="w-4 h-4" />
                                    </button>
                                    <button v-if="can('approve vacations') && v.status === 'pendiente'" @click="confirmApprove(v)"
                                        class="p-2 rounded-lg hover:bg-green-100 dark:hover:bg-green-900/20 text-gray-400 hover:text-green-600 dark:hover:text-green-400 transition"
                                        title="Aprobar">
                                        <Check class="w-4 h-4" />
                                    </button>
                                    <button v-if="can('reject vacations') && v.status === 'pendiente'" @click="confirmReject(v)"
                                        class="p-2 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/20 text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition"
                                        title="Rechazar">
                                        <X class="w-4 h-4" />
                                    </button>
                                    <button v-if="can('edit vacations') && v.status === 'pendiente'" @click="openEdit(v)"
                                        class="p-2 rounded-lg hover:bg-amber-50 dark:hover:bg-amber-900/20 text-gray-400 hover:text-amber-600 dark:hover:text-amber-400 transition"
                                        title="Editar">
                                        <Pencil class="w-4 h-4" />
                                    </button>
                                    <button v-if="can('delete vacations') && v.status !== 'aprobado'" @click="confirmDelete(v)"
                                        class="p-2 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition"
                                        title="Eliminar">
                                        <AlertTriangle class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <ConfirmDelete :show="showDelete" @close="showDelete = false" @confirm="executeDelete" />
        </div>

        <teleport to="body">
            <transition name="fade">
                <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showForm = false">
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-md p-6 mx-4">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Solicitar vacaciones</h3>
                            <button @click="showForm = false" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                                <X class="w-5 h-5" />
                            </button>
                        </div>
                        <form @submit.prevent="submitForm" class="space-y-4">
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Tipo</label>
                                <select v-model="form.type"
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                                    <option v-for="o in typeOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Fecha inicio</label>
                                <input v-model="form.start_date" type="date"
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            </div>
                            <div v-if="form.start_date" class="rounded-xl bg-gray-50 dark:bg-gray-900/50 p-3 text-sm text-gray-600 dark:text-gray-400">
                                Fin estimado: <span class="font-semibold text-gray-900 dark:text-white">{{ previewEndDate }}</span> ({{ typeDays[form.type] }} días)
                            </div>
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Motivo (opcional)</label>
                                <textarea v-model="form.reason" rows="2"
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white text-sm resize-none"></textarea>
                            </div>
                            <div class="flex justify-end gap-3 pt-2">
                                <button type="button" @click="showForm = false"
                                    class="px-4 py-2 rounded-xl border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300">
                                    Cancelar
                                </button>
                                <button type="submit" :disabled="submitting"
                                    class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white disabled:opacity-50">
                                    {{ submitting ? 'Enviando...' : 'Solicitar' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </transition>
        </teleport>

        <teleport to="body">
            <transition name="fade">
                <div v-if="showEdit" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showEdit = false">
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-md p-6 mx-4">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Editar vacaciones</h3>
                            <button @click="showEdit = false" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                                <X class="w-5 h-5" />
                            </button>
                        </div>
                        <form @submit.prevent="submitEdit" class="space-y-4">
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Tipo</label>
                                <select v-model="editForm.type"
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                                    <option v-for="o in typeOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Fecha inicio</label>
                                <input v-model="editForm.start_date" type="date"
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            </div>
                            <div v-if="editForm.start_date" class="rounded-xl bg-gray-50 dark:bg-gray-900/50 p-3 text-sm text-gray-600 dark:text-gray-400">
                                Fin estimado: <span class="font-semibold text-gray-900 dark:text-white">{{ editPreviewEndDate }}</span> ({{ typeDays[editForm.type] }} días)
                            </div>
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Motivo (opcional)</label>
                                <textarea v-model="editForm.reason" rows="2"
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white text-sm resize-none"></textarea>
                            </div>
                            <div class="flex justify-end gap-3 pt-2">
                                <button type="button" @click="showEdit = false"
                                    class="px-4 py-2 rounded-xl border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300">
                                    Cancelar
                                </button>
                                <button type="submit" :disabled="editSubmitting"
                                    class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white disabled:opacity-50">
                                    {{ editSubmitting ? 'Guardando...' : 'Guardar' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </transition>
        </teleport>

        <teleport to="body">
            <transition name="fade">
                <div v-if="showDetail && detailItem" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showDetail = false">
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-lg p-6 mx-4">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Detalle de vacaciones</h3>
                            <button @click="showDetail = false" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                                <X class="w-5 h-5" />
                            </button>
                        </div>
                        <dl class="space-y-3 text-sm">
                            <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                                <dt class="text-gray-500 dark:text-gray-400">Usuario</dt>
                                <dd class="text-gray-900 dark:text-white font-medium">{{ detailItem.user_name }}</dd>
                            </div>
                            <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                                <dt class="text-gray-500 dark:text-gray-400">Periodo</dt>
                                <dd class="text-gray-900 dark:text-white font-medium">{{ detailItem.start_date }} al {{ detailItem.end_date }}</dd>
                            </div>
                            <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                                <dt class="text-gray-500 dark:text-gray-400">Tipo</dt>
                                <dd class="text-gray-900 dark:text-white font-medium capitalize">{{ detailItem.type === 'completa' ? 'Completa' : 'Parcial' }}</dd>
                            </div>
                            <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                                <dt class="text-gray-500 dark:text-gray-400">Dias</dt>
                                <dd class="text-gray-900 dark:text-white font-medium">{{ detailItem.days_used }}</dd>
                            </div>
                            <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                                <dt class="text-gray-500 dark:text-gray-400">Año</dt>
                                <dd class="text-gray-900 dark:text-white font-medium">{{ detailItem.year }}</dd>
                            </div>
                            <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                                <dt class="text-gray-500 dark:text-gray-400">Estado</dt>
                                <dd>
                                    <span class="text-[11px] font-medium px-2.5 py-1 rounded-full" :class="statusColors[detailItem.status]">
                                        {{ statusLabels[detailItem.status] }}
                                    </span>
                                </dd>
                            </div>
                            <div v-if="detailItem.reason" class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                                <dt class="text-gray-500 dark:text-gray-400">Motivo</dt>
                                <dd class="text-gray-900 dark:text-white font-medium text-right max-w-[280px]">{{ detailItem.reason }}</dd>
                            </div>
                            <div v-if="detailItem.approved_by" class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                                <dt class="text-gray-500 dark:text-gray-400">Aprobado por</dt>
                                <dd class="text-gray-900 dark:text-white font-medium">{{ detailItem.approved_by }}</dd>
                            </div>
                            <div v-if="detailItem.approved_at" class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                                <dt class="text-gray-500 dark:text-gray-400">Aprobado el</dt>
                                <dd class="text-gray-900 dark:text-white font-medium">{{ detailItem.approved_at }}</dd>
                            </div>
                            <div class="flex justify-between py-2">
                                <dt class="text-gray-500 dark:text-gray-400">Solicitado el</dt>
                                <dd class="text-gray-900 dark:text-white font-medium">{{ detailItem.created_at }}</dd>
                            </div>
                        </dl>
                        <div class="flex justify-end mt-6">
                            <button @click="showDetail = false"
                                class="px-4 py-2 rounded-xl border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                Cerrar
                            </button>
                        </div>
                    </div>
                </div>
            </transition>
        </teleport>

        <teleport to="body">
            <transition name="fade">
                <div v-if="showApprove" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showApprove = false">
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-md p-6 mx-4">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="p-3 rounded-xl bg-green-100 dark:bg-green-900">
                                <Check class="w-5 h-5 text-green-600 dark:text-green-400" />
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white">Aprobar vacaciones</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Esta acción no se puede deshacer</p>
                            </div>
                        </div>
                        <p class="text-sm text-gray-700 dark:text-gray-300 mb-6 px-2">
                            ¿Aprobar vacaciones de <strong>{{ targetItem?.user_name }}</strong> ({{ targetItem?.start_date }} al {{ targetItem?.end_date }})?
                        </p>
                        <div class="flex justify-end gap-3">
                            <button @click="showApprove = false"
                                class="px-4 py-2 rounded-xl border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                Cancelar
                            </button>
                            <button @click="executeApprove"
                                class="px-4 py-2 rounded-xl bg-green-600 hover:bg-green-700 text-white text-sm font-medium flex items-center gap-2 transition">
                                <Check class="w-4 h-4" /> Aprobar
                            </button>
                        </div>
                    </div>
                </div>
            </transition>
        </teleport>

        <teleport to="body">
            <transition name="fade">
                <div v-if="showReject" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showReject = false">
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-md p-6 mx-4">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="p-3 rounded-xl bg-red-100 dark:bg-red-900">
                                <X class="w-5 h-5 text-red-600 dark:text-red-400" />
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white">Rechazar vacaciones</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Esta acción no se puede deshacer</p>
                            </div>
                        </div>
                        <p class="text-sm text-gray-700 dark:text-gray-300 mb-6 px-2">
                            ¿Rechazar vacaciones de <strong>{{ targetItem?.user_name }}</strong> ({{ targetItem?.start_date }} al {{ targetItem?.end_date }})?
                        </p>
                        <div class="flex justify-end gap-3">
                            <button @click="showReject = false"
                                class="px-4 py-2 rounded-xl border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                Cancelar
                            </button>
                            <button @click="executeReject"
                                class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white text-sm font-medium flex items-center gap-2 transition">
                                <X class="w-4 h-4" /> Rechazar
                            </button>
                        </div>
                    </div>
                </div>
            </transition>
        </teleport>

        <teleport to="body">
            <transition name="fade">
                <div v-if="showDelete" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showDelete = false">
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-md p-6 mx-4">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="p-3 rounded-xl bg-red-100 dark:bg-red-900">
                                <AlertTriangle class="w-5 h-5 text-red-600 dark:text-red-400" />
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white">Eliminar solicitud</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Esta acción no se puede deshacer</p>
                            </div>
                        </div>
                        <p class="text-sm text-gray-700 dark:text-gray-300 mb-6 px-2">
                            ¿Eliminar solicitud de vacaciones de <strong>{{ targetItem?.user_name }}</strong> ({{ targetItem?.start_date }} al {{ targetItem?.end_date }})?
                        </p>
                        <div class="flex justify-end gap-3">
                            <button @click="showDelete = false"
                                class="px-4 py-2 rounded-xl border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                Cancelar
                            </button>
                            <button @click="executeDelete"
                                class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white text-sm font-medium flex items-center gap-2 transition">
                                <AlertTriangle class="w-4 h-4" /> Eliminar
                            </button>
                        </div>
                    </div>
                </div>
            </transition>
        </teleport>
    </AppLayout>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity .2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
