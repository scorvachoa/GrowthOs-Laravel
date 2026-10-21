<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import SearchInput from '@/Components/Forms/SearchInput.vue'
import TextInput from '@/Components/Forms/TextInput.vue'
import Modal from '@/Components/Modals/Modal.vue'
import PrimaryButton from '@/Components/UI/PrimaryButton.vue'
import { useDebounce } from '@/Composables/useDebounce'
import { useFormat } from '@/Composables/useFormat'
import { Ban, Check, Gauge, Pencil, Play } from 'lucide-vue-next'

const props = defineProps({
    organizations: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
})

const { formatDate } = useFormat()

const search = ref(props.filters?.search || '')
const status = ref(props.filters?.status || '')

const showPlanModal = ref(false)
const showSuspendModal = ref(false)
const selectedOrg = ref(null)

const planForm = ref({ plan: '', trial_ends_at: '' })

const statusColors = {
    active: 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
    trial: 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
    suspended: 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
    expired: 'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300',
}

const applyFilters = () => {
    router.get('/admin/organizations', {
        search: search.value || undefined,
        status: status.value || undefined,
    }, { preserveState: true, replace: true })
}

useDebounce(search, applyFilters)

const onStatusChange = () => {
    applyFilters()
}

const openPlanModal = (org) => {
    selectedOrg.value = org
    planForm.value = {
        plan: org.plan || '',
        trial_ends_at: org.trial_ends_at || '',
    }
    showPlanModal.value = true
}

const submitPlan = () => {
    router.patch(`/admin/organizations/${selectedOrg.value.id}/plan`, {
        plan: planForm.value.plan || null,
        trial_ends_at: planForm.value.trial_ends_at || null,
    }, {
        preserveState: true,
        onSuccess: () => { showPlanModal.value = false },
    })
}

const openSuspendModal = (org) => {
    selectedOrg.value = org
    showSuspendModal.value = true
}

const confirmSuspend = () => {
    router.patch(`/admin/organizations/${selectedOrg.value.id}/suspend`, {}, {
        preserveState: true,
        onSuccess: () => { showSuspendModal.value = false },
    })
}

const activate = (org) => {
    router.patch(`/admin/organizations/${org.id}/activate`, {}, { preserveState: true })
}

const totalUsage = (org) => {
    return Object.values(org.usage_this_month || {}).reduce((a, b) => a + b, 0)
}
</script>

<template>
    <AppLayout>
        <div class="space-y-6">
            <PageHeader title="Consola" subtitle="Empresas, planes y uso">
                <SearchInput v-model="search" placeholder="Buscar empresa..." />
                <select
                    v-model="status"
                    @change="onStatusChange"
                    class="rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="">Todos los estados</option>
                    <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                        {{ opt.label }}
                    </option>
                </select>
            </PageHeader>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="text-left px-4 py-4 font-semibold text-gray-500 dark:text-gray-400">Empresa</th>
                            <th class="text-left px-4 py-4 font-semibold text-gray-500 dark:text-gray-400 w-[120px]">Estado</th>
                            <th class="text-left px-4 py-4 font-semibold text-gray-500 dark:text-gray-400 w-[160px]">Plan</th>
                            <th class="text-left px-4 py-4 font-semibold text-gray-500 dark:text-gray-400 w-[90px]">Usuarios</th>
                            <th class="text-left px-4 py-4 font-semibold text-gray-500 dark:text-gray-400 hidden lg:table-cell">Uso del mes</th>
                            <th class="text-left px-4 py-4 font-semibold text-gray-500 dark:text-gray-400 hidden xl:table-cell w-[110px]">Creado</th>
                            <th class="text-right px-4 py-4 font-semibold text-gray-500 dark:text-gray-400 w-[120px]">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="org in organizations" :key="org.id"
                            class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                            <td class="px-4 py-4 font-medium text-gray-900 dark:text-white">{{ org.name }}</td>
                            <td class="px-4 py-4">
                                <span class="inline-flex items-center text-xs font-medium px-2.5 py-1 rounded-full whitespace-nowrap"
                                    :class="statusColors[org.status] || 'bg-gray-100 text-gray-700'">
                                    {{ org.status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">
                                <span v-if="org.plan" class="font-medium">{{ org.plan }}</span>
                                <span v-else class="text-gray-400">—</span>
                                <span v-if="org.trial_ends_at" class="block text-xs text-gray-400">
                                    hasta {{ formatDate(org.trial_ends_at) }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">{{ org.users_count }}</td>
                            <td class="px-4 py-4 hidden lg:table-cell">
                                <span v-if="totalUsage(org) === 0" class="text-xs text-gray-400">Sin uso</span>
                                <div v-else class="flex flex-wrap gap-1.5">
                                    <span v-for="(qty, event) in org.usage_this_month" :key="event"
                                        class="inline-flex items-center text-[11px] font-medium px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                                        {{ event }}: {{ qty }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-4 text-xs text-gray-400 dark:text-gray-500 hidden xl:table-cell whitespace-nowrap">
                                {{ formatDate(org.created_at) }}
                            </td>
                            <td class="px-4 py-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button @click="openPlanModal(org)"
                                        class="p-2 rounded-lg hover:bg-amber-50 dark:hover:bg-amber-900/20 text-gray-400 hover:text-amber-600 dark:hover:text-amber-400 transition"
                                        title="Editar plan">
                                        <Pencil class="w-4 h-4" />
                                    </button>
                                    <button v-if="org.status !== 'suspended'" @click="openSuspendModal(org)"
                                        class="p-2 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition"
                                        title="Suspender">
                                        <Ban class="w-4 h-4" />
                                    </button>
                                    <button v-else @click="activate(org)"
                                        class="p-2 rounded-lg hover:bg-green-50 dark:hover:bg-green-900/20 text-gray-400 hover:text-green-600 dark:hover:text-green-400 transition"
                                        title="Reactivar">
                                        <Play class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!organizations.length">
                            <td colspan="7" class="text-center py-12 text-gray-400 dark:text-gray-500 text-sm">
                                <Gauge class="w-10 h-10 mx-auto mb-2 opacity-40" />
                                No se encontraron empresas
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Modal :show="showPlanModal" title="Editar plan" @close="showPlanModal = false">
                <form @submit.prevent="submitPlan" class="space-y-4">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                        Plan de {{ selectedOrg?.name }}
                    </h3>

                    <TextInput
                        v-model="planForm.plan"
                        label="Plan (vacío = sin plan)"
                        placeholder="trial, basic, pro..."
                    />

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            Fin de trial
                        </label>
                        <input
                            v-model="planForm.trial_ends_at"
                            type="date"
                            class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showPlanModal = false"
                            class="px-4 py-2 rounded-xl border border-gray-300 dark:border-gray-600 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            Cancelar
                        </button>
                        <PrimaryButton class="inline-flex items-center gap-2">
                            <Check class="w-4 h-4" /> Guardar
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>

            <Modal :show="showSuspendModal" title="Suspender empresa" @close="showSuspendModal = false">
                <div class="space-y-4">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                        ¿Suspender {{ selectedOrg?.name }}?
                    </h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Los usuarios de esta empresa perderán el acceso hasta que se reactive.
                    </p>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showSuspendModal = false"
                            class="px-4 py-2 rounded-xl border border-gray-300 dark:border-gray-600 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            Cancelar
                        </button>
                        <button type="button" @click="confirmSuspend"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white text-sm font-semibold transition">
                            <Ban class="w-4 h-4" /> Suspender
                        </button>
                    </div>
                </div>
            </Modal>
        </div>
    </AppLayout>
</template>
